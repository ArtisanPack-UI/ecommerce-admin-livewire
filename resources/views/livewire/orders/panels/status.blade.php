<div>
    <x-artisanpack-card :title="__( 'Status' )" shadow>
        <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __( 'Current status' ) }}">
            <x-artisanpack-ec-status-badge type="system" :value="$order->system_status" />
            @if ( null !== $order->substatus )
                <x-artisanpack-ec-status-badge :substatus="$order->substatus" />
            @endif
            <x-artisanpack-ec-status-badge type="payment" :value="$order->payment_status" />
            <x-artisanpack-ec-status-badge type="fulfillment" :value="$order->fulfillment_status" />
        </div>

        @if ( null !== $substatusWarning || [] !== $boardWarnings )
            <x-artisanpack-alert color="warning" class="mt-3" icon="o-exclamation-triangle" role="status">
                <ul class="flex flex-col gap-1">
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
                <p class="mt-3 text-sm opacity-75">{{ __( 'This order is :status and cannot move to another status.', [ 'status' => \ArtisanPackUI\EcommerceAdminLivewire\Support\StatusPresenter::present( 'system', $order->system_status )['label'] ] ) }}</p>
            @else
                <form wire:submit="changeStatus" class="mt-3 flex flex-col gap-2" data-status-form>
                    <x-artisanpack-select
                        id="order-target-status"
                        :label="__( 'Move to' )"
                        :options="$statusOptions"
                        :placeholder="__( 'Choose a status' )"
                        placeholder-value=""
                        wire:model.live="targetStatus"
                    />

                    @if ( [] !== $targetWarnings )
                        <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" role="status">
                            <ul class="flex flex-col gap-1">
                                @foreach ( $targetWarnings as $warning )
                                    <li wire:key="status-target-warning-{{ $loop->index }}">{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </x-artisanpack-alert>
                    @endif

                    <x-artisanpack-input id="order-status-reason" :label="__( 'Reason (optional)' )" wire:model="statusReason" />
                    <x-artisanpack-button type="submit" size="sm" class="self-start" wire:loading.attr="disabled" :label="__( 'Change status' )" />
                </form>
            @endif

            <form wire:submit="changeSubstatus" class="mt-4 flex flex-col gap-2" data-substatus-form>
                <x-artisanpack-select
                    id="order-substatus"
                    :label="__( 'Sub-status' )"
                    :options="$substatusOptions"
                    :placeholder="__( 'Choose a sub-status' )"
                    placeholder-value=""
                    :hint="$hasOtherSubstatus ? __( 'Greyed-out sub-statuses belong to another order status and become available once the order moves there.' ) : null"
                    wire:model="substatusId"
                />
                <x-artisanpack-button type="submit" size="sm" class="self-start" wire:loading.attr="disabled" :label="__( 'Set sub-status' )" />
            </form>
        @endif

        @if ( $canCancel )
            <div class="mt-4 border-t border-base-300 pt-4">
                <x-artisanpack-button
                    variant="outline"
                    size="sm"
                    icon="o-x-circle"
                    wire:click="$set( 'confirmingCancel', true )"
                    :label="__( 'Cancel order' )"
                />
            </div>
        @endif
    </x-artisanpack-card>

    @if ( null !== $cancellation )
        <x-artisanpack-modal wire:model="confirmingCancel" :title="__( 'Cancel order #:number?', [ 'number' => $order->order_number ] )" separator>
            <div class="flex flex-col gap-4" data-cancel-form>
                <div>
                    <h3 class="font-semibold">{{ __( 'What happens' ) }}</h3>
                    <ul class="mt-1 list-disc ps-5 text-sm" data-cancel-summary>
                        @if ( [] === $cancellation['reservations'] )
                            <li>{{ __( 'No stock is held for this order.' ) }}</li>
                        @else
                            <li>
                                {{ trans_choice( ':count reserved unit goes back on sale:|:count reserved units go back on sale:', $cancellation['units'], [ 'count' => $cancellation['units'] ] ) }}
                                <ul class="list-[circle] ps-5">
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
                            <li class="font-semibold">{{ __( 'The customer has paid. Cancelling does not refund: :amount is still owed and must be refunded separately.', [ 'amount' => $cancellation['refundOwed'] ] ) }}</li>
                        @else
                            <li>{{ __( 'No refund is owed.' ) }}</li>
                        @endif
                    </ul>
                </div>

                <x-artisanpack-textarea id="order-cancel-reason" :label="__( 'Reason' )" wire:model="cancelReason" rows="3" required />
            </div>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="$set( 'confirmingCancel', false )" :label="__( 'Keep order' )" />
                <x-artisanpack-button
                    color="error"
                    wire:click="cancelOrder( {{ \Illuminate\Support\Js::from( $cancelToken ) }} )"
                    wire:loading.attr="disabled"
                    :label="__( 'Cancel order' )"
                />
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif
</div>
