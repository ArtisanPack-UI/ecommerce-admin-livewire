<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Contracts\PaymentGateway;
use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\Refund;
use ArtisanPackUI\Ecommerce\Models\Shipment;
use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\Ecommerce\ValueObjects\RefundResult;
use Money\Money;
use Tests\Browser\Support\Locators;

beforeEach( function (): void {
    // A gateway that refunds part of an order, standing in for a real one.
    $gateway = Mockery::mock( PaymentGateway::class );
    $gateway->shouldReceive( 'key' )->andReturn( 'browser-fake' );
    $gateway->shouldReceive( 'label' )->andReturn( 'Fake Pay' );
    $gateway->shouldReceive( 'supportsRefunds' )->andReturn( true );
    $gateway->shouldReceive( 'supportsPartialRefunds' )->andReturn( true );
    $gateway->shouldReceive( 'refund' )->andReturnUsing( static fn ( Order $order, Money $amount ): RefundResult => RefundResult::success( $amount, 're_browser_1' ) );

    app( PaymentGatewayRegistry::class )->register( 'browser-fake', $gateway );

    $this->product = Product::factory()->create( [ 'name' => 'Linen Shirt' ] );
    $this->stock   = InventoryItem::query()->updateOrCreate(
        [ 'stockable_type' => $this->product->getMorphClass(), 'stockable_id' => $this->product->getKey() ],
        [ 'track_inventory' => true, 'quantity_on_hand' => 10, 'quantity_reserved' => 0 ],
    );
    $this->order = Order::factory()->create( [
        'order_number'        => 'BROWSER1',
        'system_status'       => 'processing',
        'payment_status'      => 'paid',
        'fulfillment_status'  => 'unfulfilled',
        'payment_gateway_key' => 'browser-fake',
        'shipping_method_key' => 'flat_rate',
        'currency'            => 'USD',
        'subtotal_amount'     => 3_000,
        'total_amount'        => 3_000,
    ] );
    $this->item = OrderItem::factory()->create( [
        'order_id'          => $this->order->id,
        'product_id'        => $this->product->id,
        'product_snapshot'  => [ 'name' => 'Linen Shirt', 'sku' => 'SHIRT', 'type' => 'simple', 'options' => [] ],
        'quantity'          => 3,
        'unit_price_amount' => 1_000,
        'total_amount'      => 3_000,
    ] );
} );

it( 'issues a partial refund with restock', function (): void {
    $line = 'refund-line-' . $this->item->id;

    visit( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => $this->order->id ] ) )
        ->click( Locators::role( 'Issue refund' ) )
        ->type( Locators::field( $line . '-quantity' ), '1' )
        ->click( Locators::field( 'refund-reason' ) )
        ->check( Locators::field( $line . '-restock' ) )
        ->type( Locators::field( 'refund-reason' ), 'Wrong size' )
        ->click( 'internal:role=dialog >> ' . Locators::role( 'Refund' ) )
        ->assertSee( 'Refunded $10.00.' )
        ->assertNoJavaScriptErrors();

    $refund = Refund::query()->where( 'order_id', $this->order->id )->sole();

    expect( (int) $refund->amount )->toBe( 1_000 )
        ->and( $refund->reason )->toBe( 'Wrong size' )
        ->and( $this->stock->fresh()->quantity_on_hand )->toBe( 11 );
} );

it( 'creates a shipment', function (): void {
    visit( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => $this->order->id ] ) )
        ->click( Locators::role( 'Create shipment' ) )
        ->type( Locators::field( 'shipment-carrier' ), 'UPS' )
        ->type( Locators::field( 'shipment-tracking-number' ), '1Z999' )
        ->click( 'internal:role=dialog >> ' . Locators::role( 'Create shipment' ) )
        ->assertSee( 'Shipment created with 3 units.' )
        ->assertNoJavaScriptErrors();

    $shipment = Shipment::query()->where( 'order_id', $this->order->id )->sole();

    expect( $shipment->carrier )->toBe( 'UPS' )
        ->and( $shipment->tracking_number )->toBe( '1Z999' )
        ->and( $this->order->fresh()->fulfillment_status )->toBe( 'fulfilled' );
} );
