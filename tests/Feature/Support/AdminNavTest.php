<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use Illuminate\Support\Facades\Route;

it( 'defines every section and entry from spec §5.4 in order', function (): void {
    $bySection = collect( AdminNav::items() )
        ->groupBy( 'section' )
        ->map( static fn ( $items ): array => $items->pluck( 'key' )->all() )
        ->all();

    expect( $bySection )->toBe( [
        'top'           => [ 'dashboard' ],
        'orders'        => [ 'orders', 'reviews' ],
        'catalog'       => [ 'products', 'categories', 'tags', 'inventory', 'digital-files', 'license-keys' ],
        'customers'     => [ 'customers' ],
        'marketing'     => [ 'promotions' ],
        'reports'       => [ 'reports' ],
        'configuration' => [ 'shipping', 'tax', 'notifications', 'webhooks', 'order-statuses', 'kanban-boards', 'settings' ],
    ] );
} );

it( 'gives every entry the AdminMenuRegistry shape', function (): void {
    foreach ( AdminNav::items() as $item ) {
        expect( $item )->toHaveKeys( [ 'key', 'label', 'icon', 'route', 'position', 'permission', 'badge', 'section', 'parameters' ] )
            ->and( $item['route'] )->toStartWith( AdminNav::ROUTE_PREFIX );
    }
} );

it( 'denies access to guests and to users with no ecommerce abilities', function (): void {
    expect( AdminNav::canAccess( null ) )->toBeFalse()
        ->and( AdminNav::canAccess( makeUser() ) )->toBeFalse();
} );

it( 'grants access when any entry permission is held', function (): void {
    grantAbilities( [ 'webhookSubscription.viewAny' ] );

    expect( AdminNav::canAccess( makeUser() ) )->toBeTrue();
} );

it( 'hides entries whose screen has not shipped yet', function (): void {
    grantAbilities( [ 'order.viewAny' ] );

    expect( collect( AdminNav::visibleItems( makeUser() ) )->pluck( 'key' )->all() )->toBe( [ 'dashboard' ] );

    Route::get( 'fake-orders', static fn (): string => 'orders' )->name( 'artisanpack.ecommerce.admin.orders.index' );
    Route::getRoutes()->refreshNameLookups();

    expect( collect( AdminNav::visibleItems( makeUser() ) )->pluck( 'key' )->all() )->toBe( [ 'dashboard', 'orders' ] );
} );

it( 'maps inventory onto product.viewAny until the engine ships inventory abilities', function (): void {
    grantAbilities( [ 'product.viewAny' ] );
    Route::get( 'fake-inventory', static fn (): string => 'inventory' )->name( 'artisanpack.ecommerce.admin.inventory.index' );
    Route::getRoutes()->refreshNameLookups();

    expect( collect( AdminNav::visibleItems( makeUser() ) )->pluck( 'key' )->all() )->toContain( 'inventory' );
} );

it( 'also honours the inventory ability itself while it stands in for product.viewAny', function (): void {
    grantAbilities( [ 'inventory.viewAny' ] );

    expect( ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization::allows( makeUser(), 'inventory.viewAny' ) )->toBeTrue()
        ->and( ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization::allows( makeUser(), 'product.viewAny' ) )->toBeFalse();
} );
