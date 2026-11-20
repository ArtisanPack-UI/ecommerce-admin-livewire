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
   npm install @artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1 @artisanpack-ui/livewire-drag-and-drop
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

Views published from 1.x use daisyUI classes and 1.x components. Re-publish the ones you changed and apply your edits again:

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
| `Webhooks::statusClass()` is replaced by `Webhooks::statusColor()`, which returns a badge colour instead of daisyUI classes. | Pass the colour to `<x-artisanpack-badge :color="…">`. |
| The error-summary partial's `message` parameter is removed. | Pass `failure`, plus optional `failureDescription`, `actionLabel`, and `actionMethod`. See [Blade components](extending/blade-components.md#error-summary). |
| `ReportPresenter::chartOptions()` is removed. The report chart is drawn by the admin. | Use `ReportPresenter::plot()` (over-time series), `bars()` (ranked reports), and `tone()` (whether a change is good news). |
| `Promotions\Index::usesLabel()` is removed. | The Uses column renders "used / limit" itself. |
| Status badge, timeline, and navigation colours are design-system colours, not daisyUI names. | `ap.ecommerceAdminLivewire.statusBadge` colours are `neutral`, `primary`, `secondary`, `accent`, `info`, `success`, `warning`, or `error`. |
| Timeline icons are design-system (Lucide) names. | Return names such as `gift` from `ap.ecommerceAdminLivewire.timeline.entry`. Navigation and palette icons still accept Heroicon names (`o-gift`) and map them. |

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

The catalogues now load in the service provider's `register()` instead of `boot()`, so the admin's strings are translated even when another package calls `__()` while booting. They also load while the satellite is inactive. If you override strings in `lang/vendor/ecommerce-admin/`, re-check them against the new keys: many strings changed with the rebuild. 2.0 adds Brazilian Portuguese, Japanese, Simplified Chinese, Italian, and Arabic. See [Localization](localization.md).

### New for extenders

These are additions, so nothing breaks if you ignore them:

- **Palette group labels.** A `SpotlightProvider` may define `label()` to name its group. See [Command palette providers](extending/command-palette.md).
- **Badge colours.** `NavBadges::tone()` picks a navigation badge's colour. See [Navigation](extending/navigation.md#badge-colour).
- **Timeline tones.** `ap.ecommerceAdminLivewire.timeline.entry` accepts an optional `tone`. See [Hooks](reference/hooks.md#describe-a-satellites-timeline-event).
- **Board links.** `ap.ecommerceAdminLivewire.kanban.boardUrl` receives a third `$query` argument, and `KanbanBoards::boardUrl()` takes it too. See [Hooks](reference/hooks.md#kanban-open-board-link).
- **Bulk confirmations.** Override `WithResourceTable::bulkActionTokenSubject()` to bind a confirmation to other input. See [Resource tables](extending/resource-tables.md#binding-a-confirmation-to-other-input).
- **Rule builder.** `ruleBuilderLists()` entries take an optional `description`.
- **Admin-only component names.** `ec-admin-money`, `ec-admin-empty-state`, `ec-admin-address`, and `ec-admin-address-form` always resolve to the admin's components. See [Blade components](extending/blade-components.md#admin-only-names).
- **Deep links.** `/webhooks?edit={id}` opens a subscription's edit drawer, and `/kanban-boards/{board}/edit?automation=new|{id}` opens an automation. See [Screens](usage/screens.md).
- **Public helpers.** `Settings\Show::displayValue()`, `Tax\Index::usageSummary()`, `Notifications\Index::channelIcon()` and `wasEdited()`, and `Notifications\Edit::errorLine()`, `diagnosticsFor()`, and `smsSegments()`. Settings resets now go through `confirmReset()` and `cancelReset()` before saving.
