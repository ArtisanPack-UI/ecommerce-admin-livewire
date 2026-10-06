<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Contracts\PaymentGateway;
use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\Refund;
use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\Ecommerce\ValueObjects\RefundResult;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\RefundsPanel;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use Livewire\Livewire;
use Money\Money;

beforeEach( function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view', 'order.refund' ] );
    $this->actingAs( $this->user = makeUser( [ 'name' => 'Grace Hopper' ] ) );
    config()->set( 'auth.providers.users.model', Tests\Fixtures\User::class );

    $this->gateway = refundsPanelGateway();

    $this->order = Order::factory()->create( [
        'payment_status'      => 'paid',
        'payment_gateway_key' => 'fake',
        'subtotal_amount'     => 3_000,
        'shipping_amount'     => 500,
        'total_amount'        => 3_500,
    ] );
    $this->product = Product::factory()->create();
    $this->item    = OrderItem::factory()->create( [
        'order_id'          => $this->order->id,
        'product_id'        => $this->product->id,
        'product_snapshot'  => [ 'name' => 'Linen Shirt', 'sku' => 'SHIRT', 'type' => 'simple', 'options' => [] ],
        'quantity'          => 3,
        'unit_price_amount' => 1_000,
        'total_amount'      => 3_000,
    ] );
} );

/**
 * Registers a fake gateway under `$key`.
 */
function refundsPanelGateway( bool $partial = true, ?string $declined = null, string $key = 'fake' ): PaymentGateway
{
    $gateway = Mockery::mock( PaymentGateway::class );
    $gateway->shouldReceive( 'key' )->andReturn( $key );
    $gateway->shouldReceive( 'label' )->andReturn( 'Fake Pay' );
    $gateway->shouldReceive( 'supportsRefunds' )->andReturn( true );
    $gateway->shouldReceive( 'supportsPartialRefunds' )->andReturn( $partial );
    $gateway->shouldReceive( 'refund' )->andReturnUsing( fn ( Order $order, Money $amount ): RefundResult => null === $declined ? RefundResult::success( $amount, 're_panel_1' ) : RefundResult::failure( $amount, 'declined', $declined ) );

    app( PaymentGatewayRegistry::class )->register( $key, $gateway );

    return $gateway;
}

/**
 * The action token the rendered refund button carries.
 */
function refundsPanelToken( $component ): string
{
    preg_match( '/refund\(\s*(?:&quot;|&#039;|\'|")([^&\'"]+)/', $component->html(), $matches );

    return $matches[1] ?? '';
}

it( 'renders the refund history with issuer and gateway reference', function (): void {
    Refund::factory()->create( [
        'order_id'          => $this->order->id,
        'amount'            => 1_000,
        'currency'          => 'USD',
        'reason'            => 'Damaged',
        'gateway_reference' => 're_history_9',
        'issued_by_user_id' => $this->user->id,
    ] );

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->assertOk()
        ->assertSee( 'Refundable:' )
        ->assertSee( '$35.00' )
        ->assertSee( '$10.00' )
        ->assertSee( 'Damaged' )
        ->assertSee( 'Grace Hopper' )
        ->assertSee( 're_history_9' )
        ->assertSee( 'Issue refund' );
} );

it( 'is denied without order.view', function (): void {
    Gate::define( 'ecommerce.order.view', static fn (): bool => false );

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order->id ] )->assertForbidden();
} );

it( 'hides and refuses refunds without order.refund', function (): void {
    Gate::define( 'ecommerce.order.refund', static fn (): bool => false );

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->assertDontSee( 'Issue refund' )
        ->call( 'startRefund' )
        ->assertForbidden();

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'refund', 'token' )
        ->assertForbidden();
} );

it( 'refunds one of three items and restocks it', function (): void {
    $stock = InventoryItem::factory()->create( [ 'stockable_type' => $this->product->getMorphClass(), 'stockable_id' => $this->product->id, 'quantity_on_hand' => 4 ] );

    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( "lines.{$this->item->id}.quantity", 1 )
        ->assertSet( "lines.{$this->item->id}.amount", 1_000 )
        ->set( "lines.{$this->item->id}.restock", true )
        ->set( 'reason', 'Wrong size' );

    $component->call( 'refund', refundsPanelToken( $component ) )
        ->assertHasNoErrors()
        ->assertSet( 'refunding', false )
        ->assertDispatched( OrderPanelRegistry::ORDER_UPDATED_EVENT );

    $refund = Refund::query()->with( 'items' )->sole();

    expect( $refund->amount )->toBe( 1_000 )
        ->and( $refund->reason )->toBe( 'Wrong size' )
        ->and( $refund->issued_by_user_id )->toBe( $this->user->id )
        ->and( $refund->items->first()->restock )->toBeTrue()
        ->and( $stock->fresh()->quantity_on_hand )->toBe( 5 )
        ->and( $this->order->fresh()->payment_status )->toBe( 'partially_refunded' );
} );

it( 'refunds shipping only as a free amount', function (): void {
    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( 'mode', 'amount' )
        ->set( 'amount', 500 )
        ->set( 'reason', 'Late delivery' );

    $component->call( 'refund', refundsPanelToken( $component ) )->assertHasNoErrors();

    $refund = Refund::query()->with( 'items' )->sole();

    expect( $refund->amount )->toBe( 500 )
        ->and( $refund->items->first()->quantity )->toBe( 0 );
} );

it( 'refunds the full order', function (): void {
    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( 'mode', 'amount' )
        ->set( 'amount', 3_500 );

    $component->call( 'refund', refundsPanelToken( $component ) )->assertHasNoErrors();

    expect( $this->order->fresh()->payment_status )->toBe( 'refunded' );

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )->assertDontSee( 'Issue refund' );
} );

it( 'validates the refund', function ( string $mode, array $set, string $error ): void {
    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )->call( 'startRefund' )->set( 'mode', $mode );

    foreach ( $set as $key => $value ) {
        $component->set( str_replace( '{item}', (string) $this->item->id, $key ), $value );
    }

    $component->call( 'refund', refundsPanelToken( $component ) )->assertHasErrors( [ str_replace( '{item}', (string) $this->item->id, $error ) ] );

    expect( Refund::query()->count() )->toBe( 0 );
} )->with( [
    'nothing chosen'     => [ 'items', [], 'lines' ],
    'too many units'     => [ 'items', [ 'lines.{item}.quantity' => 4, 'lines.{item}.amount' => 100 ], 'lines.{item}.quantity' ],
    'units no amount'    => [ 'items', [ 'lines.{item}.quantity' => 1, 'lines.{item}.amount' => 0 ], 'lines.{item}.amount' ],
    'amount over total'  => [ 'amount', [ 'amount' => 3_501 ], 'amount' ],
    'amount missing'     => [ 'amount', [ 'amount' => null ], 'amount' ],
    'unknown mode'       => [ 'bogus', [], 'mode' ],
    'more than the line' => [ 'items', [ 'lines.{item}.quantity' => 1, 'lines.{item}.amount' => 3_001 ], 'lines.{item}.amount' ],
] );

it( 'only offers the full balance when the gateway cannot refund part of an order', function (): void {
    refundsPanelGateway( partial: false, key: 'full-only' );
    $this->order->update( [ 'payment_gateway_key' => 'full-only' ] );

    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->assertSee( 'partial refunds are not available' )
        ->call( 'startRefund' )
        ->assertSet( 'mode', 'amount' )
        ->assertSet( 'amount', 3_500 )
        ->set( 'amount', 1_000 );

    $component->call( 'refund', refundsPanelToken( $component ) )
        ->assertHasErrors( [ 'refund' ] )
        ->assertSee( 'Fake Pay can only refund the full remaining balance of $35.00.' );

    expect( Refund::query()->count() )->toBe( 0 );
} );

it( 'shows the gateway\'s refusal without recording a refund', function (): void {
    refundsPanelGateway( declined: 'The card has expired.', key: 'declining' );
    $this->order->update( [ 'payment_gateway_key' => 'declining' ] );

    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( 'mode', 'amount' )
        ->set( 'amount', 500 );

    $component->call( 'refund', refundsPanelToken( $component ) )
        ->assertHasErrors( [ 'refund' ] )
        ->assertSee( 'The card has expired.' );

    expect( Refund::query()->count() )->toBe( 0 )
        ->and( $this->order->fresh()->payment_status )->toBe( 'paid' );
} );

it( 'refunds only once per token', function (): void {
    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )->call( 'startRefund' );
    $token     = refundsPanelToken( $component );

    $component->set( 'mode', 'amount' )->set( 'amount', 500 )->call( 'refund', $token );
    $component->set( 'refunding', true )->set( 'mode', 'amount' )->set( 'amount', 500 )->call( 'refund', $token );

    expect( Refund::query()->count() )->toBe( 1 );
} );

it( 'explains an order whose gateway is missing', function (): void {
    $this->order->update( [ 'payment_gateway_key' => 'gone' ] );

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->assertSee( 'The payment gateway "gone" is not installed' )
        ->assertDontSee( 'Issue refund' );
} );

it( 'ignores lines for another order\'s items', function (): void {
    $foreign = OrderItem::factory()->create( [ 'quantity' => 1, 'total_amount' => 900 ] );

    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( "lines.{$foreign->id}", [ 'quantity' => 1, 'amount' => 900, 'restock' => false ] )
        ->set( "lines.{$this->item->id}.quantity", 1 );

    $component->call( 'refund', refundsPanelToken( $component ) )->assertHasNoErrors();

    expect( Refund::query()->with( 'items' )->sole()->items->pluck( 'order_item_id' )->all() )->toBe( [ $this->item->id ] );
} );

it( 'opens the refund dialog when the page is opened with ?action=refund', function (): void {
    Livewire::withQueryParams( [ 'action' => 'refund' ] )
        ->test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->assertSet( 'refunding', true )
        ->assertSet( 'mode', 'items' );
} );

it( 'does not open the refund dialog from the URL without order.refund', function (): void {
    Illuminate\Support\Facades\Gate::define( 'ecommerce.order.refund', static fn (): bool => false );

    Livewire::withQueryParams( [ 'action' => 'refund' ] )
        ->test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->assertOk()
        ->assertSet( 'refunding', false );
} );

it( 'does not open the refund dialog from the URL when nothing is refundable', function (): void {
    $this->order->update( [ 'payment_status' => 'refunded', 'total_refunded_amount' => 3_500 ] );

    Livewire::withQueryParams( [ 'action' => 'refund' ] )
        ->test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->assertSet( 'refunding', false );
} );
