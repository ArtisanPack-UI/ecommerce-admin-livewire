<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Jobs\DeliverWebhookJob;
use ArtisanPackUI\Ecommerce\Models\WebhookDelivery;
use ArtisanPackUI\Ecommerce\Models\WebhookSubscription;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Webhooks\Show;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'webhookSubscription.viewAny', 'webhookSubscription.update' ] );
    $this->actingAs( makeUser() );
} );

/**
 * A delivery in a given state.
 */
function webhookDelivery( WebhookSubscription $subscription, array $attributes = [] ): WebhookDelivery
{
    return WebhookDelivery::factory()->create( $attributes + [ 'subscription_id' => $subscription->id ] );
}

it( 'renders the subscription and its deliveries newest first', function (): void {
    $subscription = WebhookSubscription::factory()->create( [ 'name' => 'Partner', 'events' => [ '*' ] ] );
    webhookDelivery( $subscription, [ 'event' => 'order.cancelled', 'attempts' => 1, 'response_status' => 200, 'delivered_at' => Carbon::now(), 'next_retry_at' => null ] );
    webhookDelivery( $subscription, [ 'event' => 'order.refunded', 'attempts' => 3, 'response_status' => 500, 'next_retry_at' => Carbon::now()->addHour() ] );
    webhookDelivery( WebhookSubscription::factory()->create(), [ 'event' => 'payment.failed' ] );

    Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->assertOk()
        ->assertSee( 'Partner' )
        ->assertSee( 'All events' )
        ->assertSeeHtml( 'data-delivery-status="retrying"' )
        ->assertSeeHtml( 'data-delivery-status="delivered"' )
        ->assertSee( 'HTTP 500' )
        ->assertSeeInOrder( [ 'order.refunded', 'order.cancelled' ] )
        ->assertDontSee( 'payment.failed' );
} );

it( 'is denied without webhookSubscription.viewAny', function (): void {
    Gate::define( 'ecommerce.webhookSubscription.viewAny', static fn (): bool => false );

    Livewire::test( Show::class, [ 'subscription' => WebhookSubscription::factory()->create()->id ] )->assertForbidden();
} );

it( 'returns not found for a missing subscription', function (): void {
    Livewire::test( Show::class, [ 'subscription' => 999 ] )->assertNotFound();
} );

it( 'filters deliveries by event and status', function (): void {
    $subscription = WebhookSubscription::factory()->create();
    webhookDelivery( $subscription, [ 'event' => 'order.cancelled', 'delivered_at' => Carbon::now(), 'next_retry_at' => null, 'attempts' => 1 ] );
    webhookDelivery( $subscription, [ 'event' => 'order.refunded', 'next_retry_at' => null, 'attempts' => 10, 'response_status' => 503 ] );
    webhookDelivery( $subscription, [ 'event' => 'order.edited', 'attempts' => 0 ] );

    Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->set( 'filters.status', 'failed' )
        ->assertSeeHtml( 'data-delivery-status="failed"' )
        ->assertDontSeeHtml( 'data-delivery-status="delivered"' )
        ->assertDontSeeHtml( 'data-delivery-status="pending"' )
        ->set( 'filters.status', 'pending' )
        ->assertSeeHtml( 'data-delivery-status="pending"' )
        ->assertDontSeeHtml( 'data-delivery-status="failed"' )
        ->set( 'filters.status', '' )
        ->set( 'filters.event', 'order.cancelled' )
        ->assertSeeHtml( 'data-delivery-status="delivered"' )
        ->assertDontSeeHtml( 'data-delivery-status="failed"' );
} );

it( 'shows the payload and the escaped response in the drawer', function (): void {
    $subscription = WebhookSubscription::factory()->create();
    $delivery     = webhookDelivery( $subscription, [
        'payload'         => [ 'event' => 'order.refunded', 'data' => [ 'order_number' => 'ORD-1042' ] ],
        'response_status' => 502,
        'response_body'   => '<h1>Bad gateway</h1>',
        'attempts'        => 2,
    ] );

    Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->call( 'openDelivery', $delivery->id )
        ->assertSet( 'viewingDelivery', true )
        ->assertSeeHtml( 'data-delivery="' . $delivery->id . '"' )
        ->assertSee( 'ORD-1042' )
        ->assertSee( 'HTTP 502' )
        ->assertSeeHtml( '&lt;h1&gt;Bad gateway&lt;/h1&gt;' )
        ->assertDontSeeHtml( '<h1>Bad gateway</h1>' );
} );

it( 'does not open another subscription\'s delivery', function (): void {
    $subscription = WebhookSubscription::factory()->create();
    $foreign      = webhookDelivery( WebhookSubscription::factory()->create() );

    Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->call( 'openDelivery', $foreign->id )
        ->assertSet( 'deliveryId', null )
        ->assertSet( 'viewingDelivery', false );
} );

it( 'replays a delivery as a new ledger row', function (): void {
    Queue::fake();
    $subscription = WebhookSubscription::factory()->create();
    $delivery     = webhookDelivery( $subscription, [ 'event' => 'order.refunded', 'next_retry_at' => null, 'attempts' => 10 ] );

    $component = Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )->call( 'openDelivery', $delivery->id );

    $component->call( 'replay', $component->viewData( 'replayToken' ) )
        ->assertSet( 'viewingDelivery', false );

    expect( WebhookDelivery::query()->where( 'subscription_id', $subscription->id )->count() )->toBe( 2 );
    Queue::assertPushed( DeliverWebhookJob::class );
} );

it( 'refuses to replay for a disabled subscription', function (): void {
    Queue::fake();
    $subscription = WebhookSubscription::factory()->inactive()->create( [ 'consecutive_failures' => 10 ] );
    $delivery     = webhookDelivery( $subscription );

    $component = Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->assertSeeHtml( 'data-subscription-disabled' )
        ->call( 'openDelivery', $delivery->id );

    $component->call( 'replay', $component->viewData( 'replayToken' ) );

    expect( WebhookDelivery::query()->count() )->toBe( 1 )
        ->and( sentToasts( $component ) )->toContain( 'This subscription is disabled.' );
    Queue::assertNothingPushed();
} );

it( 're-enables and rotates from the detail screen', function (): void {
    $subscription = WebhookSubscription::factory()->inactive()->create( [ 'consecutive_failures' => 10, 'secret' => 'whsec_previous-secret-0000000' ] );

    $component = Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->call( 'reEnable', $subscription->id )
        ->assertDontSeeHtml( 'data-subscription-disabled' )
        ->call( 'confirmRotate', $subscription->id );

    $component->call( 'rotateSecret', $component->viewData( 'rotateToken' ) );

    $subscription->refresh();

    expect( $subscription->is_active )->toBeTrue()
        ->and( $subscription->consecutive_failures )->toBe( 0 )
        ->and( $subscription->secret )->not->toBe( 'whsec_previous-secret-0000000' );

    $component->assertSee( $subscription->secret )->assertSet( 'showingSecret', true );
} );

it( 'hides replay, rotate, and re-enable without webhookSubscription.update', function (): void {
    Gate::define( 'ecommerce.webhookSubscription.update', static fn (): bool => false );
    $subscription = WebhookSubscription::factory()->inactive()->create();
    $delivery     = webhookDelivery( $subscription );

    Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->assertDontSeeHtml( 'wire:click="confirmRotate(' )
        ->assertDontSeeHtml( 'wire:click="reEnable(' )
        ->call( 'openDelivery', $delivery->id )
        ->assertDontSee( 'Replay delivery' )
        ->call( 'replay', 'token' )
        ->assertForbidden();
} );
