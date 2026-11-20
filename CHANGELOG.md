# ArtisanPack UI Ecommerce Admin (Livewire) Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package
follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [2.0.0] - 2026-11-19

Every screen is rebuilt on the ArtisanPack UI design system through `livewire-ui-components` 3.0. Screens, abilities, routes, and config keys are unchanged unless noted. See [Upgrading](docs/upgrading.md#from-1x-to-20) for the steps.

### Breaking

- **Front end:** daisyUI, ApexCharts, and flatpickr are gone. Hosts install `@artisanpack-ui/css@^1`, `@artisanpack-ui/behavior@^1`, and `@artisanpack-ui/livewire-drag-and-drop@^2`, and register the behavior engine. The template editor no longer loads Ace from a CDN.
- **Published views and layouts** from 1.x must be re-published. Custom layouts use `<x-artisanpack-toaster />`, `@apuiThemeScript`, `partials.styles`, and `partials.spotlight-button`.
- The admin palette route returns groups (`[ { id, label, items } ]`) instead of a flat list, and `AdminSpotlight::icon()` returns an icon name instead of SVG.
- Toasts are a `toast` browser event shown by `<x-artisanpack-toaster>`, not `window.toast()`.
- `Webhooks::statusClass()` is replaced by `statusColor()`. `ReportPresenter::chartOptions()`, `Promotions\Index::usesLabel()`, and the error-summary partial's `message` parameter are removed.
- Status badge, timeline, and navigation colours are design-system colours, and timeline and navigation icons are icon-registry names.
- Multi-word table and report filter names are camelCase (`ap.ecommerceAdminLivewire.table.licenseKeys.columns`, `reports.topProducts.table`). The kebab-case names still run after them and are deprecated until 3.0.
- `Orders\RefundsPanel::refund()` takes the confirmed total, `Orders\FulfillmentPanel::$labelProvider` is now `$labelProviderFor` (keyed by shipment), and `Notes::deleteNote()` takes no argument.

### Added

- Brazilian Portuguese, Japanese, Simplified Chinese, Italian, and Arabic translations. The catalogues load in `register()`, so they're available while other packages boot and while the satellite is inactive.
- "Open board" links on order detail, deep links to a webhook's edit drawer and a kanban automation, and a toast text guard.
- **Send missed deliveries** on a webhook subscription, for deliveries held back while it was off.
- Extension points:
  - `SpotlightProvider::label()`, `NavBadges::tone()`, and timeline `tone`;
  - a `$query` argument on board links, `WithResourceTable::bulkActionTokenSubject()`, and rule-builder list descriptions;
  - the `ec-admin-*` Blade component names, and `numeric`, `listed`, and `labelHidden` table columns;
  - `withActionToken()`'s `$releaseOn`, `PickerSource::MAX_IDS`, `AdminSpotlight::RESULTS_PER_LIMIT`, `StockLevels::canAdjust()`, `DigitalDisks::viewableMedia()`, and `ProductMedia::featuredMany()`;
  - a `gift` icon in the icon registry.
- The order totals card lists each tax rate from the engine's `meta.tax_breakdown`.
- A `--prefer-lowest` CI job, so the lowest supported engine, Laravel, and Livewire versions are tested.

### Changed

- **Rate limiting:** the `ecommerce.admin.mutate` limiter counts every mutating call and changed component in a Livewire request, so batching calls no longer gets around it. Real-time refreshes and lazy loads are not counted.
- **Persistent middleware:** middleware groups in `admin.middleware` are expanded, so 2FA and `verified` inside a group also run on Livewire updates. `throttle:*` entries are no longer made persistent across the app.
- **Catalog import:** prices must be a plain dot decimal, files that aren't UTF-8 (such as Excel's Windows-1252 CSVs) are converted, types that keep no stock get no stock rows, imported rows are logged against the importing user, and an import whose worker died is marked failed after about an hour so it can be resumed or discarded.
- **Webhooks:** a delivery held back while its subscription was off shows as Pending, matching the engine API.
- **Colour warnings** on sub-statuses and kanban columns check 3:1 non-text contrast of the icon and dot against the light and dark surfaces.
- **Order edits** save only the changes that were previewed. **Refunds** refuse when the amount changed after it was shown.
- **Inline `style=` attributes** are gone from the admin's own views. The CSP docs explain the `style-src-attr` that `livewire-ui-components` still needs.
- Changing a scheduled price's currency clears its amounts.
- Shipping regions accept ISO 3166-2 codes (`US-CA`). Short report presets switch a monthly interval back to daily.
- "Create shipment" is hidden on unpaid orders, a nameless registered customer shows their email instead of "Guest", and detail pages show the order number or record name in the title.
- Large product lists, child pickers, and bulk stock adjustments run far fewer queries.
- `artisanpack-ui/accessibility` and `artisanpack-ui/icons` are no longer listed as Composer suggestions: `livewire-ui-components` 3.0 always installs them.
- **CI:** satellite verification uses the engine's `verify-satellite.yml@v1.0.1`, and the release step's action is pinned to a commit SHA. The signed contract-verification report is attached as `ecommerce-verify-report.json`; v1.0.0's appears as `default.ecommerce-verify-report.json`, because GitHub renames asset names that start with a dot.

### Fixed

- Notes, names, addresses, and replies containing `<` are no longer cut off when saved.
- Blur-timed fields reach the server on Livewire 4.
- The upgrade from 1.x re-syncs notification templates (the satellite now reports version 2.0.0).
- Accessibility:
  - kanban delete dialogs are alert dialogs that focus the safe button;
  - report changes say whether they're an improvement;
  - the backorder switch's name matches its label, and import progress is announced only at milestones;
  - form headings no longer skip a level;
  - empty cells read "None" instead of "em dash";
  - the bulk bar announces only the selection count, and alerts no longer carry a duplicate `role`.
- Japanese and Chinese plural strings always show the count. The command palette legend is translated as whole phrases.
- Fallback date cells use the store's time zone and locale.

### Security

- Stock changes from the product form, variants, and CSV import need `inventory.adjust`.
- Digital-file changes need `digitalFile.*`, and only media the user may view can be attached or previewed.
- Coupon codes can only be added or generated by someone who can edit the promotion. Inline tag creation needs `product.create`.
- Shipping, order sub-statuses, inventory, license keys, and tax-import updates are authorized per record. Detail pages answer 403 before 404 to users who can't list the records.
- The notification template preview is rate limited, and oversized output is refused.
- Picker selections and palette provider results are capped.
- The timeline hides personal and secret values from unrecognised events.
- Kanban webhook secrets render as password fields, and translated lines on the settings screen are escaped.
- Tampered JSON config no longer causes server errors.

## [1.0.0] - 2026-10-06

The first stable release of the Livewire store admin for the ArtisanPack UI
ecommerce engine. It runs on engine 1.0, Laravel 12 or 13, and Livewire 3.6+ or 4.

### Added

- **Screens** for every part of the store, each authorized against the engine's abilities:
  - dashboard with key figures, 30-day sales, lowest stock, and recent orders;
  - orders and order detail: status and sub-status, shipments and tracking, label purchase, refunds (per item or by amount, with restock), order edits with a totals diff and rollback, cancellation, notes, and the activity timeline;
  - review moderation;
  - products for every type (simple, variable, digital, grouped, bundled), with scheduled per-currency prices, a variant matrix, gallery, linked products (upsells, cross-sells, related), featured, and catalog position;
  - catalog CSV import (column mapping, dry run, queued and resumable) and export;
  - categories, tags, inventory (adjustments with a reason, thresholds, backorders, bulk adjust from CSV), and digital files with archiving;
  - license keys: activations, machine deactivation, and revocation;
  - customers with orders, addresses, notification preferences, notes, and activity;
  - promotions with a rule builder and plain-language summary, and coupon codes (generate, rename, export);
  - shipping zones and methods, tax classes and rates (with CSV import and export);
  - reports with date presets, comparison, charts, and CSV export;
  - notification templates (Twig, live preview, locales, test sends), webhooks with a delivery log and replay, order statuses, kanban board settings, and store settings.
- **Command palette** (Cmd+K) across orders, products, customers, promotions, and coupons, with contextual order actions.
- **Real-time updates** for the dashboard and order screens over Laravel Echo, when enabled.
- **Extension points**: registries and filters for navigation, dashboard widgets, palette providers, order panels, product type panels, customer tabs, settings tabs, config forms, pickers, and table columns, filters, and bulk actions.
- **cms-framework integration**: the CMS layout, the CMS menu, and RBAC permissions with a `shop-manager` role.
- **Commands**: `ecommerce-admin:install` (config, front-end steps, access check, notification templates) and `ecommerce-admin:prune-imports`.
- **Translations**: English, Spanish, French, and German.
- **Accessibility** targeting WCAG 2.2 AA:
  - keyboard reordering on every sortable list, with announcements;
  - focus return, error summaries, and status in text as well as colour;
  - captions and sort state on every table, and live regions for updates;
  - an unsaved-changes warning on the edit forms;
  - an axe check on every screen in the browser suite.
- **Browser test suite** (Pest and Playwright) for the main flows and accessibility.

### Changed

- **Engine 1.0 compatibility:**
  - queries qualify every column through its model, so the engine's `ecommerce_` table prefix works everywhere;
  - config forms read the schemas engine entries declare (`DescribesConfig`) and validate with the engine's rules;
  - stock reads and writes use the engine's default warehouse;
  - license keys are searched by their hash, so only full keys match;
  - store currencies, navigation sections and badges, refund limits, and digital-file deletion come from the engine.
- RBAC is delegated to the engine: permissions come from its ability catalog and `ecommerce:sync-permissions`. `ecommerce-admin:sync-permissions` remains as a deprecated alias.
- `rebing/graphql-laravel` is suggested rather than required. Real-time updates are off without it.
- Refunds by amount are spread across the order's lines, and pending refunds count toward what can still be refunded. "Refunded" is offered as an order status only once the payment is refunded.
- Bulk actions check each selected record and skip the ones the user may not change.
- The package is licensed under MIT.

### Security

- The command palette searches through its own admin route, behind the admin middleware.
- The configured admin middleware (2FA, `verified`) also runs on every Livewire update from an admin page.
- Mutating Livewire updates count toward the engine's `ecommerce.admin.mutate` rate limiter; polls, searches, sorting, and paging don't.
- A new webhook secret is no longer kept in the component's public state.
- Inline scripts carry the application's CSP nonce.
- Picker caches, row selections, and import error reports are capped.
