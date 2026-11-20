<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Contracts\PaymentGateway;
use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\OrderTimelineEntry;
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

    // The engine folds shipping and tax into each line's total, so the
    // lines add up to the order total: shirts 3,000 + gift wrap 500.
    $this->order = Order::factory()->create( [
        'payment_status'      => 'paid',
        'payment_gateway_key' => 'fake',
        'subtotal_amount'     => 3_500,
        'shipping_amount'     => 0,
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
    $this->wrap    = OrderItem::factory()->create( [
        'order_id'          => $this->order->id,
        'product_id'        => null,
        'product_snapshot'  => [ 'name' => 'Gift wrap', 'sku' => 'WRAP', 'type' => 'simple', 'options' => [] ],
        'quantity'          => 1,
        'unit_price_amount' => 500,
        'total_amount'      => 500,
    ] );
} );

/**
 * Registers a fake gateway under `$key`.
 */
function refundsPanelGateway( bool $partial = true, ?string $declined = null, string $key = 'fake', int $shortBy = 0 ): PaymentGateway
{
    $gateway = Mockery::mock( PaymentGateway::class );
    $gateway->shouldReceive( 'key' )->andReturn( $key );
    $gateway->shouldReceive( 'label' )->andReturn( 'Fake Pay' );
    $gateway->shouldReceive( 'supportsRefunds' )->andReturn( true );
    $gateway->shouldReceive( 'supportsPartialRefunds' )->andReturn( $partial );
    // The test's own transaction (RefreshDatabase) is the baseline.
    $baseline = Illuminate\Support\Facades\DB::transactionLevel();

    $gateway->shouldReceive( 'refund' )->andReturnUsing( static function ( Order $order, Money $amount ) use ( $declined, $shortBy, $baseline ): RefundResult {
        // Engine 1.0 calls the gateway outside any transaction of its own,
        // and the admin must not open one around it either.
        expect( Illuminate\Support\Facades\DB::transactionLevel() )->toBe( $baseline );

        return null === $declined ? RefundResult::success( $amount->subtract( Money::USD( $shortBy ) ), 're_panel_1' ) : RefundResult::failure( $amount, 'declined', $declined );
    } );

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

/**
 * The token and the total the submit button sends.
 *
 * @return array{0: string, 1: int}
 */
function refundsPanelArgs( $component ): array
{
    preg_match( '/refund\(\s*(?:&quot;|&#039;|\'|")[^&\'"]+(?:&quot;|&#039;|\'|"),\s*(\d+)\s*\)/', $component->html(), $matches );

    return [ refundsPanelToken( $component ), (int) ( $matches[1] ?? 0 ) ];
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
        ->assertSeeHtml( 'data-start-refund' );
} );

it( 'is denied without order.view', function (): void {
    Gate::define( 'ecommerce.order.view', static fn (): bool => false );

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order->id ] )->assertForbidden();
} );

it( 'hides and refuses refunds without order.refund', function (): void {
    Gate::define( 'ecommerce.order.refund', static fn (): bool => false );

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->assertDontSeeHtml( 'data-start-refund' )
        ->call( 'startRefund' )
        ->assertForbidden();

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'refund', 'token', 0 )
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

    $component->call( 'refund', ...refundsPanelArgs( $component ) )
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

    $component->call( 'refund', ...refundsPanelArgs( $component ) )->assertHasNoErrors();

    $refund = Refund::query()->with( 'items' )->sole();

    expect( $refund->amount )->toBe( 500 )
        ->and( $refund->items->first()->quantity )->toBe( 0 );
} );

it( 'refunds the full order', function (): void {
    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( 'mode', 'amount' )
        ->set( 'amount', 3_500 );

    $component->call( 'refund', ...refundsPanelArgs( $component ) )->assertHasNoErrors();

    $refund = Refund::query()->with( 'items' )->sole();

    expect( $this->order->fresh()->payment_status )->toBe( 'refunded' )
        ->and( $refund->items->pluck( 'amount', 'order_item_id' )->all() )->toBe( [ $this->item->id => 3_000, $this->wrap->id => 500 ] );

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )->assertDontSeeHtml( 'data-start-refund' );
} );

it( 'validates the refund', function ( string $mode, array $set, string $error ): void {
    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )->call( 'startRefund' )->set( 'mode', $mode );

    foreach ( $set as $key => $value ) {
        $component->set( str_replace( '{item}', (string) $this->item->id, $key ), $value );
    }

    $component->call( 'refund', ...refundsPanelArgs( $component ) )->assertHasErrors( [ str_replace( '{item}', (string) $this->item->id, $error ) ] );

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

    $component->call( 'refund', ...refundsPanelArgs( $component ) )
        ->assertHasErrors( [ 'refund' ] )
        ->assertSee( 'Fake Pay can only refund the full remaining balance of $35.00.' );

    expect( Refund::query()->count() )->toBe( 0 );
} );

it( 'shows the gateway\'s refusal and keeps the failed refund on record', function (): void {
    refundsPanelGateway( declined: 'The card has expired.', key: 'declining' );
    $this->order->update( [ 'payment_gateway_key' => 'declining' ] );

    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( 'mode', 'amount' )
        ->set( 'amount', 500 );

    $component->call( 'refund', ...refundsPanelArgs( $component ) )
        ->assertHasErrors( [ 'refund' ] )
        ->assertSee( 'The card has expired.' );

    // The engine records the attempt as failed, with a timeline entry; a
    // rollback around the gateway call would have erased both.
    expect( Refund::query()->sole()->status )->toBe( Refund::STATUS_FAILED )
        ->and( OrderTimelineEntry::query()->where( 'order_id', $this->order->id )->where( 'event_type', 'refund.failed' )->count() )->toBe( 1 )
        ->and( $this->order->fresh()->payment_status )->toBe( 'paid' );

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->assertSeeHtml( 'data-refund-status="failed"' )
        ->assertSee( '$35.00' );
} );

it( 'refunds only once per token', function (): void {
    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )->call( 'startRefund' );
    $token     = refundsPanelToken( $component );

    $component->set( 'mode', 'amount' )->set( 'amount', 500 )->call( 'refund', $token, 500 );
    $component->set( 'refunding', true )->set( 'mode', 'amount' )->set( 'amount', 500 )->call( 'refund', $token, 500 );

    expect( Refund::query()->count() )->toBe( 1 );
} );

it( 'refuses a refund whose total changed after it was shown, and keeps the token', function (): void {
    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( 'mode', 'amount' )
        ->set( 'amount', 1_000 );

    [ $token, $shown ] = refundsPanelArgs( $component );

    expect( $shown )->toBe( 1_000 );

    // The field changes in the same request as the click.
    $component->set( 'amount', 5_000 )->set( 'amount', 3_000 )->call( 'refund', $token, $shown )
        ->assertHasErrors( [ 'refund' ] )
        ->assertSee( 'The amount changed. Check it and confirm again.' )
        ->assertSee( 'Refund $30.00' );

    expect( Refund::query()->count() )->toBe( 0 );

    $component->call( 'refund', $token, 3_000 )->assertHasNoErrors();

    expect( Refund::query()->sole()->amount )->toBe( 3_000 );
} );

it( 'explains an order whose gateway is missing', function (): void {
    $this->order->update( [ 'payment_gateway_key' => 'gone' ] );

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->assertSee( 'The payment gateway "gone" is not installed' )
        ->assertDontSeeHtml( 'data-start-refund' );
} );

it( 'ignores lines for another order\'s items', function (): void {
    $foreign = OrderItem::factory()->create( [ 'quantity' => 1, 'total_amount' => 900 ] );

    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( "lines.{$foreign->id}", [ 'quantity' => 1, 'amount' => 900, 'restock' => false ] )
        ->set( "lines.{$this->item->id}.quantity", 1 );

    $component->call( 'refund', ...refundsPanelArgs( $component ) )->assertHasNoErrors();

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

it( 'fully refunds a two-line order through a gateway that cannot refund part of it', function (): void {
    refundsPanelGateway( partial: false, key: 'full-only' );
    $this->order->update( [ 'payment_gateway_key' => 'full-only' ] );

    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->assertSet( 'amount', 3_500 );

    $component->call( 'refund', ...refundsPanelArgs( $component ) )->assertHasNoErrors();

    expect( $this->order->fresh()->payment_status )->toBe( 'refunded' )
        ->and( Refund::query()->sole()->items )->toHaveCount( 2 );
} );

it( 'refuses an amount the order\'s lines can\'t hold, before the engine sees it', function (): void {
    $this->order->update( [ 'total_amount' => 4_000 ] );

    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( 'mode', 'amount' )
        ->set( 'amount', 3_600 );

    $component->call( 'refund', ...refundsPanelArgs( $component ) )
        ->assertHasErrors( [ 'amount' ] )
        ->assertSee( 'Only $35.00 can be refunded against the order&#039;s lines.', false );

    expect( Refund::query()->count() )->toBe( 0 );
} );

it( 'keeps a mismatched refund pending and refuses another until it is reconciled', function (): void {
    refundsPanelGateway( key: 'short', shortBy: 100 );
    $this->order->update( [ 'payment_gateway_key' => 'short' ] );

    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( 'mode', 'amount' )
        ->set( 'amount', 1_000 );

    $component->call( 'refund', ...refundsPanelArgs( $component ) )->assertHasErrors( [ 'refund' ] );

    $pending = Refund::query()->sole();

    expect( $pending->status )->toBe( Refund::STATUS_PENDING );

    // The pending 1,000 is no longer refundable.
    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order->fresh() ] )
        ->assertSeeHtml( 'data-refund-status="pending"' )
        ->assertSee( '$25.00' )
        ->call( 'startRefund' )
        ->set( 'mode', 'amount' )
        ->set( 'amount', 3_000 );

    $component->call( 'refund', ...refundsPanelArgs( $component ) )->assertHasErrors( [ 'amount' ] );

    expect( Refund::query()->count() )->toBe( 1 );
} );

it( 'lets the units of a declined refund be refunded again', function (): void {
    refundsPanelGateway( declined: 'Try later.', key: 'declining' );
    $this->order->update( [ 'payment_gateway_key' => 'declining' ] );

    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( "lines.{$this->item->id}.quantity", 3 );

    $component->call( 'refund', ...refundsPanelArgs( $component ) )->assertHasErrors( [ 'refund' ] );

    $this->order->update( [ 'payment_gateway_key' => 'fake' ] );

    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order->fresh() ] )
        ->call( 'startRefund' )
        ->set( "lines.{$this->item->id}.quantity", 3 )
        ->assertSet( "lines.{$this->item->id}.amount", 3_000 );

    $component->call( 'refund', ...refundsPanelArgs( $component ) )->assertHasNoErrors();

    expect( Refund::query()->where( 'status', Refund::STATUS_SUCCEEDED )->sole()->amount )->toBe( 3_000 );
} );

it( 'pre-fills no more than is left on a line after an earlier amount refund', function (): void {
    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( 'mode', 'amount' )
        ->set( 'amount', 500 );

    $component->call( 'refund', ...refundsPanelArgs( $component ) )->assertHasNoErrors();

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order->fresh() ] )
        ->call( 'startRefund' )
        ->set( "lines.{$this->item->id}.quantity", 3 )
        ->assertSet( "lines.{$this->item->id}.amount", 2_500 );
} );

it( 'builds the history as a flush design-system table with status badges', function (): void {
    Refund::factory()->create( [ 'order_id' => $this->order->id, 'amount' => 1_000, 'currency' => 'USD', 'status' => Refund::STATUS_PENDING ] );

    $html = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )->html();

    expect( $html )
        ->toContain( 'ec-card-flush' )
        ->toContain( 'data-refund-history' )
        ->toContain( 'data-refund-status="pending"' )
        ->toContain( 'Pending' )
        ->not->toContain( 'table-sm' )
        ->not->toContain( 'opacity-75' );
} );

it( 'shows the gateway error under a failed refund', function (): void {
    $refund = Refund::factory()->create( [ 'order_id' => $this->order->id, 'amount' => 1_000, 'currency' => 'USD', 'status' => Refund::STATUS_FAILED, 'reason' => 'Damaged' ] );
    OrderTimelineEntry::query()->create( [
        'order_id'   => $this->order->id,
        'event_type' => 'refund.failed',
        'payload'    => [ 'refund_id' => $refund->id, 'amount' => 1_000, 'currency' => 'USD', 'gateway' => 'fake', 'error' => 'charge_already_refunded' ],
    ] );

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->assertSee( 'Failed' )
        ->assertSeeHtml( 'data-refund-error' )
        ->assertSee( 'Gateway: charge_already_refunded' );
} );

it( 'names the running total in the primary button and keeps it enabled at zero', function (): void {
    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )->call( 'startRefund' );

    // A server-rendered disabled state would swallow the click that lands
    // with the blur of the first field; the confirmed total guards instead.
    expect( Illuminate\Support\Str::betweenFirst( $component->html(), 'data-submit-refund', '</button>' ) )->toContain( 'Refund $0.00' );
    expect( $component->html() )->not->toMatch( '/<button[^>]*\sdisabled[^>]*data-submit-refund/s' );

    $component->set( "lines.{$this->item->id}.quantity", 1 );

    expect( $component->html() )
        ->toContain( 'Refund $10.00' )
        ->not->toMatch( '/<button[^>]*\sdisabled[^>]*data-submit-refund/s' );

    $component->set( 'mode', 'amount' )->set( 'amount', 250 );

    expect( $component->html() )->toContain( 'Refund $2.50' );
} );

it( 'fills the full remaining balance from the amount view', function (): void {
    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( 'mode', 'amount' )
        ->assertSee( 'Use the full remaining balance ($35.00)' );

    $component->set( 'amount', 3_500 )->assertSee( 'Refund $35.00' );
} );

it( 'names each per-line control for its item and restocks with a checkbox', function (): void {
    $html = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )->call( 'startRefund' )->html();

    expect( $html )
        ->toContain( 'aria-label="Quantity of Linen Shirt to refund"' )
        ->toContain( 'aria-label="Restock Linen Shirt"' )
        ->toContain( 'class="apui-checkbox' )
        ->toContain( 'data-variant="segmented"' )
        ->not->toContain( 'apui-toggle' );
} );

it( 'hides "Refund by" for a gateway without partial refunds and says why', function (): void {
    refundsPanelGateway( partial: false, key: 'full-only' );
    $this->order->update( [ 'payment_gateway_key' => 'full-only' ] );

    Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->assertSeeHtml( 'data-full-only-note' )
        ->assertDontSee( 'Refund by' )
        ->assertSee( 'Refund $35.00' );
} );

it( 'leaves a line whose item left the order out of the running total', function (): void {
    $component = Livewire::test( RefundsPanel::class, [ 'order' => $this->order ] )
        ->call( 'startRefund' )
        ->set( "lines.{$this->item->id}.quantity", 1 )
        ->set( "lines.{$this->wrap->id}.amount", 500 )
        ->assertSee( 'Refund $15.00' );

    // An edit removes the gift wrap while the dialog is open.
    $this->wrap->delete();

    $component->dispatch( OrderPanelRegistry::ORDER_UPDATED_EVENT )
        ->assertSee( 'Refund $10.00' )
        ->assertDontSee( 'Refund $15.00' );
} );
