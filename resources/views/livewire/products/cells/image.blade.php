{{-- Products list cell (#119): a 36px thumbnail named after the product, or a placeholder tile. --}}
{{-- The screen resolves the page's thumbnails at once (ProductMedia::featuredMany); a cell rendered elsewhere looks its own up. --}}
@php( $image = array_key_exists( (int) $row->id, (array) ( $context['thumbnails'] ?? [] ) ) ? $context['thumbnails'][ (int) $row->id ] : \ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia::featured( $row ) )
@if ( null === $image )
    <span class="ec-products-thumb" data-empty role="img" aria-label="{{ __( ':product: no image', [ 'product' => $row->name ] ) }}" data-product-thumb>
        <x-artisanpack-icon name="image" aria-hidden="true" />
    </span>
@else
    <img src="{{ $image['url'] }}" alt="{{ $row->name }}" class="ec-products-thumb" width="36" height="36" loading="lazy" data-product-thumb />
@endif
