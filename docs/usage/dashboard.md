---
title: Dashboard
---

# Dashboard

The dashboard (`/`, route `artisanpack.ecommerce.admin.dashboard`) shows what needs attention. Its numbers come from the engine's report queries, so they match the Reports screen.

## Key figures

Each figure links to the screen that explains it. Each is hidden unless the user holds the ability of that screen.

| Figure | Counts | Links to | Ability |
| --- | --- | --- | --- |
| Sales today | Sales placed today, in the base currency, with the order count | Sales report, preset "today" | `report.view` |
| Sales, last 30 days | Sales over the last 30 days, in the base currency, with the order count | Sales report | `report.view` |
| Awaiting fulfillment | Orders to ship | Orders, filtered to orders awaiting fulfillment | `order.viewAny` |
| Low stock | Tracked items at or below their low-stock threshold | Inventory, filtered to items to reorder | `inventory.viewAny` |
| Reviews to moderate | Reviews with status pending | Reviews, filtered to pending | `review.viewAny` |

The row is hidden when the user can see none of the figures.

## Widgets

| Widget | Shows | Width | Ability |
| --- | --- | --- | --- |
| Key figures (`kpis`) | The figures above | Full | Per figure |
| Sales, last 30 days (`sales`) | A daily sales sparkline with a text summary (total and best day) for screen readers | Half | `report.view` |
| Lowest stock (`low-stock`) | The five tracked items with the least stock available | Half | `inventory.viewAny` |
| Recent orders (`recent-orders`) | The ten most recently placed orders | Full | `order.viewAny` |

Only the data for visible widgets is queried. Other packages add widgets with the `ap.ecommerceAdminLivewire.dashboard.widgets` filter. See [Dashboard Widgets](Extending-Dashboard-Widgets).

The sparkline needs ApexCharts on the page. See [Front-End Setup](Installation-Front-End).

## First run

A store with no products and no orders gets a first-run state in place of the core widgets. Users who can create products get links to **Add your first product** and **Import products**. Widgets added by other packages still show.

To fill a development store, run `php artisan ecommerce:seed-demo`.

## Nothing to show

A user who can open the admin but sees none of the widgets gets a list of the screens they can open instead.

## Live updates

With [real-time updates](Usage-Real-Time) on, the dashboard refreshes when an order changes status, a payment succeeds, stock changes, or a review is submitted. A screen-reader announcement says what changed.
