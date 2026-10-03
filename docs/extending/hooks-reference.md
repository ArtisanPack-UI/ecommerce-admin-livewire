---
title: Hooks Reference
---

# Hooks Reference

Every filter the admin applies, the hooks from other packages it uses, and the browser events its components exchange. Register filters with `addFilter()` from `artisanpack-ui/hooks`, usually in a service provider's `boot()`. A filter must return a value of the same shape it received.

The admin fires no actions (`doAction`).

## Filters the admin applies

| Filter | Arguments | Return | Applied when | Docs |
| --- | --- | --- | --- | --- |
| `ap.ecommerceAdminLivewire.nav.items` | `array $items` | Navigation entries | Building the navigation (every admin page) | [Navigation](Extending-Navigation) |
| `ap.ecommerceAdminLivewire.nav.sections` | `array $sections` (key => `[ label, position ]`) | Sections | Building the navigation | [Navigation](Extending-Navigation) |
| `ap.ecommerceAdminLivewire.nav.badge` | `null $count`, `string $badge` | `int\|null` count | Resolving a badge key the core does not know | [Navigation](Extending-Navigation) |
| `ap.ecommerceAdminLivewire.nav.badgeLabel` | `string $label`, `string $badge`, `int $count` | Accessible badge description | Describing a badge the core does not know | [Navigation](Extending-Navigation) |
| `ap.ecommerceAdminLivewire.dashboard.widgets` | `array $widgets`, `?Authenticatable $user` | Widgets | Rendering the dashboard | [Dashboard Widgets](Extending-Dashboard-Widgets) |
| `ap.ecommerceAdminLivewire.spotlight.providers` | `array $providers` (name => provider or class), `?Authenticatable $user` | Providers | Every palette search | [Command Palette Providers](Extending-Command-Palette) |
| `ap.ecommerceAdminLivewire.table.{screen}.columns` | `array $columns`, `Component $component` | Columns | Rendering or exporting an index screen | [Resource Tables](Extending-Resource-Tables) |
| `ap.ecommerceAdminLivewire.table.{screen}.filters` | `array $filters`, `Component $component` | Filters | Rendering or querying an index screen | [Resource Tables](Extending-Resource-Tables) |
| `ap.ecommerceAdminLivewire.table.{screen}.bulkActions` | `array $actions`, `Component $component` | Bulk actions | Rendering or running a bulk action | [Resource Tables](Extending-Resource-Tables) |
| `ap.ecommerceAdminLivewire.layout.viteEntries` | `array $entries` | Vite entry paths | Rendering either layout | [Layout](Extending-Layout) |
| `ap.ecommerceAdminLivewire.timeline.entry` | `array $presented` (`icon`, `description`, `known`), `Model $row`, `Model $subject` | `array` with `icon`, `description`, `known` | Rendering each activity timeline entry | Below |
| `ap.ecommerceAdminLivewire.statusBadge` | `array $presented` (`label`, `color`), `string $type`, `string $value` | `array` with `label`, `color` | Rendering any status badge | Below |
| `ap.ecommerceAdminLivewire.ruleBuilder.describe` | `?string $text`, `string $registry`, `string $type`, `array $config` | Lower-case phrase or `null` | Building a promotion's summary sentence | [Config Forms](Extending-Config-Forms) |
| `ap.ecommerceAdminLivewire.reports.{report}.table` | `array $table` (`columns`, `rows`, `totals`), `array $result` | Table | Rendering or exporting a report | Below |
| `ap.ecommerceAdminLivewire.order.taxBreakdown` | `array $lines` (`[ label, amount ]`), `Order $order` | Tax lines | Rendering an order's totals | Below |
| `ap.ecommerceAdminLivewire.currencies` | `array $currencies` (ISO codes), `?Product $product` | ISO codes | Building the product form's price rows | Below |
| `ap.ecommerceAdminLivewire.coupons.usage` | `array $counts` (empty), `Promotion $promotion`, `array $codes` | code => count | Rendering a coupon promotion's Coupons tab | Below |
| `ap.ecommerceAdminLivewire.notifications.locales` | `array $locales` | Locale codes | Offering locales to add to a notification template | Below |
| `ap.ecommerceAdminLivewire.kanban.boardUrl` | `?string $url`, `KanbanBoard $board` | URL or `null` | Rendering an "Open board" link | Below |

`{screen}` is one of `orders`, `reviews`, `products`, `tags`, `inventory`, `digital-files`, `license-keys`, `customers`, `promotions`, `tax-rates`, `webhooks`, `webhook-deliveries`. `{report}` is a report key from the engine's `ReportRegistry`, such as `sales`.

## Filters from other packages

| Hook | Owner | How the admin uses it |
| --- | --- | --- |
| `ap.ecommerce.abilities.{resource}.{action}` | Engine | Applies (through the engine) when deciding an ability. With `cms-framework`, the admin adds one per ability so RBAC permissions grant it. |
| `ap.ecommerce.product.listQuery` | Engine | Applied to the products index query, with the screen's filters and search |
| `ap.livewireUiComponents.spotlightCommands` | livewire-ui-components | The admin appends its palette results |
| `ap.cmsFramework.admin.menu` | cms-framework | The admin adds its navigation to the CMS menu |

## Browser events

Livewire events the admin's components dispatch and listen for. Use the class constants rather than the strings.

| Constant | Event | Dispatch it when | Listened for by |
| --- | --- | --- | --- |
| `OrderPanelRegistry::ORDER_UPDATED_EVENT` | `ecommerce-admin-order-updated` | A panel changed the order | The order page and panels showing order state |
| `CustomerTabRegistry::CUSTOMER_UPDATED_EVENT` | `ecommerce-admin-customer-updated` | A tab changed the customer | The customer page header |
| `ProductTypePanel::SAVED_EVENT` | `ecommerce-admin-product-saved` | Dispatched by the product form after Save | Product type panels |

## Examples

### Describe a satellite's timeline event

Entries the admin does not know show their raw event type and a collapsible payload. Describe yours:

```php
addFilter( 'ap.ecommerceAdminLivewire.timeline.entry', function ( array $presented, $row, $subject ): array {
    if ( 'loyalty.points_awarded' !== $row->event_type ) {
        return $presented;
    }

    return [
        'icon'        => 'o-gift',
        'description' => __( ':points points awarded', [ 'points' => $row->payload['points'] ?? 0 ] ),
        'known'       => true,
    ];
} );
```

The timeline covers orders, products, customers, and promotions.

### Label a status value

```php
addFilter( 'ap.ecommerceAdminLivewire.statusBadge', function ( array $presented, string $type, string $value ): array {
    if ( 'fulfillment' === $type && 'awaiting_pickup' === $value ) {
        return [ 'label' => __( 'Awaiting pickup' ), 'color' => 'info' ];
    }

    return $presented;
} );
```

Unknown values otherwise show a headline-cased label in the neutral colour. Colours are daisyUI names: `neutral`, `info`, `success`, `warning`, `error`.

### Add columns to a report

```php
addFilter( 'ap.ecommerceAdminLivewire.reports.top-products.table', function ( array $table, array $result ): array {
    $table['columns'][] = [ 'key' => 'margin', 'label' => __( 'Margin' ), 'type' => 'percent' ];

    foreach ( $table['rows'] as $index => $row ) {
        $table['rows'][ $index ]['margin'] = MarginCalculator::for( $row );
    }

    return $table;
} );
```

Column types are `text`, `int`, `money`, `percent`, and `rate`. The same table drives the screen and the CSV export.

### Show a jurisdiction tax breakdown

An order's tax lines come from `meta.tax_lines` when the tax provider recorded them, otherwise one line labelled with the engine's `TaxLabel`:

```php
addFilter( 'ap.ecommerceAdminLivewire.order.taxBreakdown', function ( array $lines, $order ): array {
    return app( AvalaraTax::class )->linesFor( $order ) ?: $lines;
} );
```

Each line is `[ 'label' => string, 'amount' => int ]` in the order currency's minor units.

### Offer extra price currencies

```php
addFilter( 'ap.ecommerceAdminLivewire.currencies', function ( array $currencies, $product ): array {
    return [ ...$currencies, 'CAD', 'AUD' ];
} );
```

The base currency always comes first. Values that are not three upper-case letters are dropped.

### Per-code coupon usage

The engine records usage per promotion, not per code. A satellite that tracks codes can fill the Coupons tab's "Uses" column, which appears only when this filter returns counts:

```php
addFilter( 'ap.ecommerceAdminLivewire.coupons.usage', function ( array $counts, $promotion, array $codes ): array {
    return CouponRedemption::query()
        ->whereIn( 'code', $codes )
        ->selectRaw( 'code, count(*) as uses' )
        ->groupBy( 'code' )
        ->pluck( 'uses', 'code' )
        ->all();
} );
```

### Notification template locales

The default list is `en`, `es`, `fr`, `de`, plus the engine's default notification locale. Locales a template already has are not offered again:

```php
addFilter( 'ap.ecommerceAdminLivewire.notifications.locales', fn ( array $locales ): array => [ ...$locales, 'it', 'pt_BR' ] );
```

### Kanban "Open board" link

The link appears when `artisanpack-ui/ecommerce-kanban-livewire` is installed. Point it somewhere else, or show it for your own board UI:

```php
addFilter( 'ap.ecommerceAdminLivewire.kanban.boardUrl', fn ( ?string $url, $board ): ?string => route( 'my-boards.show', $board ) );
```
