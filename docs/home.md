---
title: ArtisanPack UI Ecommerce Admin (Livewire) Documentation Home
---

# ArtisanPack UI Ecommerce Admin (Livewire) Documentation

Welcome to the documentation for the ArtisanPack UI Ecommerce Admin. The package is a Livewire admin for the `artisanpack-ui/ecommerce` engine: orders, products, customers, promotions, shipping, tax, reports, notifications, webhooks, and store settings, built from `artisanpack-ui/livewire-ui-components`.

It renders inside the `cms-framework` admin when that package is installed, and as a standalone admin otherwise. It calls the engine's services in-process and owns no tables of its own.

## Table of Contents

- **Getting Started**
  - [Quick Start Guide](Getting-Started)

- **Installation**
  - [Installation Overview](Installation)
  - [Requirements](Installation-Requirements)
  - [Configuration](Installation-Configuration)
  - [Front-End Setup](Installation-Front-End)
  - [Layouts](Installation-Layouts)

- **Usage**
  - [Usage Overview](Usage)
  - [Screens](Usage-Screens)
  - [Dashboard](Usage-Dashboard)
  - [Command Palette](Usage-Command-Palette)
  - [Real-Time Updates](Usage-Real-Time)
  - [Product CSV Import and Export](Usage-Product-Csv)

- **Access**
  - [Authorization](Authorization)

- **Extending**
  - [Extending Overview](Extending)
  - [Navigation](Extending-Navigation)
  - [Dashboard Widgets](Extending-Dashboard-Widgets)
  - [Command Palette Providers](Extending-Command-Palette)
  - [Order Panels](Extending-Order-Panels)
  - [Product Type Panels](Extending-Product-Type-Panels)
  - [Customer Tabs](Extending-Customer-Tabs)
  - [Settings Tabs](Extending-Settings-Tabs)
  - [Config Forms](Extending-Config-Forms)
  - [Pickers](Extending-Pickers)
  - [Resource Tables](Extending-Resource-Tables)
  - [Layout](Extending-Layout)
  - [Blade Components](Extending-Blade-Components)
  - [Hooks Reference](Extending-Hooks-Reference)

- **Quality**
  - [Accessibility](Accessibility)
  - [Localization](Localization)

- **Advanced**
  - [Advanced Overview](Advanced)
  - [Artisan Commands](Advanced-Artisan-Commands)
  - [Browser Tests](Advanced-Browser-Tests)

- **Help**
  - [FAQ](Faq)
  - [Troubleshooting](Troubleshooting)

## Features

- **Every store screen**: Orders, reviews, products, categories, tags, inventory, digital files, license keys, customers, promotions and coupons, shipping, tax, notification templates, webhooks, order statuses, kanban boards, reports, and settings.
- **Order work in one place**: Status and sub-status changes, shipments, refunds, order edits with a totals diff, cancellation, notes, and an activity timeline.
- **Product form for every type**: Simple, variable, digital, grouped, and bundled products, with a panel registry for satellite types.
- **Dashboard**: Sales, orders to ship, low stock, and reviews to moderate, each linking to the filtered screen.
- **Command palette**: Cmd+K search across orders, products, customers, promotions, and coupons, with contextual order actions.
- **Real-time updates**: Optional live order and stock updates over Laravel Echo.
- **CSV import and export**: Catalog import with column mapping, a dry run, and a queued, resumable apply.
- **Authorization on every action**: Each screen and each Livewire action checks its engine ability. Default is deny.
- **cms-framework integration**: The CMS layout, the CMS menu, and RBAC permissions with a `shop-manager` role.
- **Extension points**: Registries and filters for navigation, dashboard widgets, palette providers, order panels, product type panels, customer tabs, settings tabs, config forms, pickers, and table columns, filters, and bulk actions.
- **Accessible and translated**: Targets WCAG 2.2 AA. Ships English, Spanish, French, and German.

## Quick Example

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define( 'ecommerce.admin', fn ( $user ) => $user->is_admin );
}
```

```bash
composer require artisanpack-ui/ecommerce-admin-livewire
php artisan ecommerce-admin:install
php artisan ecommerce:seed-demo   # optional demo store
```

Then open `/ecommerce-admin`.

## Support

For support, please open an issue on the [GitHub repository](https://github.com/ArtisanPack-UI/ecommerce-admin-livewire).

## License

This package is open-source software licensed under the MIT license.
