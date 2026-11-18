{{--
    Order edits panel (#102): changing an order after it was placed.

    The card lists past edits newest first, each with who, when, why and
    what changed; only the latest can be rolled back, and that confirms in
    a dialog rather than a browser prompt. The edit dialog changes
    quantities, variants, added or removed items, the shipping method and
    the addresses (in an Accordion), then previews the new totals in a
    polite live region and says plainly when the change creates a payment
    to collect or money to refund. Once fulfillment starts, items and
    shipping lock and the dialog says so at the top. Save stays disabled
    until the changes are previewed and a reason is entered.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\RowKeys;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\UserNames;
    use Illuminate\Support\Js;

    $latestEdit = $edits->first();
@endphp
<div>
    <x-artisanpack-card :title="__( 'Edits' )" :classes="[ 'body' => 'ec-stack-sm' ]" data-edits-panel>
        @if ( $canEdit )
            <x-slot:actions>
                <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="pencil" wire:click="startEdit" wire:loading.attr="disabled" wire:target="startEdit" data-start-edit>{{ __( 'Edit order' ) }}</x-artisanpack-button>
            </x-slot:actions>
        @endif

        @if ( null !== $blockedReason )
            <p class="ec-muted" role="status" data-edit-blocked>{{ $blockedReason }}</p>
        @elseif ( $fulfillmentStarted )
            <p class="ec-muted" role="status">{{ __( 'Fulfillment has started, so only the addresses can be edited.' ) }}</p>
        @endif

        @error( 'edit' )
            @if ( ! $editing )
                <x-artisanpack-alert color="error" icon="circle-alert" role="alert">{{ $message }}</x-artisanpack-alert>
            @endif
        @enderror

        @if ( $edits->isEmpty() )
            <p class="ec-muted">{{ __( 'This order has not been edited.' ) }}</p>
        @else
            <ol class="ec-list" aria-label="{{ __( 'Edit history, newest first' ) }}" data-edit-history>
                @foreach ( $edits as $edit )
                    <li wire:key="order-edit-{{ $edit->id }}" class="ec-item">
                        <div class="ec-item-head">
                            <span class="ec-strong">{{ __( 'Edit #:id', [ 'id' => $edit->id ] ) }}</span>
                            <span class="ec-muted">
                                {{ UserNames::label( null === $edit->actor_user_id ? null : (int) $edit->actor_user_id, $actors ) }}
                                @if ( null !== $edit->created_at )
                                    &middot; <time datetime="{{ $edit->created_at->toIso8601String() }}">{{ LocalizedDate::format( $edit->created_at ) }}</time>
                                @endif
                            </span>
                            @if ( $loop->first && null !== $rollbackToken )
                                <span class="ec-push">
                                    <x-artisanpack-button
                                        variant="ghost"
                                        color="neutral"
                                        size="xs"
                                        leading-icon="undo-2"
                                        class="ec-icon-mirror"
                                        wire:click="$set( 'confirmingRollback', true )"
                                        :aria-label="__( 'Roll back edit #:id', [ 'id' => $edit->id ] )"
                                        data-rollback>{{ __( 'Roll back' ) }}</x-artisanpack-button>
                                </span>
                            @endif
                        </div>
                        @if ( filled( $edit->reason ) )
                            <p class="ec-body">{{ $edit->reason }}</p>
                        @endif
                        <ul class="ec-bullets ec-bullets-muted">
                            @forelse ( $editLines[ (int) $edit->id ] ?? [] as $line )
                                <li wire:key="order-edit-{{ $edit->id }}-line-{{ $loop->index }}">{{ $line }}</li>
                            @empty
                                <li>{{ __( 'No changes recorded.' ) }}</li>
                            @endforelse
                        </ul>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-artisanpack-card>

    @if ( $confirmingRollback && null !== $rollbackToken && null !== $latestEdit )
        @php
            $rollbackTitle = __( 'Roll back edit #:id?', [ 'id' => $latestEdit->id ] );
        @endphp
        <x-artisanpack-dialog
            wire:model="confirmingRollback"
            color="warning"
            icon="undo-2"
            :title="$rollbackTitle"
            :description="__( 'The order goes back to how it was before this edit. The roll back is recorded as a new edit, and no money moves.' )"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
            data-rollback-dialog
        >
            <x-slot:footer>
                <x-artisanpack-button variant="outline" color="neutral" wire:click="$set( 'confirmingRollback', false )" data-autofocus>{{ __( 'Keep edit' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    color="warning"
                    leading-icon="undo-2"
                    wire:click="rollbackEdit( {{ (int) $latestEdit->id }}, {{ Js::from( $rollbackToken ) }} )"
                    wire:loading.attr="disabled"
                    wire:target="rollbackEdit">{{ __( 'Roll back' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif

    @if ( null !== $editToken )
        @php
            $editTitle = __( 'Edit order #:number', [ 'number' => $order->order_number ] );
        @endphp
        <x-artisanpack-dialog
            wire:model="editing"
            size="lg"
            class="ec-dialog-xl"
            :title="$editTitle"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided', 'body' => 'ec-stack-lg' ]"
            data-edit-form
        >
            @error( 'edit' )
                <x-artisanpack-alert color="error" icon="circle-alert" role="alert">{{ $message }}</x-artisanpack-alert>
            @enderror

            @if ( $fulfillmentStarted )
                <x-artisanpack-alert color="info" icon="info" role="status" data-edit-locked>{{ __( 'Fulfillment has started, so items and the shipping method are locked. Only the addresses can change.' ) }}</x-artisanpack-alert>
            @else
                <section aria-labelledby="edit-items-heading" class="ec-stack-sm">
                    <h3 id="edit-items-heading" class="ec-subheading">{{ __( 'Items' ) }}</h3>
                    @error( 'draftItems' )
                        <x-artisanpack-alert color="error" icon="circle-alert" role="alert">{{ $message }}</x-artisanpack-alert>
                    @enderror
                    <div class="apui-table ec-table">
                        <div data-scope="table" data-part="scroll" data-density="compact">
                            <table data-scope="table" data-part="table">
                                <caption class="apui-sr-only">{{ __( 'Items on the order' ) }}</caption>
                                <thead>
                                    <tr>
                                        <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Item' ) }}</th>
                                        <th data-scope="table" data-part="head-cell" scope="col" class="ec-col-variant">{{ __( 'Variant' ) }}</th>
                                        <th data-scope="table" data-part="head-cell" scope="col" class="ec-col-qty">{{ __( 'Quantity' ) }}</th>
                                        <th data-scope="table" data-part="head-cell" scope="col" class="ec-col-check">{{ __( 'Remove' ) }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ( $draftItems as $itemId => $draft )
                                        @php
                                            $itemName = $itemNames[ (int) $itemId ] ?? __( 'Item #:id', [ 'id' => $itemId ] );
                                        @endphp
                                        <tr data-scope="table" data-part="row" wire:key="edit-item-{{ $itemId }}" @if ( $draft['remove'] ?? false ) data-removing @endif>
                                            <th data-scope="table" data-part="cell" scope="row">
                                                {{ $itemName }}
                                                @if ( $draft['remove'] ?? false )
                                                    <x-artisanpack-badge size="sm" color="warning" class="ec-badge-after" data-will-remove>{{ __( 'Will be removed' ) }}</x-artisanpack-badge>
                                                @endif
                                            </th>
                                            <td data-scope="table" data-part="cell">
                                                @if ( isset( $variantOptions[ (int) $itemId ] ) )
                                                    <x-artisanpack-select
                                                        id="edit-item-{{ $itemId }}-variant"
                                                        size="sm"
                                                        :options="ControlOptions::from( $variantOptions[ (int) $itemId ] )"
                                                        :aria-label="__( 'Variant of :item', [ 'item' => $itemName ] )"
                                                        wire:model.live="draftItems.{{ $itemId }}.variant_id"
                                                    />
                                                @else
                                                    <span aria-hidden="true" class="ec-cell-muted">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                                @endif
                                            </td>
                                            <td data-scope="table" data-part="cell">
                                                <x-artisanpack-input
                                                    id="edit-item-{{ $itemId }}-quantity"
                                                    type="number"
                                                    size="sm"
                                                    min="1"
                                                    :aria-label="__( 'Quantity of :item', [ 'item' => $itemName ] )"
                                                    wire:model.live.blur="draftItems.{{ $itemId }}.quantity"
                                                />
                                            </td>
                                            <td data-scope="table" data-part="cell">
                                                <x-artisanpack-checkbox
                                                    id="edit-item-{{ $itemId }}-remove"
                                                    :aria-label="__( 'Remove :item', [ 'item' => $itemName ] )"
                                                    wire:model.live="draftItems.{{ $itemId }}.remove"
                                                />
                                            </td>
                                        </tr>
                                    @endforeach
                                    @foreach ( $newLines as $index => $line )
                                        <tr data-scope="table" data-part="row" wire:key="edit-new-item-{{ RowKeys::of( $newItems[ $index ] ?? null, $index ) }}">
                                            @if ( null === $line )
                                                <th data-scope="table" data-part="cell" scope="row" colspan="3" class="ec-text-error">{{ __( 'This product is no longer available at a price in :currency.', [ 'currency' => $order->currency ] ) }}</th>
                                            @else
                                                <th data-scope="table" data-part="cell" scope="row">
                                                    {{ $line['snapshot']['name'] }}
                                                    <x-artisanpack-badge size="sm" color="info" class="ec-badge-after">{{ __( 'New' ) }}</x-artisanpack-badge>
                                                </th>
                                                <td data-scope="table" data-part="cell">
                                                    @php
                                                        $options = implode( ', ', array_map( static fn ( $label, $value ) => $label . ': ' . $value, array_keys( (array) $line['snapshot']['options'] ), (array) $line['snapshot']['options'] ) );
                                                    @endphp
                                                    @if ( '' !== $options )
                                                        {{ $options }}
                                                    @else
                                                        <span aria-hidden="true" class="ec-cell-muted">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                                    @endif
                                                </td>
                                                <td data-scope="table" data-part="cell" class="ec-nowrap">{{ $line['quantity'] }} &times; <bdi dir="ltr" class="ec-money">{{ MoneyFormatter::format( (int) $line['unit_price_amount'], (string) $order->currency ) }}</bdi></td>
                                            @endif
                                            <td data-scope="table" data-part="cell">
                                                <x-artisanpack-button
                                                    variant="ghost"
                                                    color="neutral"
                                                    size="xs"
                                                    icon-only
                                                    leading-icon="x"
                                                    wire:click="removeNewItem( {{ (int) $index }} )" wire:loading.attr="disabled"
                                                    :aria-label="__( 'Remove :item', [ 'item' => $line['snapshot']['name'] ?? __( 'Item' ) ] )" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="ec-add-item" data-add-item>
                        <x-artisanpack-ec-product-picker
                            id="edit-add-product"
                            model="addProductId"
                            single
                            live
                            :label="__( 'Add a product' )"
                            :options="$this->optionsForPicker( 'product', 'addProductId' )"
                        />
                        <x-artisanpack-select
                            id="edit-add-variant"
                            :label="__( 'Variant' )"
                            :options="ControlOptions::from( $addVariantOptions )"
                            :placeholder="__( 'Choose a variant' )"
                            :disabled="[] === $addVariantOptions"
                            wire:model="addVariantId"
                        />
                        <x-artisanpack-input id="edit-add-quantity" type="number" min="1" :label="__( 'Quantity' )" wire:model="addQuantity" />
                        <x-artisanpack-button variant="outline" color="neutral" leading-icon="plus" wire:click="addItem" wire:loading.attr="disabled" wire:target="addItem">{{ __( 'Add' ) }}</x-artisanpack-button>
                    </div>
                </section>

                <div class="ec-field-narrow">
                    <x-artisanpack-select
                        id="edit-shipping-method"
                        :label="__( 'Shipping method' )"
                        :options="ControlOptions::from( $methodOptions )"
                        :placeholder="__( 'No shipping method' )"
                        wire:model.live="shippingMethod"
                    />
                </div>
            @endif

            <x-artisanpack-accordion multiple :heading-level="3" :value="$openAddresses" wire:model="openAddresses" class="ec-address-accordion" data-edit-addresses>
                @foreach ( [ 'shipping' => [ __( 'Shipping address' ), 'map-pin' ], 'billing' => [ __( 'Billing address' ), 'credit-card' ] ] as $key => [ $addressLabel, $addressIcon ] )
                    @php
                        $draftAddress = 'shipping' === $key ? $shipping : $billing;
                        $summary      = 'billing' === $key && $billing === $shipping && [] !== array_filter( $billing )
                            ? __( 'Same as shipping' )
                            : implode( ', ', array_filter( [ $draftAddress['address1'] ?? null, $draftAddress['city'] ?? null ], 'filled' ) );
                    @endphp
                    <x-artisanpack-accordion-item :value="$key" wire:key="edit-address-{{ $key }}">
                        <x-slot:title>
                            <span class="ec-accordion-title">
                                <x-artisanpack-icon :name="$addressIcon" aria-hidden="true" />
                                <span>{{ $addressLabel }}</span>
                                <span class="ec-accordion-summary">{{ '' !== $summary ? $summary : __( 'No address' ) }}</span>
                            </span>
                        </x-slot:title>
                        <x-artisanpack-ec-admin-address-form :model="$key" live :aria-label="$addressLabel" />
                    </x-artisanpack-accordion-item>
                @endforeach
            </x-artisanpack-accordion>

            <section aria-labelledby="edit-preview-heading" aria-live="polite" class="ec-sunken ec-preview" data-edit-preview-region>
                <div class="ec-preview-head">
                    <h3 id="edit-preview-heading" class="ec-subheading">{{ __( 'Changes' ) }}</h3>
                    <x-artisanpack-button variant="outline" color="neutral" size="xs" leading-icon="refresh-cw" wire:click="previewEdit" wire:loading.attr="disabled" wire:target="previewEdit" data-preview-edit>{{ __( 'Preview changes' ) }}</x-artisanpack-button>
                </div>

                @if ( null === $preview )
                    <p class="ec-muted">{{ __( 'Preview the changes to see the new totals before saving.' ) }}</p>
                @else
                    <ul class="ec-bullets" data-edit-preview>
                        @foreach ( $preview['lines'] as $line )
                            <li wire:key="edit-preview-{{ $loop->index }}">{{ $line }}</li>
                        @endforeach
                    </ul>
                    <p class="ec-diff" data-edit-total>
                        <span class="apui-sr-only">{{ __( 'Total: :before → :after', [ 'before' => MoneyFormatter::format( $preview['before'], $preview['currency'] ), 'after' => MoneyFormatter::format( $preview['after'], $preview['currency'] ) ] ) }}</span>
                        <span aria-hidden="true">{{ __( 'Total:' ) }}</span>
                        <bdi dir="ltr" class="ec-money ec-diff-before" aria-hidden="true">{{ MoneyFormatter::format( $preview['before'], $preview['currency'] ) }}</bdi>
                        <x-artisanpack-icon name="chevron-right" class="ec-diff-arrow" aria-hidden="true" />
                        <bdi dir="ltr" class="ec-money ec-strong" aria-hidden="true">{{ MoneyFormatter::format( $preview['after'], $preview['currency'] ) }}</bdi>
                    </p>
                    @if ( null !== $preview['paymentDelta'] )
                        <x-artisanpack-alert color="warning" icon="triangle-alert" role="status" data-payment-action>
                            {{ __( 'Payment action required: the total rises by :amount, which the customer has not paid.', [ 'amount' => MoneyFormatter::format( $preview['paymentDelta'], $preview['currency'] ) ] ) }}
                        </x-artisanpack-alert>
                    @elseif ( null !== $preview['refundDelta'] )
                        <x-artisanpack-alert color="info" icon="info" role="status" data-refund-due>
                            {{ __( 'The total falls by :amount. Refund it from Refunds once the edit is saved.', [ 'amount' => MoneyFormatter::format( $preview['refundDelta'], $preview['currency'] ) ] ) }}
                        </x-artisanpack-alert>
                    @endif
                @endif
            </section>

            <x-artisanpack-input id="edit-reason" :label="__( 'Reason' )" maxlength="255" required wire:model="reason" />

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'editing', false )">{{ __( 'Discard' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    x-bind:disabled="{{ null === $preview ? 'true' : '! ( $wire.reason || \'\' ).trim()' }}"
                    wire:click="applyEdit( {{ Js::from( $editToken ) }} )"
                    wire:loading.attr="disabled"
                    wire:target="applyEdit"
                    data-save-edit>{{ __( 'Save changes' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
