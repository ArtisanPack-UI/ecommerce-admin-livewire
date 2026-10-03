---
title: Installation Overview
---

# Installation

Everything you need to install the ecommerce admin and wire it into your application.

## In this section

- [Requirements](Installation-Requirements) - PHP, Laravel, Livewire, and optional packages
- [Configuration](Installation-Configuration) - Every configuration key
- [Front-End Setup](Installation-Front-End) - Tailwind sources, npm packages, and the scripts the components need
- [Layouts](Installation-Layouts) - Standalone admin vs. the `cms-framework` admin

## Install via Composer

```bash
composer require artisanpack-ui/ecommerce-admin-livewire
```

The service provider is auto-discovered. The package has no migrations: it reads and writes the engine's tables through the engine's services.

## Run the install command

```bash
php artisan ecommerce-admin:install
```

It publishes the config, prints the front-end steps, registers RBAC permissions when `cms-framework` is installed, and warns when it finds neither `cms-framework` RBAC support nor an `ecommerce.admin` gate (it does not check which users can actually get in). Pass `--force` to overwrite a config you published before. See [Artisan Commands](Advanced-Artisan-Commands).

## Publishing

| Tag | Publishes to | Use it to |
| --- | --- | --- |
| `ecommerce-admin-config` | `config/artisanpack/ecommerce-admin-livewire.php` | Change defaults (the install command does this) |
| `ecommerce-admin-views` | `resources/views/vendor/ecommerce-admin` | Override Blade views |
| `ecommerce-admin-lang` | `lang/vendor/ecommerce-admin` | Override translations |

```bash
php artisan vendor:publish --tag=ecommerce-admin-views
```

The config loads under `artisanpack.ecommerce-admin-livewire`:

```php
config( 'artisanpack.ecommerce-admin-livewire.admin.route_prefix' ); // 'ecommerce-admin'
```

Views load under the `ecommerce-admin::` namespace. Prefer the [extension points](Extending) over published views: a published view does not pick up fixes in later releases.

## Grant access

The engine denies every ability by default, so a fresh install answers 403 on every screen. Define the umbrella gate:

```php
Gate::define( 'ecommerce.admin', fn ( $user ) => $user->is_admin );
```

or define individual `ecommerce.{resource}.{action}` abilities. With `cms-framework`, RBAC permissions and a `shop-manager` role are registered for you. See [Authorization](Authorization).

## Two-factor authentication

Admin routes run the middleware in `admin.middleware` (`web`, `auth` by default). Neither this package nor the engine applies 2FA. Add your own middleware there:

```php
'admin' => [
    'middleware' => [ 'web', 'auth', 'verified', 'two-factor' ],
],
```

## Uninstalling

The admin registers itself with the engine's `SatelliteRegistry`. It owns no tables, columns, or product types, so it ships no uninstaller. When the engine marks the satellite uninstalled, the provider wires nothing: no routes, views, components, or hooks.

## Next steps

- [Front-End Setup](Installation-Front-End) — required before the admin is styled
- [Configuration](Installation-Configuration) — every key you can tune
- [Quick Start Guide](Getting-Started) — install to a seeded demo store
