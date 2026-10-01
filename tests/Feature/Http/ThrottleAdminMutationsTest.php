<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Http\Middleware\ThrottleAdminMutations;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

beforeEach( function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.admin.mutate.per_user', 2 );
    grantAbilities( [ 'order.viewAny' ] );
} );

/**
 * Mounts the dashboard and returns a closure that sends one Livewire update.
 *
 * Livewire applies persistent middleware once per route per request cycle;
 * flushing its state makes each test request behave like a separate one.
 */
function throttledUpdate( $test, $user ): Closure
{
    $page = $test->actingAs( $user )->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )->assertOk();

    preg_match( '/wire:snapshot="([^"]+)"/', $page->getContent(), $matches );
    $snapshot = htmlspecialchars_decode( $matches[1] );

    return fn () => tap( $test, static fn () => app( 'livewire' )->flushState() )->actingAs( $user )->withHeaders( [ 'X-Livewire' => '1' ] )->postJson(
        app( 'livewire' )->getUpdateUri(),
        [ 'components' => [ [ 'snapshot' => $snapshot, 'calls' => [], 'updates' => [] ] ] ],
    );
}

it( 'registers the limiter as Livewire persistent middleware and on the admin routes', function (): void {
    expect( app( PersistentMiddleware::class )->getPersistentMiddleware() )->toContain( ThrottleAdminMutations::class )
        ->and( Illuminate\Support\Facades\Route::getRoutes()->getByName( 'artisanpack.ecommerce.admin.orders.index' )->gatherMiddleware() )
        ->toContain( ThrottleAdminMutations::ALIAS );
} );

it( 'answers 429 with the retry time once the user exceeds ecommerce.admin.mutate', function (): void {
    $update = throttledUpdate( $this, makeUser() );

    $update()->assertOk();
    $update()->assertOk();

    $response = $update()->assertStatus( 429 );

    expect( (int) $response->headers->get( 'Retry-After' ) )->toBeGreaterThan( 0 )->toBeLessThanOrEqual( 60 )
        ->and( $response->json( 'retry_after' ) )->toBe( (int) $response->headers->get( 'Retry-After' ) )
        ->and( $response->json( 'message' ) )->toContain( 'Try again in' );
} );

it( 'counts each user separately', function (): void {
    $first  = throttledUpdate( $this, makeUser() );
    $second = throttledUpdate( $this, makeUser() );

    $first();
    $first();
    $first()->assertStatus( 429 );

    $second()->assertOk();
} );

it( 'does not count page loads', function (): void {
    $user = makeUser();

    foreach ( range( 1, 4 ) as $attempt ) {
        $this->actingAs( $user )->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )->assertOk();
    }

    throttledUpdate( $this, $user )()->assertOk();
} );

it( 'passes through when the engine limiter is not registered', function (): void {
    $update = throttledUpdate( $this, makeUser() );

    app()->instance( Illuminate\Cache\RateLimiter::class, new Illuminate\Cache\RateLimiter( app( 'cache' )->driver( 'array' ) ) );

    foreach ( range( 1, 3 ) as $attempt ) {
        $update()->assertOk();
    }
} );

it( 'turns a 429 into a toast instead of the Livewire error modal', function (): void {
    $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->assertOk()
        ->assertSee( 'data-ecommerce-admin-rate-limit', false )
        ->assertSee( "window.Livewire.hook( 'request'", false );
} );

afterEach( function (): void {
    RateLimiter::clear( 'ecommerce.admin.mutate' );
} );
