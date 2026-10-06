<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\InventoryReservation;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\Ecommerce\Models\OrderTimelineEntry;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Services\InventoryService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\StatusPanel;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view', 'order.update', 'order.cancel' ] );
    $this->actingAs( makeUser() );
} );

/**
 * The action token the rendered cancel button carries.
 */
function statusPanelCancelToken( $component ): string
{
    preg_match( '/cancelOrder\(\s*(?:&quot;|&#039;|\'|")([^&\'"]+)/', $component->html(), $matches );

    return $matches[1] ?? '';
}

it( 'renders the current status with only the allowed transitions', function (): void {
    grantAbilities( [ 'order.refund' ] );

    $order = Order::factory()->withSystemStatus( 'processing' )->create( [ 'payment_status' => 'refunded' ] );

    Livewire::test( StatusPanel::class, [ 'order' => $order ] )
        ->assertOk()
        ->assertSee( 'Move to' )
        ->assertSeeHtml( 'value="complete"' )
        ->assertSeeHtml( 'value="refunded"' )
        ->assertDontSeeHtml( 'value="pending"' )
        ->assertDontSeeHtml( 'value="cancelled"' )
        ->assertDontSeeHtml( 'value="failed"' )
        ->assertSee( 'Cancel order' );
} );

it( 'does not offer refunded for an order whose payment is not refunded', function (): void {
    grantAbilities( [ 'order.refund' ] );

    $order = Order::factory()->withSystemStatus( 'processing' )->create( [ 'payment_status' => 'paid' ] );

    Livewire::test( StatusPanel::class, [ 'order' => $order ] )
        ->assertDontSeeHtml( 'value="refunded"' )
        ->set( 'targetStatus', 'refunded' )
        ->call( 'changeStatus' )
        ->assertHasErrors( [ 'targetStatus' ] );

    expect( $order->fresh()->system_status )->toBe( 'processing' );
} );

it( 'only lets users who may refund mark an order refunded', function (): void {
    $order = Order::factory()->withSystemStatus( 'processing' )->create( [ 'payment_status' => 'refunded' ] );

    Livewire::test( StatusPanel::class, [ 'order' => $order ] )
        ->assertDontSeeHtml( 'value="refunded"' )
        ->set( 'targetStatus', 'refunded' )
        ->call( 'changeStatus' )
        ->assertHasErrors( [ 'targetStatus' ] );

    expect( $order->fresh()->system_status )->toBe( 'processing' );
} );

it( 'does not let an order be marked failed by hand', function (): void {
    $order = Order::factory()->withSystemStatus( 'pending' )->create();

    Livewire::test( StatusPanel::class, [ 'order' => $order ] )
        ->set( 'targetStatus', 'failed' )
        ->call( 'changeStatus' )
        ->assertHasErrors( [ 'targetStatus' ] );
} );

it( 'is denied without order.view', function (): void {
    Gate::define( 'ecommerce.order.view', static fn (): bool => false );

    Livewire::test( StatusPanel::class, [ 'order' => Order::factory()->create()->id ] )->assertForbidden();
} );

it( 'hides the controls without order.update and order.cancel', function (): void {
    Gate::define( 'ecommerce.order.update', static fn (): bool => false );
    Gate::define( 'ecommerce.order.cancel', static fn (): bool => false );

    Livewire::test( StatusPanel::class, [ 'order' => Order::factory()->create() ] )
        ->assertOk()
        ->assertDontSee( 'Move to' )
        ->assertDontSee( 'Cancel order' )
        ->call( 'changeStatus' )
        ->assertForbidden();
} );

it( 'marks a processing order complete through the status machine', function (): void {
    $order = Order::factory()->withSystemStatus( 'processing' )->create();

    Livewire::test( StatusPanel::class, [ 'order' => $order ] )
        ->set( 'targetStatus', 'complete' )
        ->set( 'statusReason', 'Shipped and delivered' )
        ->call( 'changeStatus' )
        ->assertHasNoErrors()
        ->assertDispatched( OrderPanelRegistry::ORDER_UPDATED_EVENT )
        ->assertSet( 'targetStatus', '' );

    expect( $order->fresh()->system_status )->toBe( 'complete' );

    $entry = OrderTimelineEntry::query()->where( 'order_id', $order->id )->sole();

    expect( $entry->event_type )->toBe( 'order.status_changed' )
        ->and( $entry->payload['reason'] )->toBe( 'Shipped and delivered' );
} );

it( 'does not offer or accept a transition the machine forbids', function ( string $target ): void {
    $order = Order::factory()->withSystemStatus( 'complete' )->create();

    Livewire::test( StatusPanel::class, [ 'order' => $order ] )
        ->set( 'targetStatus', $target )
        ->call( 'changeStatus' )
        ->assertHasErrors( [ 'targetStatus' ] );

    expect( $order->fresh()->system_status )->toBe( 'complete' );
} )->with( [ 'complete → pending' => 'pending', 'cancel bypass' => 'cancelled', 'empty' => '' ] );

it( 'explains board columns a new status would not fit', function (): void {
    $order     = Order::factory()->withSystemStatus( 'processing' )->create();
    $board     = KanbanBoard::factory()->create( [ 'name' => 'Production' ] );
    $substatus = OrderSubstatus::factory()->forSystemStatus( 'processing' )->create( [ 'label' => 'Printing' ] );

    OrderBoardAssignment::factory()->create( [ 'order_id' => $order->id, 'board_id' => $board->id, 'substatus_id' => $substatus->id ] );

    Livewire::test( StatusPanel::class, [ 'order' => $order ] )
        ->set( 'targetStatus', 'refunded' )
        ->assertSee( 'On "Production" the order sits in "Printing", which belongs to Processing.' )
        ->set( 'targetStatus', 'complete' )
        ->assertDontSee( 'On "Production"' );
} );

it( 'sets a sub-status of the order status and records it', function (): void {
    $order     = Order::factory()->withSystemStatus( 'processing' )->create();
    $substatus = OrderSubstatus::factory()->forSystemStatus( 'processing' )->create( [ 'label' => 'Packing' ] );

    Livewire::test( StatusPanel::class, [ 'order' => $order ] )
        ->set( 'substatusId', $substatus->id )
        ->call( 'changeSubstatus' )
        ->assertHasNoErrors();

    expect( $order->fresh()->substatus_id )->toBe( $substatus->id )
        ->and( OrderTimelineEntry::query()->where( 'event_type', 'order.substatus_changed' )->exists() )->toBeTrue();
} );

it( 'lists other statuses\' sub-statuses as disabled and explains refusing them', function (): void {
    $order = Order::factory()->withSystemStatus( 'pending' )->create();
    $other = OrderSubstatus::factory()->forSystemStatus( 'processing' )->create( [ 'label' => 'Packing' ] );

    Livewire::test( StatusPanel::class, [ 'order' => $order ] )
        ->assertSeeHtml( 'value="' . $other->id . '"  disabled' )
        ->assertSee( 'Greyed-out sub-statuses belong to another order status' )
        ->set( 'substatusId', $other->id )
        ->call( 'changeSubstatus' )
        ->assertHasErrors( [ 'substatusId' ] )
        ->assertSee( '"Packing" belongs to Processing, but the order is Pending.' );

    expect( $order->fresh()->substatus_id )->toBeNull();
} );

it( 'validates the sub-status', function ( mixed $value ): void {
    Livewire::test( StatusPanel::class, [ 'order' => Order::factory()->create() ] )
        ->set( 'substatusId', $value )
        ->call( 'changeSubstatus' )
        ->assertHasErrors( [ 'substatusId' ] );
} )->with( [ 'missing' => null, 'unknown' => 99999 ] );

it( 'explains a sub-status the order has outgrown', function (): void {
    $substatus = OrderSubstatus::factory()->forSystemStatus( 'processing' )->create( [ 'label' => 'Packing' ] );
    $order     = Order::factory()->withSystemStatus( 'complete' )->create( [ 'substatus_id' => $substatus->id ] );

    Livewire::test( StatusPanel::class, [ 'order' => $order ] )
        ->assertSee( '"Packing" belongs to Processing, but the order is Complete.' );
} );

it( 'summarises a cancel and cancels an unpaid order, releasing its stock', function (): void {
    $order   = Order::factory()->create( [ 'payment_status' => 'pending' ] );
    $product = Product::factory()->create( [ 'name' => 'Linen Shirt' ] );
    $stock   = InventoryItem::factory()->create( [ 'stockable_type' => $product->getMorphClass(), 'stockable_id' => $product->id ] );

    app( InventoryService::class )->reserve( $stock, $order, 2 );

    $component = Livewire::test( StatusPanel::class, [ 'order' => $order ] )
        ->set( 'confirmingCancel', true )
        ->assertSee( '2 reserved units go back on sale' )
        ->assertSee( 'Linen Shirt' )
        ->assertSee( 'No refund is owed.' );

    $component->set( 'cancelReason', 'Customer asked' )
        ->call( 'cancelOrder', statusPanelCancelToken( $component ) )
        ->assertHasNoErrors()
        ->assertSet( 'confirmingCancel', false )
        ->assertDispatched( OrderPanelRegistry::ORDER_UPDATED_EVENT );

    expect( $order->fresh()->system_status )->toBe( 'cancelled' )
        ->and( InventoryReservation::query()->count() )->toBe( 0 )
        ->and( $stock->fresh()->quantity_reserved )->toBe( 0 );
} );

it( 'says a paid order still needs refunding when cancelled', function (): void {
    $order = Order::factory()->withSystemStatus( 'processing' )->create( [ 'payment_status' => 'paid', 'total_amount' => 4_200 ] );

    Livewire::test( StatusPanel::class, [ 'order' => $order ] )
        ->set( 'confirmingCancel', true )
        ->assertSee( 'Cancelling does not refund: $42.00 is still owed' );
} );

it( 'requires a reason to cancel', function (): void {
    $order     = Order::factory()->create();
    $component = Livewire::test( StatusPanel::class, [ 'order' => $order ] )->set( 'confirmingCancel', true );

    $component->call( 'cancelOrder', statusPanelCancelToken( $component ) )
        ->assertHasErrors( [ 'cancelReason' => 'required' ] );

    expect( $order->fresh()->system_status )->toBe( 'pending' );
} );

it( 'cancels only once per token', function (): void {
    $order     = Order::factory()->create();
    $component = Livewire::test( StatusPanel::class, [ 'order' => $order ] )->set( 'confirmingCancel', true );
    $token     = statusPanelCancelToken( $component );

    $component->set( 'cancelReason', 'Duplicate' )->call( 'cancelOrder', $token );
    Illuminate\Support\Facades\DB::table( ( new Order() )->getTable() )->where( 'id', $order->id )->update( [ 'system_status' => 'pending' ] );

    $component->set( 'cancelReason', 'Duplicate' )->call( 'cancelOrder', $token );

    expect( $order->fresh()->system_status )->toBe( 'pending' )
        ->and( OrderTimelineEntry::query()->where( 'event_type', 'order.cancelled' )->count() )->toBe( 1 );
} );

it( 'rejects a forged cancel token', function (): void {
    $order = Order::factory()->create();

    Livewire::test( StatusPanel::class, [ 'order' => $order ] )
        ->set( 'cancelReason', 'x' )
        ->call( 'cancelOrder', 'forged' );

    expect( $order->fresh()->system_status )->toBe( 'pending' );
} );

it( 'does not offer cancel once the order can no longer be cancelled', function (): void {
    Livewire::test( StatusPanel::class, [ 'order' => Order::factory()->withSystemStatus( 'complete' )->create() ] )
        ->assertDontSee( 'Cancel order' );
} );

it( 'refuses to cancel without order.cancel', function (): void {
    Gate::define( 'ecommerce.order.cancel', static fn (): bool => false );

    Livewire::test( StatusPanel::class, [ 'order' => Order::factory()->create() ] )
        ->assertDontSee( 'Cancel order' )
        ->call( 'cancelOrder', 'token' )
        ->assertForbidden();
} );
