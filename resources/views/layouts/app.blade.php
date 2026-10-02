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
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield( 'title', __( 'Store admin' ) ) &middot; {{ config( 'app.name' ) }}</title>
    @include( 'ecommerce-admin::partials.vite-assets' )
    @livewireStyles
    @stack( 'styles' )
</head>
<body class="min-h-screen font-sans antialiased bg-base-200">
    <a href="#ecommerce-admin-content" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 btn btn-sm">
        {{ __( 'Skip to content' ) }}
    </a>

    <x-artisanpack-nav sticky full-width class="ecommerce-admin__topbar">
        <x-slot:brand>
            {{-- A real, focusable button: the drawer's own toggle is a hidden checkbox. --}}
            <button
                type="button"
                class="lg:hidden me-3 btn btn-ghost btn-sm btn-square"
                aria-label="{{ __( 'Open navigation' ) }}"
                aria-controls="ecommerce-admin-drawer"
                x-data
                x-on:click="document.getElementById( 'ecommerce-admin-drawer' )?.click()"
                data-nav-toggle
            >
                <x-artisanpack-icon name="o-bars-3" aria-hidden="true" />
            </button>
            <a href="{{ route( AdminNav::ROUTE_PREFIX . 'dashboard' ) }}" class="font-bold">
                {{ __( 'Store admin' ) }}
            </a>
        </x-slot:brand>

        <x-slot:actions>
            @include( 'ecommerce-admin::partials.spotlight-button' )
            <x-artisanpack-theme-toggle aria-label="{{ __( 'Toggle dark mode' ) }}" />
        </x-slot:actions>
    </x-artisanpack-nav>

    <x-artisanpack-main with-nav full-width>
        <x-slot:sidebar drawer="ecommerce-admin-drawer" class="bg-base-100 lg:bg-inherit ecommerce-admin__sidebar">
            <livewire:artisanpack-ecommerce-admin-navigation />
        </x-slot:sidebar>

        <x-slot:content id="ecommerce-admin-content">
            @yield( 'content' )
        </x-slot:content>
    </x-artisanpack-main>

    <x-artisanpack-toast />

    @include( 'ecommerce-admin::partials.spotlight' )

    @livewireScripts
    @include( 'ecommerce-admin::partials.rate-limit-notice' )
    @stack( 'scripts' )
</body>
</html>
