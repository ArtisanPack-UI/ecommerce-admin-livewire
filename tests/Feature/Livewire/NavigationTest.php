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
    grantAbilities( [ 'order.viewAny', 'review.viewAny', 'inventory.viewAny' ] );

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

it( 'counts low stock the same way as the inventory low-stock filter', function (): void {
    grantAbilities( [ 'inventory.viewAny' ] );

    InventoryItem::factory()->create( [ 'quantity_on_hand' => 0, 'low_stock_threshold' => 5 ] );
    InventoryItem::factory()->create( [ 'quantity_on_hand' => 3, 'low_stock_threshold' => 5 ] );

    $filtered = ( new ArtisanPackUI\EcommerceAdminLivewire\Queries\InventoryQuery() )->build( '', [ 'stock' => 'low' ] )->count();

    expect( NavBadges::count( NavBadges::LOW_STOCK ) )->toBe( 1 )
        ->and( $filtered )->toBe( 1 );
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

    // The breadcrumbs mark the page too; within the navigation, one entry is current.
    $html = $this->actingAs( $user )->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )->getContent();

    $sidebar = Illuminate\Support\Str::between( $html, 'data-ecommerce-admin-navigation', 'data-part="main"' );

    expect( substr_count( $sidebar, 'aria-current="page"' ) )->toBe( 1 );
} );

it( 'rejects tampering with the active entry', function (): void {
    grantAbilities( [ 'order.viewAny' ] );

    // The navigation exposes one action and no writable state: the active key
    // is locked so a client cannot point it elsewhere.
    Livewire::actingAs( makeUser() )
        ->test( Navigation::class )
        ->set( 'activeKey', 'orders' );
} )->throws( \Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class );

it( 'renders a design-system Menu named Store admin with section titles and rail separators', function (): void {
    grantAbilities( [ 'order.viewAny', 'product.viewAny' ] );

    $html = Livewire::actingAs( makeUser() )->test( Navigation::class )->html();

    expect( $html )
        ->toContain( 'class="apui-menu ec-admin-nav"' )
        ->toContain( 'aria-label="Store admin"' )
        ->toMatch( '/data-scope="menu" data-part="title">\s*Catalog/' )
        ->toContain( 'class="ec-admin-nav__rail-separator"' )
        ->toContain( 'data-tooltip="Products"' )
        // Always named by aria-label, so the rail tooltip's generated text never joins the name.
        ->toContain( 'aria-label="Products"' )
        ->not->toContain( 'heroicon' );
} );

it( 'tones the badges: primary for orders to ship, warning for low stock, neutral for reviews', function (): void {
    grantAbilities( [ 'order.viewAny', 'review.viewAny', 'inventory.viewAny' ] );

    InventoryItem::factory()->create( [ 'quantity_on_hand' => 1, 'low_stock_threshold' => 5 ] );
    ProductReview::factory()->create();
    Order::factory()->create( [ 'system_status' => 'processing', 'fulfillment_status' => 'unfulfilled' ] );

    $html = Livewire::actingAs( makeUser() )->test( Navigation::class )->html();

    expect( $html )
        ->toMatch( '/data-tooltip="Orders \(1 order awaiting fulfillment\)"\s+data-badge-tone="primary"/' )
        ->toMatch( '/data-tooltip="Inventory \(1 low-stock item\)"\s+data-badge-tone="warning"/' )
        ->toMatch( '/data-tooltip="Reviews \(1 review awaiting moderation\)"\s+data-badge-tone="neutral"/' )
        ->toContain( 'class="ec-admin-nav__badge"' );
} );

it( 'leaves the badge tone off an entry with no count', function (): void {
    grantAbilities( [ 'order.viewAny' ] );

    $html = Livewire::actingAs( makeUser() )->test( Navigation::class )->html();

    expect( $html )->not->toContain( 'data-badge-tone' )
        ->and( $html )->not->toContain( 'ec-admin-nav__badge' );
} );

it( 'maps the core Heroicon names to the design system\'s Lucide icons and passes others through', function (): void {
    expect( ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight::icon( 'o-view-columns' ) )->toBe( 'columns-3' )
        ->and( ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight::icon( 'o-truck' ) )->toBe( 'truck' )
        ->and( NavBadges::tone( NavBadges::ORDERS_AWAITING_FULFILLMENT ) )->toBe( 'primary' )
        ->and( NavBadges::tone( NavBadges::LOW_STOCK ) )->toBe( 'warning' )
        ->and( NavBadges::tone( NavBadges::PENDING_REVIEWS ) )->toBe( 'neutral' )
        ->and( NavBadges::tone( null ) )->toBe( 'neutral' );
} );
