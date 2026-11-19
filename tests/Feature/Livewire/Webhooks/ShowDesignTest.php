<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\WebhookDelivery;
use ArtisanPackUI\Ecommerce\Models\WebhookSubscription;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Webhooks\Show;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Webhooks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'webhookSubscription.viewAny', 'webhookSubscription.update' ] );
    $this->actingAs( makeUser() );
} );

it( 'shows the URL in left-to-right mono and an Active badge', function (): void {
    $subscription = WebhookSubscription::factory()->create( [ 'name' => 'Fulfillment sync', 'url' => 'https://ops.example.com/hooks/orders', 'events' => [ 'order.paid' ] ] );

    Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->assertSeeHtml( 'data-subscription-url' )
        ->assertSeeHtml( 'https://ops.example.com/hooks/orders</bdi>' )
        ->assertSeeHtml( 'data-subscription-state="active"' )
        ->assertSee( 'Active' )
        ->assertDontSeeHtml( 'data-subscription-disabled' )
        ->assertDontSeeHtml( 'data-re-enable' )
        ->assertSee( 'order.paid' );
} );

it( 'names the failures in a row and makes Re-enable primary when failures disabled it', function (): void {
    $subscription = WebhookSubscription::factory()->inactive()->create( [ 'consecutive_failures' => Webhooks::disableAfter() ] );

    $html = Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->assertSeeHtml( 'data-subscription-disabled="failures"' )
        ->assertSee( 'Disabled after ' . Webhooks::disableAfter() . ' failed deliveries in a row' )
        ->assertSeeHtml( 'data-subscription-state="disabled"' )
        ->assertSeeHtml( 'data-subscription-failures="' . Webhooks::disableAfter() . '"' )
        ->assertSeeHtml( 'ec-webhook-detail-failures' )
        ->html();

    expect( $html )->toMatch( '/<[^>]*data-variant="solid"[^>]*data-re-enable|data-re-enable[^>]*data-variant="solid"/' );
} );

it( 'says plainly that staff turned it off', function (): void {
    $subscription = WebhookSubscription::factory()->inactive()->create( [ 'consecutive_failures' => 0 ] );

    Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->assertSeeHtml( 'data-subscription-disabled="staff"' )
        ->assertSee( 'This subscription is disabled' )
        ->assertDontSee( 'in a row' )
        ->assertDontSeeHtml( 'ec-webhook-detail-failures' );
} );

it( 'shows All events as a primary badge', function (): void {
    $subscription = WebhookSubscription::factory()->create( [ 'events' => [ WebhookSubscription::ALL_EVENTS ] ] );

    $html = Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )->html();

    expect( $html )->toContain( 'All events' )
        ->and( $html )->toMatch( '/data-color="primary"[^>]*>.*?All events/s' );
} );

it( 'pairs each delivery status with its color, icon and HTTP code', function ( array $attributes, string $status, string $color ): void {
    $subscription = WebhookSubscription::factory()->create();
    WebhookDelivery::factory()->create( $attributes + [ 'subscription_id' => $subscription->id ] );

    $html = Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )->html();

    expect( $html )->toContain( 'data-delivery-status="' . $status . '"' )
        ->and( $html )->toMatch( '/data-delivery-status="' . $status . '".*?data-color="' . $color . '"/s' );
} )->with( [
    'delivered' => [ [ 'delivered_at' => Carbon::now(), 'next_retry_at' => null, 'attempts' => 1, 'response_status' => 200 ], 'delivered', 'success' ],
    'pending'   => [ [ 'delivered_at' => null, 'next_retry_at' => Carbon::now()->addMinute(), 'attempts' => 0, 'response_status' => null ], 'pending', 'neutral' ],
    'retrying'  => [ [ 'delivered_at' => null, 'next_retry_at' => Carbon::now()->addMinutes( 4 ), 'attempts' => 3, 'response_status' => 503 ], 'retrying', 'warning' ],
    'failed'    => [ [ 'delivered_at' => null, 'next_retry_at' => null, 'attempts' => 8, 'response_status' => 404 ], 'failed', 'error' ],
] );

it( 'shows the next retry relative to now and a dash once settled', function (): void {
    $subscription = WebhookSubscription::factory()->create();
    WebhookDelivery::factory()->create( [ 'subscription_id' => $subscription->id, 'delivered_at' => null, 'next_retry_at' => Carbon::now()->addMinutes( 4 ), 'attempts' => 3 ] );
    WebhookDelivery::factory()->create( [ 'subscription_id' => $subscription->id, 'delivered_at' => Carbon::now(), 'next_retry_at' => null, 'attempts' => 1 ] );

    Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->assertSeeHtml( 'data-delivery-next-retry' )
        ->assertSee( 'from now' )
        ->assertSee( '—' );
} );

it( 'keeps Delivered at out of the grid but in the export', function (): void {
    $subscription = WebhookSubscription::factory()->create();
    WebhookDelivery::factory()->create( [ 'subscription_id' => $subscription->id ] );

    $component = Livewire::test( Show::class, [ 'subscription' => $subscription->id ] );

    $listed = array_column( $component->viewData( 'tableColumns' ), 'key' );

    expect( $listed )->toBe( [ 'event', 'status', 'attempts', 'next_retry', 'created', 'actions' ] );
} );

it( 'blocks Replay in the drawer while the subscription is disabled', function (): void {
    $subscription = WebhookSubscription::factory()->inactive()->create();
    $delivery     = WebhookDelivery::factory()->create( [ 'subscription_id' => $subscription->id ] );

    $html = Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->call( 'openDelivery', $delivery->id )
        ->assertSeeHtml( 'data-replay-blocked' )
        ->assertSee( 'Re-enable this subscription to replay its deliveries.' )
        ->html();

    expect( $html )->toMatch( '/<button[^>]*disabled[^>]*data-replay-delivery|data-replay-delivery[^>]*disabled/' );
} );

it( 'titles the drawer with the delivery and keeps payload and response left-to-right', function (): void {
    $subscription = WebhookSubscription::factory()->create();
    $delivery     = WebhookDelivery::factory()->create( [ 'subscription_id' => $subscription->id, 'event' => 'order.refunded', 'response_status' => 503, 'response_body' => 'upstream unavailable' ] );

    $html = Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->call( 'openDelivery', $delivery->id )
        ->assertSee( 'Delivery ' . $delivery->id . ' (order.refunded)' )
        ->assertDontSeeHtml( 'data-replay-blocked' )
        ->html();

    expect( $html )->toMatch( '/<pre[^>]*dir="ltr"[^>]*data-delivery-payload/' )
        ->and( $html )->toMatch( '/<pre[^>]*dir="ltr"[^>]*data-delivery-response/' );
} );

it( 'shows the empty state when there are no deliveries', function (): void {
    $subscription = WebhookSubscription::factory()->create();

    Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->assertSee( 'No deliveries yet' )
        ->assertSee( 'Each event sent to this URL is logged here with the response.' );
} );

it( 'leaves no DaisyUI or Tailwind utility classes on the page', function (): void {
    Gate::define( 'ecommerce.webhookSubscription.update', static fn (): bool => true );
    $subscription = WebhookSubscription::factory()->inactive()->create( [ 'consecutive_failures' => 3 ] );
    $delivery     = WebhookDelivery::factory()->create( [ 'subscription_id' => $subscription->id, 'response_status' => 500, 'response_body' => 'oops' ] );

    $html = Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->call( 'openDelivery', $delivery->id )
        ->html();

    preg_match_all( '/class="[^"]*(?<![\w-])(btn|badge-[a-z]+|alert-[a-z]+|bg-base-\d+|rounded-box|opacity-75|font-semibold|text-lg|grid-cols-\d|flex-col|text-xs)(?![\w-])[^"]*"/', $html, $found );

    expect( $found[0] )->toBe( [] );
} );

it( 'links Edit to the list with the edit drawer open', function (): void {
    $subscription = WebhookSubscription::factory()->create();

    $html = Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->assertSeeHtml( 'data-edit-subscription' )
        ->html();

    expect( $html )->toContain( e( route( 'artisanpack.ecommerce.admin.webhooks.index', [ 'edit' => $subscription->id ] ) ) );
} );

it( 'hides Edit from people who cannot update subscriptions', function (): void {
    Gate::define( 'ecommerce.webhookSubscription.update', fn (): bool => false );

    $subscription = WebhookSubscription::factory()->create();

    Livewire::test( Show::class, [ 'subscription' => $subscription->id ] )
        ->assertDontSeeHtml( 'data-edit-subscription' );
} );
