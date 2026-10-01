<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\ProductReview;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Navigation;
use ArtisanPackUI\EcommerceAdminLivewire\Support\NavBadges;
use Livewire\Livewire;

beforeEach( function (): void {
    fakeAdminRoutes( [ 'orders.index', 'reviews.index', 'products.index', 'inventory.index', 'customers.index' ] );
} );

it( 'renders the entries the user may see', function (): void {
    grantAbilities( [ 'order.viewAny', 'product.viewAny' ] );

    Livewire::actingAs( makeUser() )
        ->test( Navigation::class )
        ->assertOk()
        ->assertSee( 'Dashboard' )
        ->assertSee( 'Orders' )
        ->assertSee( 'Products' )
        ->assertSee( 'Catalog' )
        ->assertDontSee( 'Reviews' )
        ->assertDontSee( 'Customers' );
} );

it( 'is forbidden to a user with no ecommerce abilities', function (): void {
    Livewire::actingAs( makeUser() )
        ->test( Navigation::class )
        ->assertForbidden();
} );

it( 'shows a reviewer-only user just reviews', function (): void {
    grantAbilities( [ 'review.viewAny' ] );

    Livewire::actingAs( makeUser() )
        ->test( Navigation::class )
        ->assertSee( 'Reviews' )
        ->assertDontSee( 'Orders</span>', false )
        ->assertDontSee( 'Products' )
        ->assertDontSee( 'Catalog' );
} );

it( 'shows the badge counts with an accessible description', function (): void {
    grantAbilities( [ 'order.viewAny', 'review.viewAny', 'product.viewAny' ] );

    InventoryItem::factory()->count( 7 )->create( [ 'quantity_on_hand' => 2, 'low_stock_threshold' => 5 ] );
    InventoryItem::factory()->create( [ 'quantity_on_hand' => 20, 'low_stock_threshold' => 5 ] );
    InventoryItem::factory()->untracked()->create( [ 'quantity_on_hand' => 0, 'low_stock_threshold' => 5 ] );
    ProductReview::factory()->count( 2 )->create();
    ProductReview::factory()->approved()->create();
    Order::factory()->count( 3 )->create( [ 'system_status' => 'processing', 'fulfillment_status' => 'unfulfilled' ] );
    Order::factory()->create( [ 'system_status' => 'processing', 'fulfillment_status' => 'fulfilled' ] );
    Order::factory()->create( [ 'system_status' => 'pending', 'fulfillment_status' => 'unfulfilled' ] );

    Livewire::actingAs( makeUser() )
        ->test( Navigation::class )
        ->assertSeeHtml( 'aria-label="Inventory (7 low-stock items)"' )
        ->assertSeeHtml( 'aria-label="Reviews (2 reviews awaiting moderation)"' )
        ->assertSeeHtml( 'aria-label="Orders (3 orders awaiting fulfillment)"' );
} );

it( 'caches each badge count and refreshes it on request', function (): void {
    grantAbilities( [ 'review.viewAny' ] );
    ProductReview::factory()->create();

    $component = Livewire::actingAs( makeUser() )->test( Navigation::class )
        ->assertSeeHtml( 'Reviews (1 review awaiting moderation)' );

    ProductReview::factory()->create();

    expect( NavBadges::count( NavBadges::PENDING_REVIEWS ) )->toBe( 1 );

    $component->dispatch( 'ecommerce-admin-nav-refresh' )
        ->assertSeeHtml( 'Reviews (2 reviews awaiting moderation)' );
} );

it( 'marks the active entry with aria-current', function (): void {
    grantAbilities( [ 'order.viewAny' ] );
    $user = makeUser();

    $this->actingAs( $user )
        ->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->assertOk()
        ->assertSeeHtml( 'aria-current="page"' );

    expect( substr_count(
        $this->actingAs( $user )->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )->getContent(),
        'aria-current="page"',
    ) )->toBe( 1 );
} );

it( 'rejects tampering with the active entry', function (): void {
    grantAbilities( [ 'order.viewAny' ] );

    // The navigation exposes one action and no writable state: the active key
    // is locked so a client cannot point it elsewhere.
    Livewire::actingAs( makeUser() )
        ->test( Navigation::class )
        ->set( 'activeKey', 'orders' );
} )->throws( \Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class );
