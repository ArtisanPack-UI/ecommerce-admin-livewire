<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\User;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the package, the engine, and Livewire in Testbench.
| Unit tests run without a Laravel application.
|
*/

pest()->extend( Tests\TestCase::class )
    ->use( RefreshDatabase::class )
    ->in( 'Feature' );

/*
| Browser tests (Pest 4 browser plugin) run the admin in a real browser
| against a seeded demo store, with the asset bundle built by `npm run
| build:browser`. CI runs them in their own job; the unit / feature job
| excludes the `browser` group.
*/
pest()->extend( Tests\BrowserTestCase::class )
    ->use( RefreshDatabase::class )
    ->group( 'browser' )
    ->in( 'Browser' );

pest()->browser()->timeout( 10_000 );

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

if ( ! function_exists( 'makeUser' ) ) {
    /**
     * Creates a host-app user.
     *
     * @param  array<string, mixed>  $attributes  Attribute overrides.
     */
    function makeUser( array $attributes = [] ): User
    {
        static $sequence = 0;
        $sequence++;

        return User::query()->create( $attributes + [
            'name'     => 'User ' . $sequence,
            'email'    => 'user' . $sequence . '-' . uniqid() . '@example.test',
            'password' => 'secret',
        ] );
    }
}

if ( ! function_exists( 'grantAbilities' ) ) {
    /**
     * Defines `ecommerce.{ability}` gates that allow every user.
     *
     * Undefined abilities stay denied (the engine's default), as long as the
     * umbrella `ecommerce.admin` gate is not defined.
     *
     * @param  array<int, string>  $abilities  `{resource}.{action}` abilities.
     */
    function grantAbilities( array $abilities ): void
    {
        foreach ( $abilities as $ability ) {
            Gate::define( 'ecommerce.' . $ability, static fn (): bool => true );
        }
    }
}

if ( ! function_exists( 'grantAdmin' ) ) {
    /**
     * Defines the umbrella `ecommerce.admin` gate for the given users only.
     *
     * @param  User  ...$users  The users to grant full access.
     */
    function grantAdmin( User ...$users ): void
    {
        $ids = array_map( static fn ( User $user ): int => (int) $user->getKey(), $users );

        Gate::define( 'ecommerce.admin', static fn ( $user ): bool => in_array( (int) $user->getKey(), $ids, true ) );
    }
}

if ( ! function_exists( 'fakeAdminRoutes' ) ) {
    /**
     * Registers stand-in routes for admin screens that have not shipped, so
     * their nav entries become visible.
     *
     * @param  array<int, string>  $names  Route names relative to `artisanpack.ecommerce.admin.`.
     */
    function fakeAdminRoutes( array $names ): void
    {
        foreach ( $names as $name ) {
            Illuminate\Support\Facades\Route::get( 'fake-admin/' . str_replace( '.', '/', $name ) . '/{report?}', static fn (): string => $name )
                ->name( 'artisanpack.ecommerce.admin.' . $name );
        }

        Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();
    }
}

if ( ! function_exists( 'sentToasts' ) ) {
    /**
     * The JavaScript a Livewire test component queued (toasts are sent this
     * way), flattened to one string for `toContain` checks.
     *
     * @param  Livewire\Features\SupportTesting\Testable  $component  The component under test.
     */
    function sentToasts( $component ): string
    {
        return (string) json_encode( $component->effects['xjs'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }
}
