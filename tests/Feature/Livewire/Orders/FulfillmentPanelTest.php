<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Contracts\ShippingLabelProvider;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\Shipment;
use ArtisanPackUI\Ecommerce\Registries\ShippingLabelProviderRegistry;
use ArtisanPackUI\Ecommerce\ValueObjects\ShippingLabel;
use ArtisanPackUI\Ecommerce\ValueObjects\TrackingStatus;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\FulfillmentPanel;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view', 'order.update' ] );
    $this->actingAs( makeUser() );

    $this->order = Order::factory()->withSystemStatus( 'processing' )->create( [ 'payment_status' => 'paid', 'shipping_method_key' => 'flat-rate' ] );
    $this->shirt = OrderItem::factory()->create( [ 'order_id' => $this->order->id, 'product_snapshot' => [ 'name' => 'Linen Shirt', 'sku' => 'S', 'type' => 'simple', 'options' => [] ], 'quantity' => 2 ] );
    $this->mug   = OrderItem::factory()->create( [ 'order_id' => $this->order->id, 'product_snapshot' => [ 'name' => 'Mug', 'sku' => 'M', 'type' => 'simple', 'options' => [] ], 'quantity' => 1 ] );
} );

/**
 * The action token on the rendered create-shipment button.
 */
function fulfillmentShipmentToken( $component ): string
{
    preg_match( '/createShipment\(\s*(?:&quot;|&#039;|\'|")([^&\'"]+)/', $component->html(), $matches );

    return $matches[1] ?? '';
}

/**
 * The action token on a rendered buy-label button.
 */
function fulfillmentLabelToken( $component ): string
{
    preg_match( '/buyLabel\(\s*\d+,\s*(?:&quot;|&#039;|\'|")([^&\'"]+)/', $component->html(), $matches );

    return $matches[1] ?? '';
}

/**
 * Registers a fake label provider.
 */
function fulfillmentLabelProvider(): void
{
    app( ShippingLabelProviderRegistry::class )->register( 'fake-labels', new class implements ShippingLabelProvider {
        public function key(): string
        {
            return 'fake-labels';
        }

        public function buyLabel( Shipment $shipment ): ShippingLabel
        {
            return new ShippingLabel( 4242, 'fake-labels', 'TRACK-' . $shipment->id, 'https://track.example.test/' . $shipment->id, 'usps', 'priority' );
        }

        public function voidLabel( ShippingLabel $label ): void
        {
        }

        public function trackLabel( ShippingLabel $label ): TrackingStatus
        {
            return new TrackingStatus( 'in_transit' );
        }
    } );
}

it( 'lists shipments with carrier, tracking link, status, and dates', function (): void {
    $shipment = Shipment::factory()->create( [
        'order_id'        => $this->order->id,
        'carrier'         => 'UPS',
        'service'         => 'Ground',
        'tracking_number' => '1Z999',
        'tracking_url'    => 'https://ups.example.test/1Z999',
        'status'          => 'in_transit',
        'shipped_at'      => now(),
    ] );
    $shipment->items()->create( [ 'order_item_id' => $this->shirt->id, 'quantity' => 1 ] );

    Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )
        ->assertOk()
        ->assertSee( 'UPS · Ground' )
        ->assertSeeHtml( 'href="https://ups.example.test/1Z999"' )
        ->assertSee( '1Z999' )
        ->assertSee( 'In transit' )
        ->assertSee( 'Linen Shirt × 1' )
        ->assertSee( 'Create shipment' )
        ->assertDontSee( 'Buy label' );
} );

it( 'is denied without order.view', function (): void {
    Gate::define( 'ecommerce.order.view', static fn (): bool => false );

    Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order->id ] )->assertForbidden();
} );

it( 'refuses shipping without order.update', function (): void {
    Gate::define( 'ecommerce.order.update', static fn (): bool => false );

    Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )
        ->assertDontSee( 'Create shipment' )
        ->call( 'startShipment' )
        ->assertForbidden();
} );

it( 'ships two of three items today and the third next week', function (): void {
    $component = Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )
        ->call( 'startShipment' )
        ->assertSet( 'quantities', [ $this->shirt->id => 2, $this->mug->id => 1 ] )
        ->assertSet( 'method', 'flat-rate' )
        ->set( "quantities.{$this->mug->id}", 0 )
        ->set( 'carrier', 'USPS' )
        ->set( 'trackingNumber', '9400' );

    $component->call( 'createShipment', fulfillmentShipmentToken( $component ) )
        ->assertHasNoErrors()
        ->assertSet( 'creating', false )
        ->assertDispatched( OrderPanelRegistry::ORDER_UPDATED_EVENT );

    expect( $this->order->fresh()->fulfillment_status )->toBe( 'partial' );

    $next = Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )
        ->call( 'startShipment' )
        ->assertSet( 'quantities', [ $this->shirt->id => 0, $this->mug->id => 1 ] );

    $next->call( 'createShipment', fulfillmentShipmentToken( $next ) )->assertHasNoErrors();

    expect( $this->order->fresh()->fulfillment_status )->toBe( 'fulfilled' )
        ->and( Shipment::query()->count() )->toBe( 2 );

    Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )
        ->assertSee( 'Every item has shipped.' )
        ->assertDontSee( 'Create shipment' );
} );

it( 'validates the shipment', function ( array $set, string $error ): void {
    $component = Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )->call( 'startShipment' );

    foreach ( $set as $key => $value ) {
        $component->set( str_replace( '{shirt}', (string) $this->shirt->id, $key ), $value );
    }

    $component->call( 'createShipment', fulfillmentShipmentToken( $component ) )
        ->assertHasErrors( [ str_replace( '{shirt}', (string) $this->shirt->id, $error ) ] );

    expect( Shipment::query()->count() )->toBe( 0 );
} )->with( [
    'over the remaining' => [ [ 'quantities.{shirt}' => 3 ], 'quantities.{shirt}' ],
    'nothing chosen'     => [ [ 'quantities' => [] ], 'quantities' ],
    'no method'          => [ [ 'method' => '' ], 'method' ],
    'bad tracking link'  => [ [ 'trackingUrl' => 'javascript:alert(1)' ], 'trackingUrl' ],
    'unknown method'     => [ [ 'method' => 'local-pickup-forged' ], 'method' ],
] );

it( 'shows why an unshippable order cannot ship', function ( string $status, string $why ): void {
    $this->order->forceFill( [ 'system_status' => $status ] )->save();

    Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )
        ->assertSee( $why )
        ->assertDontSee( 'Create shipment' )
        ->call( 'createShipment', 'token' )
        ->assertHasErrors( [ 'shipment' ] );
} )->with( [
    'cancelled' => [ 'cancelled', 'This order was cancelled' ],
    'refunded'  => [ 'refunded', 'This order was refunded' ],
    'failed'    => [ 'failed', 'payment failed' ],
] );

it( 'pastes a tracking number and updates the status', function (): void {
    $shipment = Shipment::factory()->create( [ 'order_id' => $this->order->id, 'status' => 'pending' ] );

    Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )
        ->call( 'editTracking', $shipment->id )
        ->assertSet( 'editStatus', 'pending' )
        ->set( 'editStatus', 'in_transit' )
        ->set( 'editTrackingNumber', '1Z123' )
        ->set( 'editTrackingUrl', 'https://ups.example.test/1Z123' )
        ->call( 'updateTracking' )
        ->assertHasNoErrors()
        ->assertSet( 'editingShipmentId', null );

    $shipment->refresh();

    expect( $shipment->status )->toBe( 'in_transit' )
        ->and( $shipment->tracking_number )->toBe( '1Z123' )
        ->and( $shipment->shipped_at )->not->toBeNull();
} );

it( 'validates tracking and ignores another order\'s shipment', function (): void {
    $shipment = Shipment::factory()->create( [ 'order_id' => $this->order->id ] );
    $foreign  = Shipment::factory()->create();

    Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )
        ->call( 'editTracking', $shipment->id )
        ->set( 'editStatus', 'lost' )
        ->call( 'updateTracking' )
        ->assertHasErrors( [ 'editStatus' ] )
        ->call( 'editTracking', $foreign->id )
        ->assertSet( 'editingShipmentId', $shipment->id );
} );

it( 'buys a label only when a label provider is registered', function (): void {
    $shipment = Shipment::factory()->create( [ 'order_id' => $this->order->id ] );

    Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )->assertDontSee( 'Buy label' );

    fulfillmentLabelProvider();

    $component = Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )->assertSee( 'Buy label' );

    $component->call( 'buyLabel', $shipment->id, fulfillmentLabelToken( $component ) )
        ->assertDispatched( OrderPanelRegistry::ORDER_UPDATED_EVENT );

    $shipment->refresh();

    expect( $shipment->label_id )->toBe( 4242 )
        ->and( $shipment->tracking_number )->toBe( 'TRACK-' . $shipment->id )
        ->and( $shipment->carrier )->toBe( 'usps' );

    Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )
        ->assertSee( 'Label printed' )
        ->assertDontSee( 'Buy label' );
} );

it( 'clears a tracking link', function (): void {
    $shipment = Shipment::factory()->create( [ 'order_id' => $this->order->id, 'tracking_number' => '1Z1', 'tracking_url' => 'https://wrong.example.test' ] );

    Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )
        ->call( 'editTracking', $shipment->id )
        ->set( 'editTrackingUrl', '' )
        ->call( 'updateTracking' )
        ->assertHasNoErrors();

    expect( $shipment->fresh()->tracking_url )->toBeNull()
        ->and( $shipment->fresh()->tracking_number )->toBe( '1Z1' );
} );

it( 'does not buy a second label for a shipment', function (): void {
    fulfillmentLabelProvider();

    $shipment  = Shipment::factory()->create( [ 'order_id' => $this->order->id ] );
    $component = Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] );
    $token     = fulfillmentLabelToken( $component );

    Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] )->call( 'buyLabel', $shipment->id, $token );
    $shipment->update( [ 'label_id' => 1 ] );

    $component->call( 'buyLabel', $shipment->id, fulfillmentLabelToken( Livewire::test( FulfillmentPanel::class, [ 'order' => $this->order ] ) ) );

    expect( $shipment->fresh()->label_id )->toBe( 1 );
} );
