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
2. prints the Tailwind `@source` lines to add to your main stylesheet;
3. prints the npm package to install (`@artisanpack-ui/livewire-drag-and-drop`);
4. checks that something grants access:
   - with `cms-framework`, runs `ecommerce-admin:sync-permissions` and reminds you to assign the `shop-manager` role or individual permissions;
   - reports when the `ecommerce.admin` gate is defined;
   - otherwise warns that every screen will return 403 and prints an example gate.

It is safe to run again.

The printed lines are only the minimum. See [Front-End Setup](Installation-Front-End) for the scripts charts, date pickers, and the editor need.

## `ecommerce-admin:sync-permissions`

```bash
php artisan ecommerce-admin:sync-permissions
```

Registers every engine ability as a `cms-framework` RBAC permission (slug `ecommerce.{resource}.{action}`) and creates the `shop-manager` role holding all of them. It is idempotent.

Without `cms-framework` it does nothing and tells you to use the `ecommerce.admin` gate instead.

You rarely need to run it by hand: it also runs from `ecommerce-admin:install` and after every `php artisan migrate`. Run it after upgrading if a release adds abilities and you have not migrated. See [Authorization](Authorization).

## Engine commands the admin relies on

| Command | Use |
| --- | --- |
| `php artisan ecommerce:seed-demo` | Seed a demo store to explore the admin. See [Quick Start Guide](Getting-Started). |
| `vendor/bin/testbench ecommerce:lint:translations` | Check translations in this package. See [Localization](Localization). |
