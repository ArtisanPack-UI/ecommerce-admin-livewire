<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\DigitalDownload;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\LicenseKey;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\Ecommerce\Models\PromotionUsage;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Show;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\KanbanBoards;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\SubscriptionPanel;

beforeEach( function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceAdminLivewire.order.taxBreakdown' );
    removeAllFilters( 'ap.ecommerceAdminLivewire.kanban.boardUrl' );
} );

it( 'renders the items from their snapshots', function (): void {
    $order = Order::factory()->create( [ 'order_number' => 'A1B2C3D4' ] );
    OrderItem::factory()->create( [
        'order_id'           => $order->id,
        'product_snapshot'   => [ 'name' => 'Linen Shirt', 'sku' => 'SHIRT-M-BLUE', 'type' => 'simple', 'options' => [ 'Size' => 'M', 'Colour' => 'Blue' ] ],
        'quantity'           => 2,
        'unit_price_amount'  => 2500,
        'discount_amount'    => 500,
        'tax_amount'         => 360,
        'total_amount'       => 4860,
        'fulfillment_status' => 'fulfilled',
    ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertOk()
        ->assertSee( 'Order #A1B2C3D4' )
        ->assertSee( 'Linen Shirt' )
        ->assertSee( 'Size: M' )
        ->assertSee( 'Colour: Blue' )
        ->assertSee( 'SHIRT-M-BLUE' )
        ->assertSee( '$25.00' )
        ->assertSee( '−$5.00' )
        ->assertSee( '$3.60' )
        ->assertSee( '$48.60' )
        ->assertSee( 'Fulfilled' )
        ->assertSeeHtml( '<caption class="apui-sr-only">Items in order #A1B2C3D4</caption>' )
        ->assertSeeHtml( '<span class="apui-sr-only">SKU</span>' )
        ->assertSeeHtml( '<bdi dir="ltr" class="ec-mono ec-order-sku">SHIRT-M-BLUE</bdi>' )
        ->assertSeeInOrder( [ 'Linen Shirt', 'Size: M', 'Colour: Blue', 'SHIRT-M-BLUE' ] )
        ->assertSeeInOrder( [ 'Item', 'Qty', 'Unit price', 'Discount', 'Tax', 'Total', 'Fulfillment' ] );
} );

it( 'still renders an item whose product was deleted', function (): void {
    $product = Product::factory()->create();
    $order   = Order::factory()->create();
    $item    = OrderItem::factory()->create( [ 'order_id' => $order->id, 'product_id' => $product->id, 'product_snapshot' => [ 'name' => 'Retired Mug', 'sku' => 'MUG-1', 'type' => 'simple', 'options' => [] ] ] );

    $product->delete();

    expect( $item->fresh()->product_id )->toBeNull();

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertOk()
        ->assertSee( 'Retired Mug' )
        ->assertSee( 'No longer in the catalog' );
} );

it( 'is denied without order.view', function (): void {
    Gate::define( 'ecommerce.order.view', static fn (): bool => false );

    Livewire::test( Show::class, [ 'order' => Order::factory()->create()->id ] )->assertForbidden();
} );

it( 'is not found for a missing order', function (): void {
    $this->get( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => 999 ] ) )->assertNotFound();
} );

it( 'answers 403, not 404, for a missing order when the user cannot list orders', function (): void {
    Gate::define( 'ecommerce.order.viewAny', static fn (): bool => false );

    Livewire::test( Show::class, [ 'order' => 999 ] )->assertForbidden();
} );

it( 'names the order number in the page title', function (): void {
    $order = Order::factory()->create( [ 'order_number' => 'T1TLE99' ] );

    $this->get( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => $order->id ] ) )
        ->assertOk()
        ->assertSee( '<title>Order #T1TLE99 &middot;', false );
} );

it( 'shows a registered customer\'s email, not "Guest", when the user cannot open their record', function (): void {
    Gate::define( 'ecommerce.customer.view', static fn (): bool => false );

    $customer = Customer::factory()->create( [ 'first_name' => null, 'last_name' => null ] );
    $order    = Order::factory()->create( [ 'customer_id' => $customer->id, 'email' => 'nameless@example.test' ] );

    $html = Livewire::test( Show::class, [ 'order' => $order->id ] )->html();

    expect( $html )->toContain( 'nameless@example.test' );
    expect( preg_match( '/<span class="ec-strong">\s*Guest\s*<\/span>/', $html ) )->toBe( 0 );
} );

it( 'skips a snapshot option whose label is not scalar', function (): void {
    $order = Order::factory()->create();
    OrderItem::factory()->create( [
        'order_id'         => $order->id,
        'product_snapshot' => [ 'name' => 'Odd Mug', 'sku' => 'ODD', 'type' => 'simple', 'options' => [ [ 'label' => [ 'nested' ], 'value' => 'x' ], [ 'label' => 'Size', 'value' => 'L' ] ] ],
    ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertOk()
        ->assertSee( 'Size: L' )
        ->assertDontSee( 'Array: x' );
} );

it( 'lists totals with promotion names, refunds, and net', function (): void {
    $order = Order::factory()->create( [
        'subtotal_amount'       => 10000,
        'discount_amount'       => 1500,
        'shipping_amount'       => 800,
        'tax_amount'            => 744,
        'total_amount'          => 10044,
        'total_refunded_amount' => 2000,
    ] );
    PromotionUsage::factory()->create( [ 'order_id' => $order->id, 'promotion_id' => Promotion::factory()->create( [ 'name' => 'Spring sale' ] )->id, 'amount_discounted' => 1000 ] );
    PromotionUsage::factory()->create( [ 'order_id' => $order->id, 'promotion_id' => Promotion::factory()->create( [ 'name' => 'First order' ] )->id, 'amount_discounted' => 500 ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeInOrder( [ 'Subtotal', '$100.00', 'Discount: Spring sale', '−$10.00', 'Discount: First order', '−$5.00', 'Shipping', '$8.00', 'Tax', '$7.44', 'Total', '$100.44', 'Refunded', '−$20.00', 'Net', '$80.44' ] )
        ->assertDontSee( 'store currency' );
} );

it( 'shows the tax breakdown the engine recorded', function (): void {
    $order = Order::factory()->create( [ 'tax_amount' => 1300, 'meta' => [ 'tax_breakdown' => [
        [ 'label' => 'State tax', 'rate_ubps' => 60_000_000, 'amount' => 1000, 'is_compound' => false, 'country_code' => 'US', 'region_code' => 'CA' ],
        [ 'label' => 'City tax', 'rate_ubps' => 18_000_000, 'amount' => 300, 'is_compound' => false, 'country_code' => 'US', 'region_code' => 'CA' ],
        [ 'label' => 'Malformed', 'amount' => 'lots' ],
    ] ] ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeInOrder( [ 'State tax', '$10.00', 'City tax', '$3.00', 'Total' ] )
        ->assertDontSee( 'Malformed' );
} );

it( 'still reads a breakdown recorded under the legacy tax_lines key', function (): void {
    $order = Order::factory()->create( [ 'tax_amount' => 1300, 'meta' => [ 'tax_lines' => [ [ 'label' => 'State tax', 'amount' => 1000 ], [ 'label' => 'City tax', 'amount' => 300 ] ] ] ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeInOrder( [ 'State tax', '$10.00', 'City tax', '$3.00' ] );
} );

it( 'falls back to one tax line when no breakdown was recorded', function (): void {
    $order = Order::factory()->create( [ 'tax_amount' => 744, 'meta' => [ 'tax_breakdown' => [] ] ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeInOrder( [ 'Tax', '$7.44', 'Total' ] );
} );

it( 'lets a satellite describe the tax breakdown', function (): void {
    $order = Order::factory()->create( [ 'tax_amount' => 500 ] );

    addFilter( 'ap.ecommerceAdminLivewire.order.taxBreakdown', static fn ( array $lines ): array => [ [ 'label' => 'VAT 20%', 'amount' => 500 ] ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )->assertSee( 'VAT 20%' );
} );

it( 'shows the store-currency equivalent when the order currency differs', function (): void {
    $order = Order::factory()->create( [
        'currency'           => 'EUR',
        'base_currency'      => 'USD',
        'fx_rate_to_base_e8' => 108_000_000,
        'subtotal_amount'    => 10000,
        'subtotal_currency'  => 'EUR',
        'total_amount'       => 10000,
        'total_currency'     => 'EUR',
    ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSee( 'USD (store currency)' )
        ->assertSee( '€100.00' )
        ->assertSee( '$108.00' )
        ->assertSee( 'exchange rate recorded when the order was placed' );
} );

it( 'shows the customer, guest and claimed flags, addresses, and payment', function (): void {
    $customer = Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'user_id' => null ] );
    $order    = Order::factory()->create( [
        'customer_id'         => $customer->id,
        'email'               => 'ada@example.test',
        'is_claimed'          => false,
        'payment_gateway_key' => 'manual-test',
        'payment_reference'   => 'pi_123',
        'shipping_address'    => [ 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'address1' => '1 Analytical Way', 'city' => 'London', 'postal_code' => 'N1', 'country_code' => 'GB' ],
        'billing_address'     => null,
    ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSee( 'Ada Lovelace' )
        ->assertSee( 'ada@example.test' )
        ->assertSee( 'Guest checkout' )
        ->assertSee( 'Not claimed' )
        ->assertSee( '1 Analytical Way' )
        ->assertSee( 'No address' )
        ->assertSee( 'manual-test' )
        ->assertSee( 'pi_123' );
} );

it( 'shows board assignments with each board\'s sub-status', function (): void {
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    $board   = KanbanBoard::factory()->create( [ 'name' => 'Fulfillment' ] );
    $order   = Order::factory()->create( [ 'system_status' => 'processing' ] );
    OrderBoardAssignment::factory()->create( [ 'order_id' => $order->id, 'board_id' => $board->id, 'substatus_id' => $packing->id ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSee( 'Fulfillment' )
        ->assertSee( 'Packing' )
        ->assertDontSee( 'Add to board' );
} );

it( 'shows each board\'s column label, color and time in column', function (): void {
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing', 'color' => '#112233' ] );
    $board   = KanbanBoard::factory()->create( [ 'name' => 'Shipping' ] );
    KanbanColumn::factory()->create( [ 'board_id' => $board->id, 'substatus_id' => $packing->id, 'label_override' => 'Being packed', 'color_override' => '#AABBCC' ] );
    $order = Order::factory()->create( [ 'system_status' => 'processing' ] );
    OrderBoardAssignment::factory()->create( [
        'order_id'     => $order->id,
        'board_id'     => $board->id,
        'substatus_id' => $packing->id,
        'assigned_at'  => now()->subDays( 5 ),
        'moved_at'     => now()->subHours( 3 ),
    ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSee( 'Shipping' )
        ->assertSee( 'Being packed' )
        ->assertSeeHtml( 'fill="#AABBCC"' )
        ->assertDontSeeHtml( 'fill="#112233"' )
        ->assertSee( 'In this column for 3 hours' );
} );

it( 'falls back to the sub-status when the board has no column for it', function (): void {
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing', 'color' => '#112233' ] );
    $board   = KanbanBoard::factory()->create( [ 'name' => 'Shipping' ] );
    $order   = Order::factory()->create( [ 'system_status' => 'processing' ] );
    OrderBoardAssignment::factory()->create( [ 'order_id' => $order->id, 'board_id' => $board->id, 'substatus_id' => $packing->id, 'moved_at' => null, 'assigned_at' => now()->subDays( 2 ) ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSee( 'Packing' )
        ->assertSeeHtml( 'fill="#112233"' )
        ->assertSee( 'In this column for 2 days' );
} );

it( 'links each board to the order\'s card when the kanban satellite is installed and the user can view boards', function (): void {
    grantAbilities( [ 'kanbanBoard.view' ] );
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    $board   = KanbanBoard::factory()->create( [ 'name' => 'Shipping' ] );
    $order   = Order::factory()->create( [ 'system_status' => 'processing' ] );
    OrderBoardAssignment::factory()->create( [ 'order_id' => $order->id, 'board_id' => $board->id, 'substatus_id' => $packing->id ] );

    app( ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry::class )->register( [
        'package_name' => KanbanBoards::KANBAN_PACKAGE,
        'version'      => '1.0.0',
    ] );
    Illuminate\Support\Facades\Route::get( 'kanban/{board}', static fn (): string => 'board' )->name( KanbanBoards::BOARD_ROUTE );
    Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeHtml( 'data-open-board' )
        ->assertSeeHtml( 'href="' . e( route( KanbanBoards::BOARD_ROUTE, [ 'board' => $board->id, 'card' => $order->id ] ) ) . '"' )
        ->assertSeeHtml( 'aria-label="Open board: Shipping"' );
} );

it( 'hides the open board link without the kanban satellite', function (): void {
    grantAbilities( [ 'kanbanBoard.view' ] );
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    $board   = KanbanBoard::factory()->create();
    $order   = Order::factory()->create( [ 'system_status' => 'processing' ] );
    OrderBoardAssignment::factory()->create( [ 'order_id' => $order->id, 'board_id' => $board->id, 'substatus_id' => $packing->id ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeHtml( 'data-order-board-column' )
        ->assertDontSeeHtml( 'data-open-board' );
} );

it( 'hides the open board link without kanbanBoard.view', function (): void {
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    $board   = KanbanBoard::factory()->create();
    $order   = Order::factory()->create( [ 'system_status' => 'processing' ] );
    OrderBoardAssignment::factory()->create( [ 'order_id' => $order->id, 'board_id' => $board->id, 'substatus_id' => $packing->id ] );
    addFilter( 'ap.ecommerceAdminLivewire.kanban.boardUrl', static fn (): string => 'https://example.test/board' );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeHtml( 'data-order-board-column' )
        ->assertDontSeeHtml( 'data-open-board' );

    grantAbilities( [ 'kanbanBoard.view' ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeHtml( 'data-open-board' );
} );

it( 'passes the card to the board URL filter', function (): void {
    grantAbilities( [ 'kanbanBoard.view' ] );
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    $board   = KanbanBoard::factory()->create( [ 'key' => 'shipping' ] );
    $order   = Order::factory()->create( [ 'system_status' => 'processing' ] );
    OrderBoardAssignment::factory()->create( [ 'order_id' => $order->id, 'board_id' => $board->id, 'substatus_id' => $packing->id ] );
    addFilter( 'ap.ecommerceAdminLivewire.kanban.boardUrl', static fn ( ?string $url, KanbanBoard $board, array $query ): string => 'https://example.test/boards/' . $board->key . '/cards/' . $query['card'] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeHtml( 'https://example.test/boards/shipping/cards/' . $order->id );
} );

it( 'adds the order to a board through the routing service', function (): void {
    grantAbilities( [ 'kanbanCard.move' ] );
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    $board   = KanbanBoard::factory()->create( [ 'name' => 'Warehouse' ] );
    KanbanColumn::factory()->create( [ 'board_id' => $board->id, 'substatus_id' => $packing->id ] );
    $order = Order::factory()->create( [ 'system_status' => 'processing' ] );

    $component = Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSee( 'Add to board' )
        ->set( 'boardToAdd', $board->id )
        ->call( 'addToBoard' )
        ->assertHasNoErrors()
        ->assertSet( 'boardToAdd', null );

    expect( OrderBoardAssignment::query()->where( 'order_id', $order->id )->where( 'board_id', $board->id )->whereNull( 'removed_at' )->exists() )->toBeTrue()
        ->and( sentToasts( $component ) )->toContain( 'Added to' );
} );

it( 'validates the board to add', function ( mixed $board, string $rule ): void {
    grantAbilities( [ 'kanbanCard.move' ] );
    $order = Order::factory()->create( [ 'system_status' => 'processing' ] );

    if ( 'inactive' === $board ) {
        $board = KanbanBoard::factory()->create( [ 'is_active' => false ] )->id;
    }

    if ( 'not_in' === $rule ) {
        $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
        $board   = KanbanBoard::factory()->create()->id;
        OrderBoardAssignment::factory()->create( [ 'order_id' => $order->id, 'board_id' => $board, 'substatus_id' => $packing->id ] );
    }

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->set( 'boardToAdd', $board )
        ->call( 'addToBoard' )
        ->assertHasErrors( [ 'boardToAdd' => $rule ] );
} )->with( [
    'missing'        => [ null, 'required' ],
    'unknown'        => [ 999, 'exists' ],
    'inactive'       => [ 'inactive', 'exists' ],
    'already on it'  => [ null, 'not_in' ],
] );

it( 'reports a board that cannot hold the order', function (): void {
    grantAbilities( [ 'kanbanCard.move' ] );
    $board = KanbanBoard::factory()->create( [ 'name' => 'Empty board' ] );
    $order = Order::factory()->create( [ 'system_status' => 'processing' ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->set( 'boardToAdd', $board->id )
        ->call( 'addToBoard' )
        ->assertHasErrors( [ 'boardToAdd' ] );

    expect( OrderBoardAssignment::query()->count() )->toBe( 0 );
} );

it( 'removes the order from a board', function (): void {
    grantAbilities( [ 'kanbanCard.move' ] );
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    $board   = KanbanBoard::factory()->create( [ 'name' => 'Fulfillment' ] );
    $order   = Order::factory()->create( [ 'system_status' => 'processing' ] );
    OrderBoardAssignment::factory()->create( [ 'order_id' => $order->id, 'board_id' => $board->id, 'substatus_id' => $packing->id ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->call( 'removeFromBoard', $board->id )
        ->assertSee( 'This order is not on any board.' );

    expect( OrderBoardAssignment::query()->first()->removed_at )->not->toBeNull();
} );

it( 'refuses board changes without kanbanCard.move', function (): void {
    $board = KanbanBoard::factory()->create();
    $order = Order::factory()->create();

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->set( 'boardToAdd', $board->id )
        ->call( 'addToBoard' )
        ->assertForbidden();
} );

it( 'lists download grants without their tokens and masks license keys', function (): void {
    $order = Order::factory()->create();
    $item  = OrderItem::factory()->create( [ 'order_id' => $order->id ] );
    $grant = DigitalDownload::factory()->create( [ 'order_item_id' => $item->id, 'download_count' => 2, 'downloads_remaining' => 3 ] );
    LicenseKey::factory()->create( [ 'order_item_id' => $item->id, 'key' => 'ABCDE-FGHIJ-KLMNO-PQRST-UVWXY', 'activations_count' => 1, 'activations_limit' => 5 ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSee( $grant->file->label )
        ->assertSee( '2 downloads' )
        ->assertSee( '3 left' )
        ->assertDontSee( $grant->token )
        ->assertSee( 'VWXY' )
        ->assertDontSee( 'ABCDE-FGHIJ' )
        ->assertSee( '1 of 5 activations' );
} );

it( 'shows full license keys to users who may view them', function (): void {
    grantAbilities( [ 'licenseKey.view' ] );
    $order = Order::factory()->create();
    $item  = OrderItem::factory()->create( [ 'order_id' => $order->id ] );
    LicenseKey::factory()->create( [ 'order_item_id' => $item->id, 'key' => 'ABCDE-FGHIJ-KLMNO-PQRST-UVWXY' ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )->assertSee( 'ABCDE-FGHIJ-KLMNO-PQRST-UVWXY' );
} );

it( 'renders panels other packages register', function (): void {
    Livewire::component( 'test-subscription-panel', SubscriptionPanel::class );
    app( OrderPanelRegistry::class )->register( 'subscription', 'test-subscription-panel', 'side', 30 );

    $order = Order::factory()->create( [ 'order_number' => 'SUBS0001' ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeHtml( 'data-order-panel="subscription"' )
        ->assertSee( 'Subscription panel for #SUBS0001' );
} );

it( 'serves the page through the admin route', function (): void {
    $order = Order::factory()->create( [ 'order_number' => 'ROUTE001' ] );

    $this->get( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => $order->id ] ) )
        ->assertOk()
        ->assertSee( 'Order #ROUTE001' );

    $this->get( route( 'artisanpack.ecommerce.admin.orders.index' ) )->assertOk()->assertSee( '#ROUTE001' );
} );

it( 'translates its strings', function (): void {
    app( 'translator' )->addLines( [ '*.Items' => 'Artículos', '*.Totals' => 'Totales' ], 'es', '*' );
    app()->setLocale( 'es' );

    Livewire::test( Show::class, [ 'order' => Order::factory()->create()->id ] )
        ->assertSee( 'Artículos' )
        ->assertSee( 'Totales' );
} );

it( 'labels tax with the engine\'s TaxLabel, so a store override applies', function (): void {
    config()->set( 'artisanpack.ecommerce.localization.tax_labels', [ 'en' => 'VAT' ] );
    $order = Order::factory()->create( [ 'tax_amount' => 200 ] );
    OrderItem::factory()->create( [ 'order_id' => $order->id ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeHtml( '<th data-scope="table" data-part="head-cell" scope="col" data-numeric>VAT</th>' )
        ->assertSeeInOrder( [ 'data-order-totals', 'VAT' ] );
} );

it( 'puts the status badges after the h1 and the side column first in the DOM', function (): void {
    $order = Order::factory()->create( [ 'order_number' => 'HEAD0001', 'system_status' => 'processing', 'payment_status' => 'paid', 'fulfillment_status' => 'partial' ] );

    // DOM order is reading and tab order: the h1 before its statuses, and the
    // side column (status first) before the main one, as it's shown below 1024px.
    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeInOrder( [ 'Order #HEAD0001', 'data-order-badges', 'data-status-type="system"', 'data-status-type="payment"', 'data-status-type="fulfillment"' ] )
        ->assertDontSeeHtml( 'data-part="meta"' )
        ->assertSeeHtml( 'aria-label="Order status"' )
        ->assertSeeHtml( 'data-size="lg"' )
        ->assertSeeInOrder( [ 'data-order-column="side"', 'data-status-panel', 'data-order-customer', 'data-order-addresses', 'data-order-payment', 'data-order-boards', 'data-order-column="main"', 'data-order-items', 'data-order-totals', 'order-notes' ] )
        ->assertSeeHtml( 'class="ec-order-col ec-order-col-side"' )
        ->assertSeeHtml( 'id="order-notes" tabindex="-1" class="ec-focus-target"' )
        ->assertSeeHtml( '<h1 data-scope="page-header" data-part="title">' )
        ->assertSeeHtml( '<h2 class="apui-sr-only">Order details</h2>' );
} );

it( 'shows the changed-elsewhere notice as a warning banner with Reload order', function (): void {
    $order     = Order::factory()->create();
    $component = Livewire::test( Show::class, [ 'order' => $order->id ] );

    $this->travel( 5 )->seconds();
    $order->forceFill( [ 'system_status' => 'processing' ] )->save();

    $component->call( 'orderBroadcast', [ 'data' => [ 'orderStatusChanged' => [ 'order' => [ 'id' => $order->id ] ] ] ] )
        ->assertSeeHtml( 'class="ec-banner apui-alert" data-color="warning" data-changed-elsewhere' )
        ->assertSeeInOrder( [ 'data-changed-elsewhere', 'This order was changed elsewhere.', 'Reload order' ] )
        ->assertSet( 'changedElsewhere', true );
} );

it( 'shows a negative discount, a muted zero discount and the item fulfillment badge', function (): void {
    $order = Order::factory()->create();
    OrderItem::factory()->create( [ 'order_id' => $order->id, 'product_snapshot' => [ 'name' => 'Plain Tee' ], 'discount_amount' => 0, 'fulfillment_status' => 'unfulfilled' ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSee( 'Plain Tee' )
        ->assertSeeHtml( 'data-numeric class="ec-cell-muted"' )
        ->assertDontSeeHtml( 'data-item-details' )
        ->assertSee( 'Unfulfilled' );
} );

it( 'marks a deleted product\'s item with a neutral badge', function (): void {
    $order = Order::factory()->create();
    OrderItem::factory()->create( [ 'order_id' => $order->id, 'product_id' => null, 'product_snapshot' => [ 'name' => 'Holiday gift box' ] ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeInOrder( [ 'Holiday gift box', 'data-item-gone', 'data-color="neutral"', 'No longer in the catalog' ] );
} );

it( 'shows an empty items card when the order has no items', function (): void {
    Livewire::test( Show::class, [ 'order' => Order::factory()->create()->id ] )
        ->assertSee( 'This order has no items.' )
        ->assertDontSeeHtml( 'Items in order #' );
} );

it( 'emphasises the total and net lines and adds a store-currency column only for a foreign order', function (): void {
    $order = Order::factory()->create( [
        'currency'           => 'CAD',
        'base_currency'      => 'USD',
        'fx_rate_to_base_e8' => 73_000_000,
        'subtotal_amount'    => 10000,
        'subtotal_currency'  => 'CAD',
        'total_amount'       => 10000,
        'total_currency'     => 'CAD',
    ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeInOrder( [ 'data-total-line="total"', 'data-emphasis' ] )
        ->assertSeeHtml( 'data-numeric class="ec-cell-muted"' )
        ->assertSeeInOrder( [ 'data-order-totals', 'CAD', 'USD (store currency)', 'data-rate-note' ] );

    $local = Order::factory()->create();

    Livewire::test( Show::class, [ 'order' => $local->id ] )
        ->assertDontSeeHtml( 'data-rate-note' )
        ->assertDontSee( 'store currency' );
} );

it( 'says the billing address is the same as shipping when they match', function (): void {
    $address = [ 'first_name' => 'Maya', 'last_name' => 'Chen', 'address1' => '2140 Lakeshore Drive', 'city' => 'Austin', 'postal_code' => '78704', 'country_code' => 'US' ];
    $order   = Order::factory()->create( [ 'shipping_address' => $address, 'billing_address' => array_reverse( $address + [ 'address2' => '' ], true ) ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeInOrder( [ 'Shipping', '2140 Lakeshore Drive', 'Billing', 'Same as shipping' ] )
        ->assertSeeHtml( 'data-billing-same' );

    $other = Order::factory()->create( [ 'shipping_address' => $address, 'billing_address' => [ 'address1' => '9 Elm Street', 'city' => 'Dallas', 'country_code' => 'US' ] ] );

    Livewire::test( Show::class, [ 'order' => $other->id ] )
        ->assertSee( '9 Elm Street' )
        ->assertDontSeeHtml( 'data-billing-same' );
} );

it( 'compares addresses ignoring blank parts and part order', function ( mixed $billing, mixed $shipping, bool $same ): void {
    expect( Show::sameAddress( $billing, $shipping ) )->toBe( $same );
} )->with( [
    'identical'         => [ [ 'address1' => '1 Way', 'city' => 'X' ], [ 'address1' => '1 Way', 'city' => 'X' ], true ],
    'reordered, blanks' => [ [ 'city' => 'X', 'address1' => '1 Way ', 'address2' => '' ], [ 'address1' => '1 Way', 'city' => 'X', 'company' => null ], true ],
    'different'         => [ [ 'address1' => '2 Way', 'city' => 'X' ], [ 'address1' => '1 Way', 'city' => 'X' ], false ],
    'both empty'        => [ null, null, false ],
    'empty shipping'    => [ [ 'city' => 'X' ], [], false ],
] );

it( 'shows the customer with an avatar, a mailto link and a phone', function (): void {
    $customer = Customer::factory()->create( [ 'first_name' => 'Maya', 'last_name' => 'Chen', 'user_id' => makeUser()->id ] );
    $order    = Order::factory()->create( [ 'customer_id' => $customer->id, 'email' => 'maya@example.test', 'phone' => '+1 512 555 0148', 'is_claimed' => true ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeInOrder( [ 'data-order-customer', 'apui-avatar', 'MC', 'Maya Chen', 'mailto:maya@example.test' ] )
        ->assertSeeHtml( '<span class="apui-sr-only">Phone:</span> <bdi dir="ltr">+1 512 555 0148</bdi>' )
        ->assertSee( 'Has an account' )
        ->assertSee( 'Claimed' );
} );

it( 'names each board\'s remove button after the board', function (): void {
    grantAbilities( [ 'kanbanCard.move' ] );
    $board = KanbanBoard::factory()->create( [ 'name' => 'Fulfillment' ] );
    $order = Order::factory()->create();
    OrderBoardAssignment::factory()->create( [ 'order_id' => $order->id, 'board_id' => $board->id ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeHtml( 'aria-label="Remove from Fulfillment"' )
        ->assertSeeHtml( 'data-remove-board' );
} );

it( 'uses design-system markup only', function (): void {
    $order = Order::factory()->create();
    OrderItem::factory()->create( [ 'order_id' => $order->id ] );

    $html = Livewire::test( Show::class, [ 'order' => $order->id ] )->html();

    foreach ( [ 'class="table', 'link-hover', 'rounded-box', 'bg-warning', 'text-end', 'lg:grid-cols-3', 'opacity-75', 'class="sr-only"' ] as $legacy ) {
        expect( $html )->not->toContain( $legacy );
    }
} );
