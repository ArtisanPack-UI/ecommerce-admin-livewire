{{--
    Promotions list cell (#130): the name as the link to the form. Coupon
    promotions show how many codes they have below it; automatic ones show
    nothing there.
--}}
@php( $editRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'promotions.edit' )
<span data-scope="table" data-part="primary">
    @if ( \Illuminate\Support\Facades\Route::has( $editRoute ) )
        <a href="{{ route( $editRoute, [ 'promotion' => $row->getKey() ] ) }}" class="apui-link ec-promotions-name" data-standalone>{{ $row->name }}</a>
    @else
        {{ $row->name }}
    @endif
</span>
@if ( 'coupon' === $row->source_type )
    <span data-scope="table" data-part="secondary" data-promotion-codes>{{ trans_choice( ':count code|:count codes', (int) $row->coupons_count, [ 'count' => (int) $row->coupons_count ] ) }}</span>
@endif
