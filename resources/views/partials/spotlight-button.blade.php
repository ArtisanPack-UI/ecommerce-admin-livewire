{{--
    Opens the command palette, for pointer users and anyone who does not
    know the shortcut.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( \ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight::mountable() )
    @php( $ecommerceAdminShortcut = \ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight::ariaShortcut() )
    <x-artisanpack-button
        icon="o-magnifying-glass"
        class="btn-ghost btn-sm"
        :label="__( 'Search' )"
        :tooltip-bottom="__( 'Search and run commands (:shortcut)', [ 'shortcut' => $ecommerceAdminShortcut ] )"
        aria-keyshortcuts="{{ $ecommerceAdminShortcut }}"
        x-data
        x-on:click="$dispatch( 'mary-search-open' )"
        data-spotlight-open
    />
@endif
