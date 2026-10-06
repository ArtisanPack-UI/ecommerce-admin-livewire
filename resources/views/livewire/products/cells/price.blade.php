@php
    $currency = \ArtisanPackUI\EcommerceAdminLivewire\Queries\ProductsQuery::baseCurrency();
    $range    = \ArtisanPackUI\EcommerceAdminLivewire\Queries\ProductsQuery::priceRange( $row );
@endphp
@if ( null === $range['min'] )
    <span class="opacity-75">{{ __( 'No price' ) }}</span>
@elseif ( $range['min'] === $range['max'] )
    <x-artisanpack-ec-money :amount="$range['min']" :currency="$currency" />
@else
    <span data-price-range><x-artisanpack-ec-money :amount="$range['min']" :currency="$currency" /> &ndash; <x-artisanpack-ec-money :amount="$range['max']" :currency="$currency" /></span>
@endif
