<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Route;
use Tests\Concerns\DisablesAdminRoutes;

uses( DisablesAdminRoutes::class );

it( 'registers no admin routes when routes_enabled is false', function (): void {
    $names = collect( Route::getRoutes()->getRoutes() )
        ->map( static fn ( $route ): string => (string) $route->getName() )
        ->filter( static fn ( string $name ): bool => str_starts_with( $name, 'artisanpack.ecommerce.admin.' ) );

    expect( $names )->toBeEmpty();

    grantAbilities( [ 'order.viewAny' ] );

    $this->actingAs( makeUser() )->get( '/ecommerce-admin' )->assertNotFound();
} );

it( 'still aliases the access middleware for hosts that register their own routes', function (): void {
    expect( Route::getMiddleware() )->toHaveKey( 'ecommerce-admin.access' );
} );
