---
title: PHP API
menu_order: 30
meta_description: Every public registry, Livewire concern, extension contract and support class the admin offers to your PHP code.
---

# PHP API

The PHP classes your app or satellite package can call, use, implement, or extend. Everything sits under the `ArtisanPackUI\EcommerceAdminLivewire\` namespace. The guides in [Extending](../extending.md) show each one in context; this page is the lookup.

Classes and methods not listed here are internal. They can change in any release.

Examples leave out the `use` statement for the class an entry describes, and for your own classes.

- [Registries](#registries): singletons you resolve from the container and register with.
- [Livewire concerns](#livewire-concerns): traits for your own admin components.
- [Contracts and base classes](#contracts-and-base-classes): what you implement or extend.
- [Support classes](#support-classes): static helpers the guides tell you to use.

The filters the admin applies and the browser events its components exchange are in [Hooks](hooks.md).

## Registries

Each registry is bound as a singleton. Resolve it with `app()` and register in your service provider's `boot()`:

```php
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;

public function boot(): void
{
    app( OrderPanelRegistry::class )->register( 'loyalty', 'loyalty-order-panel', 'side', 30 );
}
```

Registering an existing key replaces the earlier registration.

## OrderPanelRegistry

`ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry` holds the panels on the order detail page. Guide: [Order Panels](../extending/order-panels.md).

| Constant | Value | Meaning |
| --- | --- | --- |
| `COLUMNS` | `[ 'main', 'side' ]` | The page's columns |
| `ORDER_UPDATED_EVENT` | `ecommerce-admin-order-updated` | The browser event a panel dispatches after it changes the order |

### `OrderPanelRegistry::register()`

Registers or replaces a panel.

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` | A unique key, such as `subscription`. |
| `$component` | `string` | Your registered Livewire component name. |
| `$column` | `string` | `main` (default) or `side`. |
| `$position` | `int` | Lower renders first (default `100`). Ties sort by key. |

**Returns:** `void`. Throws `InvalidArgumentException` when the key or component is blank or the column is unknown.

**Since:** 1.0.0

```php
app( OrderPanelRegistry::class )->register( 'loyalty', 'loyalty-order-panel', 'side', 30 );
```

### `OrderPanelRegistry::unregister()`

Removes a panel.

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` | The panel key. |

**Returns:** `void`

**Since:** 1.0.0

```php
app( OrderPanelRegistry::class )->unregister( 'edits' );
```

### `OrderPanelRegistry::has()`

Whether a panel is registered.

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` | The panel key. |

**Returns:** `bool`

**Since:** 1.0.0

```php
$hasRefunds = app( OrderPanelRegistry::class )->has( 'refunds' );
```

### `OrderPanelRegistry::all()`

Every panel, keyed by panel key, in registration order.

**Returns:** `array<string, array{key: string, component: string, column: string, position: int}>`

**Since:** 1.0.0

```php
$panels = app( OrderPanelRegistry::class )->all();
```

### `OrderPanelRegistry::forColumn()`

The panels of one column, sorted by position and then key.

| Parameter | Type | Description |
| --- | --- | --- |
| `$column` | `string` | `main` or `side`. |

**Returns:** `array<int, array{key: string, component: string, column: string, position: int}>`

**Since:** 1.0.0

```php
$sidePanels = app( OrderPanelRegistry::class )->forColumn( 'side' );
```

## ProductTypePanelRegistry

`ArtisanPackUI\EcommerceAdminLivewire\Registries\ProductTypePanelRegistry` maps a product type key to the Livewire panel for the product form's type tab. Guide: [Product Type Panels](../extending/product-type-panels.md).

### `ProductTypePanelRegistry::register()`

Registers or replaces the panel for a product type.

| Parameter | Type | Description |
| --- | --- | --- |
| `$typeKey` | `string` | The product type key. |
| `$livewireComponent` | `string\|null` | A Livewire alias or class extending [`ProductTypePanel`](#producttypepanel), or `null` for a type with no panel. |

**Returns:** `void`. Throws `InvalidArgumentException` when the key is empty.

**Since:** 1.0.0

```php
app( ProductTypePanelRegistry::class )->register( 'subscription', 'subscriptions-product-panel' );
```

### `ProductTypePanelRegistry::forget()`

Removes a type's registration, so the form shows its "no panel registered" notice.

| Parameter | Type | Description |
| --- | --- | --- |
| `$typeKey` | `string` | The product type key. |

**Returns:** `void`

**Since:** 1.0.0

```php
app( ProductTypePanelRegistry::class )->forget( 'bundled' );
```

### `ProductTypePanelRegistry::has()`

Whether the type is registered, with a panel or explicitly without one.

| Parameter | Type | Description |
| --- | --- | --- |
| `$typeKey` | `string` | The product type key. |

**Returns:** `bool`

**Since:** 1.0.0

```php
$known = app( ProductTypePanelRegistry::class )->has( 'subscription' );
```

### `ProductTypePanelRegistry::component()`

The Livewire component registered for the type.

| Parameter | Type | Description |
| --- | --- | --- |
| `$typeKey` | `string` | The product type key. |

**Returns:** `string|null`, the alias or class, or `null` for none.

**Since:** 1.0.0

```php
$component = app( ProductTypePanelRegistry::class )->component( 'variable' );
```

### `ProductTypePanelRegistry::panelClass()`

The panel's class, resolving a Livewire alias when needed. The result is cached per type.

| Parameter | Type | Description |
| --- | --- | --- |
| `$typeKey` | `string` | The product type key. |

**Returns:** `class-string<ProductTypePanel>|null`, or `null` when the type has no panel. Throws `InvalidArgumentException` when the component can't be resolved or doesn't extend `ProductTypePanel`.

**Since:** 1.0.0

```php
$class = app( ProductTypePanelRegistry::class )->panelClass( 'subscription' );
$label = null === $class ? null : $class::label();
```

### `ProductTypePanelRegistry::all()`

Every registration.

**Returns:** `array<string, string|null>`, type key => component.

**Since:** 1.0.0

```php
$panels = app( ProductTypePanelRegistry::class )->all();
```

## CustomerTabRegistry

`ArtisanPackUI\EcommerceAdminLivewire\Registries\CustomerTabRegistry` holds the tabs on the customer detail page. Guide: [Customer Tabs](../extending/customer-tabs.md).

| Constant | Value | Meaning |
| --- | --- | --- |
| `CUSTOMER_UPDATED_EVENT` | `ecommerce-admin-customer-updated` | The browser event a tab dispatches after it changes the customer, so the page header refreshes |

### `CustomerTabRegistry::register()`

Registers or replaces a tab.

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` | A unique kebab-case key; also the tab's URL fragment. |
| `$label` | `string\|Closure` | The label, or a closure returning it so it is translated per request. |
| `$component` | `string` | Your Livewire component name or class. |
| `$position` | `int` | Lower positions come first (default `100`). |
| `$parameter` | `string` | The mount parameter the customer is passed as (default `customer`). |
| `$ability` | `string\|null` | A `{resource}.{action}` ability the tab needs. Users without it don't see the tab. |

**Returns:** `void`. Throws `InvalidArgumentException` when the key isn't kebab-case or the component or parameter is blank.

**Since:** 1.0.0

```php
app( CustomerTabRegistry::class )->register( 'loyalty', fn (): string => __( 'Loyalty' ), 'loyalty-customer-tab', 50, 'customer', 'customer.view' );
```

### `CustomerTabRegistry::unregister()`

Removes a tab.

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` | The tab key. |

**Returns:** `void`

**Since:** 1.0.0

```php
app( CustomerTabRegistry::class )->unregister( 'loyalty' );
```

### `CustomerTabRegistry::has()`

Whether a tab is registered.

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` | The tab key. |

**Returns:** `bool`

**Since:** 1.0.0

```php
$hasOrders = app( CustomerTabRegistry::class )->has( 'orders' );
```

### `CustomerTabRegistry::all()`

Every tab, sorted by position and then key, with labels resolved.

**Returns:** `array<int, array{key: string, label: string, component: string, position: int, parameter: string, ability: string|null}>`

**Since:** 1.0.0

```php
$tabs = app( CustomerTabRegistry::class )->all();
```

## SettingsTabRegistry

`ArtisanPackUI\EcommerceAdminLivewire\Registries\SettingsTabRegistry` holds settings tabs that render their own Livewire component. Guide: [Settings Tabs](../extending/settings-tabs.md).

### `SettingsTabRegistry::register()`

Registers or replaces a tab.

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` | A unique key of lower-case letters and digits in groups joined by `-` or `_`; also the URL segment. |
| `$label` | `string\|Closure` | The label, or a closure returning it. |
| `$component` | `string` | Your Livewire component name or class. |
| `$position` | `int` | Lower positions come first (default `100`). The engine's groups sit at 10–100. |

**Returns:** `void`. Throws `InvalidArgumentException` when the key is malformed or the component is blank.

**Since:** 1.0.0

```php
app( SettingsTabRegistry::class )->register( 'loyalty', fn (): string => __( 'Loyalty' ), 'loyalty-settings', 110 );
```

### `SettingsTabRegistry::unregister()`

Removes a tab.

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` | The tab key. |

**Returns:** `void`

**Since:** 1.0.0

```php
app( SettingsTabRegistry::class )->unregister( 'loyalty' );
```

### `SettingsTabRegistry::has()`

Whether a tab is registered.

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` | The tab key. |

**Returns:** `bool`

**Since:** 1.0.0

```php
$registered = app( SettingsTabRegistry::class )->has( 'loyalty' );
```

### `SettingsTabRegistry::get()`

One tab, with its label resolved.

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` | The tab key. |

**Returns:** `array{key: string, label: string, component: string, position: int}|null`

**Since:** 1.0.0

```php
$tab = app( SettingsTabRegistry::class )->get( 'loyalty' );
```

### `SettingsTabRegistry::all()`

Every tab, sorted by position and then key, with labels resolved.

**Returns:** `array<int, array{key: string, label: string, component: string, position: int}>`

**Since:** 1.0.0

```php
$tabs = app( SettingsTabRegistry::class )->all();
```

## ConfigFormRegistry

`ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry` holds the field schemas for the `config` JSON of engine registry entries: promotion conditions and actions, shipping method types, kanban triggers, and kanban card widgets. Guide: [Config Forms](../extending/config-forms.md).

Most extensions only call `register()` and `refine()`. The other methods are what `WithConfigForms` and `WithRuleBuilder` use, and are public for components that manage a config form themselves.

| Constant | Value | Meaning |
| --- | --- | --- |
| `TYPES` | `[ 'text', 'textarea', 'number', 'money', 'percent', 'boolean', 'select', 'multiselect', 'product', 'category', 'tag', 'product-tag', 'date', 'daterange', 'weekday', 'template', 'json', 'repeater' ]` | The field types a schema may use |
| `REGISTRIES` | registry name => engine registry class | `promotion-condition`, `promotion-action`, `shipping-method`, `kanban-trigger`, `kanban-widget` |
| `MAX_ROWS` | `50` | The most rows a repeater accepts |

### `ConfigFormRegistry::register()`

Registers or replaces the schema for a registry entry. An array schema is checked at once; a closure is checked when it is resolved.

| Parameter | Type | Description |
| --- | --- | --- |
| `$registry` | `string` | The registry name, such as `promotion-action`. |
| `$key` | `string` | The entry key in the engine registry. |
| `$schema` | `array\|Closure` | The fields, or a closure returning them. See [Field definition](../extending/config-forms.md#field-definition). |

**Returns:** `void`. Throws `InvalidArgumentException` when the registry or key is blank or a field is malformed.

**Since:** 1.0.0

```php
app( ConfigFormRegistry::class )->register( 'promotion-condition', 'customer-has-points', fn (): array => [
    [ 'name' => 'min_points', 'type' => 'number', 'label' => __( 'Minimum points' ), 'rules' => [ 'required', 'integer', 'min:1' ] ],
] );
```

### `ConfigFormRegistry::refine()`

Appends validation rules to fields of an entry's schema, whether the schema is registered here or declared by the engine. See [Cross-field validation](../extending/config-forms.md#cross-field-validation).

| Parameter | Type | Description |
| --- | --- | --- |
| `$registry` | `string` | The registry name. |
| `$key` | `string` | The entry key. |
| `$rules` | `array<string, array>` | Rules keyed by field path. `tiers.percent` is the `percent` column of the `tiers` repeater. |

**Returns:** `void`

**Since:** 1.0.0

```php
app( ConfigFormRegistry::class )->refine( 'promotion-action', 'tiered-discount', [
    'tiers.percent' => [ 'required_without:@amount' ],
] );
```

### `ConfigFormRegistry::has()`

Whether the entry has a schema, registered here or declared by the engine.

| Parameter | Type | Description |
| --- | --- | --- |
| `$registry` | `string` | The registry name. |
| `$key` | `string` | The entry key. |

**Returns:** `bool`. When it is `false`, the form falls back to a JSON editor.

**Since:** 1.0.0

```php
$hasForm = app( ConfigFormRegistry::class )->has( 'shipping-method', 'flat-rate' );
```

### `ConfigFormRegistry::schema()`

The normalized schema with refinements applied. A schema the engine entry declares wins over a registered one.

| Parameter | Type | Description |
| --- | --- | --- |
| `$registry` | `string` | The registry name. |
| `$key` | `string` | The entry key. |

**Returns:** `array<int, array>|null`, the fields with every key filled in (`name`, `type`, `label`, `hint`, `rules`, `options`, `default`, `multiple`, `source`, `fields`), or `null` when the entry has none.

**Since:** 1.0.0

```php
$fields = app( ConfigFormRegistry::class )->schema( 'promotion-action', 'percent-off-cart' ) ?? [];
```

### `ConfigFormRegistry::keys()`

The `{registry}:{key}` names registered with `register()`. Schemas the engine declares aren't listed.

**Returns:** `array<int, string>`

**Since:** 1.0.0

```php
$registered = app( ConfigFormRegistry::class )->keys();
```

### `ConfigFormRegistry::rules()`

Validation rules for a config bound at `$prefix`: one rule set per field and per repeater cell. Without a schema, the value at `$prefix` must be a JSON object string.

| Parameter | Type | Description |
| --- | --- | --- |
| `$registry` | `string` | The registry name. |
| `$key` | `string` | The entry key. |
| `$prefix` | `string` | The property path the config is bound to, such as `config`. |

**Returns:** `array<string, array>`

**Since:** 1.0.0

```php
$rules = app( ConfigFormRegistry::class )->rules( 'promotion-condition', 'customer-has-points', 'config' );
```

### `ConfigFormRegistry::attributes()`

Readable attribute names for validation messages, keyed like `rules()`.

| Parameter | Type | Description |
| --- | --- | --- |
| `$registry` | `string` | The registry name. |
| `$key` | `string` | The entry key. |
| `$prefix` | `string` | The property path. |

**Returns:** `array<string, string>`

**Since:** 1.0.0

```php
$forms = app( ConfigFormRegistry::class );

$this->validate(
    $forms->rules( 'promotion-condition', 'customer-has-points', 'config' ),
    [],
    $forms->attributes( 'promotion-condition', 'customer-has-points', 'config' ),
);
```

### `ConfigFormRegistry::defaults()`

The starting config: each field's `default`, or an empty value of its type.

| Parameter | Type | Description |
| --- | --- | --- |
| `$registry` | `string` | The registry name. |
| `$key` | `string` | The entry key. |

**Returns:** `array<string, mixed>`

**Since:** 1.0.0

```php
$config = app( ConfigFormRegistry::class )->defaults( 'promotion-condition', 'customer-has-points' );
```

### `ConfigFormRegistry::formValues()`

A stored config as form state: the defaults overlaid with the stored values, `json` fields encoded as text, and repeater rows given stable row keys.

| Parameter | Type | Description |
| --- | --- | --- |
| `$registry` | `string` | The registry name. |
| `$key` | `string` | The entry key. |
| `$config` | `array<string, mixed>` | The stored config. |

**Returns:** `array<string, mixed>`

**Since:** 1.0.0

```php
$this->config = app( ConfigFormRegistry::class )->formValues( 'promotion-condition', 'customer-has-points', $condition->config ?? [] );
```

### `ConfigFormRegistry::validateDeclared()`

Checks a cast config against the schema the engine entry declares, with the engine's own `ConfigSchema` rules. Does nothing when the entry declares no schema. Call it after `cast()` when you store a config without going through an engine service.

| Parameter | Type | Description |
| --- | --- | --- |
| `$registry` | `string` | The registry name. |
| `$key` | `string` | The entry key. |
| `$config` | `array<string, mixed>` | The cast config. |
| `$prefix` | `string` | The property path; errors are keyed under it. |

**Returns:** `void`. Throws `ValidationException` when the engine rejects the config.

**Since:** 1.0.0

```php
app( ConfigFormRegistry::class )->validateDeclared( 'shipping-method', 'flat-rate', $config, 'config' );
```

### `ConfigFormRegistry::cast()`

Static. A validated config ready to store: keys outside the schema and empty values dropped, the rest cast to their field types.

| Parameter | Type | Description |
| --- | --- | --- |
| `$schema` | `array<int, array>` | A normalized schema, from `schema()`. |
| `$config` | `array<string, mixed>` | The validated input. |

**Returns:** `array<string, mixed>`

**Since:** 1.0.0

```php
$forms  = app( ConfigFormRegistry::class );
$config = ConfigFormRegistry::cast( $forms->schema( 'promotion-condition', 'customer-has-points' ) ?? [], $this->config );
```

### `ConfigFormRegistry::normalize()`

Static. Checks a raw schema and fills in every optional key. `register()` and `schema()` call it for you.

| Parameter | Type | Description |
| --- | --- | --- |
| `$schema` | `array<int, mixed>` | The raw fields. |
| `$nested` | `bool` | Whether these are repeater columns, which may not be repeaters themselves (default `false`). |

**Returns:** `array<int, array>`, the normalized fields. Throws `InvalidArgumentException` when a field is malformed.

**Since:** 1.0.0

```php
$fields = ConfigFormRegistry::normalize( [
    [ 'name' => 'note', 'type' => 'text', 'label' => __( 'Note' ) ],
] );
```

### `ConfigFormRegistry::emptyRow()`

Static. An empty repeater row for a field, with a fresh row key.

| Parameter | Type | Description |
| --- | --- | --- |
| `$field` | `array<string, mixed>` | A normalized repeater field. |

**Returns:** `array<string, mixed>`

**Since:** 1.0.0

```php
$row = ConfigFormRegistry::emptyRow( $tiersField );
```

## PickerSourceRegistry

`ArtisanPackUI\EcommerceAdminLivewire\Registries\PickerSourceRegistry` holds the search source behind each picker type. Guide: [Pickers](../extending/pickers.md).

### `PickerSourceRegistry::register()`

Registers or replaces the source for a picker type.

| Parameter | Type | Description |
| --- | --- | --- |
| `$type` | `string` | The picker type, such as `subscription-plan`. |
| `$source` | [`PickerSource`](#pickersource) | The source. |

**Returns:** `void`

**Since:** 1.0.0

```php
app( PickerSourceRegistry::class )->register( 'subscription-plan', new SubscriptionPlanPickerSource() );
```

### `PickerSourceRegistry::has()`

Whether a type has a source.

| Parameter | Type | Description |
| --- | --- | --- |
| `$type` | `string` | The picker type. |

**Returns:** `bool`

**Since:** 1.0.0

```php
$available = app( PickerSourceRegistry::class )->has( 'subscription-plan' );
```

### `PickerSourceRegistry::get()`

The source for a type.

| Parameter | Type | Description |
| --- | --- | --- |
| `$type` | `string` | The picker type. |

**Returns:** `PickerSource|null`

**Since:** 1.0.0

```php
$options = app( PickerSourceRegistry::class )->get( 'product' )?->search( 'mug', 10 ) ?? [];
```

### `PickerSourceRegistry::all()`

Every registered source.

**Returns:** `array<string, PickerSource>`, keyed by type.

**Since:** 1.0.0

```php
$types = array_keys( app( PickerSourceRegistry::class )->all() );
```

## Livewire concerns

Traits under `ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\` for your own Livewire components. Most need `AuthorizesEcommerce` alongside them; each section says what it needs.

Methods listed as **Livewire action** are public so the component's view can call them with `wire:click` or from Alpine. You don't call them from PHP. Every action authorizes itself.

## AuthorizesEcommerce

Enforces the engine's abilities inside a Livewire component. The admin calls engine services in-process, so it skips the REST layer's `ecommerce.can` middleware: authorize the screen in `mount()` and again in every action, because a Livewire action can be called with a forged request. Every denial ends with a 403 and a translated message.

### `authorizeEcommerce()`

Protected. Authorizes a policy ability on a model or model class through the engine's policies.

| Parameter | Type | Description |
| --- | --- | --- |
| `$ability` | `string` | The policy ability, such as `viewAny`, `update`, or `edit-fulfilled`. |
| `$subject` | `object\|string` | The model, or its class for `viewAny` and `create`. |

**Returns:** `void`. Ends the request with a 403 when denied.

**Since:** 1.0.0

```php
$this->authorizeEcommerce( 'update', $order );
$this->authorizeEcommerce( 'viewAny', Customer::class );
```

### `authorizeEcommerceAbility()`

Protected. Authorizes a `{resource}.{action}` ability that has no policy method, such as `report.view` or `settings.update`.

| Parameter | Type | Description |
| --- | --- | --- |
| `$ability` | `string` | The ability. |
| `$subject` | `mixed` | The subject, when there is one (default `null`). |

**Returns:** `void`. Ends the request with a 403 when denied.

**Since:** 1.0.0

```php
$this->authorizeEcommerceAbility( 'settings.update' );
```

### `authorizeAdminAccess()`

Protected. Authorizes entry to the admin as a whole: the user may see at least one navigation entry. See [`AdminNav::canAccess()`](#adminnavcanaccess).

**Returns:** `void`. Ends the request with a 403 when denied.

**Since:** 1.0.0

```php
$this->authorizeAdminAccess();
```

### `canEcommerce()`

Protected. Whether the user holds a policy ability, for showing or hiding a control. Doesn't stop the request.

| Parameter | Type | Description |
| --- | --- | --- |
| `$ability` | `string` | The policy ability. |
| `$subject` | `object\|string` | The model or model class. |

**Returns:** `bool`

**Since:** 1.0.0

```php
$canRefund = $this->canEcommerce( 'refund', $order );
```

### `productIsReadOnly()`

Protected. Whether a product must render read-only: its type's satellite isn't installed, or the user may not update it.

| Parameter | Type | Description |
| --- | --- | --- |
| `$product` | `Product` | The product. |

**Returns:** `bool`

**Since:** 1.0.0

```php
$readOnly = $this->productIsReadOnly( $product );
```

### `denyEcommerce()`

Protected. Ends the request with a 403 and the message "You are not allowed to do that."

**Returns:** `never`

**Since:** 1.0.0

```php
if ( ! $this->ownsSubscription( $subscription ) ) {
    $this->denyEcommerce();
}
```

## SendsToasts

Shows design-system toasts (`<x-artisanpack-toaster>`) from a component. `livewire-ui-components` ships a `Toast` trait too, but its methods are public, which makes each one a Livewire action any client could call. These are protected. The toaster renders text only, so don't HTML-escape the title or description.

### `toastSuccess()`

Protected. Shows a success toast.

| Parameter | Type | Description |
| --- | --- | --- |
| `$title` | `string` | The title. |
| `$description` | `string\|null` | A line under the title (default `null`). |

**Returns:** `void`

**Since:** 1.0.0

```php
$this->toastSuccess( __( 'Points awarded.' ) );
```

### `toastWarning()`

Protected. Shows a warning toast. Takes the same parameters as `toastSuccess()`.

**Returns:** `void`

**Since:** 1.0.0

```php
$this->toastWarning( __( 'Nothing to award.' ), __( 'The order has no eligible items.' ) );
```

### `toastError()`

Protected. Shows an error toast. Takes the same parameters as `toastSuccess()`.

**Returns:** `void`

**Since:** 1.0.0

```php
$this->toastError( __( 'The points service did not respond.' ) );
```

## WithActionToken

Makes destructive and money-moving actions idempotent. Mint a token when the button renders, pass it back as the action's argument, and wrap the write in `withActionToken()`. A second click, or a concurrent resubmit, with the same token does nothing and shows a notice. Needs `SendsToasts`.

```blade
<x-artisanpack-button
    wire:click="award( '{{ $this->actionToken( 'award', $order ) }}' )"
    wire:loading.attr="disabled"
>{{ __( 'Award points' ) }}</x-artisanpack-button>
```

A token is signed with the app key over the user, the component class, the action, and the subject, and is valid for 12 hours. Using it writes a row to the engine's idempotency table before the write runs.

### `actionToken()`

Protected. Mints a token for an action. Call it from the view (`$this->actionToken()`) as the button renders.

| Parameter | Type | Description |
| --- | --- | --- |
| `$action` | `string` | The action name, such as `refund`. |
| `$subject` | `mixed` | A model or scalar key the token is limited to (default `null`). |

**Returns:** `string`

**Since:** 1.0.0

```php
$token = $this->actionToken( 'award', $order );
```

### `withActionToken()`

Protected. Runs a write once per token. When the token is invalid, expired, or already used, the write doesn't run, the user gets a warning toast, and the method returns `null`. When the write throws, the exception is rethrown and the token is released so the user can retry.

| Parameter | Type | Description |
| --- | --- | --- |
| `$token` | `string` | The token the client sent back. |
| `$action` | `string` | The action name it was minted for. |
| `$write` | `callable` | The write. |
| `$subject` | `mixed` | The subject it was minted for (default `null`). |
| `$releaseOn` | `array<int, class-string<Throwable>>\|null` | Only these exceptions release the token. `null` (the default) releases on any exception. |

**Returns:** `mixed`, the write's return value, or `null` when it didn't run.

**Since:** 1.0.0 (`$releaseOn`: 2.0.0)

```php
public function award( string $token ): void
{
    $order = Order::query()->findOrFail( $this->orderId );

    $this->authorizeEcommerce( 'update', $order );

    $this->withActionToken(
        $token,
        'award',
        fn () => app( PointsService::class )->awardFor( $order ),
        $order,
        [ PointsUnavailableException::class ],
    );
}
```

The write doesn't run inside a transaction. Open your own `DB::transaction()` when it changes several rows. Pass `$releaseOn` when a write can fail after its side effect (a gateway call that timed out but may have gone through): any other exception keeps the token spent, so the user reloads and checks before retrying.

## WithConfigForms

State, validation, and repeater rows for `<x-artisanpack-ec-config-form>`. Needs `AuthorizesEcommerce`, and `WithPickers` when a schema has `product`, `category`, or `product-tag` fields. Guide: [Config Forms](../extending/config-forms.md#using-a-config-form-in-your-own-component).

The config property must be public, not `#[Locked]`, and typed `array|string`: it holds the JSON text when the entry has no schema.

### `configFormState()`

Protected. The value to bind a config form to: the stored config over the schema's defaults, or pretty-printed JSON for the fallback editor.

| Parameter | Type | Description |
| --- | --- | --- |
| `$registry` | `string` | The registry name. |
| `$entry` | `string` | The entry key. |
| `$config` | `array<string, mixed>` | The stored config. |

**Returns:** `array<string, mixed>|string`

**Since:** 1.0.0

```php
$this->config = $this->configFormState( 'promotion-condition', 'customer-has-points', $condition->config ?? [] );
```

### `validateConfigForm()`

Protected. Validates the config at `$property` against its schema and the engine's declared schema, and returns it ready to store: unknown keys dropped and values cast. The fallback JSON is decoded.

| Parameter | Type | Description |
| --- | --- | --- |
| `$registry` | `string` | The registry name. |
| `$entry` | `string` | The entry key. |
| `$property` | `string` | The config's property path, such as `config`. |

**Returns:** `array<string, mixed>`. Throws `ValidationException` on invalid input.

**Since:** 1.0.0

```php
$config = $this->validateConfigForm( 'promotion-condition', 'customer-has-points', 'config' );
```

### Livewire actions

| Action | Called by |
| --- | --- |
| `addConfigRow( $registry, $entry, $property, $field )` | A repeater's **Add** button. Stops at `ConfigFormRegistry::MAX_ROWS`. |
| `removeConfigRow( $registry, $entry, $property, $field, $index )` | A repeater row's **Remove** button |

Both authorize admin access and only touch a real repeater field on a public, unlocked array property. **Since:** 1.0.0

## WithPickers

Server search for the `<x-artisanpack-ec-*-picker>` components. Needs `AuthorizesEcommerce`. Guide: [Pickers](../extending/pickers.md#using-a-picker-in-your-component).

| Member | Meaning |
| --- | --- |
| `MAX_PICKER_CACHE` | `20`: the most result sets kept in the component's snapshot |
| `$pickerOptions` | Locked. The latest results per picker, keyed `{type}:{field}`. Only `searchPicker()` writes it. |

### `optionsForPicker()`

Public, for the view. The options to render: the latest search results, or the selected options followed by the first page. Returns nothing for a user who may not search the source, so the form still renders.

| Parameter | Type | Description |
| --- | --- | --- |
| `$type` | `string` | The picker type. |
| `$field` | `string` | The property the picker binds to. |

**Returns:** `array<int, array<string, mixed>>`

**Since:** 1.0.0

```blade
<x-artisanpack-ec-product-picker model="productIds" :options="$this->optionsForPicker( 'product', 'productIds' )" />
```

### `searchPicker()`

**Livewire action.** Called by the picker as the user types. Authorizes the source's ability (a 403 for a user who may not search it), then stores the selected options and up to 20 matches in `$pickerOptions`. Only a path into one of the component's own public properties is cached. **Since:** 1.0.0

### `pickerExcludedIds()`

Protected; override it. Ids a picker must not offer, such as the product being edited in its own related-products picker.

| Parameter | Type | Description |
| --- | --- | --- |
| `$type` | `string` | The picker type. |
| `$field` | `string` | The property path. |

**Returns:** `array<int, int|string>`. Empty by default.

**Since:** 1.0.0

```php
protected function pickerExcludedIds( string $type, string $field ): array
{
    return 'product' === $type && 'relatedIds' === $field ? [ $this->productId ] : [];
}
```

### `pickerLimit()`

Protected static; override it. How many options a search returns.

**Returns:** `int`, `20` by default.

**Since:** 1.0.0

```php
protected static function pickerLimit(): int
{
    return 10;
}
```

## WithRuleBuilder

Ordered lists of rules, such as promotion conditions and actions or kanban routing conditions, rendered by `<x-artisanpack-ec-rule-builder>`. Each list is fed by an engine registry, so entries satellites register appear on their own. Each row is `{ id, type, config }` and edits its config through the config form. Needs `AuthorizesEcommerce`, `WithConfigForms`, and `WithPickers`.

| Property | Meaning |
| --- | --- |
| `$ruleRows` | List key => rows of `{ id, type, config }` |
| `$ruleToAdd` | List key => the entry chosen in its "add" picker |
| `$openRule` | Locked. The row whose settings are open, as `{list}:{id}` |

### `ruleBuilderLists()`

Abstract; implement it. The lists the builder edits.

**Returns:** `array<string, array{registry: string, label: string, add: string, empty: string, description?: string}>`: list key => a [`ConfigFormRegistry::REGISTRIES`](#configformregistry) name, the list heading, the add button's label, the text for an empty list, and an optional line under the heading (since 2.0.0). A list keyed `actions` makes the summary describe a promotion; without one it describes a condition set.

**Since:** 1.0.0

```php
protected function ruleBuilderLists(): array
{
    return [
        'conditions' => [
            'registry' => 'promotion-condition',
            'label'    => __( 'Conditions' ),
            'add'      => __( 'Add condition' ),
            'empty'    => __( 'Applies to every order.' ),
        ],
    ];
}
```

### `authorizeRuleBuilder()`

Abstract; implement it. Authorizes changing the rules. Every rule builder action calls it.

**Returns:** `void`

**Since:** 1.0.0

```php
protected function authorizeRuleBuilder(): void
{
    $this->authorizeEcommerce( 'update', $this->promotion() );
}
```

### `ruleBuilderState()`

Protected. Loads stored rows into the builder, resetting it first.

| Parameter | Type | Description |
| --- | --- | --- |
| `$stored` | `array<string, iterable>` | List key => stored rows of `{ type, config }`. |

**Returns:** `void`

**Since:** 1.0.0

```php
$this->ruleBuilderState( [ 'conditions' => $rule->conditions ?? [] ] );
```

### `validateRules()`

Protected. Validates every row against its config schema and the engine's declared schema, and returns the rows to store. On failure it opens the first row with an error, then rethrows.

**Returns:** `array<string, array<int, array{type: string, config: array}>>`. Throws `ValidationException` when a row is invalid.

**Since:** 1.0.0

```php
$rules = $this->validateRules();

app( RoutingService::class )->saveConditions( $board, $rules['conditions'] );
```

### `ruleBuilderViewData()`

Protected. What `<x-artisanpack-ec-rule-builder>` renders: each list with its options and rows, the plain-language summary, and the open row.

**Returns:** `array{lists: array, summary: string, openRule: string|null}`

**Since:** 1.0.0

```php
return view( 'loyalty::admin.rules', [ 'ruleBuilder' => $this->ruleBuilderViewData() ] );
```

### `maxRules()`

Protected static; override it. The most rows one list accepts.

**Returns:** `int`, `20` by default.

**Since:** 1.0.0

```php
protected static function maxRules(): int
{
    return 10;
}
```

### Livewire actions

| Action | Called by |
| --- | --- |
| `addRule( $list )` | The list's **Add** button, with the type chosen in `$ruleToAdd` |
| `removeRule( $list, $index )` | A row's **Remove** button |
| `moveRule( $list, $index, $direction )` | **Move up** (`-1`) and **Move down** (`1`) |
| `reorderRules( $list, $ids )` | The drag handle. Ignored unless `$ids` names every row once. |
| `toggleRule( $list, $id )` | A row's **Settings** button |

**Since:** 1.0.0

## WithResourceTable

Search, filters, sort, pagination, selection, bulk actions, and CSV export for an index screen. Search, filters, sort, and page size live in the URL. Needs `AuthorizesEcommerce`, `SendsToasts`, and `WithActionToken`; it brings Livewire's `WithPagination` and `livewire-ui-components`' `WithTableExport` itself. Guides: [Resource Tables](../extending/resource-tables.md) and [the resource-table partial](../extending/blade-components.md#the-resource-table-partial).

Your screen's columns, filters, and bulk actions pass through the `ap.ecommerceAdminLivewire.table.{screen}.*` filters like the core screens'. The definitions use the shapes in [Resource Tables](../extending/resource-tables.md#columns).

```php
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithResourceTable;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SubscriptionsIndex extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithActionToken;
    use WithResourceTable;

    public function mount(): void
    {
        $this->authorizeTable();
    }

    public function render(): View
    {
        return view( 'subscriptions::admin.index', $this->resourceTableData() );
    }

    protected function authorizeTable(): void
    {
        if ( ! Authorization::allows( auth()->user(), 'order.viewAny' ) ) {
            $this->denyEcommerce();
        }
    }

    protected function tableScreen(): string
    {
        return 'subscriptions';
    }

    protected function tableQuery(): ResourceQuery
    {
        return new SubscriptionsQuery();
    }

    protected function tableCaption(): string
    {
        return __( 'Subscriptions' );
    }

    protected function tableColumns(): array
    {
        return [
            [ 'key' => 'reference', 'label' => __( 'Reference' ), 'sortable' => true ],
            [ 'key' => 'created_at', 'label' => __( 'Started' ), 'sortable' => true ],
        ];
    }

    protected function tableFilters(): array
    {
        return [
            [ 'key' => 'started', 'label' => __( 'Started' ), 'type' => 'date-range' ],
        ];
    }

    protected function tableBulkActions(): array
    {
        return [ $this->exportBulkAction() ];
    }
}
```

`hydrate` re-authorizes the table on every request, but not on mount: call `authorizeTable()` in your `mount()`.

### What you implement

| Method | Returns | Since |
| --- | --- | --- |
| `tableScreen(): string` | The screen key used in the extension filter names, such as `subscriptions` | 1.0.0 |
| `tableQuery(): ResourceQuery` | The screen's [query class](#resourcequery) | 1.0.0 |
| `authorizeTable(): void` | Authorizes viewing the table. Called by every table action and on every request. | 1.0.0 |
| `tableColumns(): array` | The core columns | 1.0.0 |
| `tableFilters(): array` | The core filters. Filters the query class handles need no `apply`. | 1.0.0 |
| `tableBulkActions(): array` | The core bulk actions. Return `[]` for none, which also hides the checkboxes. | 1.0.0 |
| `tableCaption(): string` | What the table lists, for screen readers | 1.0.0 |

All are abstract and protected.

### `resourceTableData()`

Protected. The variables the resource-table partial needs: `tableRows`, `tableColumns`, `tableFilters`, `tableBulkActions`, `tableCaption`, `tableSort`, `tableDirection`, `tablePerPageValues`, `tableFiltersActive`, `tableFilterCount`, `tableSelectionCount`, `tableAllMatching`, `tableSelectable`, `tableConfirming`, and `tableConfirmToken`.

**Returns:** `array<string, mixed>`

**Since:** 1.0.0

```php
return view( 'subscriptions::admin.index', [ ...$this->resourceTableData(), 'plans' => $plans ] );
```

### `exportBulkAction()`

Protected. The "Export selection" bulk action every core table offers, ready to return from `tableBulkActions()`.

**Returns:** `array<string, mixed>`

**Since:** 1.0.0

```php
protected function tableBulkActions(): array
{
    return [ $this->exportBulkAction(), $this->pauseBulkAction() ];
}
```

### `filteredQuery()`

Protected. The query for the current search, filters, and sort, extension filters included.

**Returns:** `Builder`

**Since:** 1.0.0

```php
$matching = $this->filteredQuery()->count();
```

### `selectionQuery()`

Protected. The query for the selection: every matching row after "select all matching", otherwise the selected keys.

**Returns:** `Builder`

**Since:** 1.0.0

```php
$this->selectionQuery()->each( fn ( $subscription ) => $this->pause( $subscription ) );
```

### `hasSelection()`

Protected. Whether any row is selected.

**Returns:** `bool`

**Since:** 1.0.0

```php
if ( ! $this->hasSelection() ) {
    return;
}
```

### `bulkActionTokenSubject()`

Protected; override it. What a bulk action's confirmation token is bound to besides the component and the action. Return the other input the action depends on (a merge's target), so a token minted for one value can't confirm the action for another. See [Binding a confirmation to other input](../extending/resource-tables.md#binding-a-confirmation-to-other-input).

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` | The bulk action key. |

**Returns:** `mixed`: a model, a scalar, or `null` (the default).

**Since:** 2.0.0

```php
protected function bulkActionTokenSubject( string $key ): mixed
{
    return 'move' === $key ? 'plan:' . (int) $this->bulkPlan : null;
}
```

### Livewire actions

| Action | Called by |
| --- | --- |
| `sort( $column )` | A sortable column header. Toggles the direction when the column is already sorted. |
| `resetFilters()` | **Clear filters** |
| `selectAllMatchingRows()` | "Select all N matching rows" in the bulk-action bar |
| `clearSelection()` | **Clear selection** |
| `runBulkAction( $key )` | A bulk action button. Asks first when the action has `confirm`. |
| `confirmBulkAction( $token )` | **Confirm**, with the one-time token |
| `cancelBulkAction()` | **Cancel** on the confirmation |
| `exportCsv()` | **Export CSV**: every matching row, up to `tables.export_max_rows` |

**Since:** 1.0.0

The concern's other protected methods (`normalizedFilters()`, `currentSort()`, `perPageValue()`, and the rest) are internal.

## InteractsWithOrderPanel

Shared plumbing for panels on the order detail page. Needs `AuthorizesEcommerce`. Guide: [Order Panels](../extending/order-panels.md#using-the-order-panel-concern).

| Member | Meaning |
| --- | --- |
| `$orderId` | Locked. The order's id. |
| `mount( Order\|int\|string $order )` | Stores the id and authorizes `view` on the order. Alias it (`mount as mountOrderPanel`) to add your own setup. |
| `hydrate()` | Re-authorizes `view` on every request |

### `order()`

Protected. The order, loaded once per request.

**Returns:** `Order`. Throws `ModelNotFoundException` when the order is gone.

**Since:** 1.0.0

```php
$this->authorizeEcommerce( 'update', $this->order() );
```

### `orderChanged()`

Protected. Drops the loaded order and dispatches `OrderPanelRegistry::ORDER_UPDATED_EVENT` with `orderId`, so the page and the other panels re-render.

**Returns:** `void`

**Since:** 1.0.0

```php
app( PointsService::class )->awardFor( $this->order() );

$this->orderChanged();
```

### `actorId()`

Protected. The signed-in user's id for the engine's audit columns.

**Returns:** `int|null`

**Since:** 1.0.0

```php
app( RefundService::class )->issue( $this->order(), $lines, $this->actorId(), $reason );
```

## ListensForAdminBroadcasts

Subscribes a component to the engine's admin broadcasts on `private-ecommerce.admin`, but only when [`AdminBroadcasts::enabled()`](#adminbroadcastsenabled) is true for the user. Otherwise no listener is registered and the component behaves as if Echo were absent. See [Real-Time Updates](../usage/real-time.md).

The concern defines `getListeners()`, merging your `$listeners` property with the broadcast listeners. Don't define `getListeners()` yourself.

| Member | Meaning |
| --- | --- |
| `$liveAnnouncement` | Public string. Set it to what changed, and render it in the polite live region: `@include( 'ecommerce-admin::partials.live-region', [ 'message' => $liveAnnouncement ] )`. |

### `adminBroadcasts()`

Abstract; implement it. The broadcasts the component handles.

**Returns:** `array<string, string>`: broadcast name (an `AdminBroadcasts` constant) => the public method that handles it. The handler receives the broadcast payload.

**Since:** 1.0.0

```php
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminBroadcasts;

protected function adminBroadcasts(): array
{
    return [
        AdminBroadcasts::PAYMENT_SUCCEEDED => 'paymentReceived',
    ];
}

public function paymentReceived(): void
{
    $this->liveAnnouncement = __( 'A new order was paid.' );
}
```

Re-read anything you show from the database. Don't trust the payload beyond deciding whether to look; [`AdminBroadcasts::orderId()`](#adminbroadcastsorderid) reads the order id from it.

## Contracts and base classes

Interfaces you implement and abstract classes you extend: picker sources, command palette providers, product type panels, index-screen queries, and picker components.

## PickerSource

`ArtisanPackUI\EcommerceAdminLivewire\Pickers\PickerSource` is the interface behind a picker type. Register an implementation with [`PickerSourceRegistry`](#pickersourceregistry). Guide: [Pickers](../extending/pickers.md#the-source-interface).

| Constant | Value | Meaning |
| --- | --- | --- |
| `MAX_IDS` | `200` | The most ids `find()` is asked to label at once. **Since:** 2.0.0 |

| Method | Returns | Meaning |
| --- | --- | --- |
| `ability(): string` | `string` | The `{resource}.{action}` ability needed to search the source |
| `search( string $term, int $limit ): array` | `array<int, array{id: int\|string, name: string, description: string\|null}>` | Options matching the term. An empty term returns the first options. |
| `find( array $ids ): array` | Same shape | Options for the selected ids, so chips keep their labels. Cap the list at `MAX_IDS`. |

**Since:** 1.0.0

## EloquentPickerSource

`ArtisanPackUI\EcommerceAdminLivewire\Pickers\EloquentPickerSource` is an abstract `PickerSource` for an Eloquent model. It implements `search()` (escaping the term) and `find()` (capped at `MAX_IDS`); you implement `ability()` and the three methods below. Guide: [Add a source](../extending/pickers.md#add-a-source).

| Member | Meaning |
| --- | --- |
| `LIKE_ESCAPE` | `!`, the `LIKE` escape character. Not a backslash, whose quoting differs between databases. |
| `query(): Builder` | Abstract, protected. The base query, with its ordering. |
| `applySearch( Builder $query, string $like ): void` | Abstract, protected. Narrows the query to the escaped `%term%` pattern. |
| `toOption( Model $model ): array` | Abstract, protected. One model as `{ id, name, description }`. |
| `whereLike( Builder $query, string $column, string $like, string $boolean = 'and' ): void` | Protected. Adds `{column} LIKE ? ESCAPE '!'`, joined with `or` when asked, so wildcards in the term match literally. |

**Since:** 1.0.0

## SpotlightProvider

`ArtisanPackUI\EcommerceAdminLivewire\Spotlight\SpotlightProvider` is the interface behind each group of command palette results. Add providers with the `ap.ecommerceAdminLivewire.spotlight.providers` filter. Guide: [Command Palette Providers](../extending/command-palette.md).

| Method | Returns | Meaning |
| --- | --- | --- |
| `ability(): ?string` | `string\|null` | The ability the user needs before the provider runs, or `null` for none |
| `search( string $search, Authenticatable $user, int $limit ): array` | `array<int, array{name: string, description?: string\|null, link: string, icon?: string\|null}>` | Results for the trimmed, non-empty search |
| `label(): string` | `string` | Optional, not part of the interface. The group heading. **Since:** 2.0.0 |

**Since:** 1.0.0

## ProductTypePanel

`ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\ProductTypePanel` is the abstract Livewire component for a product type's tab on the product form. Register subclasses with [`ProductTypePanelRegistry`](#producttypepanelregistry). Guide: [Product Type Panels](../extending/product-type-panels.md).

| Member | Meaning |
| --- | --- |
| `SAVED_EVENT` | `ecommerce-admin-product-saved`, dispatched by the form after it saves |
| `$state` | `#[Modelable]` array bound to the form; bind fields to `state.*` |
| `$panelErrors` | `#[Reactive]` errors from the form, keyed by state path, shown on the panel's fields |
| `$productId` | Locked. The product being edited, or `null` while creating. |
| `$readOnly` | Locked. Whether the form is read-only. |
| `mount()`, `hydrate()` | Authorize `view` on the product, or `create` for a new one |

### `ProductTypePanel::initialState()`

Abstract, static. The panel's state for a product.

| Parameter | Type | Description |
| --- | --- | --- |
| `$product` | `Product\|null` | The product, or `null` while creating. |

**Returns:** `array<string, mixed>`

**Since:** 1.0.0

```php
public static function initialState( ?Product $product ): array
{
    return [ 'interval' => $product?->meta['interval'] ?? 'month' ];
}
```

### `ProductTypePanel::rules()`

Abstract, static. Validation rules for the state, keyed by state path. The form runs them on Save.

| Parameter | Type | Description |
| --- | --- | --- |
| `$state` | `array<string, mixed>` | The state. |
| `$product` | `Product\|null` | The product being edited. |

**Returns:** `array<string, mixed>`

**Since:** 1.0.0

```php
public static function rules( array $state, ?Product $product ): array
{
    return [ 'interval' => [ 'required', 'in:week,month,year' ] ];
}
```

### `ProductTypePanel::save()`

Abstract, static. Stores the validated state for the saved product, inside the form's transaction. Throw to roll the whole save back.

| Parameter | Type | Description |
| --- | --- | --- |
| `$product` | `Product` | The saved product. |
| `$state` | `array<string, mixed>` | The validated state. |

**Returns:** `void`

**Since:** 1.0.0

```php
public static function save( Product $product, array $state ): void
{
    app( SubscriptionPlanService::class )->syncFor( $product, $state );
}
```

### `ProductTypePanel::validationAttributes()`

Static; override it. Readable field names for validation messages, keyed by state path.

**Returns:** `array<string, string>`, empty by default.

**Since:** 1.0.0

```php
public static function validationAttributes(): array
{
    return [ 'interval' => __( 'billing interval' ) ];
}
```

### `ProductTypePanel::label()`

Static; override it. The tab label.

**Returns:** `string`, "Type settings" by default.

**Since:** 1.0.0

```php
public static function label(): string
{
    return __( 'Subscription' );
}
```

### `ProductTypePanel::product()`

Protected. The product being edited, loaded once per request.

**Returns:** `Product|null`, or `null` while creating.

**Since:** 1.0.0

```php
$sku = $this->product()?->sku;
```

### `ProductTypePanel::assertWritable()`

Protected. Refuses a state change on a read-only form with a 403. Call it first in every action that changes `$state`.

**Returns:** `void`

**Since:** 1.0.0

```php
public function addTier(): void
{
    $this->assertWritable();

    $this->state['tiers'][] = [ 'quantity' => 1 ];
}
```

## ResourceQuery

`ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery` builds the query behind an index screen: search, filters, and sort. `WithResourceTable::tableQuery()` returns one. `build()` trusts nothing: unknown filters and sorts are ignored, and the search is trimmed and capped at `MAX_SEARCH_LENGTH` (200) characters.

```php
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionsQuery extends ResourceQuery
{
    public function sorts(): array
    {
        return [
            'reference'  => self::column( Subscription::class, 'reference' ),
            'created_at' => self::column( Subscription::class, 'created_at' ),
        ];
    }

    public function defaultSort(): array
    {
        return [ 'created_at', 'desc' ];
    }

    protected function baseQuery(): Builder
    {
        return Subscription::query()->with( 'customer' );
    }

    protected function applySearch( Builder $query, string $search ): void
    {
        $query->where( function ( Builder $inner ) use ( $search ): void {
            self::orWhereContains( $inner, self::column( Subscription::class, 'reference' ), $search );
            self::orWhereContains( $inner, self::column( Subscription::class, 'customer_email' ), $search );
        } );
    }

    protected function filters(): array
    {
        return [
            'started' => function ( Builder $query, mixed $value ): void {
                $from = self::dayStart( $value['from'] ?? null );
                $to   = self::dayEnd( $value['to'] ?? null );

                if ( null !== $from ) {
                    $query->where( self::column( Subscription::class, 'created_at' ), '>=', $from );
                }

                if ( null !== $to ) {
                    $query->where( self::column( Subscription::class, 'created_at' ), '<=', $to );
                }
            },
        ];
    }
}
```

### What you implement

| Method | Returns | Meaning |
| --- | --- | --- |
| `sorts(): array` | `array<string, string\|Closure>` | Public. Sort key => a column, or a `Closure( Builder $query, string $direction )` that applies the order |
| `defaultSort(): array` | `array{0: string, 1: string}` | Public. The sort key and direction used when none, or an unknown one, is requested |
| `baseQuery(): Builder` | `Builder` | Protected. The query with its eager loads and scoping |
| `applySearch( Builder $query, string $search ): void` | `void` | Protected. Applies the trimmed, non-empty term |
| `filters(): array` | `array<string, Closure>` | Protected. Filter key => `Closure( Builder $query, mixed $value )`, run only for a non-empty value |

All since 1.0.0. The primary key is always added as the last sort, so pagination is stable.

### `ResourceQuery::build()`

The query for a search, filter values, and a sort.

| Parameter | Type | Description |
| --- | --- | --- |
| `$search` | `string` | The search term (default `''`). |
| `$filters` | `array<string, mixed>` | Filter values keyed by filter key. Empty values are skipped. |
| `$sort` | `string` | The sort key (default `''`, the default sort). |
| `$direction` | `string` | `asc` or `desc`. |

**Returns:** `Builder`

**Since:** 1.0.0

```php
$active = ( new SubscriptionsQuery() )->build( '', [ 'started' => [ 'from' => '2026-01-01' ] ], 'created_at', 'desc' )->get();
```

### `ResourceQuery::withFilters()`

Adds filter callbacks the class doesn't define, such as those satellites add through the table filter. A key the class already handles is ignored.

| Parameter | Type | Description |
| --- | --- | --- |
| `$filters` | `array<string, callable>` | Filter key => `callable( Builder $query, mixed $value )`. |

**Returns:** `static`

**Since:** 1.0.0

```php
$query = ( new SubscriptionsQuery() )->withFilters( [ 'vip' => fn ( $query, $value ) => $query->where( 'vip', true ) ] );
```

### `ResourceQuery::canSortBy()`

Whether a sort key is supported.

| Parameter | Type | Description |
| --- | --- | --- |
| `$sort` | `string` | The sort key. |

**Returns:** `bool`

**Since:** 1.0.0

```php
$sortable = ( new SubscriptionsQuery() )->canSortBy( 'reference' );
```

### Static helpers

| Method | Returns | Meaning |
| --- | --- | --- |
| `isEmptyValue( mixed $value ): bool` | `bool` | Public. Whether a filter value counts as not set: `null`, a blank string, or an array of empty values |
| `orWhereContains( Builder $query, string $column, string $term ): void` | `void` | Public. Adds a case-insensitive `OR column contains term` clause with `%`, `_`, and `\` matched literally |
| `column( string $modelClass, string $column ): string` | `string` | Public. A column qualified with its model's table. Engine tables are prefixed, so never hard-code table names. |
| `raw( Builder $query, string $modelClass, string $column ): string` | `string` | Public. `column()`, wrapped by the query's grammar for raw SQL |
| `date( mixed $value ): ?Carbon` | `Carbon\|null` | Protected. A `Y-m-d` value in the store's time zone, or `null` when it isn't a real date |
| `dayStart( mixed $value ): ?Carbon`, `dayEnd( mixed $value ): ?Carbon` | `Carbon\|null` | Protected. The start or end of a store-time-zone day, in the app time zone |
| `minorBound( mixed $major, int $subunit, bool $roundUp ): ?int` | `int\|null` | Protected. A decimal bound from a number-range filter in a currency's minor units, rounded inward. **Since:** 2.0.0 |

All since 1.0.0 unless noted.

## Picker

`ArtisanPackUI\EcommerceAdminLivewire\View\Components\Picker` is the abstract Blade component behind the picker components. Extend it for your own picker type and register it with `Blade::component()`. Guide: [A picker component for your type](../extending/pickers.md#a-picker-component-for-your-type).

| Method | Meaning |
| --- | --- |
| `type(): string` | Abstract. The picker type in `PickerSourceRegistry`. |
| `unavailableMessage(): string` | Override it. The notice shown while no source is registered for the type. |
| `defaultPlaceholder(): string` | Override it. The type's placeholder when the `placeholder` attribute isn't set (default "Search…"). **Since:** 2.0.0 |

The constructor takes the attributes listed in [Pickers](../extending/pickers.md#using-a-picker-in-your-component) and throws `InvalidArgumentException` when `model` isn't a property path. **Since:** 1.0.0

## Support classes

Static helpers under `ArtisanPackUI\EcommerceAdminLivewire\Support\`. Every class here is `final`; call the methods statically.

## Authorization

Checks `{resource}.{action}` abilities through the engine's authorizer, which checks the `ecommerce.{resource}.{action}` gate, falls back to the `ecommerce.admin` gate, then applies the `ap.ecommerce.abilities.{resource}.{action}` filter. The default is deny. See [Authorization](../authorization.md).

### `Authorization::allows()`

Whether the user holds the ability.

| Parameter | Type | Description |
| --- | --- | --- |
| `$user` | `Authenticatable\|null` | The user to check. |
| `$ability` | `string` | A `{resource}.{action}` ability, such as `product.viewAny`. |
| `$subject` | `mixed` | The model the ability applies to, when there is one (default `null`). |

**Returns:** `bool`. Throws `InvalidArgumentException` when the ability isn't `{resource}.{action}`.

**Since:** 1.0.0

```php
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;

abort_unless( Authorization::allows( auth()->user(), 'order.update', $order ), 403 );
```

### `Authorization::split()`

Splits an ability into its resource and action.

| Parameter | Type | Description |
| --- | --- | --- |
| `$ability` | `string` | The ability. |

**Returns:** `array{0: string, 1: string}`. Throws `InvalidArgumentException` when the ability isn't `{resource}.{action}`.

**Since:** 1.0.0

```php
[ $resource, $action ] = Authorization::split( 'order.refund' );
```

## AdminNav

The single source of the admin navigation: the sidebar, the `cms-framework` menu, the command palette's "Go to" actions, and the admin access check all read it. Guide: [Navigation](../extending/navigation.md).

| Constant | Value | Meaning |
| --- | --- | --- |
| `ROUTE_PREFIX` | `artisanpack.ecommerce.admin.` | The prefix of every admin route name |
| `TOP` | `top` | The section for entries above every other section |
| `MENU_REGISTRY` | `ArtisanPackUI\Ecommerce\Registries\AdminMenuRegistry` | The engine registry satellites add entries to |

Entries are built once per request.

### `AdminNav::items()`

Every entry: the core entries, then those in the engine's `AdminMenuRegistry`, then the `ap.ecommerceAdminLivewire.nav.items` filter, sorted by section and position. A later entry with the same `key` replaces an earlier one.

**Returns:** `array<int, array{key: string, label: string, icon: string, route: string, parameters: array, position: int, permission: string|null, section: string, badge: string|null}>`

**Since:** 1.0.0

```php
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;

$keys = array_column( AdminNav::items(), 'key' );
```

### `AdminNav::sections()`

The navigation sections, after the `ap.ecommerceAdminLivewire.nav.sections` filter.

**Returns:** `array<string, array{label: string|null, position: int}>`

**Since:** 1.0.0

```php
$sections = AdminNav::sections();
```

### `AdminNav::visibleItems()`

The entries the user may see.

| Parameter | Type | Description |
| --- | --- | --- |
| `$user` | `Authenticatable\|null` | The user. |

**Returns:** `array<int, array<string, mixed>>`

**Since:** 1.0.0

```php
$entries = AdminNav::visibleItems( auth()->user() );
```

### `AdminNav::grouped()`

The visible entries grouped by section, with empty sections dropped.

| Parameter | Type | Description |
| --- | --- | --- |
| `$user` | `Authenticatable\|null` | The user. |

**Returns:** `array<string, array{label: string|null, items: array}>`

**Since:** 1.0.0

```php
foreach ( AdminNav::grouped( auth()->user() ) as $section => $group ) {
    // …
}
```

### `AdminNav::canAccess()`

Whether the user may enter the admin at all: they hold the permission of at least one entry.

| Parameter | Type | Description |
| --- | --- | --- |
| `$user` | `Authenticatable\|null` | The user. |

**Returns:** `bool`

**Since:** 1.0.0

```php
if ( AdminNav::canAccess( $request->user() ) ) {
    // Show the "Store admin" link.
}
```

### `AdminNav::url()`

An entry's URL, or `#` when its route isn't registered.

| Parameter | Type | Description |
| --- | --- | --- |
| `$item` | `array<string, mixed>` | The entry. |

**Returns:** `string`

**Since:** 1.0.0

```php
$href = AdminNav::url( $entry );
```

### `AdminNav::isActive()`

Whether an entry is the current page. Routes nested under the entry's route count, so `products.edit` marks "Products" active.

| Parameter | Type | Description |
| --- | --- | --- |
| `$item` | `array<string, mixed>` | The entry. |

**Returns:** `bool`

**Since:** 1.0.0

```php
$current = AdminNav::isActive( $entry );
```

### `AdminNav::flush()`

Drops the entries built for this request, so the next call rebuilds them, for example after registering an entry mid-request.

**Returns:** `void`

**Since:** 1.0.0

```php
AdminNav::flush();
```

## NavBadges

Counts behind the navigation badges, cached for `TTL` (60) seconds. Guide: [Badges](../extending/navigation.md#badges).

| Constant | Value |
| --- | --- |
| `ORDERS_AWAITING_FULFILLMENT` | `orders-awaiting-fulfillment` |
| `PENDING_REVIEWS` | `pending-reviews` |
| `LOW_STOCK` | `low-stock` |
| `TTL` | `60` |
| `CACHE_PREFIX` | `artisanpack.ecommerce-admin-livewire.nav.badge.` |

### `NavBadges::count()`

The count for a badge key. Keys the core doesn't know go through the `ap.ecommerceAdminLivewire.nav.badge` filter.

| Parameter | Type | Description |
| --- | --- | --- |
| `$badge` | `string` | The badge key. |

**Returns:** `int|null`, or `null` when there is nothing to show.

**Since:** 1.0.0

```php
use ArtisanPackUI\EcommerceAdminLivewire\Support\NavBadges;

$toShip = NavBadges::count( NavBadges::ORDERS_AWAITING_FULFILLMENT );
```

### `NavBadges::describe()`

The accessible description of a badge, such as "7 low-stock items".

| Parameter | Type | Description |
| --- | --- | --- |
| `$badge` | `string` | The badge key. |
| `$count` | `int` | The count. |

**Returns:** `string`

**Since:** 1.0.0

```php
$label = NavBadges::describe( NavBadges::LOW_STOCK, 7 );
```

### `NavBadges::tone()`

A badge's color: `primary` for orders to ship, `warning` for low stock, `neutral` for everything else.

| Parameter | Type | Description |
| --- | --- | --- |
| `$badge` | `string\|null` | The badge key. |

**Returns:** `string`

**Since:** 2.0.0

```php
$color = NavBadges::tone( 'loyalty-pending' ); // neutral
```

### `NavBadges::flush()`

Clears the cached counts. Call it after a change the badges should show at once.

**Returns:** `void`

**Since:** 1.0.0

```php
NavBadges::flush();
```

## DashboardWidgets

The dashboard's widget list. Guide: [Dashboard Widgets](../extending/dashboard-widgets.md).

| Constant | Value |
| --- | --- |
| `FILTER` | `ap.ecommerceAdminLivewire.dashboard.widgets` |
| `KPIS` | `kpis` |
| `SALES` | `sales` |
| `LOW_STOCK` | `low-stock` |
| `RECENT_ORDERS` | `recent-orders` |

### `DashboardWidgets::visible()`

The widgets the user may see, in position order.

| Parameter | Type | Description |
| --- | --- | --- |
| `$user` | `Authenticatable\|null` | The current user. |

**Returns:** `array<int, array{key: string, label: string, view: string|null, component: string|null, permission: string|null, position: int, width: string}>`

**Since:** 1.0.0

```php
use ArtisanPackUI\EcommerceAdminLivewire\Support\DashboardWidgets;

$widgets = DashboardWidgets::visible( auth()->user() );
```

### `DashboardWidgets::all()`

Every widget after the filter, normalized and sorted, whether or not the user may see it. Entries without a `key`, or with neither a `view` nor a `component`, are dropped.

| Parameter | Type | Description |
| --- | --- | --- |
| `$user` | `Authenticatable\|null` | The current user, passed to the filter. |

**Returns:** the same shape as `visible()`.

**Since:** 1.0.0

```php
$keys = array_column( DashboardWidgets::all( auth()->user() ), 'key' );
```

### `DashboardWidgets::core()`

The core widgets, before the filter.

**Returns:** `array<int, array<string, mixed>>`

**Since:** 1.0.0

```php
$core = DashboardWidgets::core();
```

## AdminBroadcasts

The engine's admin broadcasts on `private-ecommerce.admin`. Use it with [`ListensForAdminBroadcasts`](#listensforadminbroadcasts). See [Real-Time Updates](../usage/real-time.md).

| Constant | Value | Meaning |
| --- | --- | --- |
| `CHANNEL` | `ecommerce.admin` | The private channel, without the `private-` prefix |
| `ORDER_STATUS_CHANGED` | `orderStatusChanged` | An order changed system status |
| `PAYMENT_SUCCEEDED` | `paymentSucceeded` | An order's payment succeeded: a new paid order |
| `STOCK_CHANGED` | `stockChanged` | Stock was adjusted |
| `REVIEW_SUBMITTED` | `reviewSubmitted` | A review was submitted |

### `AdminBroadcasts::enabled()`

Whether the user's screens subscribe to the channel: there is a user, `realtime.enabled` and the engine's `graphql.subscriptions` are both on, `rebing/graphql-laravel` is installed, and the user holds `order.viewAny`, `product.viewAny`, and `webhookSubscription.viewAny`.

| Parameter | Type | Description |
| --- | --- | --- |
| `$user` | `Authenticatable\|null` | The current user. |

**Returns:** `bool`

**Since:** 1.0.0

```php
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminBroadcasts;

$live = AdminBroadcasts::enabled( auth()->user() );
```

### `AdminBroadcasts::listener()`

The Livewire listener name for a broadcast, in the leading-dot form Echo needs for the engine's `broadcastAs()` names.

| Parameter | Type | Description |
| --- | --- | --- |
| `$event` | `string` | The broadcast name, such as `orderStatusChanged`. |

**Returns:** `string`, such as `echo-private:ecommerce.admin,.orderStatusChanged`.

**Since:** 1.0.0

```php
$listener = AdminBroadcasts::listener( AdminBroadcasts::STOCK_CHANGED );
```

### `AdminBroadcasts::orderId()`

The order id a broadcast is about. Use it only to decide whether to look, then re-read the order from the database.

| Parameter | Type | Description |
| --- | --- | --- |
| `$payload` | `mixed` | The broadcast payload. |

**Returns:** `int|null`

**Since:** 1.0.0

```php
public function orderBroadcast( mixed $payload = null ): void
{
    if ( $this->orderId !== AdminBroadcasts::orderId( $payload ) ) {
        return;
    }

    $this->liveAnnouncement = __( 'This order was changed elsewhere.' );
}
```

### `AdminBroadcasts::graphQlInstalled()`

Whether `rebing/graphql-laravel` is installed.

**Returns:** `bool`

**Since:** 1.0.0

```php
$canBroadcast = AdminBroadcasts::graphQlInstalled();
```

## KanbanBoards

Links to the kanban satellite's boards. Most of this class serves the admin's own board screens; these members are for your code.

| Constant | Value |
| --- | --- |
| `KANBAN_PACKAGE` | `artisanpack-ui/ecommerce-kanban-livewire` |
| `BOARD_ROUTE` | `artisanpack.ecommerce.kanban.boards.show` |

### `KanbanBoards::boardUrl()`

The "Open board" URL, after the `ap.ecommerceAdminLivewire.kanban.boardUrl` filter.

| Parameter | Type | Description |
| --- | --- | --- |
| `$board` | `KanbanBoard` | The board. |
| `$query` | `array<string, int\|string>` | Extra query parameters, such as `[ 'card' => $orderId ]` to focus an order's card (default `[]`). |

**Returns:** `string|null`, or `null` when the kanban satellite isn't installed.

**Since:** 1.0.0 (`$query`: 2.0.0)

```php
use ArtisanPackUI\EcommerceAdminLivewire\Support\KanbanBoards;

$url = KanbanBoards::boardUrl( $board, [ 'card' => $order->id ] );
```

### `KanbanBoards::kanbanInstalled()`

Whether the kanban satellite is installed and active.

**Returns:** `bool`

**Since:** 1.0.0

```php
if ( KanbanBoards::kanbanInstalled() ) {
    // Offer the board link.
}
```

## StockLevels

Stock helpers for the inventory and product screens. Only `canAdjust()` is meant for your code.

### `StockLevels::canAdjust()`

Whether the user may adjust a product's or variant's stock (`inventory.adjust`). The policy gets the stock row when there is one; a new product or variant has none, so the check runs without a subject.

| Parameter | Type | Description |
| --- | --- | --- |
| `$user` | `Authenticatable\|null` | The user. |
| `$stockable` | `Product\|ProductVariant\|null` | The product or variant, or `null` when new. |

**Returns:** `bool`

**Since:** 2.0.0

```php
use ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels;

$canAdjust = StockLevels::canAdjust( auth()->user(), $variant );
```

## DigitalDisks

Disk and media checks for digital files. Only `viewableMedia()` is meant for your code.

### `DigitalDisks::viewableMedia()`

A validation rule: the media-library id must be one the signed-in user may view, unless it is already saved on the record. Use it on any client-writable media id, so a user can't attach, preview, or deliver a private file by guessing its id. Without the media library, every id passes.

| Parameter | Type | Description |
| --- | --- | --- |
| `$saved` | `array<int, int\|null>` | Media ids already saved on the record (default `[]`). |

**Returns:** `Closure`, a validation rule.

**Since:** 2.0.0

```php
use ArtisanPackUI\EcommerceAdminLivewire\Support\DigitalDisks;

$this->validate( [
    'mediaId' => [ 'nullable', 'integer', DigitalDisks::viewableMedia( [ $this->savedMediaId ] ) ],
] );
```

## ProductMedia

Product image URLs, with or without the media library.

### `ProductMedia::featured()`

A product's featured image: its media item, its `meta.featured_image_url`, or the first gallery image. Eager-load `images` to avoid a query.

| Parameter | Type | Description |
| --- | --- | --- |
| `$product` | `Product` | The product. |
| `$size` | `string` | The image size name (default `thumbnail`). |

**Returns:** `array{url: string, alt: string}|null`

**Since:** 1.0.0

```php
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;

$image = ProductMedia::featured( $product );
```

### `ProductMedia::featuredMany()`

`featured()` for a page of products at once, keyed by product id. Gallery images and media items load in one query each instead of one per row.

| Parameter | Type | Description |
| --- | --- | --- |
| `$products` | `iterable<int, Product>` | The products. |
| `$size` | `string` | The image size name (default `thumbnail`). |

**Returns:** `array<int, array{url: string, alt: string}|null>`

**Since:** 2.0.0

```php
$images = ProductMedia::featuredMany( $rows->items() );
```

### `ProductMedia::mediaUrl()`

The URL of a media-library item.

| Parameter | Type | Description |
| --- | --- | --- |
| `$mediaId` | `int\|null` | The media id. |
| `$size` | `string` | The image size name (default `thumbnail`). |

**Returns:** `string|null`

**Since:** 1.0.0

```php
$url = ProductMedia::mediaUrl( $file->media_id, 'medium' );
```

### `ProductMedia::mediaUrls()`

Media-library URLs for several items at once, keyed by id.

| Parameter | Type | Description |
| --- | --- | --- |
| `$ids` | `array<int, int>` | The media ids. |
| `$size` | `string` | The image size name (default `thumbnail`). |

**Returns:** `array<int, string>`

**Since:** 2.0.0

```php
$urls = ProductMedia::mediaUrls( [ 12, 15, 21 ] );
```

### `ProductMedia::libraryInstalled()`

Whether `artisanpack-ui/media-library` is installed.

**Returns:** `bool`

**Since:** 1.0.0

```php
$canPick = ProductMedia::libraryInstalled();
```

## View helpers for published views

If you published and changed the report or webhook views, these replace the 1.x helpers that were removed (see [Upgrading](../upgrading.md)). Their other methods serve the admin's own views.

| Method | Returns | Since |
| --- | --- | --- |
| `ReportPresenter::plot( array $chart, string $currency ): array` | The geometry of an over-time chart from `ReportPresenter::chart()`: grid, axis labels, a line per series, and a focusable column per category | 2.0.0 |
| `ReportPresenter::bars( array $chart, string $currency ): array` | The rows of a ranked bar chart: `{ name, value, percent }` | 2.0.0 |
| `ReportPresenter::tone( string $key, ?float $change ): ?string` | Whether a KPI's change is `good`, `bad`, or `flat` news, or `null` without a change. Down is good for refunds and discounts. | 2.0.0 |
| `Webhooks::statusColor( string $status ): string` | The design-system badge color of a delivery status | 2.0.0 |
