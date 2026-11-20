---
title: Overview
menu_order: 0
---

# Overview

A Livewire store admin for the `artisanpack-ui/ecommerce` engine: orders, products, customers, promotions, shipping, tax, reports, notifications, webhooks, and store settings, built on the ArtisanPack UI design system through `artisanpack-ui/livewire-ui-components`.

It renders inside the `cms-framework` admin when that package is installed, and as a standalone admin otherwise. It calls the engine's services in-process and owns no tables of its own.

## Features

- **Every store screen**: Orders, reviews, products, categories, tags, inventory, digital files, license keys, customers, promotions and coupons, shipping, tax, notification templates, webhooks, order statuses, kanban boards, reports, and settings.
- **Order work in one place**: Status and sub-status changes, shipments, refunds, order edits with a totals diff, cancellation, notes, kanban board placement, and an activity timeline.
- **Product form for every type**: Simple, variable, digital, grouped, and bundled products, with linked products (upsells, cross-sells, related), featured and catalog position, and a panel registry for satellite types.
- **Dashboard**: Sales, orders to ship, low stock, and reviews to moderate, each linking to the filtered screen.
- **Command palette**: Cmd+K search across orders, products, customers, promotions, and coupons, grouped by source, with quick actions and contextual order actions.
- **Real-time updates**: Optional live order and stock updates over Laravel Echo (needs `rebing/graphql-laravel`).
- **CSV import and export**: Catalog import with column mapping, a dry run, and a queued, resumable apply.
- **Authorization on every action**: Each screen and each Livewire action checks its engine ability. Default is deny.
- **cms-framework integration**: The CMS layout, the CMS menu, and RBAC permissions with a `shop-manager` role.
- **Extension points**: Registries and filters for navigation, dashboard widgets, palette providers, order panels, product type panels, customer tabs, settings tabs, config forms, pickers, and table columns, filters, and bulk actions.
- **Accessible and translated**: Targets WCAG 2.2 AA, with right-to-left layouts. Ships English, Spanish, French, German, Brazilian Portuguese, Japanese, Simplified Chinese, Italian, and Arabic.

## Requirements

PHP 8.3+, Laravel 12 or 13, Livewire 3.6+ or 4, the `artisanpack-ui/ecommerce` engine, and a Vite build with Tailwind CSS 4. See [Requirements](installation/requirements.md).

## Quick install

```bash
composer require artisanpack-ui/ecommerce-admin-livewire
php artisan ecommerce-admin:install
php artisan ecommerce:seed-demo   # optional demo store
```

Grant access with the umbrella gate, in a service provider's `boot()`:

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;

Gate::define( 'ecommerce.admin', fn ( $user ) => $user->is_admin );
```

Then set up the front end and open `/ecommerce-admin`.

## Next steps

- [Getting started](getting-started.md): from `composer require` to a working admin.
- [Installation](installation.md): requirements, configuration, the front-end build, and layouts.
- [Usage](usage.md): every screen, the dashboard, the command palette, and CSV import.
- [Extending](extending.md): add panels, tabs, widgets, and table columns from your own package.
- [Reference](reference.md): hooks, Artisan commands, and configuration.
- [Upgrading](upgrading.md): moving from 1.x to 2.0.
