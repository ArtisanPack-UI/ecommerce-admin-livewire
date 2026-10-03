---
title: Requirements
---

# Requirements

## Core requirements

- **PHP 8.3+** with the `bcmath` extension
- **Laravel 12 or 13**
- **Livewire 3.6+ or 4**

Composer installs these ArtisanPack UI packages with it:

| Package | Version | Why |
| --- | --- | --- |
| `artisanpack-ui/ecommerce` | `^1.0` | The engine: models, services, policies, reports |
| `artisanpack-ui/livewire-ui-components` | `^2.1` | Every input, table, tab, modal, and chart |
| `artisanpack-ui/core` | `^1.0` | Shared helpers |
| `artisanpack-ui/hooks` | `^1.2` | The filters used for [extension](Extending) |
| `artisanpack-ui/security` | `^1.0` or `^2.0` | Output escaping and sanitization |

## Front-end requirements

The package ships no build. Your application needs:

- **Tailwind CSS 4** with **daisyUI 5** (pin `daisyui` to `~5.0`; see [Front-End Setup](Installation-Front-End))
- **Vite** with `laravel-vite-plugin`
- `@artisanpack-ui/livewire-drag-and-drop`, `apexcharts`, and `flatpickr` from npm

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

CSV export works without any of them.

## Real-time updates

Live updates are off by default. Turning them on needs Laravel Echo, a broadcaster such as Reverb, and the engine's subscriptions flag. See [Real-Time Updates](Usage-Real-Time).
