@php
    $customerName = \ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Index::customerName( $row );
    $showRoute    = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'customers.show';
@endphp
@if ( \Illuminate\Support\Facades\Route::has( $showRoute ) )
    <a href="{{ route( $showRoute, [ 'customer' => $row->getKey() ] ) }}" class="link link-hover font-semibold">{{ $customerName ?? __( 'No name' ) }}</a>
@else
    <span class="font-semibold">{{ $customerName ?? __( 'No name' ) }}</span>
@endif
