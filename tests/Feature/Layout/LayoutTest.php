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
        ->assertSee( 'ecommerce-admin__sidebar', false )
        ->assertSee( 'ecommerce-admin-drawer', false )
        ->assertSee( '<title>Dashboard &middot;', false )
        ->assertSee( 'Store admin' )
        ->assertSee( 'Skip to content' )
        ->assertSee( 'window.toast', false )
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
        ->assertDontSee( 'ecommerce-admin__sidebar', false );

    // The CMS layout ships no Livewire assets or toasts, so the page pushes them onto its stacks.
    expect( $response->getContent() )->toContain( 'window.toast' );

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
