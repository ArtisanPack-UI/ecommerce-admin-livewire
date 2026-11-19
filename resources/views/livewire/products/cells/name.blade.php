{{-- Products list cell (#119): the name as the link to the form, with the variant count (or the SKU) below. --}}
@php( $editRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'products.edit' )
<span data-scope="table" data-part="primary">
    @if ( \Illuminate\Support\Facades\Route::has( $editRoute ) )
        <a href="{{ route( $editRoute, [ 'product' => $row->getKey() ] ) }}" class="apui-link ec-products-name" data-standalone>{{ $row->name }}</a>
    @else
        {{ $row->name }}
    @endif
</span>
@if ( (int) $row->variants_count > 0 )
    <span data-scope="table" data-part="secondary">{{ trans_choice( ':count variant|:count variants', (int) $row->variants_count, [ 'count' => (int) $row->variants_count ] ) }}</span>
@elseif ( null !== $row->sku && '' !== (string) $row->sku )
    <span data-scope="table" data-part="secondary"><bdi dir="ltr" class="ec-mono" data-product-sku>{{ $row->sku }}</bdi></span>
@endif
