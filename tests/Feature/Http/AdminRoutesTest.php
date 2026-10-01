<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Http\Controllers\AdminScreenController;
use ArtisanPackUI\EcommerceAdminLivewire\Http\Middleware\EnsureAdminAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

it( 'registers the dashboard under the prefix, name prefix, and middleware', function (): void {
    $route = Route::getRoutes()->getByName( 'artisanpack.ecommerce.admin.dashboard' );

    expect( $route )->not->toBeNull()
        ->and( $route->uri() )->toBe( 'ecommerce-admin' )
        ->and( $route->gatherMiddleware() )->toBe( [ 'web', 'auth', EnsureAdminAccess::ALIAS ] )
        ->and( $route->getActionName() )->toBe( AdminScreenController::class . '@dashboard' );
} );

it( 'uses controller actions, never closures', function (): void {
    $routes = collect( Route::getRoutes()->getRoutes() )
        ->filter( static fn ( $route ): bool => str_starts_with( (string) $route->getName(), 'artisanpack.ecommerce.admin.' ) );

    expect( $routes )->not->toBeEmpty();

    $routes->each( static function ( $route ): void {
        expect( $route->getActionName() )->not->toBe( 'Closure' )
            ->toStartWith( AdminScreenController::class . '@' );
    } );
} );

it( 'follows the configured route prefix and middleware', function (): void {
    config()->set( 'artisanpack.ecommerce-admin-livewire.admin.route_prefix', '/store/admin/' );
    config()->set( 'artisanpack.ecommerce-admin-livewire.admin.middleware', [ 'web', 'auth', 'verified' ] );

    require __DIR__ . '/../../../routes/admin.php';

    $route = collect( Route::getRoutes()->getRoutes() )
        ->first( static fn ( $route ): bool => 'store/admin' === $route->uri() );

    expect( $route )->not->toBeNull()
        ->and( $route->getName() )->toBe( 'artisanpack.ecommerce.admin.dashboard' )
        ->and( $route->gatherMiddleware() )->toBe( [ 'web', 'auth', 'verified', EnsureAdminAccess::ALIAS ] );
} );

it( 'rejects guests', function (): void {
    $this->getJson( route( 'artisanpack.ecommerce.admin.dashboard' ) )->assertUnauthorized();
} );

it( 'forbids a signed-in user with no ecommerce abilities', function (): void {
    $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->assertForbidden();
} );

it( 'lets in a user who can see at least one nav entry', function (): void {
    grantAbilities( [ 'review.viewAny' ] );

    $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->assertOk();
} );

it( 'lets in a user granted the umbrella ecommerce.admin gate', function (): void {
    $user = makeUser();
    grantAdmin( $user );

    $this->actingAs( $user )
        ->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->assertOk();
} );

it( 'registers the access check as Livewire persistent middleware', function (): void {
    expect( app( PersistentMiddleware::class )->getPersistentMiddleware() )
        ->toContain( EnsureAdminAccess::class );
} );

/**
 * Mounts the dashboard as the user and returns a closure that sends a
 * Livewire update request for it.
 */
function dashboardUpdateRequest( $test, $user ): Closure
{
    $page = $test->actingAs( $user )->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )->assertOk();

    preg_match( '/wire:snapshot="([^"]+)"/', $page->getContent(), $matches );
    $snapshot = htmlspecialchars_decode( $matches[1] );

    return fn () => $test->actingAs( $user )->withHeaders( [ 'X-Livewire' => '1' ] )->postJson(
        app( 'livewire' )->getUpdateUri(),
        [ 'components' => [ [ 'snapshot' => $snapshot, 'calls' => [], 'updates' => [] ] ] ],
    );
}

it( 'accepts Livewire update requests while the user keeps access', function (): void {
    grantAbilities( [ 'order.viewAny' ] );

    dashboardUpdateRequest( $this, makeUser() )()->assertOk();
} );

it( 're-checks access on Livewire update requests', function (): void {
    grantAbilities( [ 'order.viewAny' ] );
    $update = dashboardUpdateRequest( $this, makeUser() );

    Gate::define( 'ecommerce.order.viewAny', static fn (): bool => false );

    $update()->assertForbidden();
} );
