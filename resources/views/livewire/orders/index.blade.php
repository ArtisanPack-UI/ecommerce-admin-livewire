<div>
    <x-artisanpack-header :title="__( 'Orders' )" :level="1" separator />

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'o-shopping-bag',
        'emptyTitle'       => __( 'No orders yet' ),
        'emptyDescription' => __( 'Orders appear here as soon as customers check out.' ),
        'bulkControls'     => 'ecommerce-admin::livewire.orders.bulk-controls',
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\Order $order ): string => __( 'order #:number', [ 'number' => $order->order_number ] ),
    ] )
</div>
