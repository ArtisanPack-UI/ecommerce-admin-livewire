{{--
    The standalone admin layout.

    Used when cms-framework is not installed. Its contract matches
    cms-framework's `cms::admin.layouts.app` on purpose: a `title` section
    (plain text), a `content` section, and `styles` / `scripts` stacks, so a
    page extends whichever layout is resolved without a branch of its own.

    It is a design-system AppShell: the navigation in the sidebar island and
    a Navbar (the command palette's button and the theme toggle) above the
    content. The design system's CSS and behavior come from the host's Vite
    entries (filterable with `ap.ecommerceAdminLivewire.layout.viteEntries`),
    loaded when a Vite build or dev server is present; the admin's own few
    styles are inlined (see partials/styles).

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
    @apuiThemeScript
    @include( 'ecommerce-admin::partials.vite-assets' )
    @include( 'ecommerce-admin::partials.styles' )
    @livewireStyles
    @stack( 'styles' )
</head>
<body class="ecommerce-admin">
    <x-artisanpack-app-shell
        id="ecommerce-admin-shell"
        class="ecommerce-admin__shell"
        :label="__( 'Store admin' )"
        :skip-label="__( 'Skip to content' )"
    >
        <x-slot:brand>
            <a href="{{ route( AdminNav::ROUTE_PREFIX . 'dashboard' ) }}" class="ecommerce-admin__brand">
                {{ __( 'Store admin' ) }}
            </a>
        </x-slot:brand>

        <x-slot:sidebar>
            <div id="ecommerce-admin-drawer" class="ecommerce-admin__sidebar">
                <livewire:artisanpack-ecommerce-admin-navigation />
            </div>
        </x-slot:sidebar>

        <x-slot:header>
            <x-artisanpack-navbar :sticky="false" :label="__( 'Store admin toolbar' )" class="ecommerce-admin__topbar">
                <x-slot:actions>
                    @include( 'ecommerce-admin::partials.spotlight-button' )
                    <x-artisanpack-theme-toggle icon-only :label="__( 'Theme' )" />
                </x-slot:actions>
            </x-artisanpack-navbar>
        </x-slot:header>

        <div id="ecommerce-admin-content" class="ecommerce-admin__content">
            @yield( 'content' )
        </div>
    </x-artisanpack-app-shell>

    <x-artisanpack-toaster />

    @livewireScripts
    @include( 'ecommerce-admin::partials.accessibility' )
    @include( 'ecommerce-admin::partials.rate-limit-notice' )
    @stack( 'scripts' )
</body>
</html>
