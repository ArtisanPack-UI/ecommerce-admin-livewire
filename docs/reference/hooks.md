---
title: Hooks
menu_order: 10
redirect_from: extending/hooks-reference
---

# Hooks

Every filter the admin applies, the hooks from other packages it uses, and the browser events its components exchange. Register filters with `addFilter()` from `artisanpack-ui/hooks`, usually in a service provider's `boot()`. A filter must return a value of the same shape it received.

The admin fires no actions (`doAction`).

## Filters the admin applies

| Filter | Arguments | Return | Applied when | Docs |
| --- | --- | --- | --- | --- |
| `ap.ecommerceAdminLivewire.nav.items` | `array $items` | Navigation entries | Building the navigation (every admin page) | [Navigation](../extending/navigation.md) |
| `ap.ecommerceAdminLivewire.nav.sections` | `array $sections` (key => `[ label, position ]`) | Sections | Building the navigation | [Navigation](../extending/navigation.md) |
| `ap.ecommerceAdminLivewire.nav.badge` | `null $count`, `string $badge` | `int\|null` count | Resolving a badge key the core does not know | [Navigation](../extending/navigation.md) |
| `ap.ecommerceAdminLivewire.nav.badgeLabel` | `string $label`, `string $badge`, `int $count` | Accessible badge description | Describing a badge the core does not know | [Navigation](../extending/navigation.md) |
| `ap.ecommerceAdminLivewire.dashboard.widgets` | `array $widgets`, `?Authenticatable $user` | Widgets | Rendering the dashboard | [Dashboard Widgets](../extending/dashboard-widgets.md) |
| `ap.ecommerceAdminLivewire.spotlight.providers` | `array $providers` (name => provider or class), `?Authenticatable $user` | Providers | Every palette search | [Command Palette Providers](../extending/command-palette.md) |
| `ap.ecommerceAdminLivewire.table.{screen}.columns` | `array $columns`, `Component $component` | Columns | Rendering or exporting an index screen | [Resource Tables](../extending/resource-tables.md) |
| `ap.ecommerceAdminLivewire.table.{screen}.filters` | `array $filters`, `Component $component` | Filters | Rendering or querying an index screen | [Resource Tables](../extending/resource-tables.md) |
| `ap.ecommerceAdminLivewire.table.{screen}.bulkActions` | `array $actions`, `Component $component` | Bulk actions | Rendering or running a bulk action | [Resource Tables](../extending/resource-tables.md) |
| `ap.ecommerceAdminLivewire.layout.viteEntries` | `array $entries` | Vite entry paths | Rendering either layout | [Layout](../extending/layout.md) |
| `ap.ecommerceAdminLivewire.timeline.entry` | `array $presented` (`icon`, `tone`, `description`, `known`), `Model $row`, `Model $subject` | `array` with `icon`, `description`, `known`, and optionally `tone` | Rendering each activity timeline entry | Below |
| `ap.ecommerceAdminLivewire.statusBadge` | `array $presented` (`label`, `color`), `string $type`, `string $value` | `array` with `label`, `color` | Rendering any status badge | Below |
| `ap.ecommerceAdminLivewire.ruleBuilder.describe` | `?string $text`, `string $registry`, `string $type`, `array $config` | Lower-case phrase or `null` | Building a promotion's summary sentence | [Config Forms](../extending/config-forms.md) |
| `ap.ecommerceAdminLivewire.reports.{report}.table` | `array $table` (`columns`, `rows`, `totals`), `array $result` | Table | Rendering or exporting a report | Below |
| `ap.ecommerceAdminLivewire.order.taxBreakdown` | `array $lines` (`[ label, amount ]`), `Order $order` | Tax lines | Rendering an order's totals | Below |
| `ap.ecommerceAdminLivewire.currencies` | `array $currencies` (ISO codes), `?Product $product` | ISO codes | Building the product form's price rows | Below |
| `ap.ecommerceAdminLivewire.coupons.usage` | `array $counts` (empty), `Promotion $promotion`, `array $codes` | code => count | Rendering a coupon promotion's Coupons tab | Below |
| `ap.ecommerceAdminLivewire.notifications.locales` | `array $locales` | Locale codes | Offering locales to add to a notification template | Below |
| `ap.ecommerceAdminLivewire.kanban.boardUrl` | `?string $url`, `KanbanBoard $board`, `array $query` | URL or `null` | Rendering an "Open board" link | Below |

`{screen}` is one of `orders`, `reviews`, `products`, `tags`, `inventory`, `digitalFiles`, `licenseKeys`, `customers`, `promotions`, `taxRates`, `webhooks`, `webhookDeliveries`. `{report}` is a report key from the engine's `ReportRegistry` in camelCase, such as `sales`, `topProducts`, `revenueByCategory` or `lowStock`. **Since:** 2.0.0

> **Note:** 1.x named multi-word screens and reports in kebab case (`table.license-keys.columns`, `reports.top-products.table`). Those names still run, after the camelCase ones and with the value the camelCase subscribers returned, but they are deprecated and will be removed in 3.0. Rename your subscribers.

## Filters from other packages

| Hook | Owner | How the admin uses it |
| --- | --- | --- |
| `ap.ecommerce.abilities.{resource}.{action}` | Engine | Applies (through the engine) when deciding an ability. With `cms-framework`, the admin adds one per ability so RBAC permissions grant it. |
| `ap.ecommerce.product.listQuery` | Engine | Applied to the products index query, with the screen's filters and search |
| `ap.livewireUiComponents.spotlightCommands` | livewire-ui-components | The admin's palette route applies it to your own spotlight's results and shows them first, in an unlabeled group. On the library's own spotlight route the admin adds nothing. |
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

Entries the admin doesn't know show their raw event type and the payload in a "Details" disclosure. Describe yours:

```php
addFilter( 'ap.ecommerceAdminLivewire.timeline.entry', function ( array $presented, $row, $subject ): array {
    if ( 'loyalty.points_awarded' !== $row->event_type ) {
        return $presented;
    }

    return [
        'icon'        => 'gift',
        'tone'        => 'success',
        'description' => __( ':points points awarded', [ 'points' => $row->payload['points'] ?? 0 ] ),
        'known'       => true,
    ];
} );
```

- `icon` is a name in the icon registry (`IconRegistry::names()`: the design-system Lucide set plus the icons the admin registers, `gift` among them) or a Heroicon `o-*` / `s-*` name. Any other name renders nothing; register its SVG with `IconRegistry::register()` first. See [Icon names](../extending/navigation.md#icon-names).
- `tone` is optional and colors the entry's marker: `success`, `warning`, `error`, or `info`. Any other value leaves the marker neutral. Leaving `tone` out keeps the event's own tone, so a filter that only changes a core event's description doesn't change its color; a satellite's event has no tone of its own, so its marker stays neutral. The core events take a tone only when they need attention (failed, blocked, or mismatched is `error`; challenged or action required is `warning`) or complete a money movement (captured or refunded is `success`). **Since:** 2.0.0

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

Unknown values otherwise show a headline-cased label in the neutral color. Colors are design-system badge colors: `neutral`, `primary`, `secondary`, `accent`, `info`, `success`, `warning`, `error`. An unknown color falls back to `neutral`.

### Add columns to a report

```php
addFilter( 'ap.ecommerceAdminLivewire.reports.topProducts.table', function ( array $table, array $result ): array {
    $table['columns'][] = [ 'key' => 'margin', 'label' => __( 'Margin' ), 'type' => 'percent' ];

    foreach ( $table['rows'] as $index => $row ) {
        $table['rows'][ $index ]['margin'] = MarginCalculator::for( $row );
    }

    return $table;
} );
```

Column types are `text`, `int`, `money`, `percent`, and `rate`. The same table drives the screen and the CSV export.

### Show a jurisdiction tax breakdown

An order's tax lines come from `meta.tax_breakdown`, the per-rate rows the engine records when the order is placed or edited (the legacy `meta.tax_lines` is still read), otherwise one line labeled with the engine's `TaxLabel`:

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

The link appears on order detail and in kanban settings when `artisanpack-ui/ecommerce-kanban-livewire` is installed and the user has `kanbanBoard.view` on the board. Point it somewhere else, or show it for your own board UI:

```php
addFilter( 'ap.ecommerceAdminLivewire.kanban.boardUrl', fn ( ?string $url, $board, array $query = [] ): ?string => route( 'my-boards.show', [ $board, ...$query ] ) );
```

`$query` holds extra query parameters for the link. Order detail passes `[ 'card' => $order->id ]`, so the board opens with that order's card focused. It's `[]` for a plain board link. **Since:** 2.0.0 (`$query`)

`KanbanBoards::boardUrl( $board, array $query = [] )` builds the same link, filter included.
