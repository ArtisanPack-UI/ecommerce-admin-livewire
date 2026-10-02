@php( $editRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'promotions.edit' )
<div class="flex flex-col">
    @if ( \Illuminate\Support\Facades\Route::has( $editRoute ) )
        <a href="{{ route( $editRoute, [ 'promotion' => $row->getKey() ] ) }}" class="link link-hover font-semibold">{{ $row->name }}</a>
    @else
        <span class="font-semibold">{{ $row->name }}</span>
    @endif
    <span class="text-xs opacity-75">
        {{ $row->key }}
        @if ( 'coupon' === $row->source_type )
            &middot; {{ trans_choice( ':count code|:count codes', (int) $row->coupons_count, [ 'count' => (int) $row->coupons_count ] ) }}
        @endif
    </span>
</div>
