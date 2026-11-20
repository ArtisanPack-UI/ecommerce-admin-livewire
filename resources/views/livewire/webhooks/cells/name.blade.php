{{--
    Webhooks list cell (#136): the name as the link to the subscription, with
    its URL below in mono so the table stays seven columns. The URL keeps
    left-to-right order in right-to-left layouts and truncates with the full
    address in its title.
--}}
@php( $showRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'webhooks.show' )
<span data-scope="table" data-part="primary">
    @if ( \Illuminate\Support\Facades\Route::has( $showRoute ) )
        <a href="{{ route( $showRoute, [ 'subscription' => $row->getKey() ] ) }}" class="apui-link ec-webhooks-name" data-standalone>{{ $row->name }}</a>
    @else
        {{ $row->name }}
    @endif
</span>
<span data-scope="table" data-part="secondary">
    @include( 'ecommerce-admin::livewire.webhooks.cells.url' )
</span>
