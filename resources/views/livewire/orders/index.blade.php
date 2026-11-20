{{--
    Orders list (#117). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Index.

    Every order, newest first, on the shared Resource table (#79). Two
    things are specific to orders. A banner, a polite live region, counts the
    orders placed since the page opened: "Show new orders" reloads the table
    on its first page, marks those orders New and moves focus to the table.
    The bulk bar adds "Move to sub-status", offering only sub-statuses that
    fit every selected order, or explaining why none do.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div
    class="ec-stack"
    data-orders-index
    x-data
    x-on:ecommerce-admin-new-orders-shown.window="$nextTick( () => $root.querySelector( '[data-orders-table]' )?.focus() )"
>
    <x-artisanpack-page-header :title="__( 'Orders' )" :description="__( 'Every order placed in your store, newest first.' )" :level="1" />

    @include( 'ecommerce-admin::partials.live-region', [ 'message' => $liveAnnouncement ] )

    @if ( $newOrders > 0 )
        <div class="ec-banner apui-alert" data-color="info" data-new-orders="{{ $newOrders }}">
            <x-artisanpack-icon name="o-bell-alert" aria-hidden="true" />
            <p class="ec-banner__text">{{ trans_choice( ':count new order since you opened this page.|:count new orders since you opened this page.', $newOrders, [ 'count' => $newOrders ] ) }}</p>
            <x-artisanpack-button color="info" size="sm" leading-icon="refresh-cw" wire:click="showNewOrders" data-show-new-orders>{{ __( 'Show new orders' ) }}</x-artisanpack-button>
            <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="dismissNewOrders">{{ __( 'Dismiss' ) }}</x-artisanpack-button>
        </div>
    @endif

    <div class="ec-focus-target" tabindex="-1" data-orders-table aria-label="{{ __( 'Orders table' ) }}" role="region">
        @include( 'ecommerce-admin::partials.resource-table', [
            'emptyIcon'        => 'o-shopping-bag',
            'emptyTitle'       => __( 'No orders yet' ),
            'emptyDescription' => __( 'Orders appear here as soon as customers check out.' ),
            'searchHint'       => __( 'Search by number, customer or email' ),
            'bulkControls'     => 'ecommerce-admin::livewire.orders.bulk-controls',
            'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\Order $order ): string => __( 'order #:number', [ 'number' => $order->order_number ] ),
        ] )
    </div>
</div>
