---
title: Layout
---

# Layout

Both layouts load your application's Vite entries, because the package ships no build of its own. Change which entries they load with the `ap.ecommerceAdminLivewire.layout.viteEntries` filter.

## The filter

```php
applyFilters( 'ap.ecommerceAdminLivewire.layout.viteEntries', array $entries ): array
```

| Default | Meaning |
| --- | --- |
| `[ 'resources/css/app.css', 'resources/js/app.js' ]` | Passed to `@vite()` in the standalone layout's `<head>`, or pushed onto the `styles` stack under `cms-framework` |

The entries load only when a Vite build (`public/build/manifest.json`) or a running dev server exists. Return an empty array to load nothing.

## Separate admin entries

Give the admin its own bundle, so storefront CSS does not ship to the admin and vice versa:

```php
addFilter( 'ap.ecommerceAdminLivewire.layout.viteEntries', function ( array $entries ): array {
    return [ 'resources/css/admin.css', 'resources/js/admin.js' ];
} );
```

Add the entries to your `vite.config.js` `input`, and put the admin's `@source` lines and scripts in them. See [Front-End Setup](Installation-Front-End).

## When your layout already loads the build

Under `cms-framework`, if your CMS admin layout already loads the same entries, the browser runs each module once and the repeated stylesheet is a no-op. You can still stop the admin pushing them:

```php
addFilter( 'ap.ecommerceAdminLivewire.layout.viteEntries', fn (): array => [] );
```

## Changing the layout itself

Publish the views (`php artisan vendor:publish --tag=ecommerce-admin-views`) and edit `layouts/app.blade.php`, or point pages at your own layout. See [Layouts](Installation-Layouts) for the contract a layout must keep.
