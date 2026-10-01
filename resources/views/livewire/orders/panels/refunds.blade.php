@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
@endphp
<div>
    <x-artisanpack-card :title="__( 'Refunds' )" shadow>
        <div class="flex flex-wrap items-center gap-3">
            <p data-refundable>
                {{ __( 'Refundable:' ) }}
                <x-artisanpack-ec-money :amount="$refundable" :currency="$order->currency" class="font-semibold" />
            </p>
            @if ( $canRefund )
                <x-artisanpack-button size="sm" icon="o-receipt-refund" class="ms-auto" wire:click="startRefund" wire:loading.attr="disabled" :label="__( 'Issue refund' )" />
            @endif
        </div>

        @if ( null !== $refundProblem )
            <p class="mt-2 text-sm opacity-75" role="status">{{ $refundProblem }}</p>
        @elseif ( $canRefund && ! $supportsPartial )
            <p class="mt-2 text-sm opacity-75" role="status">{{ __( 'This order\'s payment gateway can only refund the full remaining balance, so partial refunds are not available.' ) }}</p>
        @endif

        @if ( $refunds->isEmpty() )
            <p class="mt-3 opacity-75">{{ __( 'No refunds have been issued.' ) }}</p>
        @else
            <div class="mt-3 overflow-x-auto">
                <table class="table table-sm" data-refund-history>
                    <caption class="sr-only">{{ __( 'Refunds for order #:number', [ 'number' => $order->order_number ] ) }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __( 'Date' ) }}</th>
                            <th scope="col" class="text-end">{{ __( 'Amount' ) }}</th>
                            <th scope="col">{{ __( 'Reason' ) }}</th>
                            <th scope="col">{{ __( 'Issued by' ) }}</th>
                            <th scope="col">{{ __( 'Gateway reference' ) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ( $refunds as $refund )
                            <tr wire:key="refund-{{ $refund->id }}">
                                <td>{{ null === $refund->created_at ? '—' : LocalizedDate::format( $refund->created_at ) }}</td>
                                <td class="text-end"><x-artisanpack-ec-money :amount="$refund->amount" :currency="$refund->currency" /></td>
                                <td>
                                    {{ $refund->reason ?: '—' }}
                                    @foreach ( $refund->items as $line )
                                        <span class="block text-xs opacity-75" wire:key="refund-{{ $refund->id }}-line-{{ $line->id }}">
                                            @if ( (int) $line->quantity > 0 )
                                                {{ __( ':item × :quantity', [ 'item' => $itemNames[ (int) $line->order_item_id ] ?? __( 'Item #:id', [ 'id' => $line->order_item_id ] ), 'quantity' => $line->quantity ] ) }}
                                                @if ( $line->restock )
                                                    {{ __( '(restocked)' ) }}
                                                @endif
                                            @else
                                                {{ __( 'Amount only' ) }}
                                            @endif
                                        </span>
                                    @endforeach
                                </td>
                                <td>{{ \ArtisanPackUI\EcommerceAdminLivewire\Support\UserNames::label( null === $refund->issued_by_user_id ? null : (int) $refund->issued_by_user_id, $issuers ) }}</td>
                                <td class="break-all font-mono text-xs">{{ $refund->gateway_reference ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-artisanpack-card>

    @if ( null !== $refundToken )
        <x-artisanpack-modal wire:model="refunding" :title="__( 'Refund order #:number', [ 'number' => $order->order_number ] )" box-class="max-w-3xl" separator>
            <div class="flex flex-col gap-4" data-refund-form>
                <p>
                    {{ __( 'Up to :amount can be refunded.', [ 'amount' => \ArtisanPackUI\Ecommerce\Support\MoneyFormatter::format( $refundable, (string) $order->currency ) ] ) }}
                    {{ __( 'Amounts are in :currency.', [ 'currency' => $order->currency ] ) }}
                </p>

                @error( 'refund' )
                    <x-artisanpack-alert color="error" icon="o-exclamation-circle" role="alert">{{ $message }}</x-artisanpack-alert>
                @enderror

                @if ( $supportsPartial )
                    <x-artisanpack-radio
                        id="refund-mode"
                        :label="__( 'Refund by' )"
                        :options="[ [ 'id' => 'items', 'name' => __( 'Item' ) ], [ 'id' => 'amount', 'name' => __( 'Amount' ) ] ]"
                        inline
                        wire:model.live="mode"
                    />
                @endif

                @if ( 'items' === $mode )
                    @error( 'lines' )
                        <p class="text-sm text-error" role="alert">{{ $message }}</p>
                    @enderror
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <caption class="sr-only">{{ __( 'Items to refund' ) }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __( 'Item' ) }}</th>
                                    <th scope="col">{{ __( 'Quantity' ) }}</th>
                                    <th scope="col">{{ __( 'Amount' ) }}</th>
                                    <th scope="col">{{ __( 'Restock' ) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ( $items as $item )
                                    @php
                                        $remaining = max( 0, (int) $item->quantity - ( $refundedUnits[ (int) $item->id ] ?? 0 ) );
                                    @endphp
                                    <tr wire:key="refund-line-{{ $item->id }}">
                                        <th scope="row" class="font-normal">
                                            {{ $itemNames[ (int) $item->id ] }}
                                            <span class="block text-xs opacity-75">{{ trans_choice( ':count of :total left to refund|:count of :total left to refund', $remaining, [ 'count' => $remaining, 'total' => (int) $item->quantity ] ) }}</span>
                                        </th>
                                        <td class="w-28">
                                            <x-artisanpack-input
                                                id="refund-line-{{ $item->id }}-quantity"
                                                type="number"
                                                min="0"
                                                :max="$remaining"
                                                :aria-label="__( 'Quantity of :item to refund', [ 'item' => $itemNames[ (int) $item->id ] ] )"
                                                :disabled="0 === $remaining"
                                                wire:model.live.blur="lines.{{ $item->id }}.quantity"
                                            />
                                        </td>
                                        <td class="w-44">
                                            <x-artisanpack-ec-money-input
                                                id="refund-line-{{ $item->id }}-amount"
                                                :currency="$order->currency"
                                                :aria-label="__( 'Amount to refund for :item', [ 'item' => $itemNames[ (int) $item->id ] ] )"
                                                wire:model="lines.{{ $item->id }}.amount"
                                            />
                                        </td>
                                        <td>
                                            <x-artisanpack-toggle
                                                id="refund-line-{{ $item->id }}-restock"
                                                :aria-label="__( 'Restock :item', [ 'item' => $itemNames[ (int) $item->id ] ] )"
                                                :disabled="0 === (int) ( $lines[ $item->id ]['quantity'] ?? 0 )"
                                                wire:model="lines.{{ $item->id }}.restock"
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-sm opacity-75">{{ __( 'Leave the quantity at 0 and enter an amount to refund money without returning units.' ) }}</p>
                @else
                    <x-artisanpack-ec-money-input
                        id="refund-amount"
                        :currency="$order->currency"
                        :label="__( 'Amount' )"
                        :hint="$supportsPartial ? __( 'For shipping, a goodwill gesture, or any amount not tied to returned units.' ) : __( 'The full remaining balance.' )"
                        :readonly="! $supportsPartial"
                        wire:model="amount"
                    />
                @endif

                <x-artisanpack-input id="refund-reason" :label="__( 'Reason' )" maxlength="255" wire:model="reason" />
            </div>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="$set( 'refunding', false )" :label="__( 'Cancel' )" />
                <x-artisanpack-button
                    color="primary"
                    wire:click="refund( {{ \Illuminate\Support\Js::from( $refundToken ) }} )"
                    wire:loading.attr="disabled"
                    :label="__( 'Refund' )"
                />
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif
</div>
