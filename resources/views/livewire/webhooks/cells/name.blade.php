@php( $showRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'webhooks.show' )
@if ( \Illuminate\Support\Facades\Route::has( $showRoute ) )
    <a href="{{ route( $showRoute, [ 'subscription' => $row->getKey() ] ) }}" class="link link-hover font-semibold">{{ $row->name }}</a>
@else
    <span class="font-semibold">{{ $row->name }}</span>
@endif
