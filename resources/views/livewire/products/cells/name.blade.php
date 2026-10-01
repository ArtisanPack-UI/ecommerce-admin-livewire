@php( $editRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'products.edit' )
@if ( \Illuminate\Support\Facades\Route::has( $editRoute ) )
    <a href="{{ route( $editRoute, [ 'product' => $row->getKey() ] ) }}" class="link link-hover font-semibold">{{ $row->name }}</a>
@else
    <span class="font-semibold">{{ $row->name }}</span>
@endif
@if ( (int) $row->variants_count > 0 )
    <span class="block text-xs opacity-75">{{ trans_choice( ':count variant|:count variants', (int) $row->variants_count, [ 'count' => (int) $row->variants_count ] ) }}</span>
@endif
