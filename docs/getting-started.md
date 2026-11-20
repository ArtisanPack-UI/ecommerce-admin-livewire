---
title: Getting Started
menu_order: 10
---

# Getting Started

Get from `composer require` to a working store admin. This assumes the `artisanpack-ui/ecommerce` engine is installed and migrated.

## 1. Install

```bash
composer require artisanpack-ui/ecommerce-admin-livewire
php artisan ecommerce-admin:install
```

The service provider is auto-discovered. The install command:

- publishes `config/artisanpack/ecommerce-admin-livewire.php`;
- prints the front-end steps: the stylesheet lines, the npm packages, and the script lines;
- registers RBAC permissions (through the engine's `ecommerce:sync-permissions`) when `cms-framework` is installed;
- warns when it finds neither RBAC support nor an `ecommerce.admin` gate (it does not check role assignments).

## 2. Set up the front end

The admin renders on the ArtisanPack UI design system. The package ships no CSS or JavaScript build: your application's Vite build loads the design system. Remove daisyUI if the admin was its only user, then install the packages the install command prints:

```bash
npm install @artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1 @artisanpack-ui/livewire-drag-and-drop@^2
```

Add the printed lines to your main stylesheet (for example `resources/css/app.css`). The `@layer` line comes first:

```css
@layer theme, base, apui, components, utilities;
@import 'tailwindcss';
@import '@artisanpack-ui/css';

@source "../../vendor/artisanpack-ui/ecommerce-admin-livewire/resources/views/**/*.blade.php";
@source "../../vendor/artisanpack-ui/ecommerce-admin-livewire/src/**/*.php";
```

Then register the behavior engine and drag-and-drop in `resources/js/app.js`:

```js
import apuiBehavior from '@artisanpack-ui/behavior/alpine';
import '@artisanpack-ui/livewire-drag-and-drop';

document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
```

The product description editor needs TinyMCE (`php artisan vendor:publish --tag=artisanpack-assets`). See [Front-End Setup](installation/front-end.md) for every step, then build:

```bash
npm run build
```

## 3. Grant access

The engine denies every ability by default. Until you grant access, every admin screen returns 403. The quickest way is the umbrella gate, in a service provider's `boot()`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define( 'ecommerce.admin', fn ( $user ) => $user->is_admin );
```

With `cms-framework`, assign the `shop-manager` role (or individual `ecommerce.*` permissions) instead. See [Authorization](authorization.md).

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

- [Configuration](installation/configuration.md) — every config key
- [Screens](usage/screens.md) — what each screen does
- [Authorization](authorization.md) — the ability each screen and action needs
- [Extending](extending.md) — add panels, tabs, widgets, and table columns from your own package
