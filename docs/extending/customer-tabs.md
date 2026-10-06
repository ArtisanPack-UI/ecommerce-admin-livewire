---
title: Customer Tabs
---

# Customer Tabs

`ArtisanPackUI\EcommerceAdminLivewire\Registries\CustomerTabRegistry` holds the tabs on the customer detail page. Each tab is a Livewire component mounted with the customer.

## Signature

```php
public function register(
    string $key,
    string|Closure $label,
    string $component,
    int $position = 100,
    string $parameter = 'customer',
    ?string $ability = null,
): void

public function unregister( string $key ): void
public function has( string $key ): bool
public function all(): array
```

| Argument | Meaning |
| --- | --- |
| `$key` | Unique kebab-case key. Also the tab's URL fragment. Registering the same key replaces the tab. |
| `$label` | The label, or a closure returning it so it is translated per request |
| `$component` | Your Livewire component name or class |
| `$position` | Lower positions come first; ties sort by key |
| `$parameter` | The mount parameter the customer is passed as |
| `$ability` | Optional `{resource}.{action}` ability. Users without it do not see the tab. |

A blank component or parameter, or a key that is not kebab-case, throws `InvalidArgumentException`.

## Built-in tabs

| Key | Label | Position | Parameter | Ability |
| --- | --- | --- | --- | --- |
| `orders` | Orders | 10 | `customer` | `order.viewAny` |
| `addresses` | Addresses | 20 | `customer` | — |
| `notifications` | Notification preferences | 30 | `customer` | — |
| `notes` | Notes | 40 | `subject` | — |
| `activity` | Activity | 50 | `subject` | — |

Positions leave room before, between, and after the built-in tabs.

## Example

```php
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\CustomerTabRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PointsTab extends Component
{
    public int $customerId;

    public function mount( Customer $customer ): void
    {
        abort_unless( Authorization::allows( auth()->user(), 'customer.view', $customer ), 403 );

        $this->customerId = $customer->id;
    }

    public function render(): View
    {
        return view( 'loyalty::admin.points-tab', [
            'balance' => PointsLedger::balanceFor( $this->customerId ),
        ] );
    }
}
```

```php
use Livewire\Livewire;

Livewire::component( 'loyalty-points-customer-tab', PointsTab::class );

app( CustomerTabRegistry::class )->register(
    'points',
    fn (): string => __( 'Points' ),
    'loyalty-points-customer-tab',
    60,
);
```

## Refreshing the page header

After your tab changes the customer, dispatch `CustomerTabRegistry::CUSTOMER_UPDATED_EVENT` (`ecommerce-admin-customer-updated`). The page header (lifetime stats and details) refreshes:

```php
$this->dispatch( CustomerTabRegistry::CUSTOMER_UPDATED_EVENT );
```
