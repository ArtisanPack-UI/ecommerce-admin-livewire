{{--
    Refunds panel (#101): what can still be refunded, what has been, and
    the way to issue a refund.

    The refundable balance leads the card. History is a Table flush in the
    card with the status, reason, the lines refunded (and whether they were
    restocked), who issued it and the gateway reference; pending and failed
    refunds stay with their badge, and a failed refund shows the gateway's
    error under its reason. The refund dialog works by item or by amount,
    each per-line control named for its item, and its primary button names
    the running total ("Refund $96.00"), disabled at zero. A gateway without
    partial refunds hides "Refund by" and offers only the full balance, with
    the reason as a muted note.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Models\Refund;
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\UserNames;
    use Illuminate\Support\Js;

    $currency    = (string) $order->currency;
    $fullOnlyMsg = __( 'This order\'s payment gateway can only refund the full remaining balance, so partial refunds are not available.' );
@endphp
<div>
    <x-artisanpack-card :title="__( 'Refunds' )" :class="$refunds->isNotEmpty() ? 'ec-card-flush' : ''" data-refunds-panel>
        <x-slot:description>
            <span data-refundable>{{ __( 'Refundable:' ) }} <x-artisanpack-ec-admin-money :amount="$refundable" :currency="$currency" class="ec-figure-inline" /></span>
        </x-slot:description>

        @if ( $canRefund )
            <x-slot:actions>
                <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="undo-2" wire:click="startRefund" wire:loading.attr="disabled" wire:target="startRefund" data-start-refund>{{ __( 'Refund' ) }}</x-artisanpack-button>
            </x-slot:actions>
        @endif

        <div class="ec-stack-sm">
            @if ( null !== $refundProblem )
                <p class="ec-muted ec-inset" role="status" data-refund-problem>{{ $refundProblem }}</p>
            @elseif ( $canRefund && ! $supportsPartial )
                <p class="ec-muted ec-inset" role="status" data-full-only>{{ $fullOnlyMsg }}</p>
            @endif

            @if ( $refunds->isEmpty() )
                <p class="ec-muted">{{ __( 'No refunds have been issued.' ) }}</p>
            @else
                <div data-scope="table" data-part="scroll" class="ec-table">
                    <table data-scope="table" data-part="table" data-refund-history>
                        <caption class="apui-sr-only">{{ __( 'Refunds for order #:number', [ 'number' => $order->order_number ] ) }}</caption>
                        <thead>
                            <tr>
                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Date' ) }}</th>
                                <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Amount' ) }}</th>
                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Status' ) }}</th>
                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Reason' ) }}</th>
                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Issued by' ) }}</th>
                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Gateway reference' ) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ( $refunds as $refund )
                                <tr data-scope="table" data-part="row" class="ec-row-top" wire:key="refund-{{ $refund->id }}">
                                    <td data-scope="table" data-part="cell" class="ec-cell-muted ec-nowrap">
                                        @if ( null !== $refund->created_at )
                                            <time datetime="{{ $refund->created_at->toIso8601String() }}">{{ LocalizedDate::format( $refund->created_at ) }}</time>
                                        @else
                                            <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                        @endif
                                    </td>
                                    <td data-scope="table" data-part="cell" data-numeric><x-artisanpack-ec-admin-money :amount="$refund->amount" :currency="$refund->currency" /></td>
                                    <td data-scope="table" data-part="cell" data-refund-status="{{ $refund->status }}">
                                        @switch ( $refund->status )
                                            @case( Refund::STATUS_PENDING )
                                                <x-artisanpack-badge color="warning" dot>{{ __( 'Pending' ) }}</x-artisanpack-badge>
                                                @break
                                            @case( Refund::STATUS_FAILED )
                                                <x-artisanpack-badge color="error" dot>{{ __( 'Failed' ) }}</x-artisanpack-badge>
                                                @break
                                            @default
                                                <x-artisanpack-badge color="success" dot>{{ __( 'Refunded' ) }}</x-artisanpack-badge>
                                        @endswitch
                                    </td>
                                    <td data-scope="table" data-part="cell" class="ec-cell-wrap">
                                        @if ( filled( $refund->reason ) )
                                            <span>{{ $refund->reason }}</span>
                                        @else
                                            <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                        @endif
                                        @foreach ( $refund->items as $line )
                                            <span class="ec-cell-secondary" wire:key="refund-{{ $refund->id }}-line-{{ $line->id }}">
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
                                        @if ( Refund::STATUS_FAILED === $refund->status && isset( $failures[ (int) $refund->id ] ) )
                                            <span class="ec-cell-secondary ec-text-error" data-refund-error>{{ __( 'Gateway: :error', [ 'error' => $failures[ (int) $refund->id ] ] ) }}</span>
                                        @endif
                                    </td>
                                    <td data-scope="table" data-part="cell">{{ UserNames::label( null === $refund->issued_by_user_id ? null : (int) $refund->issued_by_user_id, $issuers ) }}</td>
                                    <td data-scope="table" data-part="cell">
                                        @if ( filled( $refund->gateway_reference ) )
                                            <bdi dir="ltr" class="ec-mono ec-break">{{ $refund->gateway_reference }}</bdi>
                                        @else
                                            <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-artisanpack-card>

    @if ( null !== $refundToken )
        @php
            $refundTitle       = __( 'Refund order #:number', [ 'number' => $order->order_number ] );
            $refundDescription = __( 'Up to :amount can be refunded.', [ 'amount' => MoneyFormatter::format( $refundable, $currency ) ] ) . ' ' . __( 'Amounts are in :currency.', [ 'currency' => $currency ] );
        @endphp
        <x-artisanpack-dialog
            wire:model="refunding"
            size="lg"
            class="ec-dialog-wide"
            :title="$refundTitle"
            :description="$refundDescription"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided', 'body' => 'ec-stack' ]"
            data-refund-form
        >
            @error( 'refund' )
                <x-artisanpack-alert color="error" icon="circle-alert" data-refund-error-alert>{{ $message }}</x-artisanpack-alert>
            @enderror

            @if ( $supportsPartial )
                <x-artisanpack-radio-group
                    id="refund-mode"
                    :label="__( 'Refund by' )"
                    :options="ControlOptions::from( [ [ 'id' => 'items', 'name' => __( 'Item' ) ], [ 'id' => 'amount', 'name' => __( 'Amount' ) ] ] )"
                    variant="segmented"
                    orientation="horizontal"
                    class="ec-inline-field"
                    wire:model.live="mode"
                />
            @else
                <p class="ec-muted" data-full-only-note>{{ $fullOnlyMsg }}</p>
            @endif

            @if ( 'items' === $mode )
                @error( 'lines' )
                    <x-artisanpack-alert color="error" icon="circle-alert">{{ $message }}</x-artisanpack-alert>
                @enderror
                <div class="apui-table ec-table">
                    <div data-scope="table" data-part="scroll" data-density="compact">
                        <table data-scope="table" data-part="table">
                            <caption class="apui-sr-only">{{ __( 'Items to refund' ) }}</caption>
                            <thead>
                                <tr>
                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Item' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col" class="ec-col-qty">{{ __( 'Quantity' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col" class="ec-col-money">{{ __( 'Amount' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col" class="ec-col-check">{{ __( 'Restock' ) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ( $items as $item )
                                    @php
                                        $remaining = max( 0, (int) $item->quantity - ( $refundedUnits[ (int) $item->id ] ?? 0 ) );
                                        $itemName  = $itemNames[ (int) $item->id ];
                                    @endphp
                                    <tr data-scope="table" data-part="row" wire:key="refund-line-{{ $item->id }}">
                                        <th data-scope="table" data-part="cell" scope="row" class="ec-cell-wrap">
                                            {{ $itemName }}
                                            <span class="ec-cell-secondary">{{ trans_choice( ':count of :total left to refund|:count of :total left to refund', $remaining, [ 'count' => $remaining, 'total' => (int) $item->quantity ] ) }}</span>
                                        </th>
                                        <td data-scope="table" data-part="cell">
                                            <x-artisanpack-input
                                                id="refund-line-{{ $item->id }}-quantity"
                                                type="number"
                                                size="sm"
                                                min="0"
                                                :max="$remaining"
                                                :aria-label="__( 'Quantity of :item to refund', [ 'item' => $itemName ] )"
                                                :disabled="0 === $remaining"
                                                wire:model.live.blur="lines.{{ $item->id }}.quantity"
                                            />
                                        </td>
                                        <td data-scope="table" data-part="cell">
                                            <x-artisanpack-ec-money-input
                                                id="refund-line-{{ $item->id }}-amount"
                                                :currency="$currency"
                                                :aria-label="__( 'Amount to refund for :item', [ 'item' => $itemName ] )"
                                                wire:model.live.blur="lines.{{ $item->id }}.amount"
                                            />
                                        </td>
                                        <td data-scope="table" data-part="cell">
                                            <x-artisanpack-checkbox
                                                id="refund-line-{{ $item->id }}-restock"
                                                :aria-label="__( 'Restock :item', [ 'item' => $itemName ] )"
                                                :disabled="0 === (int) ( $lines[ $item->id ]['quantity'] ?? 0 )"
                                                wire:model="lines.{{ $item->id }}.restock"
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="ec-muted">{{ __( 'Leave the quantity at 0 and enter an amount to refund money without returning units.' ) }}</p>
            @else
                <div class="ec-field-narrow">
                    <x-artisanpack-ec-money-input
                        id="refund-amount"
                        :currency="$currency"
                        :label="__( 'Amount' )"
                        :hint="$supportsPartial ? __( 'For shipping, a goodwill gesture, or any amount not tied to returned units.' ) : __( 'The full remaining balance.' )"
                        :readonly="! $supportsPartial"
                        wire:model.live.blur="amount"
                    />
                </div>
                @if ( $supportsPartial )
                    <div>
                        <x-artisanpack-button variant="link" size="sm" wire:click="$set( 'amount', {{ (int) $refundable }} )" data-use-full-balance>{{ __( 'Use the full remaining balance (:amount)', [ 'amount' => MoneyFormatter::format( $refundable, $currency ) ] ) }}</x-artisanpack-button>
                    </div>
                @endif
            @endif

            <x-artisanpack-input id="refund-reason" :label="__( 'Reason (optional)' )" maxlength="255" wire:model="reason" />

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'refunding', false )">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    wire:click="refund( {{ Js::from( $refundToken ) }}, {{ (int) $refundTotal }} )"
                    wire:loading.attr="disabled"
                    wire:target="refund"
                    data-submit-refund>{{ __( 'Refund :amount', [ 'amount' => MoneyFormatter::format( $refundTotal, $currency ) ] ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
