---
title: Layouts
---

# Layouts

The admin has two setups. It picks one automatically: there is nothing to configure.

| Setup | When | Layout the pages extend |
| --- | --- | --- |
| Standalone | `cms-framework` is not installed | `ecommerce-admin::layouts.app` |
| CMS | `artisanpack-ui/cms-framework` is installed | `cms::admin.layouts.app` |

Every admin page is a controller action that returns a view embedding one Livewire component. A view composer on `ecommerce-admin::pages.*` hands the page its layout, so the same page works in both setups and `php artisan route:cache` works.

## Standalone

The package's own layout. It is built from `x-artisanpack-nav`, `x-artisanpack-main`, and `x-artisanpack-menu`, and includes:

- a top bar with the store name, the command palette's **Search** button, and the theme toggle;
- the sidebar navigation (a drawer on small screens, opened by a real button);
- a "Skip to content" link;
- the toast container, the command palette, the keyboard and announcement helpers, and the rate-limit notice;
- your Vite entries, `@livewireStyles`, and `@livewireScripts`.

The navigation is grouped into sections: Orders, Catalog, Customers, Marketing, Reports, and Configuration, with the Dashboard on top. Entries the user cannot open are hidden. Orders, Reviews, and Inventory show badges for orders awaiting fulfillment, reviews to moderate, and low-stock items. Badge counts are cached for 60 seconds.

To change the markup, publish the views and edit `resources/views/vendor/ecommerce-admin/layouts/app.blade.php`. Keep its contract: a `title` section (plain text), a `content` section, and `styles` / `scripts` stacks.

## With cms-framework

When `cms-framework` is installed:

- **Layout.** Pages extend the CMS admin layout. That layout ships no Livewire assets or CSS, so each admin page pushes your Vite entries and `@livewireStyles` onto its `styles` stack, and the palette, toasts, `@livewireScripts`, and helpers onto its `scripts` stack. The palette's Search button sits above the page, because the CMS layout has no top bar.
- **Menu.** The admin's navigation joins the CMS admin menu through the `ap.cmsFramework.admin.menu` filter. The dashboard becomes a top-level "Store" item. Each section becomes a CMS menu section. Entries are filtered for the signed-in user. Turn this off with `admin.auto_register_cms_nav`. A CMS menu entry with the same slug (`ecommerce-{key}`) wins over the admin's.
- **Permissions.** Each engine ability is registered as an RBAC permission, and a `shop-manager` role holds all of them. See [Authorization](Authorization).

If your CMS layout already loads the same Vite entries, the repeated stylesheet is harmless and each module runs once. To stop the admin pushing them, return an empty array from `ap.ecommerceAdminLivewire.layout.viteEntries`.

## Using your own layout

Publish the views and change `@extends( $ecommerceAdminLayout )` in the page views you want to move, or replace `layouts/app.blade.php`. Your layout must provide:

- `@yield( 'title' )` and `@yield( 'content' )`;
- `@stack( 'styles' )` and `@stack( 'scripts' )`;
- `@livewireStyles` and `@livewireScripts`;
- `@include( 'ecommerce-admin::partials.spotlight' )`, `@include( 'ecommerce-admin::partials.accessibility' )`, and `@include( 'ecommerce-admin::partials.rate-limit-notice' )`, plus `<x-artisanpack-toast />`.

Without the accessibility partial, reorder announcements and focus return stop working. Without the rate-limit notice, a throttled action fails silently.
