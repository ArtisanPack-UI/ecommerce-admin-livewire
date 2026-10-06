{{--
    The command palette (spec §8.1). Mounted once per page: by the
    standalone layout, or pushed by each page under cms-framework's layout,
    which mounts none of its own.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( \ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight::mountable() )
    <x-artisanpack-spotlight
        :url="\Illuminate\Support\Facades\Route::has( \ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight::ADMIN_ROUTE ) ? route( \ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight::ADMIN_ROUTE, absolute: false ) : null"
        :shortcut="\ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight::shortcut()"
        :search-text="__( 'Search orders, products, customers, or type a command…' )"
        :no-results-text="__( 'Nothing found.' )"
    />
@endif
