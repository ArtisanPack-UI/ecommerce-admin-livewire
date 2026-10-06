---
title: Command Palette
---

# Command Palette

The command palette finds records and jumps to screens from anywhere in the admin.

## Opening it

- Press **Cmd+K** (the `spotlight.shortcut` config key, `meta.k` by default).
- Or select the **Search** button in the top bar. Under `cms-framework` the button sits above the page.

The button carries `aria-keyshortcuts`, and its tooltip names the shortcut.

## What it searches

Type at least one character. Each source returns up to `spotlight.limit` results (5 by default, at most 20).

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

Set `spotlight.enabled` to `false`. The Search button disappears and the package adds nothing to the component library's spotlight.

## How it fits with your own spotlight

The palette is the component library's `x-artisanpack-spotlight`. The admin adds its results through the library's `ap.livewireUiComponents.spotlightCommands` filter, after whatever your own spotlight class returns. Your spotlight keeps working.

If the class named in `artisanpack.livewire-ui-components.components.spotlight.class` does not exist, the admin binds an empty stand-in, so the library's search route still reaches the filter.

To add your own result sources, see [Command Palette Providers](Extending-Command-Palette).
