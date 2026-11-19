{{--
    License keys cell (#127): the order the key came from, linked to it in
    monospace, or "Deleted order".
--}}
@php
    $order     = $row->orderItem?->order;
    $showRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'orders.show';
@endphp
@if ( null === $order )
    <span class="ec-muted">{{ __( 'Deleted order' ) }}</span>
@elseif ( \Illuminate\Support\Facades\Route::has( $showRoute ) )
    <a href="{{ route( $showRoute, [ 'order' => $order->getKey() ] ) }}" class="apui-link" data-standalone><bdi dir="ltr" class="ec-mono">#{{ $order->order_number }}</bdi></a>
@else
    <bdi dir="ltr" class="ec-mono">#{{ $order->order_number }}</bdi>
@endif
