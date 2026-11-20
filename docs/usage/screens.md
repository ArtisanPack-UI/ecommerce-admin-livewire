---
title: Screens
menu_order: 10
---

# Screens

Paths are relative to `admin.route_prefix` (`/ecommerce-admin` by default). Route names are relative to `artisanpack.ecommerce.admin.`, so `orders.show` is `artisanpack.ecommerce.admin.orders.show`.

For the ability each screen and action needs, see [Authorization](../authorization.md).

## Every screen

| Path | Route name | What it does |
| --- | --- | --- |
| `/` | `dashboard` | Key figures, 30-day sales, lowest stock, recent orders. See [Dashboard](dashboard.md). |
| `/orders` | `orders.index` | All orders. Filter by status, sub-status, payment, fulfillment, date, currency, and board. Bulk-change sub-status, export. |
| `/orders/{order}` | `orders.show` | One order: items, totals, fulfillment, refunds, edits, status, customer, addresses, payment, boards, notes, timeline. |
| `/reviews` | `reviews.index` | Review moderation queue (pending first). Read a review in a drawer; approve, reject with an optional private reason, mark as spam, requeue, delete. |
| `/products` | `products.index` | The catalog. Filter by status, type, category, tag, stock state, and featured. Sort by catalog position. Bulk publish, archive, mark or unmark featured, delete, add or remove a category or tag, export. |
| `/products/create` | `products.create` | New product form. |
| `/products/{product}/edit` | `products.edit` | Edit a product. |
| `/products/import` | `products.import` | CSV import with column mapping and a dry run. See [Product CSV](product-csv.md). |
| `/categories` | `categories.index` | Category tree: create, edit, reorder within a parent, delete. |
| `/tags` | `tags.index` | Tags: create, rename in the row, merge, delete. Product counts link to the products list filtered by that tag. |
| `/inventory` | `inventory.index` | Stock per product and variant. Adjust with a reason, edit the low-stock threshold, toggle backorders, bulk adjust from CSV. |
| `/digital-files` | `digital-files.index` | Files attached to digital products. Create, edit (a new version notifies customers), archive, delete. A file customers already bought can't be deleted; archive it instead. |
| `/license-keys` | `license-keys.index` | Issued keys, in full with `licenseKey.view` and masked otherwise: search by order or customer, or by full key with `licenseKey.view`; view activations; deactivate a machine; revoke with an optional reason. |
| `/customers` | `customers.index` | Customers with order count, total spent, last order, marketing consent, and account. Export. |
| `/customers/{customer}` | `customers.show` | Lifetime stats and tabs for orders, addresses, notification preferences, notes, and activity. Edit details. Delete and anonymize. |
| `/promotions` | `promotions.index` | Promotions with their source, state (scheduled, active, expired, switched off), run window, uses, and priority. Bulk switch on or off, delete. |
| `/promotions/create` | `promotions.create` | New promotion: details, rule builder, and a plain-language summary. |
| `/promotions/{promotion}/edit` | `promotions.edit` | Edit a promotion, its coupon codes, and its usage. |
| `/shipping` | `shipping.index` | Shipping zones by priority and the methods in each. Warns about countries no zone covers. |
| `/tax` | `tax.index` | Tax classes (with what uses each) and rates, CSV import (with a dry run) and export of rates. Shows the active tax provider. |
| `/notifications` | `notifications.index` | Notification templates, one card per notification with a row per channel and language. Filter by channel and language. |
| `/notifications/{template}/edit` | `notifications.edit` | Edit a template's Twig subject and body with a live preview. Add a locale, reset to default, send a test. |
| `/webhooks` | `webhooks.index` | Webhook subscriptions: create, edit, delete, rotate the secret, re-enable. `?edit={id}` opens a subscription's edit drawer. |
| `/webhooks/{subscription}` | `webhooks.show` | One subscription's details and delivery log. Inspect payloads and responses, replay a delivery. |
| `/order-statuses` | `order-statuses.index` | Order sub-statuses under each system status: create, edit, reorder, delete. |
| `/kanban-boards` | `kanban-boards.index` | Kanban boards: create, set the default, reorder, delete. |
| `/kanban-boards/{board}/edit` | `kanban-boards.edit` | A board's details, routing rules, columns, and automations. `?automation=new` or `?automation={id}` opens the automation drawer. |
| `/reports/{report}` | `reports.show` | A report with date range, comparison, interval, chart, table, and CSV export. |
| `/settings/{group}` | `settings.show` | Store settings, one tab per group. |

Record parameters (`{order}`, `{product}`, …) are numeric ids. `{report}` and `{group}` are lower-case keys.

Every screen that can't be changed by the current user shows its controls disabled and hides Save, so view-only staff can still read it.

## Orders

**Order detail** has two columns.

- **Main:** items with per-item fulfillment, totals (with the base-currency equivalent when the order was placed in another currency), fulfillment (create shipments, update tracking, buy a label when a label provider is registered), refunds (per item or a free amount, restock per item, reason), order edits (items, quantities, addresses, shipping method, with a before/after totals diff and a rollback of the latest edit), digital downloads and license keys, notes, and the timeline.
- **Side:** status and sub-status, customer, addresses, payment, and board assignments.

Each board the order is on shows its column as a badge (the column's label and colour dot, or the sub-status when the board has no column for it) and how long the order has been in that column. With `artisanpack-ui/ecommerce-kanban-livewire` installed, **Open board** opens the board with the order's card focused, for users with `kanbanBoard.view` on that board.

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

The promotion form groups its details into Basics, Schedule, Limits, and Stacking and priority. Source is **Coupon** or **Automatic**.

The rule builder has two ordered lists, **Conditions** and **Discounts**, each with a short description. Each row's settings form comes from the config-form registry and opens inside the row. Rows reorder by drag or by their move buttons. A summary sentence ("10% off the cart when the subtotal is at least $50.00") updates as you edit. A tab with errors carries a "!" badge.

A promotion that's off reads **Switched off**. Bulk **Delete** confirms in the bulk bar and says the promotion's codes stop working.

Coupon promotions add a **Coupons** tab: add a code, bulk generate (count, prefix, length), rename, delete, and export. Codes are unique across the store and case-insensitive. Generated codes avoid look-alike characters.

## Digital files and license keys

A digital file's status is **Offered**, **Streaming only**, or **Archived**. In the CSV export, Archived and Streaming only are separate columns, and **Updated** is an ISO 8601 timestamp (`DATE_ATOM`, for example `2026-11-19T14:05:00+00:00`).

Deleting a digital file that customers already bought is refused, because they keep their right to download it. The screen offers to **archive** those files instead: an archived file stays available to existing buyers and is left out of new purchases. You can also archive files with the **Archived** switch on the edit form, or with the bulk action. Removing such a file from a product's digital panel is refused the same way.

License keys are stored encrypted, so the search matches a key only in full (case and surrounding spaces don't matter). Order numbers, e-mail addresses, and customer names match in part.

Users with `licenseKey.view` see each key in full, on the screen and in the CSV export. Users with only `licenseKey.revoke` see it masked as `••••••••XXXX`, with only the last four characters (hyphens aside), whatever the key's length or grouping, and can't search by key. A key with four or fewer characters besides hyphens shows in full after the dots.

Activations read "2 of 3" over a short bar that turns to a warning when every slot is used. A key with an activation limit of zero reads "0 of 0", and a key with no limit reads "1, no limit" with no bar.

A key's drawer lists the machines it is activated on, with its product, customer, and order. **Deactivate** frees one activation slot, after confirmation. **Revoke** takes an optional reason and isn't offered for a key that's already revoked. Users with only `licenseKey.revoke` see a masked fingerprint and no IP addresses.

## Reports

| Key | Report |
| --- | --- |
| `sales` | Sales over time |
| `top-products` | Top products |
| `revenue-by-category` | Revenue by category |
| `tax` | Tax collected |
| `inventory` | Inventory levels (point in time, no date controls) |
| `low-stock` | Low stock |

Date presets: today, yesterday, last 7 days, last 30 days (default), last 90 days, this and last month, quarter, and year, or custom dates. From and To show only for a custom range. An invalid range shows one warning and disables Export. The range and options live in the URL. Amounts are in the base currency. Orders placed under an earlier base currency are converted at today's rate and flagged. Satellites add reports to the engine's `ReportRegistry`.

Over-time reports draw a column chart with focusable columns and tooltips, and Compare adds the previous period as a dashed line. Ranked reports (top products, revenue by category, tax, inventory, low stock) draw a horizontal bar list. Every chart is captioned by a text summary, and the table below it ends with a totals row.

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

Fields come from the engine's `SettingsRegistry`. A changed value is marked and can be reset to its configured default. **Reset** asks for confirmation first, because the reset saves immediately; it leaves your other unsaved edits alone. Changing the base currency asks for confirmation first. Satellites add groups through the engine registry or the [settings tab registry](../extending/settings-tabs.md).

## Customers and CSV exports

The customers list shows an avatar and the name (a guest with no name reads "No name"), marketing consent, and whether the customer has an account. In the CSV export, consent is under a **Marketing** header.

Customer detail shows the account and marketing badges under the name, five figures (lifetime value, orders, average order, first and last order), a contact card, and the tabs. **Edit** swaps the contact card for the form in place. **Delete** stays disabled until the customer's email is typed, and the dialog says what is erased and what is kept.

## Tax, notifications, and webhooks

- **Tax.** An info alert names the active tax provider; under any other provider, a warning says the rates on the page aren't used. Each class lists what uses it ("Used by": rates, products, and shipping methods, zeros included). A class in use can't be deleted: its dialog names what uses it and offers only **Keep class**. Rates read **Active** or **Inactive**. **Check file** runs an import as a dry run first and shows how many rates it adds, updates, and skips.
- **Notification templates.** The **Last edited** column reads **Never** while a row's subject and body still match the defaults the store shipped, even after a sync or switching it on or off. Each row's **Edit** link names the notification, the channel, and the language. SMS templates have no subject, show the character and message count, and preview as a message bubble.
- **Webhooks.** The list shows each subscription's events, state, last success and failure, and failures in a row. `?edit={id}` opens that subscription's edit drawer when the user may edit it; the link follows whichever subscription is open and clears when the drawer closes. A subscription's page lists deliveries with **Event**, **Status**, **Attempts**, **Next retry**, **Delivered**, and **Created**.

## Kanban boards

The boards list is numbered in routing order, with each board's key, column count, and card count. A badge marks the default board, boards that are switched off, and boards with routing rules. **Make default** shows only on active boards that aren't the default. Deleting the default board names the board that takes over.

The board editor shows details, routing rules, columns, and automations. On the default board the active switch is on and disabled. A default board with routing rules warns that unmatched orders go nowhere. Nested any or not rule groups, which the editor can't show, get a warning with **Replace with simple rules**, and the stored rules stay readable in a closed disclosure.

Automations open from a link: `?automation=new` opens the add drawer (unless the board has no columns), and `?automation={id}` opens that automation's edit drawer. Either does nothing for users without update access, for another board's automation, or for any other value.
