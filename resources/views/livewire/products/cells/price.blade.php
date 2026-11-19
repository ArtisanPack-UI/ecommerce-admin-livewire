{{-- Products list cell (#119): the base-currency price, a range for products whose variants differ. --}}
@php
    $currency = \ArtisanPackUI\EcommerceAdminLivewire\Queries\ProductsQuery::baseCurrency();
    $range    = \ArtisanPackUI\EcommerceAdminLivewire\Queries\ProductsQuery::priceRange( $row );
@endphp
@if ( null === $range['min'] )
    <span class="ec-muted-text">{{ __( 'No price' ) }}</span>
@elseif ( $range['min'] === $range['max'] )
    <x-artisanpack-ec-admin-money :amount="$range['min']" :currency="$currency" />
@else
    <span class="ec-money-group" data-price-range><x-artisanpack-ec-admin-money :amount="$range['min']" :currency="$currency" /> &ndash; <x-artisanpack-ec-admin-money :amount="$range['max']" :currency="$currency" /></span>
@endif
