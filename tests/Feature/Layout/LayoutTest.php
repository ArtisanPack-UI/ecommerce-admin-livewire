<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Support\CmsFramework;
use Illuminate\Support\Facades\View;

beforeEach( function (): void {
    grantAbilities( [ 'order.viewAny' ] );
    $this->actingAs( makeUser() );
} );

it( 'renders inside the standalone layout when cms-framework is absent', function (): void {
    CmsFramework::fake( false );

    $this->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->assertOk()
        ->assertSee( 'apui-app-shell', false )
        ->assertSee( 'apui-navbar', false )
        ->assertSee( 'data-ecommerce-admin-styles', false )
        ->assertSee( 'class="ecommerce-admin__sidebar"', false )
        ->assertSee( 'id="ecommerce-admin-drawer"', false )
        ->assertSee( '<title>Dashboard &middot;', false )
        ->assertSee( 'Store admin' )
        ->assertSee( 'Skip to content' )
        ->assertSee( 'apui-toaster', false )
        ->assertSee( 'livewire', false )
        ->assertDontSee( 'cms-admin-fixture', false );
} );

it( 'renders inside the cms-framework layout when it is installed', function (): void {
    CmsFramework::fake( true );
    View::addNamespace( 'cms', __DIR__ . '/../../Fixtures/views/cms' );

    $response = $this->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->assertOk()
        ->assertSee( 'cms-admin-fixture', false )
        ->assertSee( '<title>Dashboard &middot; CMS</title>', false )
        ->assertDontSee( 'class="ecommerce-admin__sidebar"', false );

    // The CMS layout ships no Livewire assets or toasts, so the page pushes them onto its stacks.
    expect( $response->getContent() )->toContain( 'apui-toaster' );

    expect( substr_count( $response->getContent(), 'livewire.js' ) + substr_count( $response->getContent(), 'livewire.min.js' ) )
        ->toBe( 1 );
} );

it( 'shares the resolved layout with every page view', function ( bool $installed, string $layout ): void {
    CmsFramework::fake( $installed );

    $view = view( 'ecommerce-admin::pages.dashboard' );
    View::callComposer( $view );

    expect( $view->getData()['ecommerceAdminLayout'] )->toBe( $layout )
        ->and( $view->getData()['ecommerceAdminPushesAssets'] )->toBe( $installed );
} )->with( [
    'standalone' => [ false, 'ecommerce-admin::layouts.app' ],
    'cms'        => [ true, 'cms::admin.layouts.app' ],
] );

it( 'probes the CMSFramework namespace with its exact casing', function (): void {
    expect( CmsFramework::PROBE )->toBe( 'ArtisanPackUI\\CMSFramework\\Modules\\Admin\\Managers\\AdminMenuManager' )
        ->and( CmsFramework::isInstalled() )->toBe( class_exists( CmsFramework::PROBE ) );
} );

it( 'lists the screens the user can open on the dashboard', function (): void {
    $this->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->assertOk()
        ->assertSee( 'Dashboard' );
} );

it( 'loads the host Vite build in both layouts', function ( bool $cms ): void {
    CmsFramework::fake( $cms );
    View::addNamespace( 'cms', __DIR__ . '/../../Fixtures/views/cms' );

    $hot = tempnam( sys_get_temp_dir(), 'vite-hot' );
    file_put_contents( $hot, 'http://localhost:5173' );
    Illuminate\Support\Facades\Vite::useHotFile( $hot );

    $html = $this->get( route( 'artisanpack.ecommerce.admin.orders.index' ) )->assertOk()->getContent();

    expect( $html )->toContain( 'http://localhost:5173/resources/css/app.css' );

    unlink( $hot );
} )->with( [
    'standalone' => [ false ],
    'cms'        => [ true ],
] );

it( 'skips the Vite build when the host filters out every entry', function (): void {
    CmsFramework::fake( true );
    View::addNamespace( 'cms', __DIR__ . '/../../Fixtures/views/cms' );

    $hot = tempnam( sys_get_temp_dir(), 'vite-hot' );
    file_put_contents( $hot, 'http://localhost:5173' );
    Illuminate\Support\Facades\Vite::useHotFile( $hot );
    addFilter( 'ap.ecommerceAdminLivewire.layout.viteEntries', static fn (): array => [] );

    expect( $this->get( route( 'artisanpack.ecommerce.admin.orders.index' ) )->assertOk()->getContent() )
        ->not->toContain( 'localhost:5173' );

    removeAllFilters( 'ap.ecommerceAdminLivewire.layout.viteEntries' );
    unlink( $hot );
} );

it( 'puts the CSP nonce on its inline scripts', function (): void {
    grantAbilities( [ 'order.viewAny' ] );
    $this->actingAs( makeUser() );
    Illuminate\Support\Facades\Vite::useCspNonce( 'nonce-abc123' );

    $html = $this->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )->assertOk()->getContent();

    expect( substr_count( $html, 'nonce="nonce-abc123"' ) )->toBeGreaterThanOrEqual( 2 )
        ->and( $html )->toMatch( '/<script data-ecommerce-admin-rate-limit\s+nonce="nonce-abc123"/' );
} );

it( 'puts the store name in the sidebar and the breadcrumbs, palette and theme toggle in the top bar', function (): void {
    CmsFramework::fake( false );
    config()->set( 'app.name', 'Northwind Goods' );

    $html = $this->get( route( 'artisanpack.ecommerce.admin.orders.index' ) )
        ->assertOk()
        ->assertSee( 'class="ecommerce-admin__brand"', false )
        ->assertSee( 'Northwind Goods' )
        ->assertSee( 'apui-breadcrumbs', false )
        ->assertSee( 'aria-current="page"', false )
        ->assertSee( 'Switch to dark theme' )
        ->getContent();

    // Store admin › Orders: the root links to the dashboard, the page is current.
    expect( $html )->toContain( 'href="' . route( 'artisanpack.ecommerce.admin.dashboard' ) . '"' )
        ->and( $html )->toMatch( '/aria-current="page"[^>]*>\s*Orders/' );
} );

it( 'shows only the page in the dashboard\'s breadcrumbs', function (): void {
    CmsFramework::fake( false );

    $html = $this->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )->assertOk()->getContent();

    expect( preg_match( '/<nav[^>]*ecommerce-admin__breadcrumbs.*?<\/nav>/s', $html, $trail ) )->toBe( 1 )
        ->and( $trail[0] )->not->toContain( 'Store admin' )
        ->and( $trail[0] )->toContain( 'Dashboard' );
} );

it( 'makes "Skip to content" the first tab stop, landing on the main landmark', function (): void {
    CmsFramework::fake( false );

    $html  = $this->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )->assertOk()->getContent();
    $body  = Illuminate\Support\Str::after( $html, '<body' );
    $first = (int) strpos( $body, '<a ' );

    expect( substr( $body, $first, 120 ) )->toContain( 'data-part="skip-link" href="#ecommerce-admin-shell-main"' )
        ->and( strpos( $body, '<button' ) )->toBeGreaterThan( $first )
        ->and( $body )->toContain( '<main data-scope="app-shell" data-part="main" id="ecommerce-admin-shell-main"' );
} );

it( 'turns the sidebar into a drawer behind the menu button on phones', function (): void {
    CmsFramework::fake( false );

    $this->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->assertOk()
        ->assertSee( 'aria-controls="ecommerce-admin-drawer"', false )
        ->assertSee( 'aria-label="Open navigation"', false )
        ->assertSee( 'x-trap.inert.noscroll="navOpen"', false )
        ->assertSee( 'aria-label="Close navigation"', false )
        ->assertSee( 'x-on:keydown.escape.window="navOpen && closeNav()"', false )
        ->assertSee( 'x-bind:data-collapsed="rail ? \'\' : null"', false );
} );
