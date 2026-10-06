---
title: Screens
---

# Screens

Paths are relative to `admin.route_prefix` (`/ecommerce-admin` by default). Route names are relative to `artisanpack.ecommerce.admin.`, so `orders.show` is `artisanpack.ecommerce.admin.orders.show`.

For the ability each screen and action needs, see [Authorization](Authorization).

## Every screen

| Path | Route name | What it does |
| --- | --- | --- |
| `/` | `dashboard` | Key figures, 30-day sales, lowest stock, recent orders. See [Dashboard](Usage-Dashboard). |
| `/orders` | `orders.index` | All orders. Filter by status, sub-status, payment, fulfillment, date, currency, and board. Bulk-change sub-status, export. |
| `/orders/{order}` | `orders.show` | One order: items, totals, fulfillment, refunds, edits, status, customer, addresses, payment, boards, notes, timeline. |
| `/reviews` | `reviews.index` | Review moderation queue (pending first). Approve, reject with a reason, mark as spam, requeue, delete. |
| `/products` | `products.index` | The catalog. Filter by status, type, category, tag, stock state, and featured. Sort by catalog position. Bulk publish, archive, mark or unmark featured, delete, add or remove a category or tag, export. |
| `/products/create` | `products.create` | New product form. |
| `/products/{product}/edit` | `products.edit` | Edit a product. |
| `/products/import` | `products.import` | CSV import with column mapping and a dry run. See [Product CSV](Usage-Product-Csv). |
| `/categories` | `categories.index` | Category tree: create, edit, reorder within a parent, delete. |
| `/tags` | `tags.index` | Tags: create, rename, merge, delete. |
| `/inventory` | `inventory.index` | Stock per product and variant. Adjust with a reason, edit the low-stock threshold, toggle backorders, bulk adjust from CSV. |
| `/digital-files` | `digital-files.index` | Files attached to digital products. Create, edit (a new version notifies customers), archive, delete. A file customers already bought can't be deleted; archive it instead. |
| `/license-keys` | `license-keys.index` | Issued keys: search by full key, order, or customer; view activations; deactivate a machine; revoke with a reason. |
| `/customers` | `customers.index` | Customers with order count, total spent, last order, and marketing consent. Export. |
| `/customers/{customer}` | `customers.show` | Lifetime stats and tabs for orders, addresses, notification preferences, notes, and activity. Edit details. Delete and anonymize. |
| `/promotions` | `promotions.index` | Promotions with their state (scheduled, active, expired, disabled). Bulk switch on or off, delete. |
| `/promotions/create` | `promotions.create` | New promotion: details, rule builder, and a plain-language summary. |
| `/promotions/{promotion}/edit` | `promotions.edit` | Edit a promotion, its coupon codes, and its usage. |
| `/shipping` | `shipping.index` | Shipping zones by priority and the methods in each. Warns about countries no zone covers. |
| `/tax` | `tax.index` | Tax classes and rates, CSV import and export of rates. Shows the active tax provider. |
| `/notifications` | `notifications.index` | Notification templates grouped by key. |
| `/notifications/{template}/edit` | `notifications.edit` | Edit a template's Twig subject and body with a live preview. Add a locale, reset to default, send a test. |
| `/webhooks` | `webhooks.index` | Webhook subscriptions: create, edit, delete, rotate the secret, re-enable. |
| `/webhooks/{subscription}` | `webhooks.show` | One subscription's delivery log. Inspect payloads and responses, replay a delivery. |
| `/order-statuses` | `order-statuses.index` | Order sub-statuses under each system status: create, edit, reorder, delete. |
| `/kanban-boards` | `kanban-boards.index` | Kanban boards: create, set the default, reorder, delete. |
| `/kanban-boards/{board}/edit` | `kanban-boards.edit` | A board's details, routing rules, columns, and automations. |
| `/reports/{report}` | `reports.show` | A report with date range, comparison, interval, chart, table, and CSV export. |
| `/settings/{group}` | `settings.show` | Store settings, one tab per group. |

Record parameters (`{order}`, `{product}`, …) are numeric ids. `{report}` and `{group}` are lower-case keys.

## Orders

**Order detail** has two columns.

- **Main:** items with per-item fulfillment, totals (with the base-currency equivalent when the order was placed in another currency), fulfillment (create shipments, update tracking, buy a label when a label provider is registered), refunds (per item or a free amount, restock per item, reason), order edits (items, quantities, addresses, shipping method, with a before/after totals diff and a rollback of the latest edit), digital downloads and license keys, notes, and the timeline.
- **Side:** status and sub-status, customer, addresses, payment, and board assignments.

Only the status transitions the engine's state machine allows are offered. Cancelling needs a reason and shows what will be released. Editing an order after fulfillment has started needs `order.edit-fulfilled`.

## Products

The product form has tabs: General, Pricing, Inventory, Shipping, Tax, Organization, Linked products, Media, a tab for the product type, and Activity. One Save validates every tab and reports the first tab with an error. Leaving the form with unsaved changes asks for confirmation first.

| Type | Type tab |
| --- | --- |
| `simple` | None |
| `variable` | Attributes and values, "generate variants" from the attribute matrix, and a variants table |
| `digital` | Attached files, download limit and expiry, license-key issuance |
| `grouped`, `bundled` | Child products with quantities |

**Organization** holds categories, tags, **Featured** (storefronts can highlight featured products), and **Catalog position** (a manual catalog order: lower numbers come first).

**Linked products** has three ordered lists:

| List | Shown |
| --- | --- |
| Upsells | Pricier or better alternatives, on the product's page |
| Cross-sells | Products that go with this one, in the cart |
| Related products | Similar products, alongside this one |

Pick a product to add it to a list. Reorder by drag or by the move buttons, and remove with the remove button. A list holds up to 50 products, and a product can't link to itself. The lists are saved with the product through the engine's `ProductService`.

Pricing has one row per store currency (the base currency, the currencies in the engine's rate table, and any currency the product is already priced in), with optional scheduled prices. Quantity changes go through the engine's inventory service with a reason.

## Promotions

The rule builder has two ordered lists: **Conditions** and **Actions**. Each row's settings form comes from the config-form registry. Rows reorder by drag or by their move buttons. A summary sentence ("10% off the cart when the subtotal is at least $50.00") updates as you edit.

Coupon promotions add a **Coupons** tab: add a code, bulk generate (count, prefix, length), rename, delete, and export. Codes are unique across the store and case-insensitive. Generated codes avoid look-alike characters.

## Digital files and license keys

Deleting a digital file that customers already bought is refused, because they keep their right to download it. The screen offers to **archive** those files instead: an archived file stays available to existing buyers and is left out of new purchases. You can also archive files with the **Archived** switch on the edit form, or with the bulk action. Removing such a file from a product's digital panel is refused the same way.

License keys are stored encrypted, so the search matches a key only in full (case and surrounding spaces don't matter). Order numbers, e-mail addresses, and customer names match in part.

A key's drawer lists the machines it is activated on. **Deactivate** frees one activation slot, after confirmation. Users with only `licenseKey.revoke` see a masked fingerprint and no IP addresses.

## Reports

| Key | Report |
| --- | --- |
| `sales` | Sales over time |
| `top-products` | Top products |
| `revenue-by-category` | Revenue by category |
| `tax` | Tax collected |
| `inventory` | Inventory levels (point in time, no date controls) |
| `low-stock` | Low stock |

Date presets: today, yesterday, last 7 days, last 30 days (default), this and last month, quarter, and year, or custom dates. The range and options live in the URL. Amounts are in the base currency. Orders placed under an earlier base currency are converted at today's rate and flagged. Satellites add reports to the engine's `ReportRegistry`.

## Settings

| Group | Contents |
| --- | --- |
| `general` | Store name, support e-mail, base currency, store time zone |
| `checkout` | Stock reservation time |
| `tax` | Tax provider, prices include tax, default and shipping tax classes, tax label per locale |
| `shipping` | Fulfillment allocation |
| `payments` | Enable Stripe, Stripe capture mode, fraud providers. Gateway credentials are read from the environment, shown only as configured or not, and never stored or edited. |
| `notifications` | Send notifications, admin recipients, default locale, customer preference channels, review request delay |
| `reviews`, `digital`, `licenses`, `kanban` | The engine's settings for each (for example guest reviews, download limits and expiry) |
| `satellites` | Read-only list of installed satellites, their versions, and status |

Fields come from the engine's `SettingsRegistry`. A changed value is marked and can be reset to its configured default; resetting asks first, saves immediately, and leaves your other unsaved edits alone. Changing the base currency asks for confirmation first. Satellites add groups through the engine registry or the [settings tab registry](Extending-Settings-Tabs).
