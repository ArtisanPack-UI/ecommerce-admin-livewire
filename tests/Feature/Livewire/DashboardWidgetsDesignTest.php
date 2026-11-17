<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Dashboard;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach( function (): void {
    $this->actingAs( makeUser() );

    config()->set( 'app.timezone', 'UTC' );
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    config()->set( 'artisanpack.ecommerce.timezone', 'UTC' );

    $this->travelTo( Carbon::parse( '2026-03-15 12:00:00', 'UTC' ) );
} );

/**
 * A paid USD order placed at `$placedAt`.
 */
function designDashboardOrder( string $placedAt, int $total, array $attributes = [] ): Order
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
function designDashboardStock( string $name, string $sku, int $onHand, int $threshold ): InventoryItem
{
    $product = Product::factory()->create( [ 'name' => $name, 'sku' => $sku ] );

    return InventoryItem::query()->updateOrCreate(
        [ 'stockable_type' => $product->getMorphClass(), 'stockable_id' => $product->getKey() ],
        [ 'track_inventory' => true, 'quantity_on_hand' => $onHand, 'quantity_reserved' => 0, 'low_stock_threshold' => $threshold ],
    );
}

it( 'builds the key figures from design-system Stats with Lucide icons and no DaisyUI classes', function (): void {
    grantAbilities( [ 'report.view', 'order.viewAny', 'inventory.viewAny', 'review.viewAny' ] );
    designDashboardOrder( '2026-03-15 09:00:00', 2_500, [ 'system_status' => 'processing', 'fulfillment_status' => 'unfulfilled' ] );

    $html = Livewire::test( Dashboard::class )->html();

    expect( $html )
        ->toContain( 'class="ec-kpis"' )
        ->toContain( 'class="apui-stat"' )
        ->toContain( 'class="ec-kpi-link"' )
        ->toContain( 'Awaiting fulfillment: 1. 1 order to ship' )
        ->not->toContain( 'shadow' )
        ->not->toContain( 'outline-primary' )
        ->not->toContain( 'heroicon' );
} );

it( 'emphasizes only the awaiting-fulfillment figure, and only while orders wait', function (): void {
    grantAbilities( [ 'report.view', 'order.viewAny', 'inventory.viewAny', 'review.viewAny' ] );

    $quiet = Livewire::test( Dashboard::class )->html();

    expect( $quiet )->not->toContain( 'data-emphasis' );

    designDashboardOrder( '2026-03-15 09:00:00', 2_500, [ 'system_status' => 'processing', 'fulfillment_status' => 'unfulfilled' ] );

    $busy = Livewire::test( Dashboard::class )->html();

    expect( substr_count( $busy, 'data-emphasis' ) )->toBe( 1 )
        ->and( $busy )->toMatch( '/data-kpi="awaiting-fulfillment".*?data-color="accent"\s+data-emphasis/s' );
} );

it( 'draws the sales chart with the best day marked, hidden from assistive technology', function (): void {
    grantAbilities( [ 'report.view' ] );
    designDashboardOrder( '2026-03-10 09:00:00', 10_000 );
    designDashboardOrder( '2026-03-12 09:00:00', 2_500 );

    $html = Livewire::test( Dashboard::class )->html();

    expect( $html )
        ->toContain( 'class="ec-sales-chart" dir="ltr" aria-hidden="true" inert' )
        ->toContain( 'class="apui-sparkline"' )
        ->toContain( 'data-part="area"' )
        ->toContain( 'data-sales-best' )
        ->toContain( 'Best day · $100.00' )
        ->toContain( 'Feb 14' )
        ->toContain( 'Mar 15' )
        ->toContain( '<h2 data-scope="card" data-part="title" id="dashboard-widget-sales">' )
        ->not->toContain( 'font-black' );
} );

it( 'draws a flat chart with no best day when nothing sold', function (): void {
    grantAbilities( [ 'report.view' ] );
    // A product, so the store is past its first-run state.
    Product::factory()->create();

    $html = Livewire::test( Dashboard::class )->html();

    expect( $html )
        ->toContain( 'No sales in the last 30 days.' )
        ->not->toContain( 'data-sales-best' );
} );

it( 'plots the sparkline left to right and labels the best day on the side with room', function (): void {
    $early = Dashboard::sparkline( [ 0, 300, 100, 0 ], 1, 'Best' );
    $late  = Dashboard::sparkline( [ 0, 100, 300, 0 ], 2, 'Best' );
    $flat  = Dashboard::sparkline( [ 0, 0 ], null, null );
    $none  = Dashboard::sparkline( [], null, null );

    expect( $early['line'] )->toBe( 'M0 112 L200 8 L400 77.3 L600 112' )
        ->and( $early['area'] )->toBe( 'M0 112 L200 8 L400 77.3 L600 112 L600 112 L0 112 Z' )
        ->and( $early['best'] )->toMatchArray( [ 'x' => 200.0, 'y' => 8.0, 'anchor' => 'start', 'labelX' => 212.0 ] )
        ->and( $late['best'] )->toMatchArray( [ 'x' => 400.0, 'anchor' => 'end', 'labelX' => 388.0 ] )
        ->and( $flat['line'] )->toBe( 'M0 112 L600 112' )
        ->and( $flat['best'] )->toBeNull()
        ->and( $none['line'] )->toBe( 'M0 112 L600 112' );
} );

it( 'lists the lowest stock flush in a compact table with a level bar and an out-of-stock badge', function (): void {
    grantAbilities( [ 'inventory.viewAny' ] );
    designDashboardStock( 'Merino beanie', 'MER-BNE', 0, 10 );
    designDashboardStock( 'Canvas tote', 'TOT-CNV', 4, 10 );
    designDashboardStock( 'Wool socks', 'SOK-WL3', 9, 10 );

    $html = Livewire::test( Dashboard::class )->html();

    expect( $html )
        ->toContain( 'ec-card-body-flush' )
        ->toContain( 'data-density="compact"' )
        ->toContain( 'Items at or below their low-stock threshold, lowest first' )
        ->toContain( 'data-out-of-stock' )
        ->toContain( '<h2 data-scope="card" data-part="title" id="dashboard-widget-low-stock">' )
        ->toMatch( '/class="apui-progress ec-w-0"[^>]*data-color="error"/s' )
        ->toMatch( '/class="apui-progress ec-w-40"[^>]*data-color="warning"/s' )
        ->toMatch( '/class="apui-progress ec-w-90"[^>]*data-color="warning"/s' )
        ->not->toContain( 'table-sm' )
        ->not->toContain( 'link-hover' );

    expect( substr_count( $html, 'data-out-of-stock=' ) )->toBe( 1 );
} );

it( 'links each low-stock item to the inventory screen searched to its SKU', function (): void {
    grantAbilities( [ 'inventory.viewAny' ] );
    designDashboardStock( 'Merino beanie', 'MER-BNE', 1, 10 );

    $html = Livewire::test( Dashboard::class )->html();

    expect( $html )
        ->toContain( e( route( 'artisanpack.ecommerce.admin.inventory.index', [ 'q' => 'MER-BNE' ] ) ) )
        ->toContain( e( route( 'artisanpack.ecommerce.admin.inventory.index', [ 'filters' => [ 'stock' => 'reorder' ] ] ) ) )
        ->toContain( 'View all low-stock items' );
} );

it( 'shows the healthy empty state when no item is low', function (): void {
    grantAbilities( [ 'inventory.viewAny' ] );
    designDashboardStock( 'Wool socks', 'SOK-WL3', 50, 10 );

    $html = Livewire::test( Dashboard::class )->html();

    expect( $html )
        ->toContain( 'Stock looks healthy' )
        ->not->toContain( 'ec-card-body-flush' )
        ->not->toContain( 'data-low-stock' );
} );
