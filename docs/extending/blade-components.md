---
title: Blade Components
menu_order: 120
---

# Blade Components

The admin composes a few store-specific components from the design-system components in `livewire-ui-components`. They are registered with the `artisanpack-ec-` prefix, so your panels and tabs can match the admin's look and behaviour.

## Display

| Component | Attributes | Renders |
| --- | --- | --- |
| `<x-artisanpack-ec-money>` | `amount` (int, minor units), `currency`, `locale`, `compare-at`, `show-code` | An amount formatted with the engine's `MoneyFormatter`, in tabular figures and kept left to right. A negative amount gets a true minus and the error tone; a missing one reads "No amount". `compare-at` is struck through and read as "Was"; `show-code` adds the currency code. |
| `<x-artisanpack-ec-status-badge>` | `type` (`system`, `payment`, `fulfillment`, `review`, `shipment`), `value`, `substatus`, `size` (`sm`, `md`, `lg`) | A soft badge with a leading dot, a translated label, and a visually hidden type prefix. Colour is never the only signal. |
| `<x-artisanpack-ec-address>` | `address`, `show-phone` | A postal address with its lines in the country's order |
| `<x-artisanpack-ec-empty-state>` | `title`, `description`, `icon`, `size` (`md`, `sm`), `heading-level` | An empty state for a list or panel, with `role="status"` |
| `<x-artisanpack-ec-product-type-warning>` | `product` | The engine's warning when a product's type is missing |

```blade
<x-artisanpack-ec-money :amount="$order->total_amount" :currency="$order->total_currency" />
<x-artisanpack-ec-status-badge type="payment" :value="$order->payment_status" />
```

Describe a status value your satellite introduces with the `ap.ecommerceAdminLivewire.statusBadge` filter. See [Hooks Reference](../reference/hooks.md).

### Admin-only names

`ecommerce-storefront-livewire` registers the shared names `ec-money`, `ec-empty-state`, `ec-address`, and `ec-address-form` too, and wins when both packages are installed. The admin's own views use these aliases, which always resolve to the admin's components:

| Alias | Same as |
| --- | --- |
| `<x-artisanpack-ec-admin-money>` | `<x-artisanpack-ec-money>` |
| `<x-artisanpack-ec-admin-empty-state>` | `<x-artisanpack-ec-empty-state>` |
| `<x-artisanpack-ec-admin-address>` | `<x-artisanpack-ec-address>` |
| `<x-artisanpack-ec-admin-address-form>` | `<x-artisanpack-ec-address-form>` |

Use the `ec-admin-` names in admin panels and tabs when the storefront may be installed.

**Since:** 2.0.0

## Input

| Component | Attributes | Use |
| --- | --- | --- |
| `<x-artisanpack-ec-money-input>` | `currency`, `label`, `hint`, `prefix`, `suffix`, `id`, plus `wire:model` | Shows major units and binds integer minor units using the currency's subunit (JPY 0, USD 2, KWD 3). Validate the bound property as an integer. |
| `<x-artisanpack-ec-percent-input>` | `label`, `hint`, `prefix`, `suffix` (`%`), `id`, plus `wire:model` | Shows a percent and binds `rate_ubps` (units of 10^-9): 8.375 binds 83750000, as the engine's `TaxRateMath::fromPercent()` returns. |
| `<x-artisanpack-ec-address-form>` | `model`, `legend`, `live`, `with-name` | An address fieldset bound to `{model}.{field}`. Country comes first and relabels region and postal code (US: State and ZIP code, UK: County and Postcode). |
| `<x-artisanpack-ec-config-form>` | `registry`, `entry`, `model` | A form from a config schema. See [Config Forms](config-forms.md). |
| `<x-artisanpack-ec-product-picker>` and the `variant`, `customer`, `category`, `tag` pickers | `model`, `options`, `label`, `hint`, `single`, `live`, `id` | Searchable pickers. See [Pickers](pickers.md). |

## Error summary

Include the shared error summary at the top of a form so a failed save looks and behaves like the admin's own forms:

```blade
@include( 'ecommerce-admin::partials.error-summary' )
```

With validation errors, it renders the design-system ValidationSummary: it takes focus, has a pluralized "Not saved" title, and links to each field. It renders nothing once the form saves.

For a save that fails for a reason outside the form (a conflict, a lost session), pass a failure instead. It renders an error alert that takes focus the same way:

| Parameter | Required | Meaning |
| --- | --- | --- |
| `failure` | Yes | The alert's title |
| `failureDescription` | No | A sentence under the title |
| `actionLabel` | No | The label of one action button |
| `actionMethod` | No | The Livewire method the button calls. Pass it with `actionLabel`. |

Include it only while the failure applies. Here `$conflict` is a property the save handler sets when the save fails and clears when it succeeds:

```blade
@if ( $conflict )
    @include( 'ecommerce-admin::partials.error-summary', [
        'failure'            => __( 'Someone else changed this order.' ),
        'failureDescription' => __( 'Reload it to see their changes, then try again.' ),
        'actionLabel'        => __( 'Reload order' ),
        'actionMethod'       => 'reload',
    ] )
@endif
```

> **Warning:** The 1.x `message` parameter is gone. An included view inherits the parent's variables, and `@error` leaves a `$message` behind, so it showed by accident. Pass `failure` instead.

## Concerns for your Livewire components

| Trait (`ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\…`) | Gives you |
| --- | --- |
| `AuthorizesEcommerce` | `authorizeEcommerce( $ability, $subject )`, `authorizeEcommerceAbility( 'order.update', $subject )`, `canEcommerce()`, and a 403 on denial |
| `WithPickers` | `searchPicker()` and `optionsForPicker()` for the picker components |
| `WithConfigForms` | `configFormState()`, `validateConfigForm()`, and repeater row actions for `<x-artisanpack-ec-config-form>` |
| `WithRuleBuilder` | The ordered rule lists of `<x-artisanpack-ec-rule-builder>` (promotion conditions and discounts, kanban routing). Describe each list in `ruleBuilderLists()` with `registry`, `label`, `add`, `empty`, and an optional `description` shown under the list's heading (since 2.0.0). |
