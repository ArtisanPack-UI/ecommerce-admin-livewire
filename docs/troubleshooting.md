---
title: Troubleshooting
menu_order: 99
---

# Troubleshooting

## The admin is unstyled

**Cause:** Your application's Vite build doesn't load the design system. The package ships no CSS build of its own.

**Fix:**

1. Add the stylesheet lines that `php artisan ecommerce-admin:install` prints to your main stylesheet (for example `resources/css/app.css`): the `@layer` line, `@import '@artisanpack-ui/css';`, and the `@source` lines. Check the relative paths from that file to `vendor/`.
2. Make sure Tailwind 4 and `@artisanpack-ui/css` are installed.
3. Rebuild: `npm run build`, or run `npm run dev`.
4. The layout loads `resources/css/app.css` and `resources/js/app.js` only when `public/build/manifest.json` or a Vite dev server exists. If your entries have other names, set them with `ap.ecommerceAdminLivewire.layout.viteEntries`.

See [Front-End Setup](installation/front-end.md).

## Components look half-styled after upgrading to 2.0

**Cause:** daisyUI is still loaded, or the design system's layer order is missing.

**Fix:** Remove the `daisyui` package and its `@plugin "daisyui"` block if nothing else uses them, and make `@layer theme, base, apui, components, utilities;` the first line of your stylesheet, before `@import 'tailwindcss';`. Then rebuild.

## Drawers, dialogs, or the command palette don't open

**Cause:** The design system's behavior engine isn't registered. Date pickers, comboboxes, tabs, and the template code editor need it too.

**Fix:** Register it in `resources/js/app.js`, then rebuild:

```js
import apuiBehavior from '@artisanpack-ui/behavior/alpine';

document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
```

## The description editor doesn't load

**Cause:** The product description uses the component library's rich text editor, which runs on TinyMCE from the library's published assets, and those assets aren't published or loaded.

**Fix:**

1. Run `php artisan vendor:publish --tag=artisanpack-assets`.
2. Load `/vendor/artisanpack-ui/js/tinymce/tinymce.min.js` on the page.

Re-publish after upgrading the component library.

## The dashboard sales widget or report charts are missing

**Cause:** The user doesn't hold `report.view`. The sales widget and the reports are hidden without it. The admin draws the charts itself, so no chart library is involved.

**Fix:** Grant the user `report.view`. See [Authorization](authorization.md).

## Every screen returns 403

**Cause:** Nothing grants access yet. The engine denies every ability by default.

**Fix:** Define the umbrella gate in a service provider's `boot()`:

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;

Gate::define( 'ecommerce.admin', fn ( $user ) => $user->is_admin );
```

With `cms-framework`, assign the `shop-manager` role or individual `ecommerce.*` permissions, and run `php artisan ecommerce:sync-permissions` if the permissions are missing. See [Authorization](authorization.md).

## One screen returns 403

**Cause:** The user can enter the admin but lacks that screen's ability.

**Fix:** Look up the screen's ability in [Authorization](authorization.md) and grant it. Inventory uses `inventory.*`, not `product.*`.

## The command palette is empty or missing

**Cause:** One of these:

- `spotlight.enabled` is `false`.
- The behavior engine isn't registered, so the palette never opens.
- Your host spotlight class (`artisanpack.livewire-ui-components.components.spotlight.class`) throws or returns something unexpected. The admin's route asks it first and puts its results in the first group, so a broken host class stops the request before the admin's groups.
- The user has searched more than 120 times in the last minute.
- The user holds none of the result sources' abilities.

**Fix:** Set `spotlight.enabled` to `true`, register the behavior engine (see [Drawers, dialogs, or the command palette don't open](#drawers-dialogs-or-the-command-palette-dont-open)), and fix the host spotlight class. A rate-limited user gets results again once the minute passes. See [Command Palette](usage/command-palette.md).

## Real-time updates never arrive

**Cause:** Part of the real-time setup is missing, so the screens quietly skip subscribing.

**Fix:** Check all of these:

1. `realtime.enabled` is `true` in this package's config.
2. `artisanpack.ecommerce.graphql.subscriptions` is `true` in the engine's config.
3. Laravel Echo is set up as `window.Echo`, with a running broadcaster (for example `php artisan reverb:start`) and a queue worker for broadcast jobs.
4. The user holds `order.viewAny`, `product.viewAny`, and `webhookSubscription.viewAny`.

See [Real-Time Updates](usage/real-time.md).

## "Too many changes in a short time"

**Cause:** The user hit the engine's `ecommerce.admin.mutate` limiter (120 Livewire updates a minute by default).

**Fix:** Wait for the time in the message. To change the limit, redefine that rate limiter in your application.

## A product is read-only for an admin

**Cause:** Its type comes from a satellite that is not installed. The form shows a warning banner.

**Fix:** Reinstall the satellite, or change the product's type.

## A product import stalls or runs twice

**Cause:** No queue worker is running on the import queue, or the queue connection's `retry_after` is shorter than the job, so the queue hands the job out again while it is still running.

**Fix:**

- Run a queue worker on `imports.queue` (or the default queue).
- The job can run for up to an hour. Set the queue connection's `retry_after` above 3600 seconds.
- Use **Resume** to continue a stopped import from the next row.

See [Product CSV Import and Export](usage/product-csv.md).

## The admin does not appear in the cms-framework menu

**Cause:** One of these:

- `admin.auto_register_cms_nav` is `false`, or `admin.routes_enabled` is `false`.
- The user cannot open any admin screen.
- A CMS menu entry already uses the same slug (`ecommerce-…`). The CMS entry wins.

**Fix:** Turn both config keys on, grant the user at least one screen's ability, or rename the conflicting CMS entry.
