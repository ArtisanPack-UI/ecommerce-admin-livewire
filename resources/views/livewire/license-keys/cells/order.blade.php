@php
    $order     = $row->orderItem?->order;
    $showRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'orders.show';
@endphp
@if ( null === $order )
    <span class="opacity-75">{{ __( 'Deleted order' ) }}</span>
@elseif ( \Illuminate\Support\Facades\Route::has( $showRoute ) )
    <a href="{{ route( $showRoute, [ 'order' => $order->getKey() ] ) }}" class="link link-hover">#{{ $order->order_number }}</a>
@else
    #{{ $order->order_number }}
@endif
