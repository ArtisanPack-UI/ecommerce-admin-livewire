# Package Spec: `artisanpack-ui/ecommerce-admin-livewire`

**Status:** Approved v0.2 — decisions in §2 confirmed 2026-09-30
**Owner:** Jacob Martella
**Last updated:** 2026-09-30
**Tracker:** [ArtisanPack-UI/ecommerce#52](https://github.com/ArtisanPack-UI/ecommerce/issues/52)
**Parent plan:** `12-ecommerce-package-plan.md` — §3.2, §10.2, §11, §15.4, §16.5, §16.6, §18 Phase 6
**Engine spec:** [`13-ecommerce-engine-spec.md`](https://github.com/ArtisanPack-UI/ecommerce/blob/main/docs/plans/13-ecommerce-engine-spec.md) (referred to below as "engine §N")

## 0. Purpose

This is the build spec for the Livewire admin satellite: the first UI on top of the headless `artisanpack-ui/ecommerce` engine. It fixes the package identity, how the admin reaches engine data, the screen inventory, the shared components, the extension surface other satellites use, and the work the engine and `livewire-ui-components` must do first. §14 breaks the build into issues.

When the parent plan and this spec disagree on admin behaviour, this spec wins. When this spec and the engine spec disagree on a table, contract, hook, or ability, the engine spec wins.

Out of scope for this file: the storefront (`14-…`), the kanban board UI (`16-…`), and the React/Vue admins. They are mentioned only where a boundary has to be drawn.

## 1. What the parent plan requires

Parent plan §10.2 lists thirteen admin deliverables. Phase 6 adds kanban settings and browser tests. This spec covers all of them:

| # | Deliverable (plan §10.2 / Phase 6) | Section |
|---|---|---|
| 1 | Products: list + filters, per-type create/edit forms, bulk actions, CSV import/export | §7.2 |
| 2 | Orders: list view, detail with timeline + notes + refunds + item-level fulfillment | §7.3 |
| 3 | Customers: list, detail with LTV, order history, addresses | §7.4 |
| 4 | Promotions/coupons: rule-builder UI | §7.5 |
| 5 | Shipping: zones, methods per zone | §7.6 |
| 6 | Tax: classes + rates table | §7.6 |
| 7 | Reports: sales over time, top products, revenue by category, tax collected, inventory levels, low stock | §7.7 |
| 8 | Notification templates editor with live preview | §7.8 |
| 9 | Webhook subscriptions | §7.8 |
| 10 | Settings | §7.9 |
| 11 | Command palette (Cmd+K) | §8.1 |
| 12 | Activity timeline on every entity | §8.2 |
| 13 | Internal notes on orders + customers | §8.3 |
| — | Kanban settings (Phase 6) | §7.10 |
| — | Browser tests (Phase 6, plan §15.4) | §12 |

## 2. Decisions

### 2.1 Locked by the parent plan

- UI base is `artisanpack-ui/livewire-ui-components` only. No custom widget primitives in this package; a missing primitive is filed against the component library (§10).
- Day-1 locales are `en`, `es`, `fr`, `de`. Every user-facing string goes through `__()` / `trans_choice()`.
- The package registers with the engine's `SatelliteRegistry` and honours the uninstall lifecycle.
- Browser tests cover create product, edit product, refund order.

### 2.2 Decided for this package (confirmed 2026-09-30)

| # | Decision | Outcome | Why |
|---|---|---|---|
| D1 | How the admin reaches engine data | **In-process**: Livewire components call engine services and Eloquent models, authorized by the engine's policies. No HTTP round-trip to `/api/ecommerce/v1`. | The plan says UI satellites "consume the same REST API", but a Livewire component runs inside the same Laravel app as the engine. Calling its own REST API would need a Sanctum token per admin session and an `Idempotency-Key` per action, and would double every request. Engine 1.0.0 also has no admin REST writes for products, variants, prices, images, categories, tags, or inventory; its `docs/api.md` says products are managed "through the Eloquent models, or by an admin satellite, for now". |
| D2 | Where missing domain writes live | **In the engine, in its 1.0 release** (1.0 is not published yet, so the additions do not need a 1.1). Every write the React and Vue admins will also need (product CRUD, order cancel, order notes, customer delete, substatus CRUD, settings, reports) lands in the engine as a service, with the REST endpoint from engine §9 on top. This package calls the service. | Keeps the three admin families on one implementation. The cost is that some admin issues wait on engine issues (§9 lists them). |
| D3 | Settings storage | **Engine-owned store** (`ecommerce_settings` table read through a repository that overlays `config('artisanpack.ecommerce')`). Secrets (gateway keys, webhook signing keys) stay in env/config and are shown as "configured / not configured" only. | The engine is config-only today, so there is nothing for a settings screen to write to. `cms-framework`'s `apRegisterSetting` exists but would make settings depend on an optional package. |
| D4 | Kanban boundary | This package owns **board, column, and automation configuration as forms**, plus order sub-status management. `ecommerce-kanban-livewire` owns the drag-and-drop board and links to these screens. | Phase 6 lists "kanban settings" under admin, and sub-statuses are needed by the order screens regardless of whether the kanban satellite is installed. |
| D5 | Browser test tool | **Pest 4 browser plugin**, which makes this package `php: ^8.3` and Pest 4. Laravel Dusk on Pest 3 was the alternative. | The plan names `pestphp/pest-plugin-browser`, which needs Pest 4 and PHP 8.3. `cms-framework` already requires PHP 8.3. No sibling package has working browser tests to follow. |
| D6 | Manual / draft order creation | **Not in v1.** | Plan §10.2 does not list it, and the engine only places orders through `PaymentOrchestrator::finalize()` from a cart. `OrderPolicy::create` exists, so it can be added later without a policy change. |

## 3. Package identity

| Item | Value |
|---|---|
| Composer name | `artisanpack-ui/ecommerce-admin-livewire` |
| Namespace | `ArtisanPackUI\EcommerceAdminLivewire\` |
| Service provider | `ArtisanPackUI\EcommerceAdminLivewire\EcommerceAdminLivewireServiceProvider` |
| Config file / key | `config/artisanpack/ecommerce-admin-livewire.php` → `artisanpack.ecommerce-admin-livewire` |
| View namespace | `ecommerce-admin` |
| Livewire component names | `artisanpack-ecommerce-admin-{screen}` (e.g. `artisanpack-ecommerce-admin-products-index`) |
| Blade component prefix | `<x-artisanpack-ec-…>` (plan §20) for composed display components |
| Route prefix | `config( '…admin.route_prefix', 'ecommerce-admin' )` |
| Route name prefix | `artisanpack.ecommerce.admin.` |
| Hook prefix | `ap.ecommerceAdminLivewire.` |
| Translations | `lang/{en,es,fr,de}.json`, loaded with `loadJsonTranslationsFrom()` |
| Publish tags | `ecommerce-admin-config`, `ecommerce-admin-views`, `ecommerce-admin-lang` |
| License | MIT (matches the engine; decided for 1.0.0) |

Naming follows the `bookings` package, the closest sibling with a Livewire admin.

## 4. Dependencies

### 4.1 `require`

```json
{
    "php": "^8.3",
    "illuminate/support": "^12.0|^13.0",
    "livewire/livewire": "^3.6|^4.0",
    "artisanpack-ui/ecommerce": "^1.0",
    "artisanpack-ui/livewire-ui-components": "^2.1",
    "artisanpack-ui/core": "^1.0",
    "artisanpack-ui/hooks": "^1.2",
    "artisanpack-ui/security": "^1.0|^2.0"
}
```

- Laravel 12+ only, because the engine requires it. This departs from the ecosystem default of 10–12.
- PHP 8.3+ because of D5 (Pest 4). The engine itself stays on `^8.2`.
- `artisanpack-ui/ecommerce: ^1.0` is enough: the §9 engine work ships in 1.0.

### 4.2 Soft integrations (`suggest` + runtime detection)

| Package | What it enables |
|---|---|
| `artisanpack-ui/cms-framework` | Admin pages render inside `cms::admin.layouts.app`; nav entries join the CMS menu through the `ap.cmsFramework.admin.menu` filter; engine abilities are registered as RBAC permissions. |
| `artisanpack-ui/media-library` | Product, variant, and category images picked through the media modal. Without it, image fields fall back to `image_url`. |
| `artisanpack-ui/icons` | Icon pickers for categories and sub-statuses. |
| `artisanpack-ui/accessibility` | Contrast warning when a sub-status or column colour fails WCAG AA. |
| `artisanpack-ui/ecommerce-kanban-livewire` | "Open board" links from order detail and kanban settings. |
| `phpoffice/phpspreadsheet`, `barryvdh/laravel-dompdf` | XLSX and PDF table export (CSV works without them). |

### 4.3 Front-end

- `@artisanpack-ui/livewire-drag-and-drop` (npm) for reorderable lists: rule-builder rows, variant order, column order, image gallery. The table's built-in `sortable` only works on Livewire 4, so it cannot be the only reorder path.
- `flatpickr`, TinyMCE, and Ace arrive through `x-artisanpack-datepicker`, `x-artisanpack-editor`, and `x-artisanpack-code`.
- No package CSS build. The host adds the package views to its Tailwind sources (`@source`); the install command prints the line.

### 4.4 `require-dev`

Standard blueprint set (Testbench `^10.2`, code-style, code-style-pint, php-cs-fixer, phpcs installer), with `pestphp/pest: ^4.0` and `pestphp/pest-plugin-browser` in place of Pest 3 (D5).

## 5. Architecture

### 5.1 Layers

```
Route → AdminScreenController (returns a page view)
          → page view  @extends( $ecommerceAdminLayout )
              → <livewire:artisanpack-ecommerce-admin-… />
                   → engine policy check (authorize)
                   → engine service / model        ← writes
                   → query builder on engine models ← reads
```

- **Pages are controller actions that return a view embedding one Livewire component.** They are not full-page Livewire routes and not closures, so `route:cache` works and the layout can be swapped. This is the `bookings` pattern.
- **Components are class-based** (`src/Livewire/{Area}/…`), not Volt, matching every sibling package.
- **Writes go through engine services.** A component never assembles a multi-table write itself. Where the engine has no service yet (§9), the admin issue depends on the engine issue.
- **Reads use Eloquent directly** with eager loading, inside a per-screen query class (`src/Queries/`) so filters and sorting are testable without rendering.
- **Registration** is guarded by `class_exists( Livewire::class )`. On Livewire 4 components are registered so that both `Livewire::component()` names resolve (the `media-library` package documents a v4 resolver quirk with `ns::name` names; this package uses hyphenated names, which avoids it).

### 5.2 Things the REST layer did that the admin must do itself

Because of D1 the admin skips the REST middleware, so it takes on three of its jobs:

- **Authorization** — §6.
- **Idempotency** — destructive or money-moving actions (refund, cancel, shipment, order edit, bulk delete) carry a one-time action token minted at render and consumed on submit, inside the same transaction as the write. Buttons are disabled with `wire:loading.attr="disabled"`.
- **Rate limiting** — Livewire update requests for this package run through the engine's `ecommerce.admin.mutate` limiter (120/min per user) via persistent middleware.

### 5.3 Layout

- A view composer on `ecommerce-admin::pages.*` sets `$ecommerceAdminLayout` to `cms::admin.layouts.app` when `cms-framework` is installed, else `ecommerce-admin::layouts.app`.
- Both layouts expose `title` and `content` sections and `styles` / `scripts` stacks. The CMS layout ships no Livewire assets or CSS build, so page views push what they need onto the stacks.
- The standalone layout is built from `x-artisanpack-main`, `x-artisanpack-nav`, and `x-artisanpack-menu`, with the theme toggle and the command palette mounted once.
- `cms-framework` is detected with `class_exists( \ArtisanPackUI\CMSFramework\Modules\Admin\Managers\AdminMenuManager::class )`. Note the capital `CMS`: `bookings` and `seo` probe `CmsFramework`, which should be checked separately.

### 5.4 Navigation

`Support\AdminNav::items()` is the single source for both layouts.

| Section | Entries |
|---|---|
| (top) | Dashboard |
| Orders | Orders, Reviews |
| Catalog | Products, Categories, Tags, Inventory, Digital files, License keys |
| Customers | Customers |
| Marketing | Promotions |
| Reports | Reports |
| Configuration | Shipping, Tax, Notifications, Webhooks, Order statuses, Kanban boards, Settings |

- Each entry carries `{ key, label, icon, route, position, permission, badge? }` — the shape engine §5 gives for `AdminMenuRegistry`.
- Entries the user cannot `viewAny` are hidden.
- Badges: pending reviews, low-stock count, orders awaiting fulfillment. Each is one cached count query.
- Other satellites add entries through the engine's `AdminMenuRegistry` once it exists (§9 E6). Until then, the filter `ap.ecommerceAdminLivewire.nav.items` does the same job.

### 5.5 Config

```php
return [
    'admin' => [
        'route_prefix'          => 'ecommerce-admin',
        'middleware'            => [ 'web', 'auth' ],   // host adds 2FA / verified here
        'routes_enabled'        => true,
        'auto_register_cms_nav' => true,
    ],
    'tables' => [
        'per_page'        => 25,
        'per_page_values' => [ 10, 25, 50, 100 ],
    ],
    'spotlight' => [
        'enabled'  => true,
        'shortcut' => 'meta.k',
        'limit'    => 5,            // results per entity type
    ],
    'realtime' => [
        'enabled' => false,         // subscribe to private-ecommerce.admin when Echo is present
    ],
    'imports' => [
        'disk'     => 'local',
        'max_rows' => 5000,
        'queue'    => null,
    ],
];
```

Plan §11.5 asks for 2FA on admin routes. The engine does not apply it, so the host adds its 2FA middleware to `admin.middleware`; the README documents this.

## 6. Authorization

- Every component authorizes through the engine's policies (`$this->authorize( 'update', $product )`). The policies delegate to `EcommerceAuthorizer`, which checks Gate ability `ecommerce.{resource}.{action}`, falls back to the umbrella `ecommerce.admin`, then applies the `ap.ecommerce.abilities.{resource}.{action}` filter. Default is deny.
- Admin sessions are cookie-authenticated, so Sanctum token narrowing does not apply.
- `mount()` authorizes the screen; every action method authorizes again. Route middleware `ecommerce-admin.access` lets a user in when at least one nav entry is visible to them, and is registered with `Livewire::addPersistentMiddleware()` so update requests are re-checked.
- When `cms-framework` is present, the provider registers each engine ability as an RBAC permission (`ap_register_permission`) and ships a `shop-manager` role with all of them, per plan §11.1.
- A product whose type is missing (`Product::typeIsMissing()`) renders read-only with the engine's `typeWarning()` banner, per plan §16.6.

Abilities used per screen:

| Screen | Abilities |
|---|---|
| Products, categories, tags | `product.{viewAny,view,create,update,delete}` |
| Inventory | `inventory.viewAny`, `inventory.adjust` |
| Reviews | `review.{viewAny,view,moderate,delete}` |
| Digital files / license keys | `digitalFile.*`, `licenseKey.{view,revoke}` |
| Orders | `order.{viewAny,view,update,edit-fulfilled,cancel,refund}`, `refund.{view,create}` |
| Customers | `customer.{viewAny,view,update,delete}` |
| Promotions / coupons | `promotion.*`, `coupon.*` |
| Tax / shipping | `taxRate.*`, `shippingZone.*` |
| Notification templates | `notificationTemplate.{viewAny,view,update}` |
| Webhooks | `webhookSubscription.*` |
| Kanban settings | `kanbanBoard.*` |
| Order statuses | `orderSubstatus.*` |
| Reports | `report.view` |
| Settings | `settings.view`, `settings.update` |

## 7. Screens

Route paths are relative to the route prefix; names are relative to `artisanpack.ecommerce.admin.`.

### 7.1 Dashboard — `/` (`dashboard`)

- KPI row (`x-artisanpack-stat` in `x-artisanpack-widget-grid`): sales today and last 30 days in base currency, orders awaiting fulfillment, low-stock items, reviews awaiting moderation.
- Sales sparkline for the last 30 days.
- Ten most recent orders; five lowest-stock items.
- Each widget is hidden when the user lacks the matching `viewAny`.
- Extension: `ap.ecommerceAdminLivewire.dashboard.widgets` filter.

### 7.2 Catalog

**Products index — `/products` (`products.index`)**

- Columns: image, name, SKU, type, status, price (store base currency), stock, categories, updated.
- Filters: search (name/SKU, through Scout when enabled), status, type, category, tag, stock state (in / low / out).
- Bulk actions: publish, archive, delete, add/remove category, add/remove tag, export selection.
- Runs the engine's `ap.ecommerce.product.listQuery` filter on the query.

**Product form — `/products/create`, `/products/{product}/edit` (`products.create`, `products.edit`)**

Tabs, in order:

| Tab | Fields (engine §3.1–§3.11) |
|---|---|
| General | name, slug (auto from name, editable), type (create only), short description, description (`x-artisanpack-editor`), status, `published_at` |
| Pricing | one row per enabled currency: price, compare-at, cost, optional `starts_at` / `ends_at` for scheduled prices |
| Inventory | SKU, barcode, track inventory, quantity on hand (adjustments go through `InventoryService::adjust()` with a reason), allow backorder, low-stock threshold |
| Shipping | weight + unit, length/width/height + unit |
| Tax | taxable toggle, tax class |
| Organization | categories (tree choices), tags (`x-artisanpack-tags`) |
| Media | featured image, ordered gallery with alt text |
| *type panel* | supplied by the product-type panel registry (§8.5) |
| Activity | §8.2 |

Core type panels:

- `simple` — none.
- `variable` — attribute editor (key, label, values, swatch, "used for variations"); "generate variants" from the attribute matrix; variants table with inline SKU, price, stock, image, and position.
- `digital` — attached digital files (label, version, streaming-only), download limit and expiry, license-key issuance toggle.
- `grouped` / `bundled` — child product picker with quantities. The field list is fixed when E1 defines how the engine stores children.

Saving is one action that validates every tab and reports the first tab with an error.

**Categories — `/categories`, Tags — `/tags`**

- Categories: tree view with parent, name, slug, description, image, icon, position; reorder within a parent.
- Tags: flat table with inline create/rename/delete and a merge action.

**Inventory — `/inventory` (`inventory.index`)**

- One row per `inventory_items` record: product/variant, SKU, on hand, reserved, available, threshold, backorder.
- Filters: low stock, out of stock, tracked only.
- Adjust action: delta or set-to, with a required reason. Bulk adjust from CSV.

**CSV import/export — `/products/import` (`products.import`)**

- Export: current filter or selection, one row per product or variant, prices as one column pair per currency.
- Import: upload → column mapping → dry-run report (creates / updates / errors per row) → queued apply with a progress bar. Rows match on SKU, then slug.
- Hard limit `imports.max_rows`. The import is resumable and each row is its own transaction.

**Reviews — `/reviews` (`reviews.index`)**

- Queue defaults to `pending`. Columns: product, rating, author, verified-purchase badge, excerpt, submitted.
- Row and bulk actions: approve, reject (with reason), mark spam, requeue, delete — all through `ReviewService`.

**Digital files — `/digital-files`, License keys — `/license-keys`**

- Digital files: table plus create/edit drawer (product/variant, label, version, file, streaming-only). Bumping the version goes through `DigitalFileService::update()`, which notifies customers.
- License keys: search by key, order, or customer; activations list; revoke with reason.

### 7.3 Orders

**Orders index — `/orders` (`orders.index`)**

- Columns: number, placed, customer, system status, sub-status, payment status, fulfillment status, total (order currency), items.
- Filters: search (number, email, customer name), system status, sub-status, payment status, fulfillment status, date range, currency, board.
- Bulk actions: change sub-status, export. No bulk refund or cancel.
- Optional live prepend of new orders when `realtime.enabled` (channel `private-ecommerce.admin`).

**Order detail — `/orders/{order}` (`orders.show`)**

Two-column layout.

Main column:

- **Items** — snapshot name, options, SKU, quantity, unit price, discount, tax, total, per-item fulfillment status.
- **Totals** — subtotal, discounts (with promotion names), shipping, tax breakdown, total, refunded, net. Shown in order currency, with the base-currency equivalent from `fx_rate_to_base_e8` when they differ.
- **Fulfillment** — shipments with carrier, tracking, status; "create shipment" picks items and quantities from `ShipmentService::remainingQuantities()`; update tracking; buy label when a label provider is registered.
- **Refunds** — history, plus "issue refund": per-item quantity and amount or a free amount, restock toggle per item, reason. Shows the remaining refundable amount and whether the gateway supports partial refunds. Goes through `RefundService::issue()`.
- **Digital** — download tokens and license keys for the order, with revoke.
- **Timeline** — §8.2.

Side column:

- **Status** — system status, sub-status picker, payment and fulfillment badges. Transitions offered are the ones `OrderStatusMachine::isTransitionAllowed()` permits.
- **Customer** — name, email, link to the customer, claimed / guest flag.
- **Addresses** — shipping and billing.
- **Payment** — gateway, reference, fraud verdict when recorded.
- **Boards** — current board assignments with each board's sub-status; add/remove assignment.
- **Notes** — §8.3.

Actions:

- **Edit order** — a draft edit (items, quantities, addresses, shipping method) with a before/after totals diff, a required reason, and a "payment action required" notice when the total rises. Applied with `OrderEditService::apply()`; the latest edit can be rolled back. Editing after fulfillment has started needs `order.edit-fulfilled`.
- **Cancel** — reason required; shows what will be released (reservations) and whether a refund is still owed.

### 7.4 Customers

**Index — `/customers` (`customers.index`)**

- Columns: name, email, orders, total spent (base currency), last order, accepts marketing, account (linked user or guest).
- Filters: search, has account, accepts marketing, order-count and spend ranges, last-order date range.
- Export.

**Detail — `/customers/{customer}` (`customers.show`)**

- Header stats: lifetime value, order count, average order value, first and last order dates.
- Tabs: Orders, Addresses (add/edit/delete, default shipping/billing), Notification preferences (read, plus admin override), Notes (§8.3), Activity (§8.2).
- Edit: name, phone, marketing consent (records `accepts_marketing_at`).
- Delete: GDPR delete-and-anonymize per plan §19.6, with a typed confirmation. Depends on E4.

### 7.5 Promotions

**Index — `/promotions` (`promotions.index`)**

- Columns: name, source type, state (scheduled / active / expired / disabled), window, uses vs. limit, priority, exclusive.
- Filters: state, source type, search.

**Form — `/promotions/create`, `/promotions/{promotion}/edit`**

- **Details** — name, key, description, source type, active, priority, exclusive, start/end, total and per-customer usage limits.
- **Rule builder** — two ordered lists, *Conditions* and *Actions*. "Add" opens a picker fed by `PromotionConditionRegistry` / `PromotionActionRegistry`; each row renders its config with the config-form renderer (§8.4). Rows reorder by drag or keyboard. A plain-language summary ("10% off the cart when subtotal is at least $50 and the customer's first order") is rendered from the rows.
- **Coupons** (source type `coupon`) — code list, add one, bulk generate (count, prefix, length), export codes.
- **Usage** — `promotion_usages` table: order, customer, amount discounted, date.

### 7.6 Shipping and tax

**Shipping — `/shipping` (`shipping.index`)**

- Zones list ordered by priority: name, countries, regions, postal patterns, active.
- Each zone expands to its methods: type (from `ShippingMethodTypeRegistry`), label, config (§8.4), tax class, active, position.
- Warns when a country is covered by no zone.

**Tax — `/tax` (`tax.index`)**

- Classes: key, label.
- Rates table, editable inline: class, country, region, postal pattern, rate (entered as a percent, stored as `rate_ubps`), label, compound, shipping taxable, priority, active.
- Filters: class, country. CSV import/export of rates.
- Shows which `TaxProvider` is active; when it is not `manual`, the rates table is labelled as unused.

### 7.7 Reports — `/reports/{report}` (`reports.show`)

Common controls: date range, comparison to previous period, interval (day / week / month), currency mode. Every report has a chart (`x-artisanpack-chart`), a table, and CSV export.

| Report | Shows |
|---|---|
| Sales over time | Gross sales, discounts, refunds, net sales, tax, shipping, order count, average order value |
| Top products | Units and net revenue by product/variant |
| Revenue by category | Net revenue by category |
| Tax collected | Tax by rate label and jurisdiction |
| Inventory levels | On hand, reserved, available, stock value at cost |
| Low stock | Items at or below threshold |

Multi-currency follows plan §16.4: amounts are converted per order with the snapshot `fx_rate_to_base_e8`. Orders whose `base_currency` differs from the current base are converted at the current cross-rate and flagged in the UI.

The aggregation queries live in the engine (E8) so the React and Vue admins return the same numbers.

### 7.8 Notifications and webhooks

**Notification templates — `/notifications` (`notifications.index`, `notifications.edit`)**

- List grouped by template key: channel, locale, active, last edited.
- Editor: subject and body as Twig source (`x-artisanpack-code`), a variables panel from `NotificationTemplateService::declaredVariables()` with click-to-insert, locale switcher, active toggle, reset to default.
- Live preview: debounced call to `NotificationTemplateService::preview()` against the template's preview data (editable as JSON). Sandbox and undeclared-variable errors show inline with the line number.
- "Send test" to the signed-in admin's address.

**Webhooks — `/webhooks` (`webhooks.index`, `webhooks.show`)**

- Subscriptions: name, URL, events, active, last success, last failure, consecutive failures.
- Create/edit: name, URL, event multi-select from `config( 'artisanpack.ecommerce.webhooks.events' )`, active. The secret is shown once on creation; "rotate secret" replaces it.
- Deliveries log per subscription: event, status code, attempts, next retry, delivered at; payload and response body in a drawer; replay through `WebhookSubscriptionService::replay()`.

### 7.9 Settings — `/settings/{group}` (`settings.show`)

| Group | Contents |
|---|---|
| General | Store name, support email, base currency (with the plan §16.4 warning), enabled currencies, store time zone |
| Checkout | Reservation TTL, guest checkout, abandoned-cart threshold |
| Tax | Active provider, prices include tax, tax label override per locale |
| Shipping | Default allocation strategy, weight and dimension units |
| Payments | Registered gateways with enabled toggle and "configured" status; fraud provider chain. Credentials are never shown or edited. |
| Notifications | Admin recipients, default locale, review-request delay, customer preference channels |
| Reviews / Digital / Licenses | The engine's `reviews`, `digital`, `licenses` config sections |
| Satellites | Read-only list from `SatelliteRegistry`: package, version, active / uninstalled, contract-verified. Uninstall stays a CLI command. |

Other satellites add a group through the settings-tab registry (§8.5). All of this depends on E7.

### 7.10 Order statuses and kanban settings

**Order statuses — `/order-statuses` (`order-statuses.index`)**

- Sub-statuses grouped under the six system statuses: key, label, colour, icon, position, terminal flag.
- Reorder within a system status. Delete is blocked while orders or board columns use the sub-status.
- Contrast warning on colour when `accessibility` is installed.

**Kanban boards — `/kanban-boards` (`kanban-boards.index`, `kanban-boards.edit`)**

- Boards: name, key, description, default, active, position.
- Routing rules: a condition tree built with the same rule-builder rows as promotions.
- Columns: sub-status, label/colour/icon overrides, WIP limit, position, card widgets (ordered pick from `KanbanCardWidgetRegistry`).
- Automations: from column (or any), to column, trigger (from `KanbanAutomationRegistry`), trigger config (§8.4), conditions, active.
- "Open board" link when the kanban satellite is installed.

## 8. Shared components

### 8.1 Command palette

- `x-artisanpack-spotlight` with `shortcut="meta.k"`, mounted once in the layout.
- Results come from a provider registered on the `ap.livewireUiComponents.spotlightCommands` filter, so the host's own spotlight class keeps working.
- Searches orders (number, email), products (name, SKU), customers (name, email), promotions, and coupons, each capped at `spotlight.limit` and each gated by `viewAny`.
- Actions: "New product", "New promotion", jump to any nav entry, and contextual actions when the query is an order number ("Refund order #…", "Add note to #…").
- Extension: `ap.ecommerceAdminLivewire.spotlight.providers` filter.

### 8.2 Activity timeline

- One Livewire component, `artisanpack-ecommerce-admin-timeline`, taking a subject model.
- Renders entries newest first with actor, relative and absolute time, an icon per `event_type`, and a one-line description built from the payload. Unknown event types fall back to the raw type and a collapsible payload.
- Filters: all / notes / system events. Paginated ("load more").
- Orders read `order_timeline_entries`, which the engine already writes. Products, customers, and promotions need E9.
- Extension: `ap.ecommerceAdminLivewire.timeline.entry` filter to describe satellite event types.

### 8.3 Internal notes

- `artisanpack-ecommerce-admin-notes`, taking a subject model.
- Add a note; the author and time are recorded; notes are append-only with delete limited to the author or an admin.
- On orders, a "visible to customer" toggle maps to `order_notes.is_customer_visible` and is off by default. Customer notes have no such toggle.
- Adding a note also writes a `note.added` timeline entry.
- Order notes need E3 (no engine service creates them today); customer notes need E9.

### 8.4 Config-form renderer

Promotion conditions and actions, shipping method types, kanban triggers, and card widgets all store a `config` JSON blob, and the engine contracts do not describe its fields. The admin therefore needs a schema per registry key.

- `Support\ConfigFormRegistry` maps `{registry}:{key}` to a field schema: `[ name, type, label, hint, rules, options ]` with types `text`, `number`, `money`, `percent`, `boolean`, `select`, `multiselect`, `product`, `category`, `tag`, `date`, `daterange`, `weekday`, `template`.
- `<x-artisanpack-ec-config-form>` renders a schema with `livewire-ui-components` inputs and validates with the schema's rules.
- This package ships schemas for every core key (engine §5 rows 3, 7, 8, 10, 11).
- A key with no schema falls back to a JSON editor (`x-artisanpack-code`) with a notice, so third-party entries stay editable.
- E11 proposes moving the schema onto the engine contracts so all three admin families share it.

### 8.5 Extension registries for other satellites

| Surface | How a satellite plugs in |
|---|---|
| Nav entry | Engine `AdminMenuRegistry` (E6); interim filter `ap.ecommerceAdminLivewire.nav.items` |
| Product-type panel | `ProductTypePanelRegistry::register( $typeKey, $livewireComponent )` |
| Order detail panel | `OrderPanelRegistry::register( $key, $livewireComponent, $column, $position )` |
| Customer detail tab | `CustomerTabRegistry::register( … )` |
| Settings group | `SettingsTabRegistry::register( $key, $label, $livewireComponent )` |
| Config form | `ConfigFormRegistry::register( $registry, $key, $schema )` |
| Table columns / filters / bulk actions | Filters `ap.ecommerceAdminLivewire.table.{screen}.columns`, `.filters`, `.bulkActions` |
| Dashboard widget, spotlight provider, timeline entry | Filters named in §7.1, §8.1, §8.2 |

All registries are singletons under `ArtisanPackUI\EcommerceAdminLivewire\Registries\` and follow the engine's registry conventions (engine §5).

### 8.6 Display and input helpers

Composed Blade components (no new primitives):

- `<x-artisanpack-ec-money>` — formats through the engine's `MoneyFormatter`.
- `<x-artisanpack-ec-money-input>` — wraps `x-artisanpack-input money`; converts between the displayed major units and integer minor units using the currency's subunit. No float arithmetic.
- `<x-artisanpack-ec-percent-input>` — percent ↔ `rate_ubps` through the engine's `TaxRateMath`.
- `<x-artisanpack-ec-status-badge>` — system, payment, fulfillment, and sub-status badges with accessible text colour.
- `<x-artisanpack-ec-address>`, `<x-artisanpack-ec-address-form>`.
- Product, customer, category, and tag pickers on `x-artisanpack-choices`.
- `Livewire\Concerns\WithResourceTable` — search, filters, sort, pagination, selection, and URL query-string state shared by every index screen.

## 9. Engine prerequisites

Verified against engine 1.0.0 source. Each row is an issue on `ArtisanPack-UI/ecommerce`, milestone `v1.0` (D2).

| # | Gap | Engine spec says | Blocks |
|---|---|---|---|
| E1 ([ecommerce#139](https://github.com/ArtisanPack-UI/ecommerce/issues/139)) | No product write service or admin REST writes: products, variants, prices, images, attributes, categories, tags. Grouped/bundled child storage is undefined. | §9.5 lists the endpoints; `docs/api.md` marks them "Not in 1.0.0" | §7.2 product form, categories, tags, import |
| E2 ([ecommerce#140](https://github.com/ArtisanPack-UI/ecommerce/issues/140)) | `InventoryService::adjust()` exists, but no `PATCH admin/inventory/{item}` or `POST …/adjust` | §9.6 | REST parity only; admin can ship on the service |
| E3 ([ecommerce#141](https://github.com/ArtisanPack-UI/ecommerce/issues/141)) | No order cancel service; no service or endpoints for order notes; no timeline endpoint | §9.3 (`cancel`, `notes`, `timeline`), event `OrderCancelled` | §7.3 cancel, §8.3 order notes |
| E4 ([ecommerce#142](https://github.com/ArtisanPack-UI/ecommerce/issues/142)) | No customer delete-and-anonymize service or endpoint; no admin writes for customer addresses | §9.4 `DELETE customers/{customer}`, plan §19.6 | §7.4 delete, addresses |
| E5 ([ecommerce#143](https://github.com/ArtisanPack-UI/ecommerce/issues/143)) | No sub-status CRUD service; `SubStatusRegistry` is not implemented | §5 row 14 | §7.10 order statuses |
| E6 ([ecommerce#144](https://github.com/ArtisanPack-UI/ecommerce/issues/144)) | `AdminMenuRegistry` is not implemented | §5 row 15 | §5.4 (interim filter covers it) |
| E7 ([ecommerce#145](https://github.com/ArtisanPack-UI/ecommerce/issues/145)) | No persisted settings store; the engine is config-only | plan §11.1 | §7.9 |
| E8 ([ecommerce#146](https://github.com/ArtisanPack-UI/ecommerce/issues/146)) | No report queries or endpoints | plan §10.2 item 7, §16.4 | §7.7, dashboard KPIs |
| E9 ([ecommerce#147](https://github.com/ArtisanPack-UI/ecommerce/issues/147)) | Timeline and notes exist for orders only. "Activity timeline on every entity" and "internal notes on customers" need a generic activity log and customer notes. | plan §10.2 items 12–13 | §8.2, §8.3 beyond orders |
| E10 ([ecommerce#148](https://github.com/ArtisanPack-UI/ecommerce/issues/148)) | No abilities for reports, settings, sub-statuses, or inventory (inventory reuses `product.viewAny`) | §6.18 list is "closed at engine v1.0" | §6 |
| E11 ([ecommerce#149](https://github.com/ArtisanPack-UI/ecommerce/issues/149)) | Config blobs have no declared schema on `PromotionCondition`, `PromotionAction`, `ShippingMethodType`, `KanbanAutomationTrigger`, `KanbanCardWidget` | — | §8.4 (admin ships its own schemas meanwhile) |
| E12 ([ecommerce#150](https://github.com/ArtisanPack-UI/ecommerce/issues/150)) | No list endpoint for webhook deliveries; `NotificationChannelRegistry` is not implemented | §5 row 13, §9.11 | REST parity only |
| E13 ([ecommerce#151](https://github.com/ArtisanPack-UI/ecommerce/issues/151)) | The `cms-framework` suggest text says the engine "auto-registers admin nav entries and reuses cms auth/roles"; no such code exists | plan §11.1 | Decide: engine or this package (this spec assumes this package) |

Not blocked: orders index and detail, refunds, shipments, order editing, status changes, reviews, digital files, license keys, customers read/update, promotions, coupons, tax, shipping, notification templates, webhooks, kanban settings. These all have engine services or simple model writes behind existing policies.

## 10. `livewire-ui-components` prerequisites

Plan §11.9 forbids custom primitives here, so these are issues on `ArtisanPack-UI/livewire-ui-components` (2.1.0 checked):

| # | Missing | Used by |
|---|---|---|
| U1 ([livewire-ui-components#111](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/111)) | Empty-state component (only the table has an `empty` slot) | Every index screen, first-run dashboard |
| U2 ([livewire-ui-components#112](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/112)) | Timeline wrapper (only `timeline-item` exists) | §8.2 |
| U3 ([livewire-ui-components#113](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/113)) | Date-range picker (or a documented range mode on `datepicker`) | Orders and customers filters, reports |
| U4 ([livewire-ui-components#114](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/114)) | Bulk-action bar for `table` selection | Every index screen |
| U5 ([livewire-ui-components#115](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/115)) | Reorderable list that works on Livewire 3 (table `sortable` is Livewire 4 only) | Rule builder, columns, gallery — `livewire-drag-and-drop` covers it meanwhile |

Until each lands, the admin composes the pattern from existing components inside a single Blade component so the swap is one file.

## 11. Localization and accessibility

- `lang/{en,es,fr,de}.json`. CI runs the engine's `ecommerce:lint:translations --path=… --lang=…` against this package so a bare string or a key missing from any catalogue fails the build.
- Money through `MoneyFormatter`, dates through `LocalizedDate`, tax label through `TaxLabel` — all engine helpers, so admin and storefront agree.
- Markup uses logical CSS properties (plan §16.5) so RTL stays a stylesheet concern.
- Target WCAG 2.2 AA. Every interactive flow works by keyboard, including drag reorders (move up / move down controls are always present). Status is never conveyed by colour alone. Toasts and validation summaries are announced through live regions. Tables have captions and sortable headers expose `aria-sort`.

## 12. Testing

- **Pest + Testbench.** One `Livewire::test()` file per component under `tests/Feature/Livewire/{Area}/`. Each covers render, authorization denied, the happy path, validation failures, and engine-exception handling.
- **Query classes** are unit-tested for each filter and sort.
- **Authorization matrix test** walks every route and every component action as a user with no abilities and asserts denial.
- **Coverage target ~80%** (plan §15.1), with the difference made up by browser tests.
- **Browser tests** (Pest 4 browser plugin), seeded with `ecommerce:seed-demo`: create a simple product, create a variable product with variants, edit a product, issue a partial refund with restock, create a shipment, build a promotion with one condition and one action, edit a notification template and see the preview change, open the command palette and jump to an order.
- **CI matrix:** PHP 8.3 and 8.4, Laravel 12 and 13, Livewire 3 and 4. Jobs: tests, `composer lint`, translation lint, PCI column lint (no migrations expected; the job guards against drift), `verify-satellite` on tags.

## 13. Satellite lifecycle

```php
$active = $this->app->make( SatelliteRegistry::class )->register( [
    'package_name'    => 'artisanpack-ui/ecommerce-admin-livewire',
    'version'         => self::VERSION,
    'label'           => __( 'Admin (Livewire)' ),
    'migration_paths' => [],
    'config_keys'     => [ 'artisanpack.ecommerce-admin-livewire' ],
    'meta_namespaces' => [],
    'tables'          => [],
    'columns'         => [],
    'product_types'   => [],
] );

if ( ! $active ) {
    return; // no routes, components, nav, or hooks
}
```

- Under D2 this package owns no tables, so there are no migrations and no uninstaller.
- It registers no engine contracts, so `ecommerce:verify-satellite` runs with `--allow-empty` and still produces a signed report on each tag.
- `php artisan ecommerce-admin:install` publishes config, prints the Tailwind `@source` line and the npm dependency, and checks that an `ecommerce.admin` gate or RBAC permissions exist.

## 14. Delivery plan

Issues on this repository (issue numbers match the A-numbers: A01 is #1), all labelled `ecosystem: ecommerce` on milestone `v1.0`. "Needs" lists engine (E) or component-library (U) prerequisites.

### M0 — Foundation

| # | Issue | Type | Needs |
|---|---|---|---|
| A01 | Package scaffold: composer, provider, config, code style, CI, `SatelliteRegistry` registration, install command | task | — |
| A02 | Admin shell: routes, page controller, access + persistent middleware, layout resolver (cms-framework / standalone) | feature | — |
| A03 | Navigation: `AdminNav`, cms-framework menu filter, badges, extension filter | feature | E6 (soft) |
| A04 | Authorization: policy checks in components, RBAC permission registration, authorization matrix test | feature | E10, E13 (soft) |
| A05 | Display and input helpers: money, percent, status badges, address, pickers | feature | — |
| A06 | Resource table foundation: search, filters, sort, pagination, selection, bulk-action bar, URL state, export | feature | U1, U4 (soft) |
| A07 | Config-form renderer + schemas for every core registry key | feature | E11 (soft) |
| A08 | One-time action tokens + admin rate limiting for Livewire updates | feature | — |

### M1 — Orders

| # | Issue | Type | Needs |
|---|---|---|---|
| A09 | Orders index | feature | U3 (soft) |
| A10 | Order detail: items, totals, customer, addresses, payment, boards | feature | — |
| A11 | Order status and sub-status changes; cancel | feature | E3 |
| A12 | Refunds: full, partial, per-item restock | feature | — |
| A13 | Fulfillment: shipments, tracking, item-level status, label purchase | feature | — |
| A14 | Order editing: draft, diff preview, apply, rollback | feature | — |
| A15 | Activity timeline component | feature | U2 (soft), E9 (non-order subjects) |
| A16 | Internal notes component | feature | E3, E9 |

### M2 — Catalog

| # | Issue | Type | Needs |
|---|---|---|---|
| A17 | Products index with filters and bulk actions | feature | — |
| A18 | Product form: general, pricing, inventory, shipping, tax, organization, media | feature | E1 |
| A19 | Product-type panel registry + variable product panel (attributes, variant generator, variants table) | feature | E1 |
| A20 | Digital and grouped/bundled product panels | feature | E1 |
| A21 | Categories and tags | feature | E1 |
| A22 | Inventory screen and adjustments | feature | E10 (soft) |
| A23 | Product CSV import and export | feature | E1 |
| A24 | Reviews moderation queue | feature | — |
| A25 | Digital files and license keys | feature | — |

### M3 — Customers

| # | Issue | Type | Needs |
|---|---|---|---|
| A26 | Customers index and detail (LTV, orders, addresses, preferences) | feature | — |
| A27 | Customer edit, address management, delete-and-anonymize | feature | E4 |

### M4 — Promotions, tax, shipping

| # | Issue | Type | Needs |
|---|---|---|---|
| A28 | Promotions index and details form | feature | — |
| A29 | Rule builder: conditions, actions, reorder, plain-language summary | feature | U5 (soft) |
| A30 | Coupons: codes, bulk generate, usage | feature | — |
| A31 | Tax classes and rates table, CSV import/export | feature | — |
| A32 | Shipping zones and methods | feature | — |

### M5 — Notifications, webhooks, configuration

| # | Issue | Type | Needs |
|---|---|---|---|
| A33 | Notification template list and editor with live preview | feature | — |
| A34 | Webhook subscriptions, deliveries log, replay, secret rotation | feature | — |
| A35 | Order statuses (sub-status management) | feature | E5 |
| A36 | Kanban settings: boards, routing rules, columns, automations | feature | — |
| A37 | Settings screens + settings-tab registry + satellites list | feature | E7, E10 (soft) |

### M6 — Reports and dashboard

| # | Issue | Type | Needs |
|---|---|---|---|
| A38 | Reports shell + sales over time (multi-currency) | feature | E8, U3 (soft) |
| A39 | Top products, revenue by category, tax collected | feature | E8 |
| A40 | Inventory levels and low-stock reports | feature | E8 |
| A41 | Dashboard | feature | E8 |

### M7 — Command palette, hardening, release

| # | Issue | Type | Needs |
|---|---|---|---|
| A42 | Command palette | feature | — |
| A43 | Real-time order updates (optional, off by default) | feature | — |
| A44 | `es`, `fr`, `de` catalogues + translation lint in CI | task | — |
| A45 | Accessibility audit (WCAG 2.2 AA) and fixes | task | — |
| A46 | Browser test suite (Pest 4 browser plugin) | task | — |
| A47 | Documentation: README, installation, configuration, authorization, extending | documentation | — |
| A48 | v1.0 release: changelog, `verify-satellite` workflow, engine `docs/satellites.md` update | task | — |

M1 comes before M2 because every order screen except cancel and notes can be built on engine 1.0.0 today, while the product form waits on E1.

## 15. Open questions

1. **E13:** should `cms-framework` nav and role wiring live in the engine (as its composer `suggest` text says) or in each admin satellite (as this spec assumes)?
2. **Grouped/bundled products:** how the engine stores children (E1) decides the panel in A20.
3. **Route prefix:** `ecommerce-admin` follows `bookings`. `admin/ecommerce` reads better inside a CMS but shares the `admin/` prefix that `cms-framework` registers slugs under.
4. **Order editing after a label is printed** is unresolved in the plan (§19.9). A14 blocks the edit with an explanation until it is.

## 16. Traceability against tracker #52

| Acceptance criterion | Status |
|---|---|
| Repo created at `ArtisanPack-UI/ecommerce-admin-livewire` | Done (existed, empty); description and topics set |
| Per-package spec drafted | This document |
| Comprehensive issue set opened on the new repo (with `ecosystem: ecommerce` label) | §14 — 48 issues, plus the §9 engine issues and §10 component-library issues |
| Tracker closed with a link to the new repo | Closed once the issue set is open |

## 17. Changelog

- **2026-09-30** — v0.2. D1–D6 confirmed: in-process data access, engine gaps land in engine 1.0, Pest 4 browser tests (PHP 8.3+).
- **2026-09-30** — Draft v0.1. Written against parent plan v2, engine spec (2026-09-29), engine 1.0.0 source, `livewire-ui-components` 2.1.0, and `cms-framework` 2.11.0.
