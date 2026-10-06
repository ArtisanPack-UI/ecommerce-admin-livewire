---
title: Quick Start Guide
---

# Quick Start Guide

Get from `composer require` to a working store admin. This assumes the `artisanpack-ui/ecommerce` engine is installed and migrated.

## 1. Install

```bash
composer require artisanpack-ui/ecommerce-admin-livewire
php artisan ecommerce-admin:install
```

The service provider is auto-discovered. The install command:

- publishes `config/artisanpack/ecommerce-admin-livewire.php`;
- prints the front-end steps: the Tailwind `@source` lines, the npm packages, and the script lines;
- registers RBAC permissions (through the engine's `ecommerce:sync-permissions`) when `cms-framework` is installed;
- warns when it finds neither RBAC support nor an `ecommerce.admin` gate (it does not check role assignments).

## 2. Set up the front end

The package ships no CSS or JavaScript build. Your application's Vite build styles it. Add the printed lines to your Tailwind entry (for example `resources/css/app.css`):

```css
@source "../../vendor/artisanpack-ui/ecommerce-admin-livewire/resources/views/**/*.blade.php";
@source "../../vendor/artisanpack-ui/ecommerce-admin-livewire/src/**/*.php";
```

Install the scripts the components need and load them in `resources/js/app.js` (the install command prints these too):

```bash
npm install @artisanpack-ui/livewire-drag-and-drop apexcharts flatpickr
```

```js
import '@artisanpack-ui/livewire-drag-and-drop';
import ApexCharts from 'apexcharts';
import flatpickr from 'flatpickr';

window.ApexCharts = ApexCharts;
window.flatpickr = flatpickr;
```

The product description editor needs TinyMCE (`php artisan vendor:publish --tag=artisanpack-assets`), and daisyUI must stay on `~5.0`. See [Front-End Setup](Installation-Front-End) for every step, then build:

```bash
npm run build
```

## 3. Grant access

The engine denies every ability by default. Until you grant access, every admin screen returns 403. The quickest way is the umbrella gate, in a service provider's `boot()`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define( 'ecommerce.admin', fn ( $user ) => $user->is_admin );
```

With `cms-framework`, assign the `shop-manager` role (or individual `ecommerce.*` permissions) instead. See [Authorization](Authorization).

## 4. Open the admin

Sign in and visit `/ecommerce-admin`. The prefix is `admin.route_prefix` in the config. Admin routes use the `web` and `auth` middleware by default.

An empty store shows a first-run dashboard with links to create or import products.

## 5. Seed a demo store (optional)

The engine ships a demo seeder. It creates products, customers, and orders spread over the last 90 days:

```bash
php artisan ecommerce:seed-demo
```

| Option | Default | Meaning |
| --- | --- | --- |
| `--products` | `50` | Number of products |
| `--orders` | `200` | Number of orders |
| `--seed` | random | Same seed, same store |
| `--fresh` | off | Empty the engine's data tables first |
| `--force` | off | Skip the confirmation prompt; required in production |

Do not run it against a live store.

## Next steps

- [Configuration](Installation-Configuration) — every config key
- [Screens](Usage-Screens) — what each screen does
- [Authorization](Authorization) — the ability each screen and action needs
- [Extending](Extending) — add panels, tabs, widgets, and table columns from your own package
