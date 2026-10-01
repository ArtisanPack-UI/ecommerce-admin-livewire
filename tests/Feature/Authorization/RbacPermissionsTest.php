<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\CmsFramework;
use ArtisanPackUI\EcommerceAdminLivewire\Support\RbacPermissions;
use Illuminate\Support\Facades\Gate;

beforeEach( function (): void {
    require_once __DIR__ . '/../../Fixtures/cms-rbac-helpers.php';

    $GLOBALS['fakeRbac'] = [ 'roles' => [], 'permissions' => [], 'grants' => [] ];

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

it( 'lists every engine ability plus the inventory, sub-status, settings, and report abilities', function (): void {
    expect( RbacPermissions::abilities() )
        ->toContain( 'product.viewAny', 'order.edit-fulfilled', 'refund.create', 'licenseKey.revoke', 'review.moderate', 'kanbanCard.move' )
        ->toContain( 'inventory.viewAny', 'inventory.adjust', 'orderSubstatus.create', 'settings.update', 'report.view' );
} );

it( 'registers a permission per ability and a shop-manager role holding them all', function (): void {
    $count = RbacPermissions::register();

    $slugs = array_map( RbacPermissions::permissionSlug( ... ), RbacPermissions::abilities() );

    expect( $count )->toBe( count( RbacPermissions::abilities() ) )
        ->and( array_keys( $GLOBALS['fakeRbac']['permissions'] ) )->toBe( $slugs )
        ->and( $GLOBALS['fakeRbac']['roles'] )->toBe( [ 'shop-manager' => 'Shop manager' ] )
        ->and( $GLOBALS['fakeRbac']['grants']['shop-manager'] )->toBe( $slugs )
        ->and( $GLOBALS['fakeRbac']['permissions']['ecommerce.order.edit-fulfilled'] )->toBe( 'Ecommerce: edit fulfilled order' );
} );

it( 'is idempotent through the sync command', function (): void {
    $this->artisan( 'ecommerce-admin:sync-permissions' )
        ->expectsOutputToContain( 'Registered ' . count( RbacPermissions::abilities() ) . ' ecommerce permissions and the shop-manager role.' )
        ->assertSuccessful();

    $this->artisan( 'ecommerce-admin:sync-permissions' )->assertSuccessful();

    expect( $GLOBALS['fakeRbac']['permissions'] )->toHaveCount( count( RbacPermissions::abilities() ) );
} );

it( 'lets an RBAC permission grant the matching engine ability', function (): void {
    $user = makeUser();

    // cms-framework's RBAC answers registered permission slugs from Gate::before.
    Gate::before( static fn ( $actor, string $ability ): ?bool => 'ecommerce.order.refund' === $ability ? true : null );

    RbacPermissions::grantThroughPermissions();

    expect( Authorization::allows( $user, 'order.refund', Order::factory()->create() ) )->toBeTrue()
        ->and( Authorization::allows( $user, 'order.cancel' ) )->toBeFalse();

    foreach ( RbacPermissions::abilities() as $ability ) {
        removeAllFilters( 'ap.ecommerce.abilities.' . $ability );
    }
} );

it( 'leaves a subject-specific deny from a host-defined gate alone', function (): void {
    $user  = makeUser();
    $own   = Order::factory()->create( [ 'email' => 'mine@example.test' ] );
    $other = Order::factory()->create( [ 'email' => 'theirs@example.test' ] );

    Gate::define( 'ecommerce.order.view', static fn ( $actor, ?Order $order = null ): bool => null === $order || 'mine@example.test' === $order->email );

    RbacPermissions::grantThroughPermissions();

    expect( Authorization::allows( $user, 'order.view', $own ) )->toBeTrue()
        ->and( Authorization::allows( $user, 'order.view', $other ) )->toBeFalse();

    foreach ( RbacPermissions::abilities() as $ability ) {
        removeAllFilters( 'ap.ecommerce.abilities.' . $ability );
    }
} );

it( 'never takes access away from the umbrella gate', function (): void {
    $user = makeUser();
    grantAdmin( $user );

    RbacPermissions::grantThroughPermissions();

    expect( Authorization::allows( $user, 'order.cancel' ) )->toBeTrue();

    foreach ( RbacPermissions::abilities() as $ability ) {
        removeAllFilters( 'ap.ecommerce.abilities.' . $ability );
    }
} );

it( 'syncs permissions from the install command when cms-framework is present', function (): void {
    $this->artisan( 'ecommerce-admin:install' )
        ->expectsOutputToContain( 'Assign the shop-manager role' )
        ->assertSuccessful();

    expect( $GLOBALS['fakeRbac']['roles'] )->toHaveKey( 'shop-manager' );

    Illuminate\Support\Facades\File::delete( config_path( 'artisanpack/ecommerce-admin-livewire.php' ) );
} );
