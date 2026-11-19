{{--
    Digital files cell (#126): the product the file belongs to (with the
    variant, when it's attached to one), linking to the product's form.
--}}
@php
    $editRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'products.edit';
    $productId = \ArtisanPackUI\EcommerceAdminLivewire\Livewire\DigitalFiles\Index::ownerProductId( $row );
    $owner     = \ArtisanPackUI\EcommerceAdminLivewire\Livewire\DigitalFiles\Index::owner( $row );
@endphp
@if ( null !== $productId && $context['canViewProducts'] && \Illuminate\Support\Facades\Route::has( $editRoute ) )
    <a href="{{ route( $editRoute, [ 'product' => $productId ] ) }}" class="apui-link" data-standalone data-file-product>{{ $owner }}</a>
@elseif ( null === $productId )
    <span class="ec-cell-muted">{{ $owner }}</span>
@else
    {{ $owner }}
@endif
