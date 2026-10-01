@php( $editRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'products.edit' )
@if ( null === $row->product )
    <span class="opacity-75">{{ __( 'Deleted product' ) }}</span>
@elseif ( \Illuminate\Support\Facades\Route::has( $editRoute ) )
    <a href="{{ route( $editRoute, [ 'product' => $row->product_id ] ) }}" class="link link-hover">{{ $row->product->name }}</a>
@else
    {{ $row->product->name }}
@endif
