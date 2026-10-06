---
title: Troubleshooting
---

# Troubleshooting

## The admin is unstyled

The package ships no CSS build. Your application's Tailwind build styles it.

1. Add the `@source` lines that `php artisan ecommerce-admin:install` prints to your Tailwind entry (for example `resources/css/app.css`). Check the relative paths from that file to `vendor/`.
2. Make sure Tailwind 4 and daisyUI 5 are installed.
3. Rebuild: `npm run build`, or run `npm run dev`.
4. The layout loads `resources/css/app.css` and `resources/js/app.js` only when `public/build/manifest.json` or a Vite dev server exists. If your entries have other names, set them with `ap.ecommerceAdminLivewire.layout.viteEntries`.

See [Front-End Setup](Installation-Front-End).

## Tabs are blank on the product, promotion, or customer form

daisyUI releases after 5.0.x hide `.tab-content`, so the component library's tab panels render empty. Pin daisyUI:

```bash
npm install -D daisyui@~5.0
npm run build
```

Keep the pin until [livewire-ui-components#119](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/119) is fixed.

## The description editor does not load

The product description uses TinyMCE from the component library's published assets.

1. Run `php artisan vendor:publish --tag=artisanpack-assets`.
2. Load `/vendor/artisanpack-ui/js/tinymce/tinymce.min.js` on the page.
3. Import `vendor/artisanpack-ui/livewire-ui-components/resources/js/tinymce-editor.js` in your `app.js`.

Re-publish after upgrading the component library.

## Charts or the dashboard sparkline are missing

The charts need ApexCharts as a global:

```js
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;
```

The sparkline also needs `vendor/artisanpack-ui/livewire-ui-components/resources/js/sparkline.js` imported. Also check the user holds `report.view`: the sales widget is hidden without it.

## Date pickers do not open

Set `window.flatpickr`:

```js
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
window.flatpickr = flatpickr;
```

## Every screen returns 403

Nothing grants access yet. The engine denies every ability by default. Define the umbrella gate in a service provider's `boot()`:

```php
Gate::define( 'ecommerce.admin', fn ( $user ) => $user->is_admin );
```

With `cms-framework`, assign the `shop-manager` role or individual `ecommerce.*` permissions, and run `php artisan ecommerce:sync-permissions` if the permissions are missing. See [Authorization](Authorization).

## One screen returns 403

The user can enter the admin but lacks that screen's ability. Check the screen in [Authorization](Authorization). Remember that inventory uses `inventory.*`, not `product.*`.

## The command palette is empty or missing

- `spotlight.enabled` is `false`. Set it to `true`.
- The component library's spotlight route (`artisanpack.spotlight`) is not registered, so the Search button is not shown. Check the library's spotlight is enabled.
- Your host spotlight class (`artisanpack.livewire-ui-components.components.spotlight.class`) throws or returns something unexpected. The admin's results are appended after it through the `ap.livewireUiComponents.spotlightCommands` filter; a broken host class stops the request before that.
- The user has searched more than 120 times in the last minute. Results return after the minute passes.
- The user holds none of the result sources' abilities.

See [Command Palette](Usage-Command-Palette).

## Real-time updates never arrive

All of these are needed:

1. `realtime.enabled` is `true` in this package's config.
2. `artisanpack.ecommerce.graphql.subscriptions` is `true` in the engine's config.
3. Laravel Echo is set up as `window.Echo`, with a running broadcaster (for example `php artisan reverb:start`) and a queue worker for broadcast jobs.
4. The user holds `order.viewAny`, `product.viewAny`, and `webhookSubscription.viewAny`.

If any is missing, the screens quietly skip subscribing. See [Real-Time Updates](Usage-Real-Time).

## "Too many changes in a short time"

The user hit the engine's `ecommerce.admin.mutate` limiter (120 Livewire updates a minute by default). Wait for the time in the message. To change the limit, redefine that rate limiter in your application.

## A product is read-only for an admin

Its type comes from a satellite that is not installed. The form shows a warning banner. Reinstall the satellite, or change the product's type.

## A product import stalls or runs twice

- Check a queue worker is running on `imports.queue` (or the default queue).
- The job can run for up to an hour. Set the queue connection's `retry_after` above 3600 seconds, or the queue hands the job out again while it is still running.
- Use **Resume** to continue a stopped import from the next row.

See [Product CSV Import and Export](Usage-Product-Csv).

## The admin does not appear in the cms-framework menu

- `admin.auto_register_cms_nav` is `false`, or `admin.routes_enabled` is `false`.
- The user cannot open any admin screen.
- A CMS menu entry already uses the same slug (`ecommerce-…`); the CMS entry wins.
