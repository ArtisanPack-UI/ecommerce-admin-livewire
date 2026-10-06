<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\OrdersQuery;
use Illuminate\Support\Carbon;

/**
 * The order numbers a query returns, in order.
 *
 * @param  array<string, mixed>  $filters
 *
 * @return array<int, string>
 */
function orderNumbers( string $search = '', array $filters = [], string $sort = '', string $direction = '' ): array
{
    return ( new OrdersQuery() )->build( $search, $filters, $sort, $direction )->pluck( 'order_number' )->all();
}

it( 'searches by order number, with or without the hash', function (): void {
    Order::factory()->create( [ 'order_number' => 'A1B2C3D4' ] );
    Order::factory()->create( [ 'order_number' => 'ZZZZ9999' ] );

    expect( orderNumbers( 'A1B2C3D4' ) )->toBe( [ 'A1B2C3D4' ] )
        ->and( orderNumbers( '#a1b2' ) )->toBe( [ 'A1B2C3D4' ] );
} );

it( 'searches by email and by customer name', function (): void {
    $jane = Customer::factory()->create( [ 'first_name' => 'Jane', 'last_name' => 'Doe' ] );
    Order::factory()->create( [ 'order_number' => 'JANE0001', 'customer_id' => $jane->id, 'email' => 'jd@example.test' ] );
    Order::factory()->create( [ 'order_number' => 'BOB00001', 'email' => 'bob@shop.test' ] );

    expect( orderNumbers( 'shop.test' ) )->toBe( [ 'BOB00001' ] )
        ->and( orderNumbers( 'jane doe' ) )->toBe( [ 'JANE0001' ] )
        ->and( orderNumbers( 'jane smith' ) )->toBe( [] );
} );

it( 'matches LIKE wildcards literally', function (): void {
    Order::factory()->create( [ 'order_number' => 'PCT00001', 'email' => '100%@example.test' ] );
    Order::factory()->create( [ 'order_number' => 'PCT00002', 'email' => '1000@example.test' ] );

    expect( orderNumbers( '100%' ) )->toBe( [ 'PCT00001' ] )
        ->and( orderNumbers( '_' ) )->toBe( [] );
} );

it( 'filters by status, payment, fulfillment, and currency', function (): void {
    Order::factory()->create( [ 'order_number' => 'PAID0001', 'system_status' => 'processing', 'payment_status' => 'paid', 'fulfillment_status' => 'unfulfilled', 'currency' => 'EUR' ] );
    Order::factory()->create( [ 'order_number' => 'PEND0001', 'system_status' => 'pending', 'payment_status' => 'pending', 'fulfillment_status' => 'unfulfilled' ] );
    Order::factory()->create( [ 'order_number' => 'DONE0001', 'system_status' => 'complete', 'payment_status' => 'paid', 'fulfillment_status' => 'fulfilled' ] );

    expect( orderNumbers( filters: [ 'system_status' => 'processing' ] ) )->toBe( [ 'PAID0001' ] )
        ->and( orderNumbers( filters: [ 'payment_status' => 'paid', 'fulfillment_status' => 'unfulfilled' ] ) )->toBe( [ 'PAID0001' ] )
        ->and( orderNumbers( filters: [ 'currency' => 'eur' ] ) )->toBe( [ 'PAID0001' ] );
} );

it( 'filters by sub-status and by board', function (): void {
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    $board   = KanbanBoard::factory()->create();

    $onBoard = Order::factory()->create( [ 'order_number' => 'BOARD001', 'system_status' => 'processing', 'substatus_id' => $packing->id ] );
    $removed = Order::factory()->create( [ 'order_number' => 'GONE0001' ] );
    Order::factory()->create( [ 'order_number' => 'NONE0001' ] );

    OrderBoardAssignment::factory()->create( [ 'order_id' => $onBoard->id, 'board_id' => $board->id, 'substatus_id' => $packing->id ] );
    OrderBoardAssignment::factory()->create( [ 'order_id' => $removed->id, 'board_id' => $board->id, 'substatus_id' => $packing->id, 'removed_at' => now() ] );

    expect( orderNumbers( filters: [ 'substatus' => (string) $packing->id ] ) )->toBe( [ 'BOARD001' ] )
        ->and( orderNumbers( filters: [ 'board' => (string) $board->id ] ) )->toBe( [ 'BOARD001' ] );
} );

it( 'filters by placed date range, inclusive of both days', function (): void {
    Order::factory()->create( [ 'order_number' => 'OLD00001', 'placed_at' => Carbon::parse( '2026-09-01 10:00' ) ] );
    Order::factory()->create( [ 'order_number' => 'MID00001', 'placed_at' => Carbon::parse( '2026-09-10 23:59' ) ] );
    Order::factory()->create( [ 'order_number' => 'NEW00001', 'placed_at' => Carbon::parse( '2026-09-20 00:00' ) ] );

    expect( orderNumbers( filters: [ 'placed' => [ 'from' => '2026-09-10', 'to' => '2026-09-20' ] ], sort: 'placed', direction: 'asc' ) )->toBe( [ 'MID00001', 'NEW00001' ] )
        ->and( orderNumbers( filters: [ 'placed' => [ 'from' => null, 'to' => '2026-09-01' ] ] ) )->toBe( [ 'OLD00001' ] )
        ->and( orderNumbers( filters: [ 'placed' => [ 'from' => '2026-02-31', 'to' => 'nonsense' ] ] ) )->toHaveCount( 3 );
} );

it( 'sorts by each sortable column and falls back to newest first', function (): void {
    Order::factory()->create( [ 'order_number' => 'B', 'total_amount' => 300, 'placed_at' => now()->subDays( 2 ) ] );
    Order::factory()->create( [ 'order_number' => 'A', 'total_amount' => 100, 'placed_at' => now()->subDay() ] );
    Order::factory()->create( [ 'order_number' => 'C', 'total_amount' => 200, 'placed_at' => now() ] );

    expect( orderNumbers( sort: 'number', direction: 'asc' ) )->toBe( [ 'A', 'B', 'C' ] )
        ->and( orderNumbers( sort: 'total', direction: 'desc' ) )->toBe( [ 'B', 'C', 'A' ] )
        ->and( orderNumbers( sort: 'not-a-column', direction: 'asc' ) )->toBe( [ 'C', 'A', 'B' ] )
        ->and( orderNumbers() )->toBe( [ 'C', 'A', 'B' ] );
} );

it( 'sums item quantities and sorts by them', function (): void {
    $two  = Order::factory()->create( [ 'order_number' => 'TWO' ] );
    $five = Order::factory()->create( [ 'order_number' => 'FIVE' ] );
    OrderItem::factory()->create( [ 'order_id' => $two->id, 'quantity' => 2 ] );
    OrderItem::factory()->create( [ 'order_id' => $five->id, 'quantity' => 3 ] );
    OrderItem::factory()->create( [ 'order_id' => $five->id, 'quantity' => 2 ] );

    $orders = ( new OrdersQuery() )->build( sort: 'items', direction: 'desc' )->get();

    expect( $orders->pluck( 'order_number' )->all() )->toBe( [ 'FIVE', 'TWO' ] )
        ->and( (int) $orders->first()->items_sum_quantity )->toBe( 5 );
} );

it( 'ignores unknown filters and empty values', function (): void {
    Order::factory()->count( 2 )->create();

    expect( orderNumbers( filters: [ 'nope' => 'x', 'system_status' => '', 'placed' => [ 'from' => '', 'to' => null ] ] ) )->toHaveCount( 2 );
} );

it( 'applies extra filters passed with withFilters', function (): void {
    Order::factory()->create( [ 'order_number' => 'META0001', 'meta' => [ 'channel' => 'pos' ] ] );
    Order::factory()->create( [ 'order_number' => 'WEB00001' ] );

    $numbers = ( new OrdersQuery() )
        ->withFilters( [ 'channel' => static fn ( $query, mixed $value ) => $query->where( $query->qualifyColumn( 'order_number' ), 'like', 'POS' === strtoupper( (string) $value ) ? 'META%' : 'WEB%' ) ] )
        ->build( filters: [ 'channel' => 'pos' ] )
        ->pluck( 'order_number' )
        ->all();

    expect( $numbers )->toBe( [ 'META0001' ] );
} );

it( 'filters orders awaiting fulfillment the way the dashboard counts them', function (): void {
    Order::factory()->create( [ 'order_number' => 'WAIT0001', 'system_status' => 'processing', 'fulfillment_status' => 'unfulfilled' ] );
    Order::factory()->create( [ 'order_number' => 'WAIT0002', 'system_status' => 'processing', 'fulfillment_status' => 'partial' ] );
    Order::factory()->create( [ 'order_number' => 'SENT0001', 'system_status' => 'processing', 'fulfillment_status' => 'fulfilled' ] );
    Order::factory()->create( [ 'order_number' => 'HOLD0001', 'system_status' => 'on-hold', 'fulfillment_status' => 'unfulfilled' ] );

    expect( orderNumbers( '', [ 'awaiting' => '1' ], 'number', 'asc' ) )->toBe( [ 'WAIT0001', 'WAIT0002' ] )
        ->and( orderNumbers( '', [ 'awaiting' => '0' ], 'number', 'asc' ) )->toBe( [ 'HOLD0001', 'SENT0001' ] );
} );

it( 'filters placed dates by the store\'s day, not the app\'s', function (): void {
    config()->set( 'app.timezone', 'UTC' );
    config()->set( 'artisanpack.ecommerce.timezone', 'America/New_York' );

    // 03:30 UTC on Oct 5 is 23:30 on Oct 4 in New York.
    Order::factory()->create( [ 'order_number' => 'LATE0001', 'placed_at' => Carbon::parse( '2026-10-05 03:30:00', 'UTC' ) ] );

    expect( orderNumbers( '', [ 'placed' => [ 'from' => '2026-10-04', 'to' => '2026-10-04' ] ] ) )->toBe( [ 'LATE0001' ] )
        ->and( orderNumbers( '', [ 'placed' => [ 'from' => '2026-10-05', 'to' => '2026-10-05' ] ] ) )->toBe( [] );
} );

it( 'filters customers\' last order dates by the store\'s day', function (): void {
    config()->set( 'app.timezone', 'UTC' );
    config()->set( 'artisanpack.ecommerce.timezone', 'America/New_York' );

    Customer::factory()->create( [ 'email' => 'late@example.test', 'last_ordered_at' => Carbon::parse( '2026-10-05 03:30:00', 'UTC' ) ] );

    $emails = static fn ( string $day ): array => ( new ArtisanPackUI\EcommerceAdminLivewire\Queries\CustomersQuery() )
        ->build( '', [ 'last_order' => [ 'from' => $day, 'to' => $day ] ] )
        ->pluck( 'email' )
        ->all();

    expect( $emails( '2026-10-04' ) )->toBe( [ 'late@example.test' ] )
        ->and( $emails( '2026-10-05' ) )->toBe( [] );
} );
