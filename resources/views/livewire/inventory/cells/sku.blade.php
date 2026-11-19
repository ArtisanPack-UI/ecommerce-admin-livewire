{{-- Inventory cell (#124): the product's or variant's SKU, left to right in a monospace face. --}}
@php( $sku = \ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels::sku( $row ) )
@if ( '' === $sku )
    <span aria-hidden="true" class="ec-muted-text">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
@else
    <bdi dir="ltr" class="ec-mono">{{ $sku }}</bdi>
@endif
