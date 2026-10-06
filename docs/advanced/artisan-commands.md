---
title: Artisan Commands
---

# Artisan Commands

## `ecommerce-admin:install`

```bash
php artisan ecommerce-admin:install [--force]
```

Sets up the admin in a host application. It:

1. publishes `config/artisanpack/ecommerce-admin-livewire.php` (tag `ecommerce-admin-config`); `--force` overwrites a config you published before;
2. prints the front-end steps:
   - the Tailwind `@source` lines to add to your main stylesheet;
   - the npm packages to install (`@artisanpack-ui/livewire-drag-and-drop`, `apexcharts`, `flatpickr`);
   - the lines that load them in `resources/js/app.js` (`window.ApexCharts`, `window.flatpickr`);
   - the command that publishes TinyMCE (`php artisan vendor:publish --tag=artisanpack-assets`);
   - a reminder to pin daisyUI to `~5.0`, and a link to [Front-End Setup](Installation-Front-End);
3. checks that something grants access:
   - with `cms-framework`, runs `ecommerce:sync-permissions` and reminds you to assign the `shop-manager` role or individual permissions;
   - reports when the `ecommerce.admin` gate is defined;
   - otherwise warns that every screen will return 403 and prints an example gate;
4. creates the default notification templates. If the engine tables are not migrated yet, it says so; the notifications screen creates them when it first opens.

It is safe to run again.

## `ecommerce-admin:prune-imports`

```bash
php artisan ecommerce-admin:prune-imports
```

Deletes product imports, with their uploaded files, that were last touched more than seven days ago and are not queued or running: completed, failed, and abandoned ones (left at the column-matching or check step). Schedule it daily:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command( 'ecommerce-admin:prune-imports' )->daily();
```

## `ecommerce-admin:sync-permissions` (deprecated)

```bash
php artisan ecommerce-admin:sync-permissions
```

Deprecated in 1.0.0. The engine owns the abilities and their permissions now: the command prints a notice and runs `php artisan ecommerce:sync-permissions`, which registers every engine ability as a `cms-framework` RBAC permission (slug `ecommerce.{resource}.{action}`) and creates the `shop-manager` role holding all of them. Use the engine command instead.

You rarely need either by hand: the engine command runs from `ecommerce-admin:install` and after every `php artisan migrate`. See [Authorization](Authorization).

## Engine commands the admin relies on

| Command | Use |
| --- | --- |
| `php artisan ecommerce:sync-permissions` | Register the ecommerce abilities as `cms-framework` permissions and the `shop-manager` role. |
| `php artisan ecommerce:seed-demo` | Seed a demo store to explore the admin. See [Quick Start Guide](Getting-Started). |
| `vendor/bin/testbench ecommerce:lint:translations` | Check translations in this package. See [Localization](Localization). |
