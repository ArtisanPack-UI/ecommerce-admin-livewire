@php( $showRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'orders.show' )
@if ( \Illuminate\Support\Facades\Route::has( $showRoute ) )
    <a href="{{ route( $showRoute, [ 'order' => $row->getKey() ] ) }}" class="link link-hover font-semibold">#{{ $row->order_number }}</a>
@else
    <span class="font-semibold">#{{ $row->order_number }}</span>
@endif
