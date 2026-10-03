{{--
    Opens the command palette, for pointer users and anyone who does not
    know the shortcut. The click stops here: if it reached the document, the
    palette's own click-outside handler would close it straight away.

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
        x-on:click.stop="$dispatch( 'mary-search-open' )"
        data-spotlight-open
    />
@endif
