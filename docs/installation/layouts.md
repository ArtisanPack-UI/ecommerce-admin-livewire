---
title: Layouts
menu_order: 40
---

# Layouts

The admin has two setups. It picks one automatically: there is nothing to configure.

| Setup | When | Layout the pages extend |
| --- | --- | --- |
| Standalone | `cms-framework` is not installed | `ecommerce-admin::layouts.app` |
| CMS | `artisanpack-ui/cms-framework` is installed | `cms::admin.layouts.app` |

Every admin page is a controller action that returns a view embedding one Livewire component. A view composer on `ecommerce-admin::pages.*` hands the page its layout, so the same page works in both setups and `php artisan route:cache` works.

## Standalone

The package's own layout: a design-system AppShell (`x-artisanpack-app-shell`) with a Navbar. It includes:

- a sidebar with the store name and the navigation;
- a top bar with breadcrumbs (Store admin › the page), the command palette's **Search** button, and the theme toggle;
- a "Skip to content" link as the first tab stop;
- the toaster (`<x-artisanpack-toaster>`), the keyboard and announcement helpers, and the rate-limit notice;
- `@apuiThemeScript`, your Vite entries, the admin's inline styles, `@livewireStyles`, and `@livewireScripts`.

Below 1024px the sidebar collapses to an icon rail, where section titles become separators and counts become dots, with a tooltip on hover and focus. Below 768px it becomes a drawer behind a menu button: a modal dialog that traps focus, closes with Escape or its close button, and returns focus to the menu button.

`<html lang>` follows the application locale, and `<html dir>` is `rtl` for right-to-left languages (Arabic, Hebrew, Persian, Urdu, and others, regional forms such as `ar_EG` included). See [Localization](../localization.md).

The navigation is a design-system Menu named "Store admin", grouped into sections: Orders, Catalog, Customers, Marketing, Reports, and Configuration, with the Dashboard on top. Entries the user cannot open are hidden. Orders, Reviews, and Inventory show counts for orders awaiting fulfillment (primary), reviews to moderate, and low-stock items (warning), and each link's accessible name includes its count. Counts are cached for 60 seconds.

To change the markup, publish the views and edit `resources/views/vendor/ecommerce-admin/layouts/app.blade.php`. Keep its contract: a `title` section (plain text), a `content` section, and `styles` / `scripts` stacks.

## With cms-framework

When `cms-framework` is installed:

- **Layout.** Pages extend the CMS admin layout. That layout ships no Livewire assets or CSS, so each admin page pushes your Vite entries, the admin's inline styles, and `@livewireStyles` onto its `styles` stack, and the toaster, `@livewireScripts`, and helpers onto its `scripts` stack. The palette's Search button sits above the page, because the CMS layout has no top bar. The CMS layout controls `<html dir>`, so right-to-left support there depends on cms-framework ([cms-framework#474](https://github.com/ArtisanPack-UI/cms-framework/issues/474)).
- **Menu.** The admin's navigation joins the CMS admin menu through the `ap.cmsFramework.admin.menu` filter. The dashboard becomes a top-level "Store" item. Each section becomes a CMS menu section. Entries are filtered for the signed-in user. Turn this off with `admin.auto_register_cms_nav`. A CMS menu entry with the same slug (`ecommerce-{key}`) wins over the admin's.
- **Permissions.** Each engine ability is registered as an RBAC permission, and a `shop-manager` role holds all of them. See [Authorization](../authorization.md).

If your CMS layout already loads the same Vite entries, the repeated stylesheet is harmless and each module runs once. To stop the admin pushing them, return an empty array from `ap.ecommerceAdminLivewire.layout.viteEntries`.

## Using your own layout

Publish the views and change `@extends( $ecommerceAdminLayout )` in the page views you want to move, or replace `layouts/app.blade.php`. Your layout must provide:

- `@yield( 'title' )` and `@yield( 'content' )`;
- `@stack( 'styles' )` and `@stack( 'scripts' )`;
- `@apuiThemeScript` in the `<head>`, then `@include( 'ecommerce-admin::partials.vite-assets' )` (or your own `@vite` entries) and `@include( 'ecommerce-admin::partials.styles' )`, before `@livewireStyles`. Without the Vite entries, the admin renders unstyled and its controls don't respond;
- `@livewireStyles` and `@livewireScripts`;
- `@include( 'ecommerce-admin::partials.spotlight-button' )` where the palette's Search button should go;
- `@include( 'ecommerce-admin::partials.accessibility' )` and `@include( 'ecommerce-admin::partials.rate-limit-notice' )`, plus `<x-artisanpack-toaster />`.

Without the accessibility partial, reorder announcements and focus return stop working. Without the rate-limit notice, a throttled action fails silently.

When `cms-framework` is installed, every admin page pushes these pieces itself, whatever layout it extends: the Vite entries, the inline styles, and the Livewire styles onto `styles`; the toaster, the Livewire scripts, and the accessibility and rate-limit partials onto `scripts`; and the Search button above the page. In that case your layout provides only the yields, the stacks, and `@apuiThemeScript`. Including the pushed pieces as well renders them twice.

> **Note:** Layouts published from 1.x include `partials.spotlight` after the page and `<x-artisanpack-toast />`. Since 2.0, `partials.spotlight` renders nothing (the palette renders with its button) and toasts are a `toast` browser event shown by `<x-artisanpack-toaster>`. Re-publish the layout, or make the changes above.
