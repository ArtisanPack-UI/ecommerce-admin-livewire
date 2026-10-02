<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductReview;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Dashboard;
use ArtisanPackUI\EcommerceAdminLivewire\Support\DashboardWidgets;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Livewire;

beforeEach( function (): void {
    $this->actingAs( makeUser() );

    config()->set( 'app.timezone', 'UTC' );
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    config()->set( 'artisanpack.ecommerce.timezone', 'UTC' );

    $this->travelTo( Carbon::parse( '2026-03-15 12:00:00', 'UTC' ) );
} );

afterEach( function (): void {
    removeAllFilters( DashboardWidgets::FILTER );
} );

/**
 * A paid USD order placed at `$placedAt`.
 */
function dashboardOrder( string $placedAt, int $total, array $attributes = [] ): Order
{
    return Order::factory()->create( [
        'placed_at'               => $placedAt,
        'payment_status'          => 'paid',
        'subtotal_amount'         => $total,
        'total_amount'            => $total,
        'subtotal_currency'       => 'USD',
        'discount_currency'       => 'USD',
        'tax_currency'            => 'USD',
        'shipping_currency'       => 'USD',
        'total_currency'          => 'USD',
        'total_refunded_currency' => 'USD',
        ...$attributes,
    ] );
}

/**
 * A tracked stock row for a new product.
 */
function dashboardStock( string $name, int $onHand, ?int $threshold ): InventoryItem
{
    $product = Product::factory()->create( [ 'name' => $name ] );

    return InventoryItem::query()->updateOrCreate(
        [ 'stockable_type' => $product->getMorphClass(), 'stockable_id' => $product->getKey() ],
        [ 'track_inventory' => true, 'quantity_on_hand' => $onHand, 'quantity_reserved' => 0, 'low_stock_threshold' => $threshold ],
    );
}

it( 'shows every KPI, the sparkline, low stock, and recent orders to a full admin', function (): void {
    grantAbilities( [ 'report.view', 'order.viewAny', 'inventory.viewAny', 'review.viewAny' ] );

    dashboardOrder( '2026-03-15 09:00:00', 2_500, [ 'order_number' => '1001', 'system_status' => 'processing', 'fulfillment_status' => 'unfulfilled' ] );
    dashboardOrder( '2026-03-10 09:00:00', 10_000, [ 'order_number' => '1000', 'system_status' => 'completed', 'fulfillment_status' => 'fulfilled' ] );
    dashboardStock( 'Blue Mug', 1, 5 );
    dashboardStock( 'Red Mug', 50, 5 );
    ProductReview::factory()->count( 2 )->create();

    Livewire::test( Dashboard::class )
        ->assertOk()
        ->assertSeeHtml( 'data-kpi="sales-today"' )
        ->assertSeeHtml( 'data-kpi="sales-30-days"' )
        ->assertSeeHtml( 'data-kpi="awaiting-fulfillment"' )
        ->assertSeeHtml( 'data-kpi="low-stock"' )
        ->assertSeeHtml( 'data-kpi="pending-reviews"' )
        ->assertSee( '$25.00' )
        ->assertSee( '$125.00' )
        ->assertSee( '1 order to ship' )
        ->assertSee( '1 item at or below its threshold' )
        ->assertSee( '2 reviews awaiting moderation' )
        ->assertSeeHtml( 'data-sales-sparkline' )
        ->assertSee( 'Sales over the last 30 days: $125.00 in total. Best day: March 10, 2026, with $100.00.' )
        ->assertSeeHtml( 'data-low-stock' )
        ->assertSee( 'Blue Mug' )
        ->assertDontSee( 'Red Mug' )
        ->assertSeeHtml( 'data-recent-orders' )
        ->assertSeeInOrder( [ '#1001', '#1000' ] )
        ->assertDontSeeHtml( 'data-first-run' );
} );

it( 'links each KPI to the matching filtered screen', function (): void {
    grantAbilities( [ 'report.view', 'order.viewAny', 'inventory.viewAny', 'review.viewAny' ] );
    dashboardOrder( '2026-03-15 09:00:00', 100 );

    $html = Livewire::test( Dashboard::class )->html();

    expect( $html )
        ->toContain( e( route( 'artisanpack.ecommerce.admin.reports.show', [ 'report' => 'sales', 'preset' => 'today' ] ) ) )
        ->toContain( e( route( 'artisanpack.ecommerce.admin.orders.index', [ 'filters' => [ 'awaiting' => '1' ] ] ) ) )
        ->toContain( e( route( 'artisanpack.ecommerce.admin.inventory.index', [ 'filters' => [ 'stock' => 'reorder' ] ] ) ) )
        ->toContain( e( route( 'artisanpack.ecommerce.admin.reviews.index', [ 'filters' => [ 'status' => 'pending' ] ] ) ) );
} );

it( 'shows a reviewer only the reviews KPI', function (): void {
    grantAbilities( [ 'review.viewAny' ] );
    dashboardOrder( '2026-03-15 09:00:00', 2_500 );
    ProductReview::factory()->create();

    Livewire::test( Dashboard::class )
        ->assertOk()
        ->assertSeeHtml( 'data-kpi="pending-reviews"' )
        ->assertSee( '1 review awaiting moderation' )
        ->assertDontSeeHtml( 'data-kpi="sales-today"' )
        ->assertDontSeeHtml( 'data-kpi="awaiting-fulfillment"' )
        ->assertDontSeeHtml( 'data-kpi="low-stock"' )
        ->assertDontSeeHtml( 'data-dashboard-widget="sales"' )
        ->assertDontSeeHtml( 'data-dashboard-widget="recent-orders"' )
        ->assertDontSeeHtml( 'data-dashboard-widget="low-stock"' )
        ->assertDontSee( '$25.00' );
} );

it( 'guides an empty store to add its first product', function (): void {
    grantAbilities( [ 'report.view', 'order.viewAny', 'product.create' ] );

    Livewire::test( Dashboard::class )
        ->assertOk()
        ->assertSeeHtml( 'data-first-run' )
        ->assertSee( 'Welcome to your store' )
        ->assertSee( 'Add your first product' )
        ->assertSeeHtml( route( 'artisanpack.ecommerce.admin.products.create' ) )
        ->assertDontSeeHtml( 'data-kpis' )
        ->assertDontSeeHtml( 'data-recent-orders' );
} );

it( 'hides the first-run actions from a user who cannot create products', function (): void {
    grantAbilities( [ 'order.viewAny' ] );

    Livewire::test( Dashboard::class )
        ->assertSeeHtml( 'data-first-run' )
        ->assertDontSeeHtml( route( 'artisanpack.ecommerce.admin.products.create' ) )
        ->assertDontSeeHtml( route( 'artisanpack.ecommerce.admin.products.import' ) );
} );

it( 'falls back to the screen list when no widget is visible', function (): void {
    grantAbilities( [ 'shippingZone.viewAny' ] );
    dashboardOrder( '2026-03-15 09:00:00', 100 );

    Livewire::test( Dashboard::class )
        ->assertOk()
        ->assertSeeHtml( 'data-dashboard-sections' )
        ->assertSee( 'Shipping' )
        ->assertDontSeeHtml( 'data-dashboard-widgets' );
} );

it( 'is denied to a user who can open no admin screen', function (): void {
    Livewire::test( Dashboard::class )->assertForbidden();
} );

it( 'is denied on update when access is revoked mid-session', function (): void {
    grantAbilities( [ 'order.viewAny' ] );
    dashboardOrder( '2026-03-15 09:00:00', 100 );

    $component = Livewire::test( Dashboard::class )->assertOk();

    Gate::define( 'ecommerce.order.viewAny', static fn (): bool => false );

    $component->call( '$refresh' )->assertForbidden();
} );

it( 'renders widgets added through the extension filter, gated by their permission', function (): void {
    grantAbilities( [ 'order.viewAny' ] );
    dashboardOrder( '2026-03-15 09:00:00', 100 );

    Livewire::component( 'dashboard-test-widget', new class extends Component {
        public function render(): string
        {
            return '<div data-test-widget>Loyalty points issued</div>';
        }
    } );

    addFilter( DashboardWidgets::FILTER, static function ( array $widgets ): array {
        $widgets[] = [ 'key' => 'loyalty', 'label' => 'Loyalty', 'component' => 'dashboard-test-widget', 'position' => 5, 'width' => 'half' ];
        $widgets[] = [ 'key' => 'hidden', 'label' => 'Hidden', 'view' => 'ecommerce-admin::livewire.dashboard.kpis', 'permission' => 'subscription.viewAny' ];
        $widgets[] = [ 'key' => 'broken' ];

        return array_values( array_filter( $widgets, static fn ( array $widget ): bool => DashboardWidgets::LOW_STOCK !== ( $widget['key'] ?? null ) ) );
    } );

    Livewire::test( Dashboard::class )
        ->assertOk()
        ->assertSeeHtml( 'data-dashboard-widget="loyalty"' )
        ->assertSee( 'Loyalty points issued' )
        ->assertDontSeeHtml( 'data-dashboard-widget="hidden"' )
        ->assertDontSeeHtml( 'data-dashboard-widget="broken"' )
        ->assertSeeInOrder( [ 'data-dashboard-widget="loyalty"', 'data-dashboard-widget="kpis"' ] );
} );

it( 'normalizes, replaces, and sorts filtered widgets', function (): void {
    addFilter( DashboardWidgets::FILTER, static function ( array $widgets ): array {
        $widgets[] = [ 'key' => DashboardWidgets::SALES, 'label' => 'Revenue', 'view' => 'custom.sales', 'position' => 99, 'width' => 'nonsense' ];

        return $widgets;
    } );

    $widgets = DashboardWidgets::all( null );
    $sales   = collect( $widgets )->firstWhere( 'key', DashboardWidgets::SALES );

    expect( array_column( $widgets, 'key' ) )->toBe( [ 'kpis', 'low-stock', 'recent-orders', 'sales' ] )
        ->and( $sales )->toMatchArray( [ 'label' => 'Revenue', 'view' => 'custom.sales', 'component' => null, 'permission' => null, 'width' => 'full' ] );
} );

it( 'reports no sales when there were none in the last 30 days', function (): void {
    grantAbilities( [ 'report.view' ] );
    dashboardOrder( '2026-01-01 09:00:00', 10_000 );

    Livewire::test( Dashboard::class )
        ->assertSee( 'No sales in the last 30 days.' );
} );
