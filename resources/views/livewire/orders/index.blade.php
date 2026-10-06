<div>
    <x-artisanpack-header :title="__( 'Orders' )" :level="1" separator />

    @include( 'ecommerce-admin::partials.live-region', [ 'message' => $liveAnnouncement ] )

    @if ( $newOrders > 0 )
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-box border border-info/40 bg-info/10 px-4 py-2 text-sm" data-new-orders="{{ $newOrders }}">
            <x-artisanpack-icon name="o-bell-alert" class="w-5 h-5" aria-hidden="true" />
            <span>{{ trans_choice( ':count new order since you opened this page.|:count new orders since you opened this page.', $newOrders, [ 'count' => $newOrders ] ) }}</span>
            <x-artisanpack-button size="sm" class="btn-ghost ms-auto" wire:click="dismissNewOrders" :label="__( 'Dismiss' )" />
        </div>
    @endif

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'o-shopping-bag',
        'emptyTitle'       => __( 'No orders yet' ),
        'emptyDescription' => __( 'Orders appear here as soon as customers check out.' ),
        'bulkControls'     => 'ecommerce-admin::livewire.orders.bulk-controls',
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\Order $order ): string => __( 'order #:number', [ 'number' => $order->order_number ] ),
    ] )
</div>
