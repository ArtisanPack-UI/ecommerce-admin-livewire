---
title: Blade Components
---

# Blade Components

The admin composes a few store-specific components from `livewire-ui-components`. They are registered with the `artisanpack-ec-` prefix, so your panels and tabs can match the admin's look and behaviour.

## Display

| Component | Attributes | Renders |
| --- | --- | --- |
| `<x-artisanpack-ec-money>` | `amount` (int, minor units), `currency`, `locale` | An amount formatted with the engine's `MoneyFormatter` |
| `<x-artisanpack-ec-status-badge>` | `type` (`system`, `payment`, `fulfillment`, `review`, `shipment`), `value`, `substatus` | A status badge with a translated label. Colour is never the only signal. |
| `<x-artisanpack-ec-address>` | `address`, `show-phone` | A formatted postal address |
| `<x-artisanpack-ec-empty-state>` | `title`, `description`, `icon` | An empty state for a list or panel |
| `<x-artisanpack-ec-product-type-warning>` | `product` | The engine's warning when a product's type is missing |

```blade
<x-artisanpack-ec-money :amount="$order->total_amount" :currency="$order->total_currency" />
<x-artisanpack-ec-status-badge type="payment" :value="$order->payment_status" />
```

Describe a status value your satellite introduces with the `ap.ecommerceAdminLivewire.statusBadge` filter. See [Hooks Reference](Extending-Hooks-Reference).

## Input

| Component | Attributes | Use |
| --- | --- | --- |
| `<x-artisanpack-ec-money-input>` | `currency`, `label`, `hint`, `prefix`, `suffix`, `id`, plus `wire:model` | Shows major units and binds integer minor units using the currency's subunit (JPY 0, USD 2, KWD 3). Validate the bound property as an integer. |
| `<x-artisanpack-ec-percent-input>` | `label`, `hint`, `prefix`, `suffix` (`%`), `id`, plus `wire:model` | Shows a percent and binds `rate_ubps` (units of 10^-9): 8.375 binds 83750000, as the engine's `TaxRateMath::fromPercent()` returns. |
| `<x-artisanpack-ec-address-form>` | `model`, `legend`, `live`, `with-name` | An address fieldset bound to `{model}.{field}` |
| `<x-artisanpack-ec-config-form>` | `registry`, `entry`, `model` | A form from a config schema. See [Config Forms](Extending-Config-Forms). |
| `<x-artisanpack-ec-product-picker>` and the `variant`, `customer`, `category`, `tag` pickers | `model`, `options`, `label`, `hint`, `single`, `live`, `id` | Searchable pickers. See [Pickers](Extending-Pickers). |

## Concerns for your Livewire components

| Trait (`ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\…`) | Gives you |
| --- | --- |
| `AuthorizesEcommerce` | `authorizeEcommerce( $ability, $subject )`, `authorizeEcommerceAbility( 'order.update', $subject )`, `canEcommerce()`, and a 403 on denial |
| `WithPickers` | `searchPicker()` and `optionsForPicker()` for the picker components |
| `WithConfigForms` | `configFormState()`, `validateConfigForm()`, and repeater row actions for `<x-artisanpack-ec-config-form>` |
