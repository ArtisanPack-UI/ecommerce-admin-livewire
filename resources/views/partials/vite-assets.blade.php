{{--
    Loads the host's Vite entries (the Tailwind build that styles the admin),
    filterable with `ap.ecommerceAdminLivewire.layout.viteEntries`; return an
    empty array to load nothing. Skipped when there is neither a Vite build
    nor a running dev server.

    Both layouts use it: the standalone layout in its head, and pages pushing
    onto cms-framework's `styles` stack, since that layout ships no CSS. When
    a host's own CMS layout already loads the same entries, the browser runs
    each module once and the repeated stylesheet is a no-op.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    $ecommerceAdminViteEntries = (array) applyFilters( 'ap.ecommerceAdminLivewire.layout.viteEntries', [ 'resources/css/app.css', 'resources/js/app.js' ] );
    $ecommerceAdminLoadsVite   = [] !== $ecommerceAdminViteEntries
        && ( \Illuminate\Support\Facades\Vite::isRunningHot() || is_file( public_path( 'build/manifest.json' ) ) );
@endphp
@if ( $ecommerceAdminLoadsVite )
    @vite( $ecommerceAdminViteEntries )
@endif
