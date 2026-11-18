{{--
    Dashboard: the most recently placed orders, newest first (#98). A
    full-width design-system Card with a Table flush inside it, using the
    same cells as the Orders list: the order number (the row's one link),
    customer, status badges and money, without the list's selection,
    sorting or paging. Guest orders fall back to the email, and a missing
    value shows a dash that screen readers hear as "None".

    The Card's markup is written out because x-artisanpack-card titles are
    always h3, and dashboard widgets are h2 sections (see the dashboard view).

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
    $orders     = $dashboard['recentOrders'];
@endphp
<div class="apui-card" data-surface="default">
    <div data-scope="card" data-part="header">
        <h2 data-scope="card" data-part="title" id="dashboard-widget-{{ $widget['key'] }}">{{ $widget['label'] }}</h2>
        @if ( \Illuminate\Support\Facades\Route::has( $indexRoute ) )
            <div data-scope="card" data-part="actions">
                <x-artisanpack-link :href="route( $indexRoute )" :navigate="false" trailing-icon="chevron-right" class="ec-card-link">{{ __( 'View all orders' ) }}</x-artisanpack-link>
            </div>
        @endif
    </div>

    <div data-scope="card" data-part="body" @class( [ 'ec-card-body-flush' => $orders->isNotEmpty() ] )>
        @if ( $orders->isEmpty() )
            <x-artisanpack-ec-admin-empty-state size="sm" :heading-level="3" icon="shopping-bag" :title="__( 'No orders yet' )" :description="__( 'Orders appear here as soon as customers check out.' )" />
        @else
            <div data-scope="table" data-part="scroll" class="ec-table">
                <table data-scope="table" data-part="table" data-recent-orders>
                    <caption class="apui-sr-only">{{ __( 'The :count most recent orders, newest first', [ 'count' => $orders->count() ] ) }}</caption>
                    <thead>
                        <tr>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Order' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Placed' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Customer' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Status' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Fulfillment' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Total' ) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ( $orders as $order )
                            @php
                                $customer = OrdersIndex::customerName( $order ) ?? ( '' !== (string) $order->email ? (string) $order->email : null );
                            @endphp
                            <tr data-scope="table" data-part="row" wire:key="dashboard-order-{{ $order->id }}">
                                <td data-scope="table" data-part="cell">
                                    @if ( $canLink )
                                        <x-artisanpack-link :href="route( $showRoute, [ 'order' => $order->getKey() ] )" :navigate="false" standalone><bdi dir="ltr" class="ec-mono">#{{ $order->order_number }}</bdi></x-artisanpack-link>
                                    @else
                                        <bdi dir="ltr" class="ec-mono">#{{ $order->order_number }}</bdi>
                                    @endif
                                </td>
                                <td data-scope="table" data-part="cell" class="ec-cell-muted">
                                    @if ( null !== $order->placed_at )
                                        <time datetime="{{ $order->placed_at->toIso8601String() }}">{{ LocalizedDate::format( $order->placed_at ) }}</time>
                                    @else
                                        <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                    @endif
                                </td>
                                <td data-scope="table" data-part="cell">
                                    @if ( null !== $customer )
                                        {{ $customer }}
                                    @else
                                        <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                    @endif
                                </td>
                                <td data-scope="table" data-part="cell"><x-artisanpack-ec-status-badge type="system" :value="$order->system_status" /></td>
                                <td data-scope="table" data-part="cell"><x-artisanpack-ec-status-badge type="fulfillment" :value="$order->fulfillment_status" /></td>
                                <td data-scope="table" data-part="cell" data-numeric><x-artisanpack-ec-admin-money :amount="$order->total_amount" :currency="$order->total_currency" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
