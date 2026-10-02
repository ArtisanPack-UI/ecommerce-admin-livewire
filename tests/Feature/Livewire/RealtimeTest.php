<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Dashboard;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Index;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Show;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminBroadcasts;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use function Livewire\invade;

beforeEach( function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view', 'product.viewAny', 'webhookSubscription.viewAny', 'report.view', 'inventory.viewAny', 'review.viewAny' ] );
    $this->actingAs( $this->user = makeUser() );

    config()->set( 'artisanpack.ecommerce-admin-livewire.realtime.enabled', true );
    config()->set( 'artisanpack.ecommerce.graphql.subscriptions', true );
} );

/**
 * A broadcast payload for an order, as Echo delivers it.
 *
 * @return array<string, mixed>
 */
function realtimePayload( Order $order, string $event = AdminBroadcasts::ORDER_STATUS_CHANGED ): array
{
    return [ 'data' => [ $event => [ 'order' => [ 'id' => $order->id ], 'from' => 'pending', 'to' => 'processing' ] ] ];
}

/**
 * The listeners a mounted component registers.
 *
 * @return array<string, string>
 */
function realtimeListeners( $component ): array
{
    return invade( $component->instance() )->getListeners();
}

it( 'subscribes to the admin channel only when every condition holds', function (): void {
    expect( AdminBroadcasts::enabled( $this->user ) )->toBeTrue()
        ->and( AdminBroadcasts::enabled( null ) )->toBeFalse();

    config()->set( 'artisanpack.ecommerce-admin-livewire.realtime.enabled', false );
    expect( AdminBroadcasts::enabled( $this->user ) )->toBeFalse();

    config()->set( 'artisanpack.ecommerce-admin-livewire.realtime.enabled', true );
    config()->set( 'artisanpack.ecommerce.graphql.subscriptions', false );
    expect( AdminBroadcasts::enabled( $this->user ) )->toBeFalse();

    config()->set( 'artisanpack.ecommerce.graphql.subscriptions', true );
    Gate::define( 'ecommerce.webhookSubscription.viewAny', static fn (): bool => false );
    expect( AdminBroadcasts::enabled( $this->user ) )->toBeFalse();
} );

it( 'is off by default and registers no Echo listener', function (): void {
    config()->set( 'artisanpack.ecommerce-admin-livewire.realtime.enabled', false );

    expect( (bool) config( 'artisanpack.ecommerce-admin-livewire.realtime.enabled' ) )->toBeFalse();

    $listeners = realtimeListeners( Livewire::test( Index::class ) );

    expect( array_filter( array_keys( $listeners ), static fn ( string $name ): bool => str_starts_with( $name, 'echo' ) ) )->toBe( [] );
} );

it( 'registers Echo listeners on the dashboard, orders index, and order detail when on', function (): void {
    $order = Order::factory()->create();

    expect( realtimeListeners( Livewire::test( Dashboard::class ) ) )->toMatchArray( [
        'echo-private:ecommerce.admin,.orderStatusChanged' => 'orderBroadcast',
        'echo-private:ecommerce.admin,.paymentSucceeded'   => 'orderBroadcast',
        'echo-private:ecommerce.admin,.stockChanged'       => 'stockBroadcast',
        'echo-private:ecommerce.admin,.reviewSubmitted'    => 'reviewBroadcast',
    ] );

    expect( realtimeListeners( Livewire::test( Index::class ) ) )->toHaveKey( 'echo-private:ecommerce.admin,.paymentSucceeded' );

    expect( realtimeListeners( Livewire::test( Show::class, [ 'order' => $order->id ] ) ) )->toMatchArray( [
        OrderPanelRegistry::ORDER_UPDATED_EVENT            => 'orderUpdated',
        'echo-private:ecommerce.admin,.orderStatusChanged' => 'orderBroadcast',
    ] );
} );

it( 'reads the order id from a broadcast payload and ignores malformed ones', function (): void {
    $order = Order::factory()->create();

    expect( AdminBroadcasts::orderId( realtimePayload( $order ) ) )->toBe( $order->id )
        ->and( AdminBroadcasts::orderId( [ 'data' => [ 'paymentSucceeded' => [ 'order' => [ 'id' => '7' ] ] ] ] ) )->toBe( 7 )
        ->and( AdminBroadcasts::orderId( null ) )->toBeNull()
        ->and( AdminBroadcasts::orderId( [ 'data' => 'x' ] ) )->toBeNull()
        ->and( AdminBroadcasts::orderId( [ 'data' => [ 'e' => [ 'order' => [ 'id' => 'abc' ] ] ] ] ) )->toBeNull();
} );

it( 'counts new orders on the index and announces them politely', function (): void {
    Order::factory()->create( [ 'order_number' => '1000' ] );

    $component = Livewire::test( Index::class )
        ->assertSet( 'newOrders', 0 )
        ->assertSeeHtml( 'aria-live="polite"' )
        ->assertDontSeeHtml( 'data-new-orders' );

    Order::factory()->create( [ 'order_number' => '1001' ] );
    Order::factory()->create( [ 'order_number' => '1002' ] );

    $component->call( 'orderBroadcast' )
        ->assertSet( 'newOrders', 2 )
        ->assertSet( 'liveAnnouncement', '2 new orders arrived.' )
        ->assertSeeHtml( 'data-new-orders="2"' )
        ->assertSee( '2 new orders since you opened this page.' )
        ->assertSeeInOrder( [ '#1002', '#1001', '#1000' ] );

    // A status change on a known order re-renders without counting it.
    $component->call( 'orderBroadcast' )->assertSet( 'newOrders', 2 );

    $component->call( 'dismissNewOrders' )
        ->assertSet( 'newOrders', 0 )
        ->assertDontSeeHtml( 'data-new-orders' );
} );

it( 'flags the order detail when the order changes elsewhere', function (): void {
    $order = Order::factory()->create();
    $other = Order::factory()->create();

    $component = Livewire::test( Show::class, [ 'order' => $order->id ] )
        ->assertSet( 'changedElsewhere', false )
        ->assertSeeHtml( 'aria-live="polite"' );

    // Another order, or no change: nothing to report.
    $component->call( 'orderBroadcast', realtimePayload( $other ) )->assertSet( 'changedElsewhere', false );
    $component->call( 'orderBroadcast', realtimePayload( $order ) )->assertSet( 'changedElsewhere', false );

    $this->travel( 5 )->seconds();
    $order->forceFill( [ 'system_status' => 'processing' ] )->save();

    $component->call( 'orderBroadcast', realtimePayload( $order ) )
        ->assertSet( 'changedElsewhere', true )
        ->assertSeeHtml( 'data-changed-elsewhere' )
        ->assertSet( 'liveAnnouncement', 'This order was changed elsewhere. Reload it to see the latest details.' );

    $component->call( 'reloadOrder' )
        ->assertSet( 'changedElsewhere', false )
        ->assertDispatched( OrderPanelRegistry::ORDER_UPDATED_EVENT )
        ->assertDontSeeHtml( 'data-changed-elsewhere' );
} );

it( 'clears the changed-elsewhere notice when a panel on the page made the change', function (): void {
    $order     = Order::factory()->create();
    $component = Livewire::test( Show::class, [ 'order' => $order->id ] );

    $this->travel( 5 )->seconds();
    $order->forceFill( [ 'system_status' => 'processing' ] )->save();

    // The broadcast can land before the panel's own refresh event.
    $component->call( 'orderBroadcast', realtimePayload( $order ) )->assertSet( 'changedElsewhere', true );
    $component->dispatch( OrderPanelRegistry::ORDER_UPDATED_EVENT )->assertSet( 'changedElsewhere', false );

    // …or after it, in which case it is ignored.
    $component->call( 'orderBroadcast', realtimePayload( $order ) )->assertSet( 'changedElsewhere', false );
} );

it( 'refreshes the dashboard KPIs on a broadcast and announces the update', function (): void {
    $component = Livewire::test( Dashboard::class )->assertSee( 'Welcome to your store' );

    Order::factory()->create( [ 'placed_at' => now(), 'system_status' => 'processing', 'fulfillment_status' => 'unfulfilled' ] );

    $component->call( 'orderBroadcast' )
        ->assertSet( 'liveAnnouncement', 'Orders changed. The dashboard has been updated.' )
        ->assertSee( '1 order to ship' );

    $component->call( 'stockBroadcast' )->assertSet( 'liveAnnouncement', 'Stock changed. The dashboard has been updated.' );
    $component->call( 'reviewBroadcast' )->assertSet( 'liveAnnouncement', 'A review was submitted. The dashboard has been updated.' );
} );

it( 'denies broadcast handlers once access is revoked', function (): void {
    $order     = Order::factory()->create();
    $component = Livewire::test( Show::class, [ 'order' => $order->id ] );

    Gate::define( 'ecommerce.order.view', static fn (): bool => false );

    $component->call( 'orderBroadcast', realtimePayload( $order ) )->assertForbidden();
} );

it( 'keeps the changed-elsewhere flag and counts locked against the client', function (): void {
    $order = Order::factory()->create();

    expect( fn () => Livewire::test( Show::class, [ 'order' => $order->id ] )->set( 'changedElsewhere', true ) )
        ->toThrow( \Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class );

    expect( fn () => Livewire::test( Index::class )->set( 'newOrders', 9 ) )
        ->toThrow( \Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class );
} );
