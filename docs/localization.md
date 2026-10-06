---
title: Localization
---

# Localization

Every user-facing string in the admin goes through Laravel's translation functions. The package ships JSON catalogues for four languages.

## Shipped languages

| Locale | File |
| --- | --- |
| English | `lang/en.json` |
| Spanish | `lang/es.json` |
| French | `lang/fr.json` |
| German | `lang/de.json` |

The admin follows the application locale (`app()->getLocale()`). The layout's `<html lang>` follows it too.

## Overriding strings

Publish the catalogues:

```bash
php artisan vendor:publish --tag=ecommerce-admin-lang
```

They are copied to `lang/vendor/ecommerce-admin/`. The package loads that directory as well as its own, so you can change individual strings there. Strings are keyed by their English text, as with any JSON catalogue.

## Adding a language

Copy `lang/en.json` to `lang/vendor/ecommerce-admin/{locale}.json` in your application and translate the values. Consider contributing it back.

Notification templates are separate: add a locale per template on the Notifications screen. The locales offered come from the `ap.ecommerceAdminLivewire.notifications.locales` filter. See [Hooks Reference](Extending-Hooks-Reference).

## Formats that follow the engine

The admin uses the engine's helpers, so the admin and the storefront agree:

| What | Helper |
| --- | --- |
| Money | `MoneyFormatter` |
| Dates | `LocalizedDate` |
| The tax line label ("Tax", "IVA", "TVA", "USt.") | `TaxLabel` |

The tax label follows the locale, and a store can override it per locale in `artisanpack.ecommerce.localization.tax_labels` or on the Settings → Tax tab (for example "Sales Tax" for `en_US`). Order totals, the order edit diff, and the tax report use it.

Markup uses logical CSS properties (`ms-`, `me-`, `start-`), so right-to-left layout is a stylesheet concern.

## Translation lint

CI fails when a user-facing string is not wrapped in a translation function, or when a key is missing from any catalogue. Run the same check locally:

```bash
vendor/bin/testbench ecommerce:lint:translations --no-engine --path=src --path=resources --lang=lang
```

It scans `src/` and `resources/` (Blade included) and compares against every catalogue in `lang/`. `--no-engine` leaves the engine's own sources out. When you add a string, add it to all four catalogues.
