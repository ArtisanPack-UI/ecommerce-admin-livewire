---
title: Config Forms
menu_order: 80
---

# Config Forms

Promotion conditions and actions, shipping method types, kanban automation triggers, and kanban card widgets each store a free-form `config` JSON blob. The engine contracts do not describe its fields, so the admin needs a schema per entry to render a form. `ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry` holds those schemas.

If your satellite adds an entry to one of these engine registries, register a schema for it. Without one, admins edit the entry's config as raw JSON.

## Registry names

| Name | Engine registry | Used on |
| --- | --- | --- |
| `promotion-condition` | `PromotionConditionRegistry` | Promotion rule builder, Conditions |
| `promotion-action` | `PromotionActionRegistry` | Promotion rule builder, Actions |
| `shipping-method` | `ShippingMethodTypeRegistry` | Shipping method form |
| `kanban-trigger` | `KanbanAutomationRegistry` | Kanban automation form |
| `kanban-widget` | `KanbanCardWidgetRegistry` | Kanban column card widgets |

## Signature

```php
public function register( string $registry, string $key, array|Closure $schema ): void
public function refine( string $registry, string $key, array $rules ): void
public function has( string $registry, string $key ): bool
public function schema( string $registry, string $key ): ?array
public function keys(): array
```

- `$key` is the entry's key in the engine registry.
- `$schema` is a list of fields, or a closure returning one. Use a closure so labels and options are translated in the request's locale and dynamic options are read when the form renders.
- An array schema is checked immediately; a closure is checked when resolved. A malformed field throws `InvalidArgumentException`.
- An empty schema (`[]`) says the entry has no settings. The form says so instead of showing the JSON editor.
- If the engine entry declares its own schema with a `configSchema()` method, that schema wins.
- `refine()` adds validation rules to an entry's fields. See [Cross-field validation](#cross-field-validation).

The registry's other public methods (`rules()`, `defaults()`, `cast()`, and so on) are listed in the [PHP API reference](../reference/php-api.md#configformregistry).

## Field definition

| Key | Required | Meaning |
| --- | --- | --- |
| `name` | Yes | Config key. Letters, digits, and underscores, starting with a letter. Unique within the schema. |
| `type` | Yes | One of the types below |
| `label` | Yes | Visible label |
| `hint` | No | Help text under the field |
| `rules` | No | Extra Laravel rules (an array or a pipe-delimited string). Fields are optional unless the rules include `required`. A rule may name a sibling field as `@name`, e.g. `required_without:@amount`. |
| `options` | No | For `select` and `multiselect`: `[ value => label ]` or `[ [ 'id' => …, 'name' => … ] ]` |
| `default` | No | Starting value for a new entry |
| `multiple` | No | For `product`, `category`, and `product-tag`: pick many or one. Defaults to `true` (a list of IDs) in schemas you register, and to `false` (one ID) in fields the engine declares. |
| `source` | No | For `product`: `product` (default) or `variant` |
| `fields` | No | For `repeater`: the row's fields (no nested repeaters) |

## Field types

| Type | Input | Stored as |
| --- | --- | --- |
| `text` | Text input (max 1,000 characters) | string |
| `textarea` | Multi-line text (max 10,000 characters) | string |
| `template` | Long text (max 10,000 characters) | string |
| `number` | Number input | number |
| `money` | Money input in the base currency | integer minor units |
| `percent` | Percent input (0–100) | number, e.g. `12.5` |
| `boolean` | Toggle | bool |
| `select` | Select from `options` | string |
| `multiselect` | Multi-select from `options` | list of strings |
| `product` | Product or variant picker | id or list of ids |
| `category` | Category picker | list of ids, or one id when `multiple` is `false` |
| `tag` | Free-form tags | list of strings |
| `product-tag` | Product tag picker | list of ids, or one id when `multiple` is `false` |
| `date` | Date picker | `Y-m-d` |
| `daterange` | Start and end dates | `[ 'start' => 'Y-m-d', 'end' => 'Y-m-d' ]` |
| `weekday` | Days of the week | list of ISO day numbers (1 = Monday) |
| `json` | JSON text editor | the decoded JSON value |
| `repeater` | Rows of the `fields` (up to 50) | list of row arrays |

On save, keys outside the schema and empty values are dropped and the rest are cast to their types.

## Example

```php
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;

app( ConfigFormRegistry::class )->register( 'promotion-condition', 'customer-has-points', fn (): array => [
    [
        'name'  => 'min_points',
        'type'  => 'number',
        'label' => __( 'Minimum points' ),
        'rules' => [ 'required', 'integer', 'min:1' ],
    ],
    [
        'name'    => 'tier',
        'type'    => 'select',
        'label'   => __( 'Tier' ),
        'options' => [ 'silver' => __( 'Silver' ), 'gold' => __( 'Gold' ) ],
    ],
] );
```

A tiered example with a repeater:

```php
app( ConfigFormRegistry::class )->register( 'promotion-action', 'points-multiplier', fn (): array => [
    [
        'name'   => 'tiers',
        'type'   => 'repeater',
        'label'  => __( 'Tiers' ),
        'rules'  => [ 'required', 'min:1' ],
        'fields' => [
            [ 'name' => 'min_subtotal', 'type' => 'money', 'label' => __( 'From subtotal' ), 'rules' => [ 'required' ] ],
            [ 'name' => 'multiplier', 'type' => 'number', 'label' => __( 'Multiplier' ), 'rules' => [ 'required', 'numeric', 'min:1' ] ],
        ],
    ],
] );
```

## Cross-field validation

A field's `rules` can already name a sibling with `@name`. `refine()` adds rules to an entry you don't own, most often an entry whose schema the engine declares with `configSchema()`, where you can't edit the field definitions. Use it for checks the schema can't express, such as "one of these two fields is required".

### `ConfigFormRegistry::refine()`

Appends validation rules to fields of an entry's schema, whether the schema is registered here or declared by the engine.

| Parameter | Type | Description |
| --- | --- | --- |
| `$registry` | `string` | The registry name, such as `promotion-action`. |
| `$key` | `string` | The entry key. |
| `$rules` | `array<string, array>` | Rules keyed by field path. `amount` is a top-level field; `tiers.percent` is the `percent` column of the `tiers` repeater. |

**Returns:** `void`

**Since:** 1.0.0

```php
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;

$forms = app( ConfigFormRegistry::class );

// Each tier needs a percent or a fixed amount.
$forms->refine( 'promotion-action', 'tiered-discount', [
    'tiers.percent' => [ 'required_without:@amount' ],
    'tiers.amount'  => [ 'required_without:@percent' ],
] );

// A top-level field: a tier is required once a minimum is set.
$forms->refine( 'promotion-condition', 'customer-has-points', [
    'tier' => [ 'required_with:@min_points' ],
] );
```

- Rules are appended to the field's own rules. Calling `refine()` again for the same path adds to what is there; it never replaces.
- `@name` points at a sibling field: a top-level field for a top-level path, or a column of the same row for a repeater column. `required_without:@amount` on `tiers.percent` becomes `required_without:config.tiers.*.amount`, and Laravel matches the `*` to the same row.
- A `required` rule makes an optional field required.
- A path that names no field in the schema is ignored.
- Refinements apply when the schema is read, so call `refine()` in `boot()` before or after the schema is registered.

## Describing a rule in the promotion summary

The promotion form shows a plain-language summary built from its rows. Describe your own condition or action with the `ap.ecommerceAdminLivewire.ruleBuilder.describe` filter. It receives `null`, the registry name, the entry key, and the config, and returns a lower-case phrase that reads in the middle of a sentence:

```php
addFilter( 'ap.ecommerceAdminLivewire.ruleBuilder.describe', function ( ?string $text, string $registry, string $type, array $config ): ?string {
    if ( 'promotion-condition' !== $registry || 'customer-has-points' !== $type ) {
        return $text;
    }

    return __( 'the customer has at least :points points', [ 'points' => $config['min_points'] ?? 0 ] );
} );
```

Keys nobody describes fall back to their registry label.

## Using a config form in your own component

`<x-artisanpack-ec-config-form>` renders a schema inside any Livewire component that uses the `WithConfigForms` concern:

```blade
<x-artisanpack-ec-config-form registry="promotion-condition" entry="customer-has-points" model="config" />
```

```php
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithConfigForms;

use AuthorizesEcommerce;
use WithConfigForms;

public array|string $config = [];

public function mount(): void
{
    $this->config = $this->configFormState( 'promotion-condition', 'customer-has-points', $existingConfig );
}

public function save(): void
{
    $config = $this->validateConfigForm( 'promotion-condition', 'customer-has-points', 'config' );
    // … hand $config to your service.
}
```

Product and category fields also need the `WithPickers` concern. See [Pickers](pickers.md).
