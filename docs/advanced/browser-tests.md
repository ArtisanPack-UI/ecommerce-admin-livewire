---
title: Browser Tests
---

# Browser Tests

For contributors. The admin has a browser suite (Pest 4's browser plugin on Playwright) that drives the real admin in Chromium against a seeded demo store. It covers the flows that unit and Livewire tests cannot: keyboard reorders and focus, the command palette, real assets, and an axe accessibility check on every screen.

## Running it

```bash
npm ci
npx playwright install chromium
npm run build:browser
composer test:browser
```

`composer test:browser` runs the build again before the tests, so after the first run it is the only command you need.

`composer test` runs only the Unit and Feature suites (`--testsuite=Unit,Feature`); loading the Browser suite would start Playwright. CI runs the browser suite in its own job, on Livewire 3 and 4.

## How the harness works

| Piece | Where | Role |
| --- | --- | --- |
| Base test case | `tests/BrowserTestCase.php` | Serves the Testbench app from the workbench `public` directory, uses the standalone layout, seeds a demo store (`ecommerce:seed-demo` with 12 products, 15 orders, fixed seed), and signs in an admin granted the `ecommerce.admin` gate |
| Workbench | `tests/Browser/workbench/` | Stands in for a host application: `resources/css/app.css` (Tailwind 4, daisyUI, the admin's `@source` lines) and `resources/js/app.js` (ApexCharts, flatpickr, drag and drop, sparkline, TinyMCE) |
| Build | `vite.config.js` | Builds the workbench entries into `tests/Browser/workbench/public/build` and copies the component library's JS and CSS the way `vendor:publish --tag=artisanpack-assets` does |
| Accessibility helper | `tests/Browser/Support/Accessibility.php` | `Accessibility::assertAccessible( $page )` runs every axe rule and fails on any violation |
| Locators | `tests/Browser/Support/Locators.php` | Finds controls by visible label or role, the way a person or screen reader does |
| Tests | `tests/Browser/*Test.php` | Accessibility, command palette, keyboard, notifications, orders, products, promotions |

The base test case throws if `public/build/manifest.json` is missing. Run `npm run build:browser` first.

Pest's group config in `tests/Pest.php` applies `BrowserTestCase` and the `browser` group to everything in `tests/Browser`, with a 10-second browser timeout.

## Known library exclusions

`Accessibility::LIBRARY_ISSUES` lists axe failures that come from `livewire-ui-components` markup this package cannot change. Each entry is an axe rule and a CSS selector; a node is ignored only when it sits inside that selector. All are tracked in [livewire-ui-components#117](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/117). Remove an entry when the library fixes it, and the suite enforces the rule again. See [Accessibility](Accessibility).

## Writing a test

```php
use Tests\Browser\Support\Accessibility;
use Tests\Browser\Support\Locators;

it( 'creates a tag', function (): void {
    $page = visit( route( 'artisanpack.ecommerce.admin.tags.index' ) )
        ->fill( Locators::label( 'New tag' ), 'Summer' )
        ->click( Locators::role( 'Add tag' ) )
        ->assertSee( 'Summer' );

    Accessibility::assertAccessible( $page );
} );
```

- Find fields by label (`Locators::label()`), not by id: the component library prefixes generated ids.
- Focus settles just after Livewire's update. Check focus and announcements with `assertScript()`, which retries until it holds.
- Assert `assertNoJavaScriptErrors()` on new screens.

## A locator that never matches

A locator that never matches makes the plugin wait for it, and a step that never resolves can wait indefinitely rather than failing fast. If a run hangs, look for a label or role that changed. CI caps the browser job at 20 minutes so a hang cannot block the pipeline.

## daisyUI pin

`package.json` pins `daisyui` to `~5.0`. Later 5.x releases hide `.tab-content`, which leaves the component library's tab panels empty and fails the product, promotion, and customer tests ([livewire-ui-components#119](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/119)). Keep the pin until that is fixed.
