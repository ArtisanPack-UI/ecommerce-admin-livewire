---
title: Upgrading
menu_order: 90
---

# Upgrading

## From 1.x to 2.0

2.0 rebuilds every screen on the ArtisanPack UI design system through `livewire-ui-components` 3.0. The admin's screens, abilities, routes, config keys, and extension points are unchanged unless listed here. Most hosts only need the front-end changes and, if they published views, a fresh copy of them.

### Update the packages

```bash
composer require artisanpack-ui/ecommerce-admin-livewire:^2.0
```

This pulls in `artisanpack-ui/livewire-ui-components` `^3.0`.

### Replace the front-end build

daisyUI, ApexCharts, and flatpickr are gone. The design system's stylesheet and behavior engine replace them.

1. Remove the old packages if nothing else in your app uses them:

   ```bash
   npm uninstall daisyui apexcharts flatpickr
   ```

2. Install the new ones:

   ```bash
   npm install @artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1 @artisanpack-ui/livewire-drag-and-drop@^2
   ```

3. In your main stylesheet, remove the `@plugin "daisyui"` block and add the design system. The `@layer` line comes first:

   ```css
   @layer theme, base, apui, components, utilities;
   @import 'tailwindcss';
   @import '@artisanpack-ui/css';
   ```

   Keep the admin's `@source` lines.

4. In `resources/js/app.js`, remove `window.ApexCharts`, `window.flatpickr`, and the `sparkline.js` and `tinymce-editor.js` imports, and register the behavior engine:

   ```js
   import apuiBehavior from '@artisanpack-ui/behavior/alpine';
   import '@artisanpack-ui/livewire-drag-and-drop';

   document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
   ```

   Keep loading TinyMCE from the published assets.

5. Rebuild with `npm run build`.

`php artisan ecommerce-admin:install` prints the new lines. See [Front-end setup](installation/front-end.md) for the whole setup.

If you allowed `cdnjs.cloudflare.com` in your Content Security Policy for the template editor's Ace scripts, you can remove it: the editor no longer loads anything from a CDN.

### Re-publish published views

Views published from 1.x use daisyUI classes and 1.x components. Commit or back up `resources/views/vendor/ecommerce-admin` first, because `--force` overwrites your edits. Then re-publish and apply your edits again from that copy:

```bash
php artisan vendor:publish --tag=ecommerce-admin-views --force
```

A custom layout must now use `<x-artisanpack-toaster />` instead of `<x-artisanpack-toast />`, include `@apuiThemeScript` and `partials.styles` in its `<head>`, and include `partials.spotlight-button` where the palette's Search button goes. `partials.spotlight` still exists for 1.x layouts but renders nothing. See [Layouts](installation/layouts.md#using-your-own-layout).

### Breaking changes

| Change | What to do |
| --- | --- |
| The admin palette route (`artisanpack.ecommerce.admin.spotlight`) returns groups, `[ { id, label, items } ]`, instead of one flat list. | Read `items` from each group. Your host spotlight's results are in the first group, with a `null` label. `AdminSpotlight::search()` and `appendTo()` still return flat lists. See [Command palette](usage/command-palette.md#how-it-fits-with-your-own-spotlight). |
| `AdminSpotlight::icon()` returns a design-system icon name instead of SVG markup. | Render it with `<x-artisanpack-icon :name="…" />`. |
| Toasts are a `toast` browser event shown by `<x-artisanpack-toaster>`, not `window.toast()`. | Dispatch the `toast` event, or use the `SendsToasts` concern. |
| `Webhooks::statusClass()` is replaced by `Webhooks::statusColor()`, which returns a badge color instead of daisyUI classes. | Pass the color to `<x-artisanpack-badge :color="…">`. |
| The error-summary partial's `message` parameter is removed. | Pass `failure`, plus optional `failureDescription`, `actionLabel`, and `actionMethod`. See [Blade components](extending/blade-components.md#error-summary). |
| `ReportPresenter::chartOptions()` is removed. The report chart is drawn by the admin. | Use `ReportPresenter::plot()` (over-time series), `bars()` (ranked reports), and `tone()` (whether a change is good news). |
| `Promotions\Index::usesLabel()` is removed. | The Uses column renders "used / limit" itself. |
| Status badge, timeline, and navigation colors are design-system colors, not daisyUI names. | `ap.ecommerceAdminLivewire.statusBadge` colors are `neutral`, `primary`, `secondary`, `accent`, `info`, `success`, `warning`, or `error`. |
| Timeline and navigation icons are icon-registry names. | Use a name from `IconRegistry::names()` (the design-system Lucide set plus the icons the admin registers, such as `gift`, `tag` or `truck`) or a Heroicon `o-*` / `s-*` name, such as `o-truck`. Any other name renders nothing until you register its SVG with `IconRegistry::register()`. The palette draws icons in the browser, so it shows only the design-system icons `livewire-ui-components` ships there. See [Icon names](extending/navigation.md#icon-names). |
| Multi-word table and report filter names are camelCase: `ap.ecommerceAdminLivewire.table.licenseKeys.columns`, `table.digitalFiles.*`, `table.taxRates.*`, `table.webhookDeliveries.*`, `reports.topProducts.table`, `reports.revenueByCategory.table`, `reports.lowStock.table`. | Rename your subscribers. The 1.x kebab-case names (`table.license-keys.columns`, `reports.top-products.table`) are deprecated but still applied, after the camelCase name, until 3.0. See [Resource tables](extending/resource-tables.md#the-filters) and [Hooks](reference/hooks.md#filters-the-admin-applies). |
| `Orders\RefundsPanel::refund()` takes the total the user confirmed: `refund( $token, $expectedTotal )`. It refuses, without using the token, when the lines no longer add up to it. | A custom refunds view passes the total shown on its confirm button. |
| `Orders\FulfillmentPanel::$labelProvider` is replaced by `$labelProviderFor`, keyed by shipment id. | Bind `wire:model="labelProviderFor.{{ $shipment->id }}"`. |
| `Notes::deleteNote()` takes no argument. It deletes the note chosen with `confirmDelete( $id )`. | Call `confirmDelete()` first, as the built-in view does. |


### Behavior changes

These need no code changes, but staff or hosts may notice them.

**Permissions**

- Changing stock from the product form, a variant row, or a catalog CSV needs `inventory.adjust`, as on the Inventory screen. Without it the stock fields are read-only and an import skips its stock columns, with a count in the report.
- Adding, changing, or removing a product's digital files needs `digitalFile.create`, `digitalFile.update`, or `digitalFile.delete`. A media-library file can only be attached by someone who may view it.
- Adding or generating coupon codes also needs `update` on the promotion. Exporting codes needs only `view`, since the codes table already shows them.
- Creating a tag from the product form or a CSV needs `product.create`, as on the Tags screen.
- Shipping zones and methods, order sub-statuses, inventory rows, license keys, and tax-import updates are checked per record, so record-level policy rules now apply to them.
- A missing order, customer, or promotion answers 403 instead of 404 to a user who can't list that kind of record.

**Requests and middleware**

- The admin's mutation limit (`ecommerce.admin.mutate`) counts each mutating call and each changed component in a Livewire request, not each request. Background refreshes from real-time updates and lazy loading are not counted.
- Middleware groups in `admin.middleware` are expanded, so their members (2FA, `verified`, and the like) also run on Livewire updates from admin pages. `throttle:*` entries are no longer made persistent, so they no longer throttle Livewire updates elsewhere in your app.

**Data and screens**

- Catalog CSV prices must be a plain dot decimal with at most the currency's decimal places (`12.50`, not `12,50` or `12.500`). Files that aren't UTF-8, such as Excel's Windows-1252 CSVs, are converted on read.
- A queued or running import that hasn't progressed for about an hour is marked failed, so it can be resumed or discarded.
- A webhook delivery held back while its subscription was off is shown as Pending rather than Failed. Re-enabling a subscription says how many were missed, and **Send missed deliveries** queues them.
- Notes, promotion and kanban names, customer details, addresses, and review replies keep `<` and anything after it ("Orders < 5 items"). Text is escaped when shown.
- The color warnings on sub-statuses and kanban columns check the 3:1 non-text contrast of the icon and dot against the light and dark surfaces, so different colors warn than in 1.x.
- The timeline hides email, phone, IP, address, card, token, and secret values when it falls back to showing an unknown event's raw data.
- Saving an order edit needs a preview of exactly those changes.

**Content Security Policy**

- The admin no longer uses inline `style=` attributes, but `livewire-ui-components` 3.0 still does, so the policy needs `style-src-attr 'unsafe-inline'`. See [Front-end setup](installation/front-end.md).

### Wording and export changes

Tests, scripts, or translations that match these strings need updating:

| Where | 1.x | 2.0 |
| --- | --- | --- |
| Promotion state | Disabled | Switched off |
| Tax rate state | Off | Inactive |
| Customers CSV header | Accepts marketing | Marketing |
| Report presets | | Adds `last-90-days` |

Every screen's layout and copy changed with the rebuild, so check any browser tests that select elements by text or class.

### Translations

The catalogs now load in the service provider's `register()` instead of `boot()`, so the admin's strings are translated even when another package calls `__()` while booting. They also load while the satellite is inactive. If you override strings in `lang/vendor/ecommerce-admin/`, re-check them against the new keys: many strings changed with the rebuild. 2.0 adds Brazilian Portuguese, Japanese, Simplified Chinese, Italian, and Arabic. See [Localization](localization.md).

### New for extenders

These are additions, so nothing breaks if you ignore them:

- **Palette group labels.** A `SpotlightProvider` may define `label()` to name its group. See [Command palette providers](extending/command-palette.md).
- **Badge colors.** `NavBadges::tone()` picks a navigation badge's color. See [Navigation](extending/navigation.md#badge-color).
- **Timeline tones.** `ap.ecommerceAdminLivewire.timeline.entry` accepts an optional `tone`. See [Hooks](reference/hooks.md#describe-a-satellites-timeline-event).
- **Board links.** `ap.ecommerceAdminLivewire.kanban.boardUrl` receives a third `$query` argument, and `KanbanBoards::boardUrl()` takes it too. See [Hooks](reference/hooks.md#kanban-open-board-link).
- **Bulk confirmations.** Override `WithResourceTable::bulkActionTokenSubject()` to bind a confirmation to other input. See [Resource tables](extending/resource-tables.md#binding-a-confirmation-to-other-input).
- **Rule builder.** `ruleBuilderLists()` entries take an optional `description`.
- **Admin-only component names.** `ec-admin-money`, `ec-admin-empty-state`, `ec-admin-address`, and `ec-admin-address-form` always resolve to the admin's components. See [Blade components](extending/blade-components.md#admin-only-names).
- **Deep links.** `/webhooks?edit={id}` opens a subscription's edit drawer, and `/kanban-boards/{board}/edit?automation=new|{id}` opens an automation. See [Screens](usage/screens.md).
- **Limits.** `PickerSource::MAX_IDS` (200) caps how many selected ids a picker resolves, and `AdminSpotlight::RESULTS_PER_LIMIT` caps each palette provider at three times the configured limit.
- **Action tokens.** `withActionToken()` takes an optional `$releaseOn` list of exception classes. Only those failures release the token; any other keeps it spent.
- **Stock and media checks.** `StockLevels::canAdjust()` and `DigitalDisks::viewableMedia()` expose the permission checks the product screens use. `ProductMedia::featuredMany()` and `mediaUrls()` resolve a page of thumbnails at once.
- **Secret config fields.** A config-form field named `secret` renders as a password input.
- **Public helpers.** `Settings\Show::displayValue()`, `Tax\Index::usageSummary()`, `Notifications\Index::channelIcon()` and `wasEdited()`, and `Notifications\Edit::errorLine()`, `diagnosticsFor()`, and `smsSegments()`. Settings resets now go through `confirmReset()` and `cancelReset()` before saving.
