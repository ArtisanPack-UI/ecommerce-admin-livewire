# ArtisanPack UI Ecommerce Admin (Livewire)

A Livewire admin for the [`artisanpack-ui/ecommerce`](https://github.com/ArtisanPack-UI/ecommerce) engine, built on
[`artisanpack-ui/livewire-ui-components`](https://github.com/ArtisanPack-UI/livewire-ui-components). It renders inside
the `cms-framework` admin when that package is installed, and as a standalone admin otherwise.

> **Status:** in development toward 1.0. See the [package spec](docs/plans/15-ecommerce-admin-livewire-spec.md).

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Livewire 3.6+ or 4
- `artisanpack-ui/ecommerce` 1.0
- `artisanpack-ui/livewire-ui-components` 2.1

## Installation

```bash
composer require artisanpack-ui/ecommerce-admin-livewire
php artisan ecommerce-admin:install
```

The install command publishes `config/artisanpack/ecommerce-admin-livewire.php` and prints two front-end steps. The
package ships no CSS build, so add its views to your Tailwind sources:

```css
@source "../../vendor/artisanpack-ui/ecommerce-admin-livewire/resources/views/**/*.blade.php";
@source "../../vendor/artisanpack-ui/ecommerce-admin-livewire/src/**/*.php";
```

and install the drag-and-drop helper used by reorderable lists:

```bash
npm install @artisanpack-ui/livewire-drag-and-drop
```

## Access

The engine denies every ability by default. Grant access with the umbrella gate:

```php
Gate::define( 'ecommerce.admin', fn ( $user ) => $user->is_admin );
```

or define individual `ecommerce.{resource}.{action}` abilities.

### Two-factor authentication

The admin routes use the middleware in `admin.middleware` (`web`, `auth` by default). The engine does not apply 2FA, so
add your 2FA middleware there:

```php
'admin' => [
    'middleware' => [ 'web', 'auth', 'verified', 'two-factor' ],
],
```

## Configuration

| Key | Default | Purpose |
|---|---|---|
| `admin.route_prefix` | `ecommerce-admin` | URL prefix for every admin screen |
| `admin.middleware` | `[ 'web', 'auth' ]` | Middleware on every admin route |
| `admin.routes_enabled` | `true` | Set to `false` to register the routes yourself |
| `admin.auto_register_cms_nav` | `true` | Add the admin to the `cms-framework` menu |
| `tables.per_page` / `per_page_values` | `25` / `[10, 25, 50, 100]` | Table pagination |
| `spotlight.*` | enabled, `meta.k`, 5 | Command palette |
| `realtime.enabled` | `false` | Live order updates over Echo |
| `imports.*` | `local`, 5000 rows, default queue | CSV import |

## Contributing

As an open source project, this package is open to contributions from anyone. Please [read through the contributing
guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
