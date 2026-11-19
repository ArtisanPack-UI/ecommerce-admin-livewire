{{--
    Admin layout (#115): the standalone shell.

    Used when cms-framework is not installed. Its contract matches
    cms-framework's `cms::admin.layouts.app` on purpose: a `title` section
    (plain text), a `content` section, and `styles` / `scripts` stacks, so a
    page extends whichever layout is resolved without a branch of its own.

    A design-system AppShell: three islands on the page surface. The sidebar
    carries the store name and the navigation; the top bar carries the
    breadcrumbs (Store admin › the page's title), the command palette's
    button and the theme toggle; the content holds the page, whose
    PageHeader has the page's one h1. "Skip to content" is the first tab
    stop and lands on the main landmark.

    Below 1024px the sidebar becomes the icon rail (the AppShell's collapsed
    state). Below 768px it becomes a drawer behind the menu button: a modal
    dialog with focus trapped, Escape or its close button closes it, and focus
    returns to the menu button. One navigation serves all three, so its
    counts are queried once. The state is inline Alpine, so a script CSP
    needs no extra nonce for it.

    The design system's CSS and behavior come from the host's Vite entries
    (filterable with `ap.ecommerceAdminLivewire.layout.viteEntries`), loaded
    when a Vite build or dev server is present; the admin's own styles are
    inlined (see partials/styles).

    Publish with `php artisan vendor:publish --tag=ecommerce-admin-views`.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
    use Illuminate\Support\Facades\Route;

    $dashboardRoute = AdminNav::ROUTE_PREFIX . 'dashboard';
    $pageTitle      = trim( (string) $__env->yieldContent( 'title' ) );
    $breadcrumbs    = [];

    if ( ! Route::is( $dashboardRoute ) && Route::has( $dashboardRoute ) ) {
        $breadcrumbs[] = [ 'label' => __( 'Store admin' ), 'href' => route( $dashboardRoute ) ];
    }

    $breadcrumbs[] = [ 'label' => '' === $pageTitle ? __( 'Store admin' ) : $pageTitle ];
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
        x-data="{
            navOpen: false,
            rail: false,
            init() {
                const rail  = window.matchMedia( '(min-width: 48rem) and (max-width: 63.999rem)' );
                const phone = window.matchMedia( '(max-width: 47.999rem)' );

                this.rail = rail.matches;
                rail.addEventListener( 'change', ( event ) => this.rail = event.matches );
                phone.addEventListener( 'change', ( event ) => event.matches || ( this.navOpen = false ) );
            },
            openNav() { this.navOpen = true },
            closeNav() { this.navOpen = false; this.$nextTick( () => this.$refs.navButton?.focus() ) },
        }"
        x-bind:data-collapsed="rail ? '' : null"
        x-bind:data-nav-open="navOpen ? '' : null"
        x-on:keydown.escape.window="navOpen && closeNav()"
    >
        <x-slot:brand>
            @if ( Route::has( $dashboardRoute ) )
                <a href="{{ route( $dashboardRoute ) }}" class="ecommerce-admin__brand">
                    <x-artisanpack-icon name="o-building-storefront" aria-hidden="true" />
                    <span class="ecommerce-admin__brand-name">{{ config( 'app.name' ) }}</span>
                </a>
            @else
                <span class="ecommerce-admin__brand">
                    <x-artisanpack-icon name="o-building-storefront" aria-hidden="true" />
                    <span class="ecommerce-admin__brand-name">{{ config( 'app.name' ) }}</span>
                </span>
            @endif
        </x-slot:brand>

        <x-slot:sidebar>
            <div
                id="ecommerce-admin-drawer"
                class="ecommerce-admin__sidebar"
                x-trap.inert.noscroll="navOpen"
                x-bind:role="navOpen ? 'dialog' : null"
                x-bind:aria-modal="navOpen ? 'true' : null"
                x-bind:aria-label="navOpen ? @js( __( 'Navigation' ) ) : null"
            >
                <x-artisanpack-button
                    variant="ghost"
                    color="neutral"
                    size="sm"
                    icon-only
                    leading-icon="x"
                    class="ecommerce-admin__drawer-close"
                    :aria-label="__( 'Close navigation' )"
                    x-on:click="closeNav()"
                />
                <livewire:artisanpack-ecommerce-admin-navigation />
            </div>
        </x-slot:sidebar>

        <x-slot:header>
            <x-artisanpack-navbar :sticky="false" class="ecommerce-admin__topbar">
                <x-slot:brand>
                    <x-artisanpack-button
                        variant="ghost"
                        color="neutral"
                        size="sm"
                        icon-only
                        leading-icon="o-bars-3"
                        class="ecommerce-admin__menu-button"
                        :aria-label="__( 'Open navigation' )"
                        aria-controls="ecommerce-admin-drawer"
                        aria-expanded="false"
                        x-ref="navButton"
                        x-bind:aria-expanded="navOpen ? 'true' : 'false'"
                        x-on:click="openNav()"
                    />
                    <x-artisanpack-breadcrumbs :items="$breadcrumbs" :navigate="false" class="ecommerce-admin__breadcrumbs" />
                </x-slot:brand>
                <x-slot:actions>
                    @include( 'ecommerce-admin::partials.spotlight-button' )
                    <x-artisanpack-theme-toggle
                        variant="icon"
                        size="sm"
                        :switch-to-light-label="__( 'Switch to light theme' )"
                        :switch-to-dark-label="__( 'Switch to dark theme' )"
                    />
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
