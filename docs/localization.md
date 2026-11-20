---
title: Localization
menu_order: 55
---

# Localization

Every user-facing string in the admin goes through Laravel's translation functions. The package ships JSON catalogues for nine languages.

## Shipped languages

| Language | Locale | File |
| --- | --- | --- |
| English | `en` | `lang/en.json` |
| Spanish | `es` | `lang/es.json` |
| French | `fr` | `lang/fr.json` |
| German | `de` | `lang/de.json` |
| Brazilian Portuguese | `pt_BR` | `lang/pt_BR.json` |
| Japanese | `ja` | `lang/ja.json` |
| Simplified Chinese | `zh_CN` | `lang/zh_CN.json` |
| Italian | `it` | `lang/it.json` |
| Arabic | `ar` | `lang/ar.json` |

> **Note:** The Brazilian Portuguese, Japanese, Simplified Chinese, Italian, and Arabic catalogues are machine-translated and waiting for review by native speakers. Corrections are welcome as issues or pull requests.

The admin follows the application locale (`app()->getLocale()`). The layout's `<html lang>` follows it too.

The catalogues load in the service provider's `register()`, so they're read even when another package calls `__()` while booting, and even when the satellite is inactive. Where a key is shared with the engine, the admin's wording wins.

## Overriding strings

Publish the catalogues:

```bash
php artisan vendor:publish --tag=ecommerce-admin-lang
```

They are copied to `lang/vendor/ecommerce-admin/`. The package loads that directory as well as its own, so you can change individual strings there. Strings are keyed by their English text, as with any JSON catalogue.

## Adding a language

Copy `lang/en.json` to `lang/vendor/ecommerce-admin/{locale}.json` in your application and translate the values. Keep placeholders (`:count`), `|` plural segments, and interval prefixes (`{0}`, `[2,*]`) intact. Consider contributing it back.

### Plural forms

Laravel picks a plural segment by the locale's plural rule, so a catalogue needs as many segments as its language has forms:

- **Arabic** has six forms (zero, one, two, 3–10, 11–99, other). Each plain `singular|plural` key has six segments in `ar.json`, and an override in `lang/vendor/ecommerce-admin/ar.json` must have six too. With only two, Arabic shows the plural for one.
- **Japanese and Chinese** have one form, so each segment carries the full sentence.

Notification templates are separate: add a locale per template on the Notifications screen. The locales offered come from the `ap.ecommerceAdminLivewire.notifications.locales` filter. See [Hooks Reference](reference/hooks.md).

## Formats that follow the engine

The admin uses the engine's helpers, so the admin and the storefront agree:

| What | Helper |
| --- | --- |
| Money | `MoneyFormatter` |
| Dates | `LocalizedDate` |
| The tax line label ("Tax", "IVA", "TVA", "USt.") | `TaxLabel` |

The tax label follows the locale, and a store can override it per locale in `artisanpack.ecommerce.localization.tax_labels` or on the Settings → Tax tab (for example "Sales Tax" for `en_US`). Order totals, the order edit diff, and the tax report use it.

## Right-to-left languages

The standalone layout sets `<html dir="rtl">` when the locale's primary language is written right to left (`ar`, `ckb`, `dv`, `fa`, `he`, `ps`, `sd`, `ug`, `ur`, `yi`), regional forms such as `ar_EG` included. The admin's styles use logical properties and the design system mirrors its directional icons, so the shell, cards, tables, and icons flip with it. Keys, SKUs, URLs, money amounts, code editors, and payloads stay left to right.

Under `cms-framework` the CMS admin layout controls `dir`, not the admin. See [cms-framework#474](https://github.com/ArtisanPack-UI/cms-framework/issues/474).

## Translation lint

CI fails when a user-facing string is not wrapped in a translation function, or when a key is missing from any catalogue. Run the same check locally:

```bash
vendor/bin/testbench ecommerce:lint:translations --no-engine --path=src --path=resources --lang=lang
```

It scans `src/` and `resources/` (Blade included) and compares against every catalogue in `lang/`. `--no-engine` leaves the engine's own sources out. When you add a string, add it to all nine catalogues.
