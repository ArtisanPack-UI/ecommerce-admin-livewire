@php
    $product   = \ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels::product( $row );
    $editRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'products.edit';
    $label     = \ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels::label( $row );
@endphp
@if ( null !== $product && \Illuminate\Support\Facades\Route::has( $editRoute ) )
    <a href="{{ route( $editRoute, [ 'product' => $product->getKey() ] ) }}" class="link link-hover font-semibold">{{ $label }}</a>
@else
    <span class="font-semibold">{{ $label }}</span>
@endif
@if ( $row->stockable instanceof \ArtisanPackUI\Ecommerce\Models\ProductVariant )
    <span class="block text-xs opacity-75">{{ __( 'Variant' ) }}</span>
@endif
@if ( null !== $product && $product->typeIsMissing() )
    <x-artisanpack-badge :value="__( 'Read-only' )" class="badge-sm" color="warning" data-read-only />
@endif
@unless ( $row->track_inventory )
    <span class="block text-xs opacity-75">{{ __( 'Not tracked' ) }}</span>
@endunless
