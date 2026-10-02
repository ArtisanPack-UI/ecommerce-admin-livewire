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
    grantAbilities( [ 'kanbanBoard.viewAny' ] );

    expect( collect( AdminNav::visibleItems( makeUser() ) )->pluck( 'key' )->all() )->toBe( [ 'dashboard' ] );

    Route::get( 'fake-kanban-boards', static fn (): string => 'kanban-boards' )->name( 'artisanpack.ecommerce.admin.kanban-boards.index' );
    Route::getRoutes()->refreshNameLookups();

    expect( collect( AdminNav::visibleItems( makeUser() ) )->pluck( 'key' )->all() )->toBe( [ 'dashboard', 'kanban-boards' ] );
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

it( 'lets satellites add entries through the nav.items filter', function (): void {
    grantAbilities( [ 'order.viewAny' ] );
    fakeAdminRoutes( [ 'subscriptions.index' ] );

    addFilter( 'ap.ecommerceAdminLivewire.nav.items', static fn ( array $items ): array => [
        ...$items,
        [ 'key' => 'subscriptions', 'section' => 'orders', 'label' => 'Subscriptions', 'icon' => 'o-arrow-path', 'route' => 'artisanpack.ecommerce.admin.subscriptions.index', 'position' => 30, 'permission' => 'order.viewAny' ],
        [ 'label' => 'Broken entry without a key' ],
    ] );

    expect( collect( AdminNav::visibleItems( makeUser() ) )->pluck( 'key' )->all() )->toBe( [ 'dashboard', 'orders', 'subscriptions' ] );

    removeAllFilters( 'ap.ecommerceAdminLivewire.nav.items' );
} );

it( 'drops empty sections', function (): void {
    grantAbilities( [ 'review.viewAny' ] );
    fakeAdminRoutes( [ 'reviews.index', 'products.index' ] );

    expect( array_keys( AdminNav::grouped( makeUser() ) ) )->toBe( [ 'top', 'orders' ] );
} );

it( 'merges entries from the engine AdminMenuRegistry when it exists', function (): void {
    grantAbilities( [ 'customer.viewAny' ] );
    fakeAdminRoutes( [ 'loyalty.index' ] );

    if ( ! class_exists( AdminNav::MENU_REGISTRY ) ) {
        eval( 'namespace ArtisanPackUI\Ecommerce\Registries; class AdminMenuRegistry { public array $items = []; public function all(): array { return $this->items; } }' );
    }

    $registry        = new ( AdminNav::MENU_REGISTRY )();
    $registry->items = [ [ 'key' => 'loyalty', 'section' => 'customers', 'label' => 'Loyalty', 'icon' => 'o-gift', 'route' => 'artisanpack.ecommerce.admin.loyalty.index', 'position' => 20, 'permission' => 'customer.viewAny' ] ];
    app()->instance( AdminNav::MENU_REGISTRY, $registry );

    expect( collect( AdminNav::visibleItems( makeUser() ) )->pluck( 'key' )->all() )->toContain( 'loyalty' );
} );

it( 'drops entries with a malformed permission instead of failing', function (): void {
    grantAbilities( [ 'order.viewAny' ] );
    fakeAdminRoutes( [ 'subscriptions.index', 'loyalty.index' ] );
    Illuminate\Support\Facades\Log::spy();

    addFilter( 'ap.ecommerceAdminLivewire.nav.items', static fn ( array $items ): array => [
        ...$items,
        [ 'key' => 'subscriptions', 'section' => 'orders', 'label' => 'Subscriptions', 'route' => 'artisanpack.ecommerce.admin.subscriptions.index', 'permission' => 'ecommerce.order.viewAny' ],
        [ 'key' => 'loyalty', 'section' => 'customers', 'label' => 'Loyalty', 'route' => 'artisanpack.ecommerce.admin.loyalty.index', 'permission' => 'loyalty.manage.all' ],
    ] );

    expect( collect( AdminNav::visibleItems( makeUser() ) )->pluck( 'key' )->all() )->toBe( [ 'dashboard', 'orders', 'subscriptions' ] )
        ->and( AdminNav::canAccess( makeUser() ) )->toBeTrue();

    Illuminate\Support\Facades\Log::shouldHaveReceived( 'warning' )->withArgs( static fn ( string $message ): bool => str_contains( $message, '"loyalty"' ) );

    removeAllFilters( 'ap.ecommerceAdminLivewire.nav.items' );
} );
