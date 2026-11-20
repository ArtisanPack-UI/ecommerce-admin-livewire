{{--
    Inventory cell (#124): the product name, linked to its form, with the
    variant below it. Stock of a product whose type is no longer installed
    is read-only and says so with a lock badge; untracked stock says so too.
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels;

    $product   = StockLevels::product( $row );
    $editRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'products.edit';
    $variant   = $row->stockable instanceof \ArtisanPackUI\Ecommerce\Models\ProductVariant ? trim( (string) ( $row->stockable->name ?? '' ) ) : '';
    $name      = null === $product ? StockLevels::label( $row ) : (string) $product->name;
@endphp
<span data-scope="table" data-part="primary">
    @if ( null !== $product && \Illuminate\Support\Facades\Route::has( $editRoute ) )
        <a href="{{ route( $editRoute, [ 'product' => $product->getKey() ] ) }}" class="apui-link ec-inventory-name" data-standalone>{{ $name }}</a>
    @else
        {{ $name }}
    @endif
</span>
@if ( '' !== $variant )
    <span data-scope="table" data-part="secondary" data-variant-name>{{ $variant }}</span>
@endif
@if ( null !== $product && $product->typeIsMissing() )
    <span class="ec-inventory-flags">
        <x-artisanpack-badge color="warning" size="sm" leading-icon="lock" :title="$product->typeWarning()" data-read-only>
            {{ __( 'Read-only' ) }}<span class="apui-sr-only">. {{ $product->typeWarning() }}</span>
        </x-artisanpack-badge>
    </span>
@endif
@unless ( $row->track_inventory )
    <span data-scope="table" data-part="secondary" data-not-tracked>{{ __( 'Not tracked' ) }}</span>
@endunless
