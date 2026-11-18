{{--
    Order status panel (#99): the first card in the order's side column.

    Shows where the order stands as a group of status badges named "Current
    status", then lets staff move it: a target status with any warnings it
    triggers, an optional reason and an explicit "Update status" button,
    then the sub-status on its own button. Sub-statuses that belong to
    another order status are listed but disabled, and the hint says why.
    Cancelling sits apart below a divider and opens a dialog that spells out
    what will happen to stock, payment and money owed; the money still owed
    is the one bold line, the dialog opens on Reason, and "Cancel order"
    stays disabled until a reason is entered.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\StatusPresenter;
    use Illuminate\Support\Js;
@endphp
<div>
    <x-artisanpack-card :title="__( 'Status' )" :classes="[ 'body' => 'ec-stack' ]" data-status-panel>
        <div class="ec-badge-row" role="group" aria-label="{{ __( 'Current status' ) }}">
            <x-artisanpack-ec-status-badge type="system" :value="$order->system_status" />
            @if ( null !== $order->substatus )
                <x-artisanpack-ec-status-badge :substatus="$order->substatus" />
            @endif
            <x-artisanpack-ec-status-badge type="payment" :value="$order->payment_status" />
            <x-artisanpack-ec-status-badge type="fulfillment" :value="$order->fulfillment_status" />
        </div>

        @if ( null !== $substatusWarning || [] !== $boardWarnings )
            <x-artisanpack-alert color="warning" icon="triangle-alert" role="status" data-current-warnings>
                <ul class="ec-alert-list">
                    @if ( null !== $substatusWarning )
                        <li>{{ $substatusWarning }}</li>
                    @endif
                    @foreach ( $boardWarnings as $warning )
                        <li wire:key="status-board-warning-{{ $loop->index }}">{{ $warning }}</li>
                    @endforeach
                </ul>
            </x-artisanpack-alert>
        @endif

        @if ( $canUpdate )
            @if ( [] === $statusOptions )
                <p class="ec-muted" data-terminal-status>{{ __( 'This order is :status and cannot move to another status.', [ 'status' => StatusPresenter::present( 'system', $order->system_status )['label'] ] ) }}</p>
            @else
                <form wire:submit="changeStatus" class="ec-form-stack" data-status-form>
                    <x-artisanpack-select
                        id="order-target-status"
                        :label="__( 'Move to' )"
                        :options="ControlOptions::from( $statusOptions )"
                        :placeholder="__( 'Choose a status' )"
                        wire:model.live="targetStatus"
                    />

                    @if ( [] !== $targetWarnings )
                        <x-artisanpack-alert color="warning" icon="triangle-alert" role="status" data-target-warnings>
                            <ul class="ec-alert-list">
                                @foreach ( $targetWarnings as $warning )
                                    <li wire:key="status-target-warning-{{ $loop->index }}">{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </x-artisanpack-alert>
                    @endif

                    <x-artisanpack-input id="order-status-reason" :label="__( 'Reason (optional)' )" maxlength="500" wire:model="statusReason" />

                    <div>
                        <x-artisanpack-button type="submit" size="sm" :disabled="'' === $targetStatus" wire:loading.attr="disabled" wire:target="changeStatus">{{ __( 'Update status' ) }}</x-artisanpack-button>
                    </div>
                </form>
            @endif

            <x-artisanpack-divider decorative />

            <form wire:submit="changeSubstatus" class="ec-form-stack" data-substatus-form>
                <x-artisanpack-select
                    id="order-substatus"
                    :label="__( 'Sub-status' )"
                    :options="ControlOptions::from( $substatusOptions )"
                    :placeholder="__( 'Choose a sub-status' )"
                    :hint="$hasOtherSubstatus ? __( 'Greyed-out sub-statuses belong to another order status and become available once the order moves there.' ) : null"
                    wire:model="substatusId"
                />
                <div>
                    <x-artisanpack-button type="submit" variant="outline" color="neutral" size="sm" wire:loading.attr="disabled" wire:target="changeSubstatus">{{ __( 'Save sub-status' ) }}</x-artisanpack-button>
                </div>
            </form>
        @else
            <p class="ec-muted" data-status-read-only>{{ __( 'You can view this order’s status but not change it.' ) }}</p>
        @endif

        @if ( $canCancel )
            <x-artisanpack-divider decorative />

            <div>
                <x-artisanpack-button
                    variant="outline"
                    color="error"
                    size="sm"
                    leading-icon="circle-x"
                    wire:click="$set( 'confirmingCancel', true )"
                    data-cancel-order>{{ __( 'Cancel order' ) }}</x-artisanpack-button>
            </div>
        @endif
    </x-artisanpack-card>

    @if ( null !== $cancellation )
        @php
            $cancelTitle = __( 'Cancel order #:number?', [ 'number' => $order->order_number ] );
        @endphp
        <x-artisanpack-dialog
            wire:model="confirmingCancel"
            color="error"
            icon="circle-x"
            :title="$cancelTitle"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided', 'body' => 'ec-stack' ]"
            data-cancel-form
        >
            <div class="ec-what-happens">
                <h3 class="ec-subheading">{{ __( 'What happens' ) }}</h3>
                <ul class="ec-bullets" data-cancel-summary>
                    @if ( [] === $cancellation['reservations'] )
                        <li>{{ __( 'No stock is held for this order.' ) }}</li>
                    @else
                        <li>
                            {{ trans_choice( ':count reserved unit goes back on sale:|:count reserved units go back on sale:', $cancellation['units'], [ 'count' => $cancellation['units'] ] ) }}
                            <ul class="ec-bullets ec-bullets-nested">
                                @foreach ( $cancellation['reservations'] as $reservation )
                                    <li wire:key="cancel-reservation-{{ $loop->index }}">{{ __( ':item × :quantity', [ 'item' => $reservation['label'], 'quantity' => $reservation['quantity'] ] ) }}</li>
                                @endforeach
                            </ul>
                        </li>
                    @endif
                    @if ( $cancellation['voidsPayment'] )
                        <li>{{ __( 'The pending payment is voided at the gateway.' ) }}</li>
                    @endif
                    @if ( null !== $cancellation['refundOwed'] )
                        <li class="ec-strong" data-refund-owed>{{ __( 'The customer has paid. Cancelling does not refund: :amount is still owed and must be refunded separately.', [ 'amount' => $cancellation['refundOwed'] ] ) }}</li>
                    @else
                        <li>{{ __( 'No refund is owed.' ) }}</li>
                    @endif
                </ul>
            </div>

            <x-artisanpack-textarea
                id="order-cancel-reason"
                :label="__( 'Reason' )"
                :placeholder="__( 'Why is this order being cancelled?' )"
                rows="3"
                maxlength="500"
                required
                data-autofocus
                wire:model="cancelReason"
            />

            <x-slot:footer>
                <x-artisanpack-button variant="outline" color="neutral" wire:click="$set( 'confirmingCancel', false )">{{ __( 'Keep order' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    color="error"
                    x-bind:disabled="! ( $wire.cancelReason || '' ).trim()"
                    wire:click="cancelOrder( {{ Js::from( $cancelToken ) }} )"
                    wire:loading.attr="disabled"
                    wire:target="cancelOrder"
                    data-confirm-cancel>{{ __( 'Cancel order' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
