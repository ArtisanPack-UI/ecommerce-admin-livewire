---
title: Resource Tables
---

# Resource Tables

Every index screen is built on the `WithResourceTable` concern. Each screen's columns, filters, and bulk actions pass through a filter, so other packages can add to them without publishing views.

## The filters

| Filter | Arguments | Returns |
| --- | --- | --- |
| `ap.ecommerceAdminLivewire.table.{screen}.columns` | `array $columns`, `Component $component` | The columns |
| `ap.ecommerceAdminLivewire.table.{screen}.filters` | `array $filters`, `Component $component` | The filters |
| `ap.ecommerceAdminLivewire.table.{screen}.bulkActions` | `array $actions`, `Component $component` | The bulk actions |

Each definition is keyed by its `key`; a later definition with the same key replaces an earlier one. Malformed definitions are skipped.

## Screens

| `{screen}` | Screen | Query model |
| --- | --- | --- |
| `orders` | Orders | `Order` |
| `reviews` | Reviews | `ProductReview` |
| `products` | Products | `Product` |
| `tags` | Tags | `ProductTag` |
| `inventory` | Inventory | `InventoryItem` |
| `digital-files` | Digital files | `DigitalFile` |
| `license-keys` | License keys | `LicenseKey` |
| `customers` | Customers | `Customer` |
| `promotions` | Promotions | `Promotion` |
| `tax-rates` | Tax rates | `TaxRate` |
| `webhooks` | Webhook subscriptions | `WebhookSubscription` |
| `webhook-deliveries` | One subscription's deliveries | `WebhookDelivery` |

The products screen also runs the engine's `ap.ecommerce.product.listQuery` filter on its query, so a filter there narrows the admin list and the engine's own lists alike.

## Columns

| Key | Meaning |
| --- | --- |
| `key` | Required. Unique key; also the row attribute shown when there is no `view` or `value`. |
| `label` | Required. Column heading |
| `sortable` | Whether the column sorts. Only honoured when the screen's query class can sort by `key`. |
| `class` | Extra classes on the cell |
| `view` | A Blade view, rendered with `$row` and `$column` |
| `value` | A callable receiving the row and returning text |
| `export` | A callable overriding the CSV value |
| `exportable` | `false` leaves the column out of CSV exports |

```php
addFilter( 'ap.ecommerceAdminLivewire.table.customers.columns', function ( array $columns ): array {
    $columns[] = [
        'key'   => 'points',
        'label' => __( 'Points' ),
        'value' => fn ( $customer ): string => number_format( PointsLedger::balanceFor( $customer->id ) ),
    ];

    return $columns;
} );
```

Avoid a query per row. Eager-load through `ap.ecommerce.product.listQuery` on products, or cache.

## Filters

| Key | Meaning |
| --- | --- |
| `key` | Required. Unique key; also the URL key (`filters[key]=…`) |
| `label` | Required. Visible label |
| `type` | `select` (default), `multiselect`, `text`, `boolean`, `date-range`, or `number-range` |
| `options` | For `select` and `multiselect`: `[ [ 'id' => …, 'name' => … ] ]`. `boolean` gets Yes/No automatically. |
| `apply` | `callable( Builder $query, mixed $value )`. Required for filters the screen's query class does not handle, which is every filter you add. |

A `date-range` value is `[ 'from' => 'Y-m-d', 'to' => 'Y-m-d' ]`. A `number-range` value is `[ 'min' => …, 'max' => … ]`. Either end may be missing. Values from the URL are normalized against the definition before `apply` runs.

```php
addFilter( 'ap.ecommerceAdminLivewire.table.orders.filters', function ( array $filters ): array {
    $filters[] = [
        'key'   => 'subscription',
        'label' => __( 'Subscription renewals' ),
        'type'  => 'boolean',
        'apply' => function ( $query, $value ): void {
            '1' === (string) $value
                ? $query->whereNotNull( 'meta->subscription_id' )
                : $query->whereNull( 'meta->subscription_id' );
        },
    ];

    return $filters;
} );
```

## Bulk actions

| Key | Meaning |
| --- | --- |
| `key` | Required. Unique key |
| `label` | Required. Button label |
| `handler` | Required. `callable( Builder $selection, Component $component )`. Return a success message (shown as a toast), a download response, or `null`. |
| `icon` | Heroicon name (default `o-bolt`) |
| `ability` | `{resource}.{action}`. The action is hidden from, and refused for, users without it. |
| `confirm` | A confirmation message. The action then asks first and carries a one-time token, so it runs once. |

`$selection` is a query for the selected rows, or for every row matching the current search and filters when the user chose "select all matching". The `ability` is an account-level check. Authorize each record in your handler when your rules depend on the record:

```php
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;

addFilter( 'ap.ecommerceAdminLivewire.table.orders.bulkActions', function ( array $actions ): array {
    $actions[] = [
        'key'     => 'pause-subscriptions',
        'label'   => __( 'Pause subscriptions' ),
        'icon'    => 'o-pause',
        'ability' => 'order.update',
        'confirm' => __( 'Pause the subscriptions on the selected orders?' ),
        'handler' => function ( $selection ): string {
            $count = 0;

            $selection->each( function ( $order ) use ( &$count ): void {
                if ( Authorization::allows( auth()->user(), 'order.update', $order ) ) {
                    app( SubscriptionService::class )->pauseFor( $order );
                    $count++;
                }
            } );

            return trans_choice( ':count subscription paused.|:count subscriptions paused.', $count, [ 'count' => $count ] );
        },
    ];

    return $actions;
} );
```

Every table also offers an export of the selection as CSV, capped at `tables.export_max_rows`.

## Other screens

Categories, shipping, notification templates, order statuses, and kanban boards are not resource tables and have no table filters.
