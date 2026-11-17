{{--
    The command palette (spec §8.1) with its open button, for pointer users
    and anyone who does not know the shortcut. The design-system Spotlight
    renders both: the button where this partial sits, and the dialog,
    teleported to the end of the page. Results open as plain links, as in
    1.x: the admin's pages don't run under wire:navigate.

    Mounted once per page: in the standalone layout's top bar, or above the
    page under cms-framework's layout, which mounts none of its own.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( \ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight::mountable() )
    <x-artisanpack-spotlight
        :url="\Illuminate\Support\Facades\Route::has( \ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight::ADMIN_ROUTE ) ? route( \ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight::ADMIN_ROUTE, absolute: false ) : null"
        :shortcut="\ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight::paletteShortcut()"
        :navigate="false"
        :trigger="__( 'Search' )"
        :label="__( 'Search and run commands' )"
        :placeholder="__( 'Search orders, products, customers, or type a command…' )"
        :empty-text="__( 'Nothing found.' )"
        data-spotlight-open
    />
@endif
