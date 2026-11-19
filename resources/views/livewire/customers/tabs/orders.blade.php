{{--
    Customer orders tab (#108). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\OrdersTab.

    The customer's orders, newest first, in the same cells as the Orders
    list: the order number (the row's one link), the date, the three status
    badges, the item count and the total. It pages on its own, without
    search, filters or selection; the Orders list is one click away. Without
    the orders permission the tab says so beside a lock instead of showing
    an empty table.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
@endphp
<div class="ec-stack-sm" data-customer-orders-tab>
    @if ( ! $canList )
        <p class="ec-muted ec-icon-line" role="status" data-orders-locked>
            <x-artisanpack-icon name="lock" aria-hidden="true" />
            {{ __( 'You are not allowed to see orders.' ) }}
        </p>
    @elseif ( 0 === $orders->total() )
        <x-artisanpack-ec-admin-empty-state
            size="sm"
            class="ec-empty-sunken"
            :heading-level="3"
            icon="shopping-bag"
            :title="__( 'No orders yet' )"
            :description="__( 'This customer has not placed an order.' )"
        />
    @else
        <div class="apui-table ec-table" data-surface="default">
            <div data-scope="table" data-part="scroll">
                <table data-scope="table" data-part="table" data-customer-orders>
                    <caption class="apui-sr-only">{{ __( 'Orders by this customer' ) }}</caption>
                    <thead>
                        <tr>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Order' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Placed' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Status' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Payment' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Fulfillment' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Items' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Total' ) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ( $orders as $order )
                            <tr data-scope="table" data-part="row" wire:key="customer-order-{{ $order->id }}">
                                <td data-scope="table" data-part="cell">
                                    @if ( null !== $showRoute )
                                        <x-artisanpack-link :href="route( $showRoute, [ 'order' => $order->id ] )" :navigate="false" standalone><bdi dir="ltr" class="ec-mono">#{{ $order->order_number }}</bdi></x-artisanpack-link>
                                    @else
                                        <bdi dir="ltr" class="ec-mono">#{{ $order->order_number }}</bdi>
                                    @endif
                                </td>
                                <td data-scope="table" data-part="cell" class="ec-cell-muted ec-nowrap">
                                    @if ( null !== $order->placed_at )
                                        <time datetime="{{ $order->placed_at->toIso8601String() }}">{{ LocalizedDate::format( $order->placed_at ) }}</time>
                                    @else
                                        <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                    @endif
                                </td>
                                <td data-scope="table" data-part="cell"><x-artisanpack-ec-status-badge type="system" :value="$order->system_status" /></td>
                                <td data-scope="table" data-part="cell"><x-artisanpack-ec-status-badge type="payment" :value="$order->payment_status" /></td>
                                <td data-scope="table" data-part="cell"><x-artisanpack-ec-status-badge type="fulfillment" :value="$order->fulfillment_status" /></td>
                                <td data-scope="table" data-part="cell" data-numeric><bdi dir="ltr">{{ $order->items_count }}</bdi></td>
                                <td data-scope="table" data-part="cell" data-numeric><x-artisanpack-ec-admin-money :amount="(int) $order->total_amount" :currency="$order->currency" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div data-scope="table" data-part="footer">
                <x-artisanpack-pagination :rows="$orders" summary :summary-label="__( 'Showing {from} to {to} of {total} results' )" />
            </div>
        </div>
    @endif
</div>
