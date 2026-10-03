---
title: Product Type Panels
---

# Product Type Panels

The product form has one tab for type-specific fields. `ArtisanPackUI\EcommerceAdminLivewire\Registries\ProductTypePanelRegistry` maps a product type key to the Livewire panel that renders that tab.

## Signature

```php
public function register( string $typeKey, ?string $livewireComponent ): void
public function forget( string $typeKey ): void
public function has( string $typeKey ): bool
public function component( string $typeKey ): ?string
public function panelClass( string $typeKey ): ?string
public function all(): array
```

- `$livewireComponent` is a Livewire alias or a class name. It must extend `ProductTypePanel`; `panelClass()` throws `InvalidArgumentException` otherwise.
- Register `null` to say the type needs no panel.
- A type that is not registered at all gets a notice on the form instead of a panel.

## Core panels

| Type | Panel |
| --- | --- |
| `simple` | None (`null`) |
| `variable` | `VariablePanel` — attributes, values, variant generation, variants |
| `digital` | `DigitalPanel` — files, download limit and expiry, license keys |
| `grouped`, `bundled` | `ChildrenPanel` — child products and quantities |

## How a panel works

A panel extends `ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\ProductTypePanel`. The form binds the panel's `$state` with `wire:model`, so the panel's state is the form's. One Save button covers every tab:

1. On Save the form validates the panel's state with its `rules()`.
2. It saves the product, then calls the panel's `save()` **in the same transaction**. Throw to roll back.
3. Validation errors come back through `$panelErrors`, keyed by state path, and are shown on the panel's fields.
4. After saving, the form dispatches `ProductTypePanel::SAVED_EVENT` (`ecommerce-admin-product-saved`) so panels re-render with the saved ids.

Panel actions (add a row, generate variants) only change `$state`. Nothing is stored until Save.

## What you implement

```php
abstract public static function initialState( ?Product $product ): array;
abstract public static function rules( array $state, ?Product $product ): array;
abstract public static function save( Product $product, array $state ): void;

public static function validationAttributes(): array; // optional, readable field names keyed by state path
public static function label(): string;               // optional, the tab label (default "Type settings")
```

The base class gives you:

| Member | Use |
| --- | --- |
| `$state` | The panel state, bound to the form |
| `$productId`, `product()` | The product being edited, or `null` while creating |
| `$readOnly`, `assertWritable()` | Call `assertWritable()` at the top of every state-changing action |
| `mount()` / `hydrate()` | Already authorize `product.view` (edit) or `product.create` (new) |

## Example

```php
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\ProductTypePanel;
use Illuminate\Contracts\View\View;

class SubscriptionPanel extends ProductTypePanel
{
    public static function label(): string
    {
        return __( 'Subscription' );
    }

    public static function initialState( ?Product $product ): array
    {
        $plan = null === $product ? null : SubscriptionPlan::query()->where( 'product_id', $product->id )->first();

        return [
            'interval'       => $plan?->interval ?? 'month',
            'interval_count' => $plan?->interval_count ?? 1,
        ];
    }

    public static function rules( array $state, ?Product $product ): array
    {
        return [
            'interval'       => [ 'required', 'in:week,month,year' ],
            'interval_count' => [ 'required', 'integer', 'min:1', 'max:24' ],
        ];
    }

    public static function save( Product $product, array $state ): void
    {
        app( SubscriptionPlanService::class )->syncFor( $product, $state );
    }

    public function render(): View
    {
        return view( 'subscriptions::admin.product-panel' );
    }
}
```

In the view, bind fields to `state.*` and show errors under the same keys:

```blade
<x-artisanpack-select
    :label="__( 'Billing interval' )"
    wire:model="state.interval"
    :options="[ [ 'id' => 'month', 'name' => __( 'Monthly' ) ], [ 'id' => 'year', 'name' => __( 'Yearly' ) ] ]"
    :disabled="$readOnly"
/>
```

Register it in your service provider's `boot()`:

```php
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ProductTypePanelRegistry;
use Livewire\Livewire;

Livewire::component( 'subscriptions-product-panel', SubscriptionPanel::class );

app( ProductTypePanelRegistry::class )->register( 'subscription', 'subscriptions-product-panel' );
```

The product type itself (`subscription`) is registered with the engine's `ProductTypeRegistry`. When the satellite that provides a type is uninstalled, products of that type render read-only with a warning.
