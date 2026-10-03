---
title: Order Panels
---

# Order Panels

`ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry` holds the panels on the order detail page. Each panel is a Livewire component.

## Signature

```php
public function register( string $key, string $component, string $column = 'main', int $position = 100 ): void
public function unregister( string $key ): void
public function has( string $key ): bool
public function all(): array
public function forColumn( string $column ): array
```

| Argument | Meaning |
| --- | --- |
| `$key` | Unique key. Registering the same key replaces the panel. |
| `$component` | Your registered Livewire component name |
| `$column` | `main` or `side`. Anything else throws `InvalidArgumentException`. |
| `$position` | Lower renders first; ties sort by key |

## Built-in panels

| Key | Component | Column | Position |
| --- | --- | --- | --- |
| `fulfillment` | `artisanpack-ecommerce-admin-order-fulfillment` | `main` | 10 |
| `refunds` | `artisanpack-ecommerce-admin-order-refunds` | `main` | 20 |
| `edits` | `artisanpack-ecommerce-admin-order-edits` | `main` | 30 |

Items, totals, status, customer, addresses, payment, boards, notes, and the timeline are part of the page, not registered panels.

## Writing a panel

The page mounts your component with `[ 'order' => $order ]`. It only guarantees the user may view the order, so authorize everything else yourself.

```php
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class LoyaltyOrderPanel extends Component
{
    public int $orderId;

    protected $listeners = [ OrderPanelRegistry::ORDER_UPDATED_EVENT => '$refresh' ];

    public function mount( Order $order ): void
    {
        abort_unless( Authorization::allows( auth()->user(), 'order.view', $order ), 403 );

        $this->orderId = $order->id;
    }

    public function award(): void
    {
        $order = Order::query()->findOrFail( $this->orderId );

        abort_unless( Authorization::allows( auth()->user(), 'order.update', $order ), 403 );

        // … award points through your own service …

        $this->dispatch( OrderPanelRegistry::ORDER_UPDATED_EVENT );
    }

    public function render(): View
    {
        return view( 'loyalty::admin.order-panel', [ 'order' => Order::query()->findOrFail( $this->orderId ) ] );
    }
}
```

Register the component and the panel in your service provider's `boot()`:

```php
use Livewire\Livewire;

Livewire::component( 'loyalty-order-panel', LoyaltyOrderPanel::class );

app( OrderPanelRegistry::class )->register( 'loyalty', 'loyalty-order-panel', 'side', 30 );
```

## Keeping the page in step

`OrderPanelRegistry::ORDER_UPDATED_EVENT` (`ecommerce-admin-order-updated`) is the browser event panels use to tell each other the order changed.

- **Dispatch it** after your panel changes the order. The page and every listening panel re-render.
- **Listen for it** if your panel shows order state, so it updates when another panel (a refund, a shipment) changes the order.

## Replacing or removing a built-in panel

```php
app( OrderPanelRegistry::class )->unregister( 'edits' );

app( OrderPanelRegistry::class )->register( 'refunds', 'my-refunds-panel', 'main', 20 );
```

A replacement takes on the built-in panel's job, including its authorization and idempotency. The built-in refund, shipment, and edit actions use one-time action tokens so a double submit runs once.
