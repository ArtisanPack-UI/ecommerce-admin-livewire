{{--
    A customer's orders. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\OrdersTab.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
@endphp
<div>
    @if ( ! $canList )
        <p class="opacity-75">{{ __( 'You are not allowed to see orders.' ) }}</p>
    @elseif ( 0 === $orders->total() )
        <x-artisanpack-ec-empty-state icon="o-shopping-bag" :title="__( 'No orders yet' )" :description="__( 'This customer has not placed an order.' )" />
    @else
        <div class="overflow-x-auto">
            <table class="table table-sm" data-customer-orders>
                <caption class="sr-only">{{ __( 'Orders by this customer' ) }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __( 'Order' ) }}</th>
                        <th scope="col">{{ __( 'Placed' ) }}</th>
                        <th scope="col">{{ __( 'Status' ) }}</th>
                        <th scope="col">{{ __( 'Payment' ) }}</th>
                        <th scope="col">{{ __( 'Fulfillment' ) }}</th>
                        <th scope="col" class="text-end">{{ __( 'Items' ) }}</th>
                        <th scope="col" class="text-end">{{ __( 'Total' ) }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ( $orders as $order )
                        <tr wire:key="customer-order-{{ $order->id }}">
                            <td>
                                @if ( null !== $showRoute )
                                    <a href="{{ route( $showRoute, [ 'order' => $order->id ] ) }}" class="link link-hover font-semibold">#{{ $order->order_number }}</a>
                                @else
                                    <span class="font-semibold">#{{ $order->order_number }}</span>
                                @endif
                            </td>
                            <td>{{ null === $order->placed_at ? '—' : LocalizedDate::format( $order->placed_at ) }}</td>
                            <td><x-artisanpack-ec-status-badge type="system" :value="$order->system_status" /></td>
                            <td><x-artisanpack-ec-status-badge type="payment" :value="$order->payment_status" /></td>
                            <td><x-artisanpack-ec-status-badge type="fulfillment" :value="$order->fulfillment_status" /></td>
                            <td class="text-end tabular-nums">{{ $order->items_count }}</td>
                            <td class="text-end"><x-artisanpack-ec-money :amount="(int) $order->total_amount" :currency="$order->currency" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-artisanpack-pagination :rows="$orders" hide-per-page :page-info-template="__( 'Showing {from} to {to} of {total} results' )" />
    @endif
</div>
