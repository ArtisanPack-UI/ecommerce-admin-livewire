---
title: Blade Components
menu_order: 120
---

# Blade Components

The admin composes a few store-specific components from the design-system components in `livewire-ui-components`. They are registered with the `artisanpack-ec-` prefix, so your panels and tabs can match the admin's look and behavior.

## Display

| Component | Attributes | Renders |
| --- | --- | --- |
| `<x-artisanpack-ec-money>` | `amount` (int, minor units), `currency`, `locale`, `compare-at`, `show-code`, `neutral` | An amount formatted with the engine's `MoneyFormatter`, in tabular figures and kept left to right. A negative amount gets a true minus and the error tone; a missing one reads "No amount". `compare-at` is struck through and read as "Was"; `show-code` adds the currency code; `neutral` keeps the minus but drops the error tone, for amounts that are negative by nature (a refund line). |
| `<x-artisanpack-ec-status-badge>` | `type` (`system`, `payment`, `fulfillment`, `review`, `shipment`), `value`, `substatus`, `size` (`sm`, `md`, `lg`) | A soft badge with a leading dot, a translated label, and a visually hidden type prefix. Color is never the only signal. |
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
| `<x-artisanpack-ec-address-form>` | `model`, `legend`, `description`, `live`, `with-name` | An address fieldset bound to `{model}.{field}`, with an optional `description` line under the legend. Country comes first and relabels region and postal code (US: State and ZIP code, UK: County and Postcode). |
| `<x-artisanpack-ec-config-form>` | `registry`, `entry`, `model` | A form from a config schema. See [Config Forms](config-forms.md). |
| `<x-artisanpack-ec-product-picker>` and the `variant`, `customer`, `category`, `tag` pickers | `model`, `options`, `label`, `hint`, `single`, `live`, `id`, `placeholder` | Searchable pickers. See [Pickers](pickers.md). |
| `<x-artisanpack-ec-rule-builder>` | `builder`, `prefix` (default `rule`) | The ordered rule lists of a component that uses `WithRuleBuilder`. See [Rule builder](#rule-builder). |

## Rule builder

`<x-artisanpack-ec-rule-builder>` renders the lists of a component that uses the `WithRuleBuilder` concern: each list with its rows, its "add" picker, and the plain-language summary above them. The promotion form and the kanban routing rules use it.

| Attribute | Type | Meaning |
| --- | --- | --- |
| `builder` | `array` | What the concern's `ruleBuilderViewData()` returns. Required. |
| `prefix` | `string` | Prefix for the DOM ids of the lists and row settings (default `rule`). Set a distinct one when two builders share a page. |

```blade
<x-artisanpack-ec-rule-builder :builder="$ruleBuilder" />
```

```php
use Illuminate\Contracts\View\View;

public function render(): View
{
    return view( 'loyalty::admin.rules', [ 'ruleBuilder' => $this->ruleBuilderViewData() ] );
}
```

Rows reorder by drag when the page loads `@artisanpack-ui/livewire-drag-and-drop`, and always by the **Move up** and **Move down** buttons. The component also needs `WithConfigForms` and `WithPickers` on the host for the row forms. See [WithRuleBuilder](../reference/php-api.md#withrulebuilder).

## Resource tables

The index screens are built from two components and a partial that ties them to the `WithResourceTable` concern. Use the partial in your own index screen; reach for the components only when you need a different layout around the grid.

### The resource-table partial

`ecommerce-admin::partials.resource-table` renders the whole table section: the toolbar (search, filters toggle, rows per page, CSV export), the filter panel, the bulk-action bar, the inline confirmation for bulk actions that ask first, the grid, the pager, and the empty states. Pass it the data from `resourceTableData()` plus your screen's own variables:

| Variable | Required | Meaning |
| --- | --- | --- |
| `$emptyIcon`, `$emptyTitle`, `$emptyDescription` | Yes | The empty state shown before there are any rows |
| `$searchHint` | No | What the search matches, shown as the search placeholder and read out to screen readers |
| `$emptyActions` | No | A view rendered as the empty state's actions (a **Create** button) |
| `$bulkControls` | No | A view rendered inside the bulk-action bar, for controls an action needs (a target status) |
| `$bulkInlineActions` | No | Keys of bulk actions that `$bulkControls` renders itself, left out of the bar's buttons |
| `$rowLabel` | No | A `Closure( Model $row ): string` naming a row for its checkbox ("Select order #1048"). Defaults to the row key. |
| `$cellContext` | No | An array passed to every cell view as `$context`, for screen state such as a route name |

```blade
<div>
    <x-artisanpack-page-header :title="__( 'Subscriptions' )" :level="1" />

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'refresh-cw',
        'emptyTitle'       => __( 'No subscriptions yet' ),
        'emptyDescription' => __( 'Subscriptions appear here when a customer subscribes.' ),
        'searchHint'       => __( 'Search by reference or email' ),
        'rowLabel'         => static fn ( $subscription ): string => $subscription->reference,
    ] )
</div>
```

```php
use Illuminate\Contracts\View\View;

public function render(): View
{
    return view( 'subscriptions::admin.index', $this->resourceTableData() );
}
```

### `<x-artisanpack-ec-resource-table>`

The grid: a design-system table with a visually hidden caption, sortable headers, and row checkboxes. Sorting, filtering, selection, and paging all happen on the server, so a selection survives paging and "select all matching" reaches rows that aren't on the page.

| Attribute | Type | Meaning |
| --- | --- | --- |
| `columns` | `array` | The normalized columns: `resourceTableData()['tableColumns']`. Required. |
| `rows` | `LengthAwarePaginator` | The current page: `tableRows`. Required. |
| `caption` | `string` | What the table lists, read by screen readers: `tableCaption`. Required. |
| `sort` | `string` | The sort key in effect: `tableSort` |
| `direction` | `string` | `asc` (default) or `desc`: `tableDirection` |
| `selectable` | `bool` | Whether rows have checkboxes (default `false`): `tableSelectable` |
| `row-label` | `Closure\|null` | Names a row for its checkbox |
| `context` | `array` | Passed to cell views as `$context` |
| `selected` | `array` | The selected row keys, for the checkboxes' first render |

A sortable header is a button inside `th[aria-sort]` that calls `sort( key )` on the component. Row checkboxes bind to `selected` with `wire:model.live`. A column with a `view` renders that view with `$row`, `$column`, and `$context`; any other column shows its `value` callable's result or the row attribute named by `key`, with dates in the store's time zone and the app locale.

```blade
<x-artisanpack-ec-resource-table
    :columns="$tableColumns"
    :rows="$tableRows"
    :caption="$tableCaption"
    :sort="$tableSort"
    :direction="$tableDirection"
    :selectable="$tableSelectable"
    :selected="$selected"
/>
```

**Since:** 1.0.0 (`selected`: 2.0.0)

### `<x-artisanpack-ec-bulk-action-bar>`

The strip shown above the grid while rows are selected: the selection count, the "select all matching" prompt, one button per bulk action, and **Clear selection**. Actions with a `confirm` message are destructive, so their buttons use the error color.

| Attribute | Type | Meaning |
| --- | --- | --- |
| `count` | `int` | How many rows are selected: `tableSelectionCount`. Required. |
| `actions` | `array` | The bulk actions the user may run, keyed by action key: `tableBulkActions`. Required. |
| `total` | `int` | How many rows match the search and filters (default `0`) |
| `page-count` | `int` | How many rows are on this page (default `0`) |
| `all-matching` | `bool` | Whether every matching row is selected (default `false`): `tableAllMatching` |

The bar offers "Select all N matching rows" once the whole page is selected and more rows match. Its buttons call `runBulkAction( key )`, `selectAllMatchingRows()`, and `clearSelection()` on the component. Anything in the slot renders before the action buttons.

```blade
@if ( $tableSelectable && $tableSelectionCount > 0 )
    <x-artisanpack-ec-bulk-action-bar
        :count="$tableSelectionCount"
        :actions="$tableBulkActions"
        :total="$tableRows->total()"
        :page-count="$tableRows->count()"
        :all-matching="$tableAllMatching"
    >
        <x-artisanpack-select :label="__( 'Plan' )" wire:model="bulkPlan" :options="$plans" />
    </x-artisanpack-ec-bulk-action-bar>
@endif
```

**Since:** 1.0.0

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
| `SendsToasts` | `toastSuccess()`, `toastWarning()`, and `toastError()`, kept server-side |
| `WithActionToken` | `actionToken()` and `withActionToken()`, so a destructive action runs once per click |
| `WithPickers` | `searchPicker()` and `optionsForPicker()` for the picker components |
| `WithConfigForms` | `configFormState()`, `validateConfigForm()`, and repeater row actions for `<x-artisanpack-ec-config-form>` |
| `WithRuleBuilder` | The ordered rule lists of `<x-artisanpack-ec-rule-builder>` (promotion conditions and discounts, kanban routing). Describe each list in `ruleBuilderLists()` with `registry`, `label`, `add`, `empty`, and an optional `description` shown under the list's heading (since 2.0.0). |
| `WithResourceTable` | Search, filters, sort, paging, selection, bulk actions, and CSV export for the resource-table partial |
| `InteractsWithOrderPanel` | Mounting, authorizing, and refreshing an order detail panel. See [Order Panels](order-panels.md#using-the-order-panel-concern). |
| `ListensForAdminBroadcasts` | Real-time listeners on the engine's admin channel, registered only when real-time updates are on |

Every method, and what each concern needs alongside it, is in the [PHP API reference](../reference/php-api.md#livewire-concerns).
