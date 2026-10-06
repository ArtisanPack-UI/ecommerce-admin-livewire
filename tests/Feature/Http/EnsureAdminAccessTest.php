<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Registries\AdminMenuRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Http\Middleware\EnsureAdminAccess;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach( function (): void {
    $this->badgeRuns = 0;
    $runs            = &$this->badgeRuns;

    app( AdminMenuRegistry::class )->register( 'loyalty', [
        'label'      => 'Loyalty',
        'route'      => 'https://example.test/loyalty',
        'permission' => 'loyalty.viewAny',
        'badge'      => static function () use ( &$runs ): int {
            ++$runs;

            return 2;
        },
    ] );
} );

/**
 * Runs the access middleware for a user.
 */
function runAdminAccess( ?Tests\Fixtures\User $user ): void
{
    $request = Request::create( '/ecommerce-admin' );
    $request->setUserResolver( static fn () => $user );

    ( new EnsureAdminAccess() )->handle( $request, static fn () => response( 'ok' ) );
}

it( 'checks access without resolving satellite badges', function (): void {
    grantAbilities( [ 'order.viewAny' ] );

    runAdminAccess( makeUser() );
    runAdminAccess( makeUser() );

    expect( $this->badgeRuns )->toBe( 0 );
} );

it( 'resolves satellite badges at most once per request', function (): void {
    grantAbilities( [ 'order.viewAny', 'loyalty.viewAny' ] );
    $user = makeUser();

    runAdminAccess( $user );
    AdminNav::visibleItems( $user );
    AdminNav::grouped( $user );
    AdminNav::items();

    expect( $this->badgeRuns )->toBe( 1 );
} );

it( 'lets a user in through a satellite entry alone', function (): void {
    grantAbilities( [ 'loyalty.viewAny' ] );

    runAdminAccess( makeUser() );

    expect( $this->badgeRuns )->toBeLessThanOrEqual( 1 );
} );

it( 'refuses a user without any admin ability', function (): void {
    expect( fn () => runAdminAccess( makeUser() ) )->toThrow( HttpException::class );
} );
