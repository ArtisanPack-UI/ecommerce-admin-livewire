@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\UserNames;
    use Illuminate\Support\Js;
@endphp
<div>
    <x-artisanpack-card :title="__( 'Edits' )" shadow>
        <div class="flex flex-wrap items-center gap-3">
            @if ( null !== $blockedReason )
                <p class="text-sm opacity-75" role="status" data-edit-blocked>{{ $blockedReason }}</p>
            @elseif ( $fulfillmentStarted )
                <p class="text-sm opacity-75">{{ __( 'Fulfillment has started, so only the addresses can be edited.' ) }}</p>
            @endif

            @if ( $canEdit )
                <x-artisanpack-button size="sm" icon="o-pencil-square" class="ms-auto" wire:click="startEdit" wire:loading.attr="disabled" :label="__( 'Edit order' )" />
            @endif
        </div>

        @error( 'edit' )
            @if ( ! $editing )
                <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>
            @endif
        @enderror

        @if ( $edits->isEmpty() )
            <p class="mt-3 opacity-75">{{ __( 'This order has not been edited.' ) }}</p>
        @else
            <ol class="mt-3 flex flex-col gap-3" aria-label="{{ __( 'Edit history, newest first' ) }}" data-edit-history>
                @foreach ( $edits as $edit )
                    <li wire:key="order-edit-{{ $edit->id }}" class="rounded-box border border-base-300 p-3">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="font-semibold">{{ __( 'Edit #:id', [ 'id' => $edit->id ] ) }}</span>
                            <span class="text-sm opacity-75">
                                {{ UserNames::label( null === $edit->actor_user_id ? null : (int) $edit->actor_user_id, $actors ) }}
                                @if ( null !== $edit->created_at )
                                    &middot; <time datetime="{{ $edit->created_at->toIso8601String() }}">{{ LocalizedDate::format( $edit->created_at ) }}</time>
                                @endif
                            </span>
                            @if ( $loop->first && null !== $rollbackToken )
                                <x-artisanpack-button
                                    variant="ghost"
                                    size="xs"
                                    icon="o-arrow-uturn-left"
                                    class="ms-auto"
                                    wire:click="rollbackEdit( {{ (int) $edit->id }}, {{ Js::from( $rollbackToken ) }} )"
                                    wire:confirm="{{ __( 'Roll back edit #:id?', [ 'id' => $edit->id ] ) }}"
                                    wire:loading.attr="disabled"
                                    :label="__( 'Roll back' )"
                                    :aria-label="__( 'Roll back edit #:id', [ 'id' => $edit->id ] )"
                                />
                            @endif
                        </div>
                        @if ( null !== $edit->reason && '' !== $edit->reason )
                            <p class="mt-1">{{ $edit->reason }}</p>
                        @endif
                        <ul class="mt-1 list-disc ps-5 text-sm">
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

    @if ( null !== $editToken )
        <x-artisanpack-modal wire:model="editing" :title="__( 'Edit order #:number', [ 'number' => $order->order_number ] )" box-class="max-w-4xl" separator>
            <div class="flex flex-col gap-5" data-edit-form>
                @error( 'edit' )
                    <x-artisanpack-alert color="error" icon="o-exclamation-circle" role="alert">{{ $message }}</x-artisanpack-alert>
                @enderror

                @if ( $fulfillmentStarted )
                    <x-artisanpack-alert color="info" icon="o-information-circle" role="status">{{ __( 'Fulfillment has started, so items and the shipping method are locked. Only the addresses can change.' ) }}</x-artisanpack-alert>
                @else
                    <section aria-labelledby="edit-items-heading">
                        <h3 id="edit-items-heading" class="mb-2 font-semibold">{{ __( 'Items' ) }}</h3>
                        @error( 'draftItems' )
                            <p class="text-sm text-error" role="alert">{{ $message }}</p>
                        @enderror
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <caption class="sr-only">{{ __( 'Items on the order' ) }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __( 'Item' ) }}</th>
                                        <th scope="col">{{ __( 'Variant' ) }}</th>
                                        <th scope="col">{{ __( 'Quantity' ) }}</th>
                                        <th scope="col">{{ __( 'Remove' ) }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ( $draftItems as $itemId => $draft )
                                        <tr wire:key="edit-item-{{ $itemId }}" @class( [ 'bg-base-200' => $draft['remove'] ?? false ] )>
                                            <th scope="row" class="font-normal">
                                                {{ $itemNames[ (int) $itemId ] ?? __( 'Item #:id', [ 'id' => $itemId ] ) }}
                                                @if ( $draft['remove'] ?? false )
                                                    <x-artisanpack-badge :value="__( 'Will be removed' )" class="badge-sm ms-1" color="warning" data-will-remove />
                                                @endif
                                            </th>
                                            <td>
                                                @if ( isset( $variantOptions[ (int) $itemId ] ) )
                                                    <x-artisanpack-select
                                                        id="edit-item-{{ $itemId }}-variant"
                                                        :options="$variantOptions[ (int) $itemId ]"
                                                        :aria-label="__( 'Variant of :item', [ 'item' => $itemNames[ (int) $itemId ] ?? $itemId ] )"
                                                        wire:model.live="draftItems.{{ $itemId }}.variant_id"
                                                    />
                                                @else
                                                    <span class="opacity-75">—</span>
                                                @endif
                                            </td>
                                            <td class="w-28">
                                                <x-artisanpack-input
                                                    id="edit-item-{{ $itemId }}-quantity"
                                                    type="number"
                                                    min="1"
                                                    :aria-label="__( 'Quantity of :item', [ 'item' => $itemNames[ (int) $itemId ] ?? $itemId ] )"
                                                    wire:model.live.blur="draftItems.{{ $itemId }}.quantity"
                                                />
                                            </td>
                                            <td>
                                                <x-artisanpack-checkbox
                                                    id="edit-item-{{ $itemId }}-remove"
                                                    :aria-label="__( 'Remove :item', [ 'item' => $itemNames[ (int) $itemId ] ?? $itemId ] )"
                                                    wire:model.live="draftItems.{{ $itemId }}.remove"
                                                />
                                            </td>
                                        </tr>
                                    @endforeach
                                    @foreach ( $newLines as $index => $line )
                                        <tr wire:key="edit-new-item-{{ \ArtisanPackUI\EcommerceAdminLivewire\Support\RowKeys::of( $newItems[ $index ] ?? null, $index ) }}">
                                            @if ( null === $line )
                                                <th scope="row" class="font-normal text-error" colspan="3">{{ __( 'This product is no longer available at a price in :currency.', [ 'currency' => $order->currency ] ) }}</th>
                                            @else
                                                <th scope="row" class="font-normal">
                                                    {{ $line['snapshot']['name'] }}
                                                    <x-artisanpack-badge :value="__( 'New' )" class="badge-sm" color="info" />
                                                </th>
                                                <td>{{ implode( ', ', array_map( static fn ( $label, $value ) => $label . ': ' . $value, array_keys( (array) $line['snapshot']['options'] ), (array) $line['snapshot']['options'] ) ) ?: '—' }}</td>
                                                <td class="tabular-nums">{{ $line['quantity'] }} &times; {{ MoneyFormatter::format( (int) $line['unit_price_amount'], (string) $order->currency ) }}</td>
                                            @endif
                                            <td>
                                                <x-artisanpack-button
                                                    variant="ghost"
                                                    size="xs"
                                                    icon="o-x-mark"
                                                    wire:click="removeNewItem( {{ (int) $index }} )" wire:loading.attr="disabled"
                                                    :label="__( 'Remove' )"
                                                    :aria-label="__( 'Remove :item', [ 'item' => $line['snapshot']['name'] ?? __( 'Item' ) ] )"
                                                />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 grid items-end gap-2 sm:grid-cols-[2fr_1fr_6rem_auto]" data-add-item>
                            <x-artisanpack-ec-product-picker
                                id="edit-add-product"
                                model="addProductId"
                                single
                                live
                                :label="__( 'Add a product' )"
                                :options="$this->optionsForPicker( 'product', 'addProductId' )"
                            />
                            @if ( [] !== $addVariantOptions )
                                <x-artisanpack-select
                                    id="edit-add-variant"
                                    :label="__( 'Variant' )"
                                    :options="$addVariantOptions"
                                    :placeholder="__( 'Choose a variant' )"
                                    placeholder-value=""
                                    wire:model="addVariantId"
                                />
                            @else
                                <div></div>
                            @endif
                            <x-artisanpack-input id="edit-add-quantity" type="number" min="1" :label="__( 'Quantity' )" wire:model="addQuantity" />
                            <x-artisanpack-button size="sm" icon="o-plus" wire:click="addItem" wire:loading.attr="disabled" :label="__( 'Add' )" />
                        </div>
                    </section>

                    <x-artisanpack-select
                        id="edit-shipping-method"
                        :label="__( 'Shipping method' )"
                        :options="$methodOptions"
                        :placeholder="__( 'No shipping method' )"
                        placeholder-value=""
                        wire:model.live="shippingMethod"
                    />
                @endif

                <div class="grid gap-5 lg:grid-cols-2">
                    <x-artisanpack-ec-address-form model="shipping" :legend="__( 'Shipping address' )" live />
                    <x-artisanpack-ec-address-form model="billing" :legend="__( 'Billing address' )" live />
                </div>

                <section aria-labelledby="edit-preview-heading" aria-live="polite">
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 id="edit-preview-heading" class="font-semibold">{{ __( 'Changes' ) }}</h3>
                        <x-artisanpack-button size="sm" variant="outline" icon="o-eye" wire:click="previewEdit" wire:loading.attr="disabled" :label="__( 'Preview changes' )" />
                    </div>

                    @if ( null === $preview )
                        <p class="mt-2 text-sm opacity-75">{{ __( 'Preview the changes to see the new totals before saving.' ) }}</p>
                    @else
                        <ul class="mt-2 list-disc ps-5 text-sm" data-edit-preview>
                            @foreach ( $preview['lines'] as $line )
                                <li wire:key="edit-preview-{{ $loop->index }}">{{ $line }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-2">
                            {{ __( 'Total: :before → :after', [ 'before' => MoneyFormatter::format( $preview['before'], $preview['currency'] ), 'after' => MoneyFormatter::format( $preview['after'], $preview['currency'] ) ] ) }}
                        </p>
                        @if ( null !== $preview['paymentDelta'] )
                            <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" class="mt-2" role="status" data-payment-action>
                                {{ __( 'Payment action required: the total rises by :amount, which the customer has not paid.', [ 'amount' => MoneyFormatter::format( $preview['paymentDelta'], $preview['currency'] ) ] ) }}
                            </x-artisanpack-alert>
                        @elseif ( null !== $preview['refundDelta'] )
                            <x-artisanpack-alert color="info" icon="o-information-circle" class="mt-2" role="status">
                                {{ __( 'The total falls by :amount. Refund it from Refunds once the edit is saved.', [ 'amount' => MoneyFormatter::format( $preview['refundDelta'], $preview['currency'] ) ] ) }}
                            </x-artisanpack-alert>
                        @endif
                    @endif
                </section>

                <x-artisanpack-input id="edit-reason" :label="__( 'Reason' )" maxlength="255" required wire:model="reason" />
            </div>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="$set( 'editing', false )" :label="__( 'Discard' )" />
                <x-artisanpack-button
                    color="primary"
                    wire:click="applyEdit( {{ Js::from( $editToken ) }} )"
                    wire:loading.attr="disabled"
                    :label="__( 'Save changes' )"
                />
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif
</div>
