---
title: FAQ
menu_order: 95
---

# FAQ

## Do I need cms-framework?

No. Without it the admin uses its own standalone layout and navigation. With it, the admin renders inside the CMS layout, joins the CMS menu, and registers RBAC permissions. See [Layouts](installation/layouts.md).

## Why does every screen return 403?

The engine denies every ability by default. Define the `ecommerce.admin` gate, individual `ecommerce.{resource}.{action}` abilities, or (with `cms-framework`) assign the `shop-manager` role. See [Authorization](authorization.md).

## Can I give staff access to only some screens?

Yes. Grant the abilities for those screens and actions only. Screens a user cannot open are hidden from the navigation and the command palette. Inventory has its own abilities, so warehouse staff can adjust stock without editing products. See [Authorization](authorization.md).

## Why is the admin unstyled?

The package ships no CSS build. Load the design system (`@artisanpack-ui/css`), add the admin's `@source` lines to your stylesheet, and rebuild. See [Front-End Setup](installation/front-end.md).

## Does it still need daisyUI?

No. Since 2.0 the admin renders on the ArtisanPack UI design system through `livewire-ui-components` 3.0. Remove daisyUI if nothing else uses it. See [Upgrading](upgrading.md).

## Does it work with Livewire 4?

Yes. It supports Livewire 3.6+ and 4, and CI runs both.

## Can I change the URL?

Set `admin.route_prefix`. Route names stay `artisanpack.ecommerce.admin.*`. See [Configuration](installation/configuration.md).

## How do I add two-factor authentication?

Add your 2FA middleware to `admin.middleware`. Neither the engine nor the admin applies it.

## Can my package add a screen, panel, or tab?

Yes. Add navigation entries with a filter, and panels and tabs through the registries. See [Extending](extending.md).

## Does the admin have its own database tables?

No. It reads and writes the engine's tables through the engine's services and ships no migrations.

## Can I export to Excel or PDF?

CSV works out of the box. Install `phpoffice/phpspreadsheet` for XLSX and `barryvdh/laravel-dompdf` for PDF table export.

## How do I try it with data?

Run `php artisan ecommerce:seed-demo` against a development database. See [Getting Started](getting-started.md).

## Which languages does it ship?

English, Spanish, French, German, Brazilian Portuguese, Japanese, Simplified Chinese, Italian, and Arabic. The last five are machine-translated and waiting for review. Right-to-left languages get a right-to-left layout. See [Localization](localization.md).

## Is it accessible?

It targets WCAG 2.2 AA, and the browser suite runs axe on every screen. Some limitations come from the component library. See [Accessibility](accessibility.md).

## What versions are supported?

PHP 8.3+, Laravel 12 or 13, Livewire 3.6+ or 4. See [Requirements](installation/requirements.md).
