{{-- Reviews cell (#125): the product, linked to its form, or "Deleted product". --}}
@php( $editRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'products.edit' )
@if ( null === $row->product )
    <span class="ec-muted-text">{{ __( 'Deleted product' ) }}</span>
@elseif ( \Illuminate\Support\Facades\Route::has( $editRoute ) )
    <a href="{{ route( $editRoute, [ 'product' => $row->product_id ] ) }}" class="apui-link" data-standalone>{{ $row->product->name }}</a>
@else
    {{ $row->product->name }}
@endif
