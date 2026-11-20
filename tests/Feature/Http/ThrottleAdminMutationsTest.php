<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Http\Middleware\ThrottleAdminMutations;
use Composer\InstalledVersions;
use Illuminate\Http\Request;
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
function throttledUpdate( $test, $user, ?array $calls = null, array $updates = [] ): Closure
{
    // A call that writes, unless the test says otherwise.
    $calls ??= [ [ 'path' => '', 'method' => 'orderBroadcast', 'params' => [] ] ];

    $page = $test->actingAs( $user )->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )->assertOk();

    // The dashboard's snapshot: the sidebar's navigation component renders
    // first. Matched on the snapshot's own name, which Livewire 3 and 4 both
    // carry (only Livewire 4 renders a wire:name attribute).
    preg_match_all( '/wire:snapshot="([^"]+)"/', $page->getContent(), $matches );
    $snapshot = collect( $matches[1] )
        ->map( static fn ( string $encoded ): string => htmlspecialchars_decode( $encoded ) )
        ->first( static fn ( string $decoded ): bool => 'artisanpack-ecommerce-admin-dashboard' === ( json_decode( $decoded, true )['memo']['name'] ?? null ) );

    return fn () => tap( $test, static fn () => app( 'livewire' )->flushState() )->actingAs( $user )->withHeaders( [ 'X-Livewire' => '1' ] )->postJson(
        app( 'livewire' )->getUpdateUri(),
        [ 'components' => [ [ 'snapshot' => $snapshot, 'calls' => $calls, 'updates' => $updates ] ] ],
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

it( 'does not count polls and other reads toward the limit', function (): void {
    $user = makeUser();

    // Livewire 3 sends `wire:poll` and `$refresh` as a commit with no calls;
    // Livewire 4 sends a `$refresh` call (which Livewire 3 rejects).
    $reads = [ throttledUpdate( $this, $user, [] ) ];

    if ( str_starts_with( (string) InstalledVersions::getVersion( 'livewire/livewire' ), '4.' ) ) {
        $reads[] = throttledUpdate( $this, $user, [ [ 'path' => '', 'method' => '$refresh', 'params' => [] ] ] );
    }

    foreach ( $reads as $read ) {
        foreach ( range( 1, 3 ) as $attempt ) {
            $read()->assertOk();
        }
    }

    $write = throttledUpdate( $this, $user, [ [ 'path' => '', 'method' => 'orderBroadcast', 'params' => [] ] ] );

    $write()->assertOk();
    $write()->assertOk();
    $write()->assertStatus( 429 );
} );

it( 'tells reads from writes by the calls and updates each component sends', function ( array $components, bool $read ): void {
    expect( ThrottleAdminMutations::onlyReads( Request::create( '/livewire/update', 'POST', [ 'components' => $components ] ) ) )->toBe( $read );
} )->with( [
    'a Livewire 3 poll (no calls)'     => [ [ [ 'calls' => [], 'updates' => [] ] ], true ],
    'a Livewire 4 poll ($refresh)'     => [ [ [ 'calls' => [ [ 'method' => '$refresh' ] ], 'updates' => [] ] ], true ],
    'sorting and paging'               => [ [ [ 'calls' => [ [ 'method' => 'sort' ], [ 'method' => 'gotoPage' ] ], 'updates' => [] ] ], true ],
    'a property update'                => [ [ [ 'calls' => [], 'updates' => [ 'search' => 'mug' ] ] ], false ],
    'any other call'                   => [ [ [ 'calls' => [ [ 'method' => 'save' ] ], 'updates' => [] ] ], false ],
    'one writing component of two'     => [ [ [ 'calls' => [], 'updates' => [] ], [ 'calls' => [ [ 'method' => 'delete' ] ], 'updates' => [] ] ], false ],
    'no components'                    => [ [], false ],
] );

it( 'weighs an update by its writing calls and updating components', function ( array $components, int $weight ): void {
    expect( ThrottleAdminMutations::weight( Request::create( '/livewire/update', 'POST', [ 'components' => $components ] ) ) )->toBe( $weight );
} )->with( [
    'a read'                                      => [ [ [ 'calls' => [ [ 'method' => 'sort' ] ], 'updates' => [] ] ], 0 ],
    'one write'                                   => [ [ [ 'calls' => [ [ 'method' => 'save' ] ], 'updates' => [] ] ], 1 ],
    'ten writes in one component (Livewire 3)'    => [ [ [ 'calls' => array_fill( 0, 10, [ 'path' => '', 'method' => 'delete', 'params' => [ 1 ] ] ), 'updates' => [] ] ], 10 ],
    'ten writes in one component (Livewire 4)'    => [ [ [ 'calls' => array_fill( 0, 10, [ 'method' => 'delete', 'params' => [ 1 ], 'metadata' => [] ] ), 'updates' => [] ] ], 10 ],
    'updates plus a write, and a read'            => [ [ [ 'calls' => [ [ 'method' => 'save' ], [ 'method' => 'sort' ] ], 'updates' => [ 'name' => 'Mug' ] ] ], 2 ],
    'writes across components'                    => [ [ [ 'calls' => [ [ 'method' => 'save' ] ], 'updates' => [] ], [ 'calls' => [ [ 'method' => 'delete' ] ], 'updates' => [ 'x' => 1 ] ] ], 3 ],
    'a Livewire 4 $set with its update'           => [ [ [ 'calls' => [ [ 'method' => '$set' ] ], 'updates' => [ 'search' => 'mug' ] ] ], 1 ],
    'a bundled child $commit with no updates'     => [ [ [ 'calls' => [ [ 'method' => 'save' ] ], 'updates' => [] ], [ 'calls' => [ [ 'method' => '$commit' ] ], 'updates' => [] ] ], 1 ],
    'a malformed component'                       => [ [ 'nope' ], 1 ],
    'no components'                               => [ [], 1 ],
] );

it( 'treats lazy loads and the admin\'s refresh events as reads, and other dispatches as writes', function ( mixed $call, bool $read ): void {
    expect( ThrottleAdminMutations::isReadCall( $call ) )->toBe( $read );
} )->with( [
    'a lazy component'                   => [ [ 'method' => '__lazyLoad', 'params' => [ 'encoded' ] ], true ],
    'a lazy island'                      => [ [ 'method' => '__lazyLoadIsland', 'params' => [] ], true ],
    'an admin broadcast refresh'         => [ [ 'method' => '__dispatch', 'params' => [ 'echo-private:ecommerce.admin,.orderStatusChanged', [] ] ], true ],
    'the sidebar badge refresh'          => [ [ 'method' => '__dispatch', 'params' => [ 'ecommerce-admin-nav-refresh', [] ] ], true ],
    'another channel'                    => [ [ 'method' => '__dispatch', 'params' => [ 'echo-private:ecommerce.administrator,.x', [] ] ], false ],
    'any other event'                    => [ [ 'method' => '__dispatch', 'params' => [ 'order-saved', [] ] ], false ],
    'an event name that is not a string' => [ [ 'method' => '__dispatch', 'params' => [ [ 'x' ], [] ] ], false ],
    'not an array'                       => [ 'save', false ],
] );

it( 'counts every writing call in one request against the limit', function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.admin.mutate.per_user', 12 );
    $user  = makeUser();
    $calls = static fn ( int $count ): array => array_fill( 0, $count, [ 'path' => '', 'method' => 'orderBroadcast', 'params' => [] ] );

    throttledUpdate( $this, $user, $calls( 10 ) )()->assertOk();
    throttledUpdate( $this, $user, $calls( 3 ) )()->assertStatus( 429 );
    throttledUpdate( $this, $user, $calls( 2 ) )()->assertOk();
    throttledUpdate( $this, $user, $calls( 1 ) )()->assertStatus( 429 );
} );

it( 'rejects a single request that weighs more than the whole budget', function (): void {
    $calls = array_fill( 0, 3, [ 'path' => '', 'method' => 'orderBroadcast', 'params' => [] ] );

    throttledUpdate( $this, makeUser(), $calls )()->assertStatus( 429 );
} );
