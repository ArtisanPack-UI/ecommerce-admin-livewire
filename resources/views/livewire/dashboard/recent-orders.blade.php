{{--
    Dashboard: the most recently placed orders.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Index as OrdersIndex;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;

    $showRoute  = AdminNav::ROUTE_PREFIX . 'orders.show';
    $indexRoute = AdminNav::ROUTE_PREFIX . 'orders.index';
    $canLink    = \Illuminate\Support\Facades\Route::has( $showRoute );
@endphp
<x-artisanpack-card shadow>
    <h2 id="dashboard-widget-{{ $widget['key'] }}" class="text-lg font-semibold">{{ $widget['label'] }}</h2>

    @if ( $dashboard['recentOrders']->isEmpty() )
        <x-artisanpack-ec-empty-state icon="o-shopping-bag" :title="__( 'No orders yet' )" :description="__( 'Orders appear here as soon as customers check out.' )" />
    @else
        <div class="overflow-x-auto">
            <table class="table table-sm" data-recent-orders>
                <caption class="sr-only">{{ __( 'The :count most recent orders, newest first', [ 'count' => $dashboard['recentOrders']->count() ] ) }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __( 'Order' ) }}</th>
                        <th scope="col">{{ __( 'Placed' ) }}</th>
                        <th scope="col">{{ __( 'Customer' ) }}</th>
                        <th scope="col">{{ __( 'Status' ) }}</th>
                        <th scope="col">{{ __( 'Fulfillment' ) }}</th>
                        <th scope="col" class="text-end">{{ __( 'Total' ) }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ( $dashboard['recentOrders'] as $order )
                        <tr wire:key="dashboard-order-{{ $order->id }}">
                            <td>
                                @if ( $canLink )
                                    <a href="{{ route( $showRoute, [ 'order' => $order->getKey() ] ) }}" class="link link-hover font-semibold">#{{ $order->order_number }}</a>
                                @else
                                    <span class="font-semibold">#{{ $order->order_number }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">{{ LocalizedDate::format( $order->placed_at ) }}</td>
                            <td>{{ OrdersIndex::customerName( $order ) ?? $order->email ?? '—' }}</td>
                            <td><x-artisanpack-ec-status-badge type="system" :value="$order->system_status" /></td>
                            <td><x-artisanpack-ec-status-badge type="fulfillment" :value="$order->fulfillment_status" /></td>
                            <td class="text-end"><x-artisanpack-ec-money :amount="$order->total_amount" :currency="$order->total_currency" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ( \Illuminate\Support\Facades\Route::has( $indexRoute ) )
        <x-slot:actions>
            <a href="{{ route( $indexRoute ) }}" class="link link-hover text-sm">{{ __( 'View all orders' ) }}</a>
        </x-slot:actions>
    @endif
</x-artisanpack-card>
