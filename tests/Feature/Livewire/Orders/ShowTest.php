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
use Livewire\Livewire;
use Tests\Fixtures\Livewire\SubscriptionPanel;

beforeEach( function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceAdminLivewire.order.taxBreakdown' );
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
        ->assertSee( '$5.00' )
        ->assertSee( '$3.60' )
        ->assertSee( '$48.60' )
        ->assertSee( 'Fulfilled' )
        ->assertSeeHtml( '<caption class="sr-only">Items in order #A1B2C3D4</caption>' );
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
        ->assertSeeInOrder( [ 'Subtotal', '$100.00', 'Discount: Spring sale', '-$10.00', 'Discount: First order', '-$5.00', 'Shipping', '$8.00', 'Tax', '$7.44', 'Total', '$100.44', 'Refunded', '-$20.00', 'Net', '$80.44' ] )
        ->assertDontSee( 'store currency' );
} );

it( 'shows the tax breakdown the order recorded', function (): void {
    $order = Order::factory()->create( [ 'tax_amount' => 1300, 'meta' => [ 'tax_lines' => [ [ 'label' => 'State tax', 'amount' => 1000 ], [ 'label' => 'City tax', 'amount' => 300 ] ] ] ] );

    Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSeeInOrder( [ 'State tax', '$10.00', 'City tax', '$3.00' ] );
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
