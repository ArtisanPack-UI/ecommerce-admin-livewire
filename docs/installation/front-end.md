---
title: Front-End Setup
---

# Front-End Setup

The package ships no CSS or JavaScript build. Your application's Vite build styles the admin and loads the scripts its components need. Until you do this, the admin renders unstyled and charts, date pickers, and the editor do not work.

The browser test suite builds a host like this. Use it as a working reference:

- `tests/Browser/workbench/resources/css/app.css`
- `tests/Browser/workbench/resources/js/app.js`
- `vite.config.js`

## 1. Tailwind 4 and daisyUI 5

The admin uses Tailwind CSS 4 and daisyUI 5 class names. Install them if your application does not already:

```bash
npm install -D tailwindcss @tailwindcss/vite daisyui@~5.0
```

Pin daisyUI to `~5.0`. Later 5.x releases hide `.tab-content`, so the component library's tab panels render empty (the product, promotion, and customer forms). Keep the pin until [livewire-ui-components#119](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/119) is fixed.

## 2. Add the admin to your Tailwind sources

`php artisan ecommerce-admin:install` prints these lines, and the npm packages and script lines in steps 3 and 4. Add them to your Tailwind entry, for example `resources/css/app.css`:

```css
@import 'tailwindcss';

@source "../../vendor/artisanpack-ui/ecommerce-admin-livewire/resources/views/**/*.blade.php";
@source "../../vendor/artisanpack-ui/ecommerce-admin-livewire/src/**/*.php";

/* The component library's views, if your app does not list them already. */
@source "../../vendor/artisanpack-ui/livewire-ui-components/resources/views/**/*.php";
@source "../../vendor/artisanpack-ui/livewire-ui-components/src/**/*.php";

@custom-variant dark (&:where(.dark, .dark *));

@plugin "daisyui" {
    themes: light --default, dark --prefersdark;
}
```

The paths are relative to the CSS file. Adjust them if your entry lives elsewhere.

## 3. Install the npm packages

```bash
npm install @artisanpack-ui/livewire-drag-and-drop apexcharts flatpickr
```

| Package | Used by |
| --- | --- |
| `@artisanpack-ui/livewire-drag-and-drop` | Reorderable lists: rule-builder rows, variants, gallery images, columns |
| `apexcharts` | Report charts and the dashboard sales sparkline |
| `flatpickr` | Date pickers (report ranges, order filters, scheduled prices) |

Every reorderable list also has move up / move down buttons, so reordering works without drag and drop.

## 4. Load the scripts

In `resources/js/app.js`:

```js
import ApexCharts from 'apexcharts';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import '@artisanpack-ui/livewire-drag-and-drop';
import '../../vendor/artisanpack-ui/livewire-ui-components/resources/js/sparkline.js';
import '../../vendor/artisanpack-ui/livewire-ui-components/resources/js/tinymce-editor.js';

window.ApexCharts = ApexCharts;
window.flatpickr = flatpickr;
```

Alpine comes with Livewire. Do not import it again.

## 5. Publish TinyMCE for the description editor

The product description uses the component library's TinyMCE editor. Publish the library's assets:

```bash
php artisan vendor:publish --tag=artisanpack-assets
```

Then load TinyMCE on the page. The `tinymce-editor.js` integration waits for it:

```js
const tinymce = document.createElement( 'script' );
tinymce.src = '/vendor/artisanpack-ui/js/tinymce/tinymce.min.js';
tinymce.referrerPolicy = 'origin';
document.head.append( tinymce );
```

A plain `<script src="/vendor/artisanpack-ui/js/tinymce/tinymce.min.js">` in your layout works too. Re-publish after upgrading the component library.

## 6. Build

```bash
npm run build   # or npm run dev
```

## Which entries the admin loads

Both layouts load your Vite entries `resources/css/app.css` and `resources/js/app.js`, but only when a Vite build (`public/build/manifest.json`) or a running dev server is present. If your entries have other names, change them with the `ap.ecommerceAdminLivewire.layout.viteEntries` filter:

```php
addFilter( 'ap.ecommerceAdminLivewire.layout.viteEntries', function ( array $entries ): array {
    return [ 'resources/css/admin.css', 'resources/js/admin.js' ];
} );
```

Return an empty array to load nothing, for example when your own layout already loads the build. See [Layout](Extending-Layout).

## Content Security Policy

If your application sends a Content Security Policy with a script nonce, the admin's inline scripts and the template editor's Ace scripts carry the same nonce: they read it from `Vite::cspNonce()`. Generate the nonce in a middleware that runs on admin pages (add it to `admin.middleware`):

```php
use Illuminate\Support\Facades\Vite;

Vite::useCspNonce();

$response->headers->set( 'Content-Security-Policy', "script-src 'nonce-" . Vite::cspNonce() . "' 'strict-dynamic'" );
```

Without a nonce, the scripts render without one, as before. The notification template editor loads Ace from `cdnjs.cloudflare.com` with Subresource Integrity, so a policy without `'strict-dynamic'` must allow that host.

## Checklist

| Symptom | Missing step |
| --- | --- |
| Admin is unstyled | Steps 2 and 6, or a Vite entry name that does not match |
| Product, promotion, or customer tabs are blank | daisyUI newer than 5.0.x (step 1) |
| Charts and the dashboard sparkline are missing | `window.ApexCharts` (step 4) |
| Date pickers do not open | `window.flatpickr` (step 4) |
| Description editor is a bare textarea or never loads | TinyMCE assets (step 5) |
| Drag handles do nothing | `@artisanpack-ui/livewire-drag-and-drop` (steps 3–4) |

See [Troubleshooting](Troubleshooting) for more.
