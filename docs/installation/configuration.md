---
title: Configuration
---

# Configuration

`php artisan ecommerce-admin:install` publishes the config to `config/artisanpack/ecommerce-admin-livewire.php`. You can also publish it with `php artisan vendor:publish --tag=ecommerce-admin-config`. It loads under the runtime key `artisanpack.ecommerce-admin-livewire`:

```php
config( 'artisanpack.ecommerce-admin-livewire.tables.per_page' ); // 25
```

Every key has a default, so you only need the keys you change.

## Admin routes

| Key | Default | What it controls |
| --- | --- | --- |
| `admin.route_prefix` | `'ecommerce-admin'` | URL prefix for every admin screen. Leading and trailing slashes are trimmed. |
| `admin.middleware` | `[ 'web', 'auth' ]` | Middleware on every admin route. Add your 2FA and `verified` middleware here. The package appends its own access check and rate limiter after these. Every entry except a middleware group (such as `web`) is also registered as Livewire persistent middleware, so it runs again on each Livewire update from an admin page, not only on the first page load. |
| `admin.routes_enabled` | `true` | Set to `false` to register the routes yourself. Route names must stay under `artisanpack.ecommerce.admin.` for the navigation to find them. With routes disabled the admin is not added to the `cms-framework` menu. |
| `admin.auto_register_cms_nav` | `true` | Add the admin's navigation to the `cms-framework` admin menu when that package is installed. Has no effect without it. |

The command palette searches through its own admin route (`artisanpack.ecommerce.admin.spotlight`), so its searches run this middleware too. The component library's shared spotlight route runs only `web`, so the admin adds no results there.

Livewire updates from admin pages count toward the engine's `ecommerce.admin.mutate` rate limiter. Updates that only read don't: polls and `$refresh`, picker searches, sorting, and paging, as long as they send no property changes. Over the limit, the user sees a toast with the wait time.

Route names are always prefixed `artisanpack.ecommerce.admin.` (for example `artisanpack.ecommerce.admin.orders.index`). See [Screens](Usage-Screens).

## Tables

| Key | Default | What it controls |
| --- | --- | --- |
| `tables.per_page` | `25` | Rows per page when the URL does not say otherwise |
| `tables.per_page_values` | `[ 10, 25, 50, 100 ]` | The page sizes users can choose. A `per_page` value outside this list falls back to `tables.per_page`. |
| `tables.export_max_rows` | `10000` | The most rows a single CSV export writes. The user is told when an export was cut short. Applies to table exports, the product catalog export, and the tax-rate export. |
| `tables.max_selection` | `0` | The most rows a user can tick one by one. `0` means the largest page size times 50. Bigger sets use "select all matching". |

## Command palette

| Key | Default | What it controls |
| --- | --- | --- |
| `spotlight.enabled` | `true` | Turn the palette and its Search button on or off. When off, the package adds nothing to the component library's spotlight. |
| `spotlight.shortcut` | `'meta.k'` | The keyboard shortcut, in Alpine key-modifier form (`meta.k`, `ctrl.k`, `ctrl.shift.p`). An invalid value falls back to `meta.k`. |
| `spotlight.limit` | `5` | Results per provider (orders, products, customers, …). Clamped to 1–20. |

See [Command Palette](Usage-Command-Palette).

## Real-time updates

| Key | Default | What it controls |
| --- | --- | --- |
| `realtime.enabled` | `false` | Subscribe the dashboard and order screens to the `private-ecommerce.admin` channel. Also needs the engine's `artisanpack.ecommerce.graphql.subscriptions` flag and Laravel Echo on the page. |

See [Real-Time Updates](Usage-Real-Time).

## Imports

| Key | Default | What it controls |
| --- | --- | --- |
| `imports.disk` | `'local'` | Filesystem disk the uploaded CSV is copied to while an import runs. Use a disk every queue worker can read. |
| `imports.max_rows` | `5000` | The most rows one product import accepts |
| `imports.queue` | `null` | Queue the import job runs on. `null` uses the connection's default queue. |

The import job can run for up to an hour. Set the queue connection's `retry_after` above 3600 seconds. Schedule `ecommerce-admin:prune-imports` daily to delete old imports; see [Artisan Commands](Advanced-Artisan-Commands). See [Product CSV Import and Export](Usage-Product-Csv).

## Settings that live elsewhere

Some behaviour the admin shows is configured in the engine, not here:

| Engine key | Effect in the admin |
| --- | --- |
| `artisanpack.ecommerce.base_currency` | The currency for the dashboard, reports, and the product form's first price row |
| `artisanpack.ecommerce.currency.rates.{BASE}` | Each currency listed adds a price row to the product form |
| `artisanpack.ecommerce.graphql.subscriptions` | Required for [real-time updates](Usage-Real-Time) |
| `artisanpack.ecommerce.localization.tax_labels` | Per-locale tax label ("Sales Tax") used on orders and reports |

Store settings that admins edit on the Settings screen are stored by the engine's settings repository, not in config files.
