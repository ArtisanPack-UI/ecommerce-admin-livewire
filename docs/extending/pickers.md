---
title: Pickers
---

# Pickers

Pickers are searchable selects for records: products, variants, customers, categories, and tags. Each picker type has a source in `ArtisanPackUI\EcommerceAdminLivewire\Registries\PickerSourceRegistry`. Add a source to make your own records pickable, or reuse the core pickers in your panels.

## Core picker types

| Type | Source | Ability to search | Component |
| --- | --- | --- | --- |
| `product` | `ProductPickerSource` | `product.viewAny` | `<x-artisanpack-ec-product-picker>` |
| `variant` | `VariantPickerSource` | `product.viewAny` | `<x-artisanpack-ec-variant-picker>` |
| `customer` | `CustomerPickerSource` | `customer.viewAny` | `<x-artisanpack-ec-customer-picker>` |
| `category` | `CategoryPickerSource` | `product.viewAny` | `<x-artisanpack-ec-category-picker>` |
| `tag` | `TagPickerSource` | `product.viewAny` | `<x-artisanpack-ec-tag-picker>` |

## Using a picker in your component

Add the `WithPickers` concern (with `AuthorizesEcommerce`) to your Livewire component, and pass the picker its options:

```php
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithPickers;

use AuthorizesEcommerce;
use WithPickers;

public array $productIds = [];
```

```blade
<x-artisanpack-ec-product-picker
    model="productIds"
    :options="$this->optionsForPicker( 'product', 'productIds' )"
    :label="__( 'Products' )"
/>
```

| Attribute | Meaning |
| --- | --- |
| `model` | The public property the picker binds to (a property path) |
| `options` | From `optionsForPicker( $type, $model )`: the selected options first, then the latest search results (20 at most) |
| `label`, `hint` | Label and help text |
| `single` | Pick one value instead of many |
| `live` | Bind with `wire:model.live` |
| `id` | A distinct id when the same model appears twice on a page |

Typing calls `searchPicker( $term, $type, $model )` on your component. It authorizes the source's ability before searching. A user who may not search the source gets no options.

## The source interface

```php
namespace ArtisanPackUI\EcommerceAdminLivewire\Pickers;

interface PickerSource
{
    /** The `{resource}.{action}` ability needed to search this source. */
    public function ability(): string;

    /** @return array<int, array{id: int|string, name: string, description: string|null}> */
    public function search( string $term, int $limit ): array;

    /** Options for the selected ids, so selected values keep their labels. */
    public function find( array $ids ): array;
}
```

An empty `$term` returns the first options.

## Add a source

For an Eloquent model, extend `EloquentPickerSource`. It implements `search()` and `find()` for you; you supply the query, the search clause, and the option shape. Use `whereLike()` so wildcards in the term match literally:

```php
use ArtisanPackUI\EcommerceAdminLivewire\Pickers\EloquentPickerSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanPickerSource extends EloquentPickerSource
{
    public function ability(): string
    {
        return 'product.viewAny';
    }

    protected function query(): Builder
    {
        return SubscriptionPlan::query()->orderBy( 'name' );
    }

    protected function applySearch( Builder $query, string $like ): void
    {
        $this->whereLike( $query, 'name', $like );
        $this->whereLike( $query, 'code', $like, 'or' );
    }

    protected function toOption( Model $model ): array
    {
        return [
            'id'          => $model->getKey(),
            'name'        => (string) $model->getAttribute( 'name' ),
            'description' => (string) $model->getAttribute( 'code' ),
        ];
    }
}
```

Register it in your service provider's `boot()`:

```php
use ArtisanPackUI\EcommerceAdminLivewire\Registries\PickerSourceRegistry;

app( PickerSourceRegistry::class )->register( 'subscription-plan', new SubscriptionPlanPickerSource() );
```

Registering an existing type replaces its source.

## A picker component for your type

Extend `ArtisanPackUI\EcommerceAdminLivewire\View\Components\Picker` and return your type. Override `unavailableMessage()` for the notice shown while no source is registered:

```php
use ArtisanPackUI\EcommerceAdminLivewire\View\Components\Picker;

class SubscriptionPlanPicker extends Picker
{
    public function type(): string
    {
        return 'subscription-plan';
    }
}
```

```php
Blade::component( 'subscriptions-plan-picker', SubscriptionPlanPicker::class );
```

```blade
<x-subscriptions-plan-picker
    model="planId"
    single
    :options="$this->optionsForPicker( 'subscription-plan', 'planId' )"
    :label="__( 'Plan' )"
/>
```
