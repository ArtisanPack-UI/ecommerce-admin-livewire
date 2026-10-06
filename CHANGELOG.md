# ArtisanPack UI Ecommerce Admin (Livewire) Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package
follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
