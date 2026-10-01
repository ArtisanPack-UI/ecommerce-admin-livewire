{{--
    The standalone admin layout.

    Used when cms-framework is not installed. Its contract matches
    cms-framework's `cms::admin.layouts.app` on purpose: a `title` section
    (plain text), a `content` section, and `styles` / `scripts` stacks, so a
    page extends whichever layout is resolved without a branch of its own.

    The package ships no CSS build. The host's Vite entries (filterable with
    `ap.ecommerceAdminLivewire.layout.viteEntries`) are loaded when a Vite
    build or dev server is present; the host adds this package's views to its
    Tailwind `@source` list (see `php artisan ecommerce-admin:install`).

    Publish with `php artisan vendor:publish --tag=ecommerce-admin-views`.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
    use Illuminate\Support\Facades\Vite;

    $viteEntries = (array) applyFilters( 'ap.ecommerceAdminLivewire.layout.viteEntries', [ 'resources/css/app.css', 'resources/js/app.js' ] );
    $loadVite    = [] !== $viteEntries && ( Vite::isRunningHot() || is_file( public_path( 'build/manifest.json' ) ) );
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield( 'title', __( 'Store admin' ) ) &middot; {{ config( 'app.name' ) }}</title>
    @if ( $loadVite )
        @vite( $viteEntries )
    @endif
    @livewireStyles
    @stack( 'styles' )
</head>
<body class="min-h-screen font-sans antialiased bg-base-200">
    <a href="#ecommerce-admin-content" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 btn btn-sm">
        {{ __( 'Skip to content' ) }}
    </a>

    <x-artisanpack-nav sticky full-width class="ecommerce-admin__topbar">
        <x-slot:brand>
            <label for="ecommerce-admin-drawer" class="lg:hidden me-3 cursor-pointer" aria-label="{{ __( 'Open navigation' ) }}">
                <x-artisanpack-icon name="o-bars-3" aria-hidden="true" />
            </label>
            <a href="{{ route( AdminNav::ROUTE_PREFIX . 'dashboard' ) }}" class="font-bold">
                {{ __( 'Store admin' ) }}
            </a>
        </x-slot:brand>

        <x-slot:actions>
            <x-artisanpack-theme-toggle aria-label="{{ __( 'Toggle dark mode' ) }}" />
        </x-slot:actions>
    </x-artisanpack-nav>

    <x-artisanpack-main with-nav full-width>
        <x-slot:sidebar drawer="ecommerce-admin-drawer" class="bg-base-100 lg:bg-inherit ecommerce-admin__sidebar">
            @include( 'ecommerce-admin::partials.nav' )
        </x-slot:sidebar>

        <x-slot:content id="ecommerce-admin-content">
            @yield( 'content' )
        </x-slot:content>
    </x-artisanpack-main>

    <x-artisanpack-toast />

    @livewireScripts
    @stack( 'scripts' )
</body>
</html>
