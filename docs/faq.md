---
title: FAQ
---

# Frequently Asked Questions

## Do I need cms-framework?

No. Without it the admin uses its own standalone layout and navigation. With it, the admin renders inside the CMS layout, joins the CMS menu, and registers RBAC permissions. See [Layouts](Installation-Layouts).

## Why does every screen return 403?

The engine denies every ability by default. Define the `ecommerce.admin` gate, individual `ecommerce.{resource}.{action}` abilities, or (with `cms-framework`) assign the `shop-manager` role. See [Authorization](Authorization).

## Can I give staff access to only some screens?

Yes. Grant the abilities for those screens and actions only. Screens a user cannot open are hidden from the navigation and the command palette. Inventory has its own abilities, so warehouse staff can adjust stock without editing products. See [Authorization](Authorization).

## Why is the admin unstyled?

The package ships no CSS. Add its `@source` lines to your Tailwind entry and rebuild. See [Front-End Setup](Installation-Front-End).

## Does it work with Livewire 4?

Yes. It supports Livewire 3.6+ and 4, and CI runs both.

## Can I change the URL?

Set `admin.route_prefix`. Route names stay `artisanpack.ecommerce.admin.*`. See [Configuration](Installation-Configuration).

## How do I add two-factor authentication?

Add your 2FA middleware to `admin.middleware`. Neither the engine nor the admin applies it.

## Can my package add a screen, panel, or tab?

Yes. Add navigation entries with a filter, and panels and tabs through the registries. See [Extending](Extending).

## Does the admin have its own database tables?

No. It reads and writes the engine's tables through the engine's services and ships no migrations.

## Can I export to Excel or PDF?

CSV works out of the box. Install `phpoffice/phpspreadsheet` for XLSX and `barryvdh/laravel-dompdf` for PDF table export.

## How do I try it with data?

Run `php artisan ecommerce:seed-demo` against a development database. See [Quick Start Guide](Getting-Started).

## Which languages does it ship?

English, Spanish, French, and German. See [Localization](Localization).

## Is it accessible?

It targets WCAG 2.2 AA, and the browser suite runs axe on every screen. Some limitations come from the component library. See [Accessibility](Accessibility).

## What versions are supported?

PHP 8.3+, Laravel 12 or 13, Livewire 3.6+ or 4. See [Requirements](Installation-Requirements).
