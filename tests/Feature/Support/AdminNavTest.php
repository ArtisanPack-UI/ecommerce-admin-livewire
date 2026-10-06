<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Registries\AdminMenuRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;

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

    addFilter( 'ap.ecommerceAdminLivewire.nav.items', static fn ( array $items ): array => [
        ...$items,
        [ 'key' => 'loyalty', 'section' => 'marketing', 'label' => 'Loyalty', 'route' => 'artisanpack.ecommerce.admin.loyalty.index', 'permission' => 'order.viewAny' ],
    ] );

    expect( collect( AdminNav::visibleItems( makeUser() ) )->pluck( 'key' )->all() )->not->toContain( 'loyalty' );

    fakeAdminRoutes( [ 'loyalty.index' ] );

    expect( collect( AdminNav::visibleItems( makeUser() ) )->pluck( 'key' )->all() )->toContain( 'loyalty' );
} );

it( 'shows inventory to holders of inventory.viewAny, not product.viewAny', function (): void {
    grantAbilities( [ 'product.viewAny' ] );

    expect( collect( AdminNav::visibleItems( makeUser() ) )->pluck( 'key' )->all() )->not->toContain( 'inventory' );

    grantAbilities( [ 'inventory.viewAny' ] );

    expect( collect( AdminNav::visibleItems( makeUser() ) )->pluck( 'key' )->all() )->toContain( 'inventory' );
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

    app( AdminMenuRegistry::class )->register( 'loyalty', [
        'section'    => 'customers',
        'label'      => 'Loyalty',
        'icon'       => 'o-gift',
        'route'      => 'artisanpack.ecommerce.admin.loyalty.index',
        'position'   => 20,
        'permission' => 'customer.viewAny',
    ] );

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

it( 'groups registry entries under registry sections and renders their resolved badge', function (): void {
    grantAbilities( [ 'customer.viewAny' ] );
    fakeAdminRoutes( [ 'customers.index', 'loyalty.index' ] );

    $registry = app( AdminMenuRegistry::class );
    $registry->registerSection( 'rewards', 'Rewards', 45 );
    $registry->register( 'loyalty', [
        'section'    => 'rewards',
        'label'      => 'Loyalty',
        'route'      => 'artisanpack.ecommerce.admin.loyalty.index',
        'permission' => 'customer.viewAny',
        'badge'      => static fn (): int => 3,
    ] );

    $user    = makeUser();
    $grouped = AdminNav::grouped( $user );
    $loyalty = collect( $grouped['rewards']['items'] ?? [] )->firstWhere( 'key', 'loyalty' );

    expect( $grouped['rewards']['label'] ?? null )->toBe( 'Rewards' )
        ->and( array_keys( $grouped ) )->toBe( [ 'top', 'customers', 'rewards' ] )
        ->and( $loyalty )->toMatchArray( [ 'badge' => null, 'badgeCount' => 3 ] );

    Livewire\Livewire::actingAs( $user )
        ->test( ArtisanPackUI\EcommerceAdminLivewire\Livewire\Navigation::class )
        ->assertSee( 'Loyalty' )
        ->assertSeeHtml( '3' )
        ->assertSee( 'Loyalty (3 items)' );
} );

it( 'links registry entries that give a URL instead of a route name', function (): void {
    grantAbilities( [ 'customer.viewAny' ] );

    app( AdminMenuRegistry::class )->register( 'docs', [
        'label'      => 'Docs',
        'route'      => 'https://example.test/docs',
        'permission' => 'customer.viewAny',
    ] );

    $docs = collect( AdminNav::visibleItems( makeUser() ) )->firstWhere( 'key', 'docs' );

    expect( $docs )->not->toBeNull()
        ->and( AdminNav::url( $docs ) )->toBe( 'https://example.test/docs' );
} );
