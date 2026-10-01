<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Support\CmsFramework;
use ArtisanPackUI\EcommerceAdminLivewire\Support\CmsMenu;

beforeEach( function (): void {
    fakeAdminRoutes( [ 'orders.index', 'reviews.index', 'products.index' ] );
    grantAbilities( [ 'order.viewAny', 'product.viewAny' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    removeAllFilters( CmsMenu::FILTER );
} );

it( 'does not subscribe while cms-framework is absent', function (): void {
    CmsFramework::fake( false );

    expect( CmsMenu::subscribe() )->toBeFalse()
        ->and( applyFilters( CmsMenu::FILTER, [] ) )->toBe( [] );
} );

it( 'does not subscribe when auto_register_cms_nav is off', function (): void {
    CmsFramework::fake( true );
    config()->set( 'artisanpack.ecommerce-admin-livewire.admin.auto_register_cms_nav', false );

    expect( CmsMenu::subscribe() )->toBeFalse()
        ->and( applyFilters( CmsMenu::FILTER, [] ) )->toBe( [] );
} );

it( 'does not subscribe when the admin routes are disabled', function (): void {
    CmsFramework::fake( true );
    config()->set( 'artisanpack.ecommerce-admin-livewire.admin.routes_enabled', false );

    expect( CmsMenu::subscribe() )->toBeFalse();
} );

it( 'injects the visible entries into the cms-framework menu', function (): void {
    CmsFramework::fake( true );

    expect( CmsMenu::subscribe() )->toBeTrue();

    $menu = applyFilters( CmsMenu::FILTER, [ 'dashboard' => [ 'slug' => 'dashboard', 'label' => 'Dashboard' ] ] );

    expect( $menu )->toHaveKeys( [ 'dashboard', 'ecommerce-dashboard', 'ecommerce-orders', 'ecommerce-catalog' ] )
        ->and( $menu['ecommerce-dashboard']['label'] )->toBe( 'Store' )
        ->and( $menu['ecommerce-dashboard']['url'] )->toBe( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->and( $menu['ecommerce-orders']['title'] )->toBe( 'Orders' )
        ->and( array_keys( $menu['ecommerce-orders']['items'] ) )->toBe( [ 'ecommerce-orders' ] )
        ->and( array_keys( $menu['ecommerce-catalog']['items'] ) )->toBe( [ 'ecommerce-products' ] )
        ->and( $menu['ecommerce-catalog']['items']['ecommerce-products'] )->not->toHaveKey( 'permission' );
} );

it( 'lets an entry the host already has win', function (): void {
    $menu = CmsMenu::injectInto( [ 'ecommerce-dashboard' => [ 'label' => 'Shop' ] ] );

    expect( $menu['ecommerce-dashboard']['label'] )->toBe( 'Shop' )
        ->and( $menu['ecommerce-dashboard']['url'] )->toBe( route( 'artisanpack.ecommerce.admin.dashboard' ) );
} );

it( 'injects nothing for a user without access', function (): void {
    $this->actingAs( makeUser() );
    Illuminate\Support\Facades\Gate::define( 'ecommerce.order.viewAny', static fn (): bool => false );
    Illuminate\Support\Facades\Gate::define( 'ecommerce.product.viewAny', static fn (): bool => false );

    expect( CmsMenu::injectInto( [] ) )->toBe( [] );
} );
