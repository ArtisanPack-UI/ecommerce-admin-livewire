---
title: Front-End Setup
menu_order: 30
---

# Front-End Setup

The admin renders on the ArtisanPack UI design system through `livewire-ui-components` 3.0. The package ships no CSS or JavaScript build of its own: your application's Vite build loads the design system's stylesheet and behavior engine. Until you set this up, the admin renders unstyled and its drawers, dialogs, date pickers, and code editors don't work.

The admin's own few styles (the parts the design system has no component for) are inlined into the page with your CSP nonce, so they need no build step.

The browser test suite builds a host like this. Use it as a working reference:

- `tests/Browser/workbench/resources/css/app.css`
- `tests/Browser/workbench/resources/js/app.js`
- `vite.config.js`

## 1. Remove daisyUI

The admin no longer uses daisyUI. If the admin was the only thing using it, remove the `daisyui` package and the `@plugin "daisyui"` block from your stylesheet. Its base styles fight the design system's components.

## 2. Install the npm packages

`php artisan ecommerce-admin:install` prints the packages here and the lines in steps 3 and 4.

```bash
npm install -D tailwindcss @tailwindcss/vite
npm install @artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1 @artisanpack-ui/livewire-drag-and-drop
```

| Package | Used for |
| --- | --- |
| `@artisanpack-ui/css` | The design system's tokens and component styles |
| `@artisanpack-ui/behavior` | The behavior engine behind drawers, dialogs, comboboxes, date pickers, tabs, the command palette, and the code editor |
| `@artisanpack-ui/livewire-drag-and-drop` | Reorderable lists: rule-builder rows, variants, gallery images, columns |

Every reorderable list also has move up and move down buttons, so reordering works without drag and drop.

## 3. Add the stylesheet lines

Add these to your main stylesheet, for example `resources/css/app.css`:

```css
@layer theme, base, apui, components, utilities;
@import 'tailwindcss';
@import '@artisanpack-ui/css';

@source "../../vendor/artisanpack-ui/ecommerce-admin-livewire/resources/views/**/*.blade.php";
@source "../../vendor/artisanpack-ui/ecommerce-admin-livewire/src/**/*.php";

/* The component library's views, if your app doesn't list them already. */
@source "../../vendor/artisanpack-ui/livewire-ui-components/resources/views/**/*.php";
@source "../../vendor/artisanpack-ui/livewire-ui-components/src/**/*.php";
```

The `@layer` line puts the design system in its own layer between Tailwind's base and its utilities, so Preflight never restyles a component and utilities still win. Keep it first in the file.

The paths are relative to the CSS file. Adjust them if your entry lives elsewhere.

## 4. Load the scripts

In `resources/js/app.js`:

```js
import apuiBehavior from '@artisanpack-ui/behavior/alpine';
import '@artisanpack-ui/livewire-drag-and-drop';

document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
```

Alpine comes with Livewire. Don't import it again: the behavior engine registers itself as Alpine starts.

## 5. Publish TinyMCE for the description editor

The product description uses the component library's rich text editor, which runs on TinyMCE. Publish the library's assets:

```bash
php artisan vendor:publish --tag=artisanpack-assets
```

Then load TinyMCE on the page:

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

Return an empty array to load nothing, for example when your own layout already loads the build. See [Layout](../extending/layout.md).

## Content Security Policy

If your application sends a Content Security Policy with a nonce, the admin's inline scripts and its inline `<style>` carry the same nonce: they read it from `Vite::cspNonce()`. The admin uses no inline `style=` attributes, so a nonce-only `style-src` works too. Generate the nonce in a middleware that runs on admin pages (add it to `admin.middleware`):

```php
use Illuminate\Support\Facades\Vite;

Vite::useCspNonce();

$nonce = Vite::cspNonce();

$response->headers->set( 'Content-Security-Policy', "script-src 'nonce-{$nonce}' 'strict-dynamic'; style-src 'self' 'nonce-{$nonce}'" );
```

Without a nonce, the scripts and styles render without one. The notification template editor uses the component library's code editor, so the admin no longer loads anything from a CDN.

## Checklist

| Symptom | Missing step |
| --- | --- |
| Admin is unstyled | Steps 2, 3, and 6, or a Vite entry name that doesn't match |
| Buttons, inputs, and cards look half-styled | daisyUI still loaded (step 1), or the `@layer` line missing or not first (step 3) |
| Drawers, dialogs, the command palette, or date pickers don't open | The behavior engine isn't registered (step 4) |
| Description editor is a bare field or never loads | TinyMCE assets (step 5) |
| Drag handles do nothing | `@artisanpack-ui/livewire-drag-and-drop` (steps 2 and 4) |

See [Troubleshooting](../troubleshooting.md) for more.
