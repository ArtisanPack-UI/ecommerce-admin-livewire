<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Auth\AbilityCatalog;
use ArtisanPackUI\Ecommerce\Auth\CmsFrameworkPermissions;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\CmsFramework;
use ArtisanPackUI\EcommerceAdminLivewire\Support\RbacPermissions;
use Illuminate\Support\Facades\Gate;

beforeEach( function (): void {
    require_once __DIR__ . '/../../Fixtures/cms-rbac-helpers.php';

    $GLOBALS['fakeRbac'] = [ 'roles' => [], 'roleCalls' => [], 'permissions' => [], 'grants' => [] ];

    CmsFramework::fake( true );
} );

it( 'is unavailable without cms-framework even when the helpers exist', function (): void {
    CmsFramework::fake( false );

    expect( RbacPermissions::available() )->toBeFalse()
        ->and( RbacPermissions::register() )->toBe( 0 );

    $this->artisan( 'ecommerce-admin:sync-permissions' )
        ->expectsOutputToContain( 'cms-framework is not installed' )
        ->assertSuccessful();
} );

it( 'is unavailable when the engine\'s cms-framework bridge is switched off', function (): void {
    config()->set( 'artisanpack.ecommerce.cms_framework.enabled', false );

    expect( RbacPermissions::available() )->toBeFalse();
} );

it( 'lists exactly the engine catalog, including satellite abilities', function (): void {
    addFilter( 'ap.ecommerce.abilities.catalog', static fn ( array $catalog ): array => $catalog + [ 'loyalty' => [ 'viewAny' ] ] );

    expect( RbacPermissions::abilities() )
        ->toHaveCount( count( AbilityCatalog::abilities() ) )
        ->toContain( 'product.viewAny', 'order.edit-fulfilled', 'licenseKey.revoke', 'orderSubstatus.view', 'report.view', 'loyalty.viewAny' );

    removeAllFilters( 'ap.ecommerce.abilities.catalog' );
} );

it( 'syncs through the engine: one shop-manager role holding every catalog permission', function (): void {
    $count = RbacPermissions::register();

    expect( $count )->toBe( count( AbilityCatalog::abilities() ) )
        ->and( $GLOBALS['fakeRbac']['roleCalls'] )->toBe( [ 'shop-manager' ] )
        ->and( array_keys( $GLOBALS['fakeRbac']['permissions'] ) )->toBe( AbilityCatalog::abilities() )
        ->and( $GLOBALS['fakeRbac']['permissions'] )->toHaveKey( 'ecommerce.orderSubstatus.view' )
        ->and( $GLOBALS['fakeRbac']['grants']['shop-manager'] )->toBe( AbilityCatalog::abilities() )
        ->and( RbacPermissions::ROLE )->toBe( CmsFrameworkPermissions::ROLE );
} );

it( 'keeps the old sync command as a deprecated alias of the engine command', function (): void {
    $this->artisan( 'ecommerce-admin:sync-permissions' )
        ->expectsOutputToContain( 'ecommerce-admin:sync-permissions is deprecated' )
        ->expectsOutputToContain( 'Synced ' . count( AbilityCatalog::abilities() ) . ' ecommerce permission(s) and the shop-manager role.' )
        ->assertSuccessful();

    $this->artisan( 'ecommerce-admin:sync-permissions' )->assertSuccessful();

    expect( $GLOBALS['fakeRbac']['permissions'] )->toHaveCount( count( AbilityCatalog::abilities() ) );
} );

it( 'lets an RBAC permission grant the matching engine ability through the engine gates', function (): void {
    $user = makeUser();

    app( CmsFrameworkPermissions::class )->defineGates();

    // cms-framework's RBAC answers registered permission slugs from Gate::before.
    Gate::before( static fn ( $actor, string $ability ): ?bool => 'ecommerce.order.refund' === $ability ? true : null );

    expect( Authorization::allows( $user, 'order.refund', Order::factory()->create() ) )->toBeTrue()
        ->and( Authorization::allows( $user, 'order.cancel' ) )->toBeFalse();
} );

it( 'leaves a subject-specific deny from a host-defined gate alone', function (): void {
    $user  = makeUser();
    $own   = Order::factory()->create( [ 'email' => 'mine@example.test' ] );
    $other = Order::factory()->create( [ 'email' => 'theirs@example.test' ] );

    Gate::define( 'ecommerce.order.view', static fn ( $actor, ?Order $order = null ): bool => null === $order || 'mine@example.test' === $order->email );

    app( CmsFrameworkPermissions::class )->defineGates();

    expect( Authorization::allows( $user, 'order.view', $own ) )->toBeTrue()
        ->and( Authorization::allows( $user, 'order.view', $other ) )->toBeFalse();
} );

it( 'never takes access away from the umbrella gate', function (): void {
    $user = makeUser();
    grantAdmin( $user );

    app( CmsFrameworkPermissions::class )->defineGates();

    expect( Authorization::allows( $user, 'order.cancel' ) )->toBeTrue();
} );

it( 'syncs permissions from the install command when cms-framework is present', function (): void {
    $this->artisan( 'ecommerce-admin:install' )
        ->expectsOutputToContain( 'Assign the shop-manager role' )
        ->assertSuccessful();

    expect( $GLOBALS['fakeRbac']['roles'] )->toHaveKey( 'shop-manager' )
        ->and( $GLOBALS['fakeRbac']['roleCalls'] )->toBe( [ 'shop-manager' ] );

    Illuminate\Support\Facades\File::delete( config_path( 'artisanpack/ecommerce-admin-livewire.php' ) );
} );
