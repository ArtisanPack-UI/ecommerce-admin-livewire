---
title: Requirements
menu_order: 10
---

# Requirements

## Core requirements

- **PHP 8.3+** with the `bcmath` and `intl` extensions
- **Laravel 12 or 13**
- **Livewire 3.6+ or 4**

Composer installs these ArtisanPack UI packages with it:

| Package | Version | Why |
| --- | --- | --- |
| `artisanpack-ui/ecommerce` | `^1.0` | The engine: models, services, policies, reports |
| `artisanpack-ui/livewire-ui-components` | `^3.0` | The design-system components: every input, table, tab, dialog, drawer, and chart |
| `artisanpack-ui/core` | `^1.0` | Shared helpers |
| `artisanpack-ui/hooks` | `^1.2` | The filters used for [extension](../extending.md) |
| `artisanpack-ui/security` | `^1.0.3` or `^2.0` | Output escaping and sanitization |

## Front-end requirements

The package ships no build. Your application needs:

- **Tailwind CSS 4** (daisyUI is no longer used; see [Front-End Setup](front-end.md))
- **Vite** with `laravel-vite-plugin`
- `@artisanpack-ui/css` and `@artisanpack-ui/behavior` (the design system, both `^1`) and `@artisanpack-ui/livewire-drag-and-drop` from npm

## Optional packages

The admin detects these at runtime and uses them when present:

| Package | What it adds |
| --- | --- |
| `artisanpack-ui/cms-framework` | Renders inside the CMS admin layout, joins the CMS menu, and registers engine abilities as RBAC permissions with a `shop-manager` role |
| `artisanpack-ui/media-library` | Pick product, variant, and category images through the media modal. Without it, image fields take a URL |
| `artisanpack-ui/icons` | Icon pickers for categories and order sub-statuses |
| `artisanpack-ui/accessibility` | Contrast warnings on sub-status and kanban column colours |
| `artisanpack-ui/ecommerce-kanban-livewire` | "Open board" links from order detail and kanban settings |
| `phpoffice/phpspreadsheet` | XLSX table export |
| `barryvdh/laravel-dompdf` | PDF table export |
| `rebing/graphql-laravel` | [Real-time updates](../usage/real-time.md). The engine registers the `ecommerce.admin` broadcast channel only when it is installed |

CSV export works without any of them.

## Real-time updates

Live updates are off by default. Turning them on needs `rebing/graphql-laravel`, Laravel Echo, a broadcaster such as Reverb, and the engine's subscriptions flag. See [Real-Time Updates](../usage/real-time.md).
