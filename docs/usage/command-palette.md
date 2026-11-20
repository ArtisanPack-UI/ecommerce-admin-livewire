---
title: Command Palette
menu_order: 30
---

# Command Palette

The command palette finds records and jumps to screens from anywhere in the admin.

## Opening it

- Press **Cmd+K** (the `spotlight.shortcut` config key, `meta.k` by default).
- Or select the **Search** field in the top bar. Under `cms-framework` it sits above the page.

The trigger looks like a search field and shows its shortcut. Below 48rem it becomes an icon button. It carries `aria-keyshortcuts`.

With an empty query, the palette lists the **Actions** group (below) and filters it as you type. Use ↑ and ↓ to move, ↵ to open, and Esc to close.

## What it searches

Type at least one character. Results are grouped by source, in the order below, and each of the sources below returns up to `spotlight.limit` results (5 by default, at most 20). The limit is passed to each provider rather than enforced, so a custom provider should respect it. Your host spotlight's results and the Actions group are not limited by it. A search with no results shows an empty state with a hint.

| Source | Matches | Opens | Needs |
| --- | --- | --- | --- |
| Orders | Order number (a leading `#` is ignored) or email. An exact number match is listed first. | Order detail | `order.viewAny`, and `view` on each order |
| Products | Name or SKU | Product form | `product.viewAny`, and `view` on each product |
| Customers | Email, or first and last name (every word must match) | Customer detail | `customer.viewAny`, and `view` on each customer |
| Promotions | Name or key | Promotion form | `promotion.viewAny`, and `view` on each promotion |
| Coupons | Code | The coupon's promotion, on its Coupons tab | `promotion.viewAny`, and `view` on the coupon's promotion |
| Actions | Action labels and keywords | See below | Per action |

Results are checked per ability and per record. A user never sees a result for a record they cannot open.

## Actions

The Actions group is rendered with the page, so it shows before you type and filters in the browser. Searching doesn't fetch it again.

| Action | Shown when |
| --- | --- |
| New product | The user holds `product.create` |
| New promotion | The user holds `promotion.create` |
| Go to {screen} | One per navigation entry the user can open |

An action matches when every word you type appears in its label or keywords. For example, "add" finds "New product".

## Contextual order actions

When the search is exactly an order number (`#1042` or `1042`), two more results follow that order:

| Result | Opens | Needs |
| --- | --- | --- |
| Refund order #1042 | The order with `?action=refund`, which opens the refund dialog | `refund` on the order |
| Add note to #1042 | The order at `#order-notes`, the notes panel | `update` on the order |

## Limits

- Each user can run 120 searches a minute. Over that, the palette returns no results until the minute passes.
- A user who cannot open any admin screen gets no results.
- Search text is trimmed and capped in length before it reaches a query.

## Turning it off

Set `spotlight.enabled` to `false`. The Search field disappears and the package adds nothing to the component library's spotlight.

## How it fits with your own spotlight

The palette is the component library's `x-artisanpack-spotlight`. It searches through the admin's own route, `artisanpack.ecommerce.admin.spotlight`, so the admin middleware (2FA, `verified`) runs on every search. The route answers with groups:

```json
[
    { "id": "results", "label": null, "items": [ … ] },
    { "id": "orders", "label": "Orders", "items": [ { "name": "…", "description": "…", "link": "…", "icon": "shopping-bag" } ] },
    { "id": "products", "label": "Products", "items": [ … ] }
]
```

- Your own spotlight class (`artisanpack.livewire-ui-components.components.spotlight.class`) runs first, then the library's `ap.livewireUiComponents.spotlightCommands` filter. Their results come first, in one unlabeled group. Your spotlight keeps working.
- Then one group per admin provider with results, labeled with what it finds.
- The core Actions group is left out, because the palette already lists it. A replaced or subclassed actions provider is searched like any other.

> **Warning:** Before 2.0 this route returned one flat list of results. If you call it yourself, read `items` from each group instead. `AdminSpotlight::search()` and `AdminSpotlight::appendTo()` still return flat lists for host palettes.

If the class named in `artisanpack.livewire-ui-components.components.spotlight.class` doesn't exist, the admin binds an empty stand-in, so the route still works.

To add your own result sources, see [Command Palette Providers](../extending/command-palette.md).
