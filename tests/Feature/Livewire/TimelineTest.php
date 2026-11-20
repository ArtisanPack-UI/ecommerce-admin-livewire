<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\ActivityLogEntry;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderTimelineEntry;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Timeline;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Fixtures\User;

beforeEach( function (): void {
    config()->set( 'auth.providers.users.model', User::class );
    grantAbilities( [ 'order.viewAny', 'order.view', 'product.view' ] );
    $this->actingAs( makeUser() );
    $this->order = Order::factory()->create( [ 'currency' => 'USD' ] );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceAdminLivewire.timeline.entry' );
} );

/**
 * Writes an order timeline entry at a given time.
 *
 * @param  array<string, mixed>  $payload
 */
function timelineEntry( Order $order, string $type, array $payload = [], ?int $actor = null, ?Carbon $at = null ): OrderTimelineEntry
{
    return OrderTimelineEntry::query()->create( [
        'order_id'      => $order->id,
        'actor_user_id' => $actor,
        'event_type'    => $type,
        'payload'       => $payload,
        'created_at'    => $at ?? Carbon::now(),
    ] );
}

it( 'renders order entries newest first with actor, time, and descriptions', function (): void {
    $admin = makeUser( [ 'name' => 'Grace Hopper' ] );

    timelineEntry( $this->order, 'order.placed', [ 'total' => 4_500, 'currency' => 'USD' ], null, Carbon::now()->subDays( 2 ) );
    timelineEntry( $this->order, 'order.status_changed', [ 'from' => 'pending', 'to' => 'processing', 'reason' => 'Paid' ], $admin->id, Carbon::now()->subDay() );
    timelineEntry( $this->order, 'order.refunded', [ 'amount' => 1_500, 'currency' => 'USD', 'reason' => 'Damaged' ], $admin->id, Carbon::now() );

    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )
        ->assertOk()
        ->assertSeeInOrder( [ 'Refunded $15.00', 'Reason: Damaged', 'Grace Hopper', 'Status changed from Pending to Processing', 'Order placed', '$45.00', 'System' ] )
        ->assertSeeHtml( 'class="apui-timeline"' )
        ->assertSeeHtml( 'aria-label="Activity, newest first"' )
        ->assertSeeHtml( 'data-part="time" datetime="' );
} );

it( 'describes cancellations, edits, notes, and board moves', function (): void {
    $board = KanbanBoard::factory()->create( [ 'name' => 'Production' ] );

    timelineEntry( $this->order, 'order.cancelled', [ 'reason' => 'Customer asked', 'released' => [ [ 'inventory_item_id' => 1, 'quantity' => 2 ] ], 'payment_voided' => true, 'refund_owed' => 0, 'currency' => 'USD' ] );
    timelineEntry( $this->order, 'order.edited', [ 'reason' => 'Size swap', 'totals' => [ 'before' => [ 'total_amount' => 1_000 ], 'after' => [ 'total_amount' => 1_200, 'currency' => 'USD' ] ] ] );
    timelineEntry( $this->order, 'note.added', [ 'note_id' => 1, 'excerpt' => 'Gift wrap', 'is_customer_visible' => true ] );
    timelineEntry( $this->order, 'kanban.assignment_added', [ 'board_id' => $board->id, 'board_key' => 'prod' ] );

    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )
        ->assertSee( 'Order cancelled' )
        ->assertSee( '2 reserved units released' )
        ->assertSee( 'payment voided' )
        ->assertSee( 'total $10.00 → $12.00' )
        ->assertSee( 'Note added: "Gift wrap" (visible to the customer)' )
        ->assertSee( 'Added to board "Production"' );
} );

it( 'is denied without view on the subject', function (): void {
    Gate::define( 'ecommerce.order.view', static fn (): bool => false );

    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )->assertForbidden();
} );

it( 'refuses unsupported subjects', function (): void {
    expect( fn () => Livewire::test( Timeline::class, [ 'subject' => makeUser() ] ) )->toThrow( 'The timeline cannot show a ' . User::class );
} );

it( 'filters notes and system events', function (): void {
    timelineEntry( $this->order, 'note.added', [ 'excerpt' => 'Called the customer' ] );
    timelineEntry( $this->order, 'order.placed' );

    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )
        ->set( 'filter', 'notes' )
        ->assertSee( 'Called the customer' )
        ->assertDontSee( 'Order placed' )
        ->set( 'filter', 'system' )
        ->assertSee( 'Order placed' )
        ->assertDontSee( 'Called the customer' )
        ->set( 'filter', 'bogus' )
        ->assertSet( 'filter', 'all' )
        ->assertSee( 'Called the customer' )
        ->assertSee( 'Order placed' );
} );

it( 'shows an empty state', function (): void {
    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )
        ->assertSeeHtml( 'data-empty-state' )
        ->assertDontSee( 'Load more' );
} );

it( 'loads more entries a page at a time', function (): void {
    foreach ( range( 1, Timeline::PAGE_SIZE + 3 ) as $minute ) {
        timelineEntry( $this->order, 'note.added', [ 'excerpt' => 'Note ' . $minute ], null, Carbon::now()->addMinutes( $minute ) );
    }

    $component = Livewire::test( Timeline::class, [ 'subject' => $this->order ] )
        ->assertSee( 'Load more' )
        ->assertSee( '"Note 23"' )
        ->assertDontSee( '"Note 3"' );

    $component->call( 'loadMore' )
        ->assertSet( 'limit', Timeline::PAGE_SIZE * 2 )
        ->assertSee( '"Note 1"' )
        ->assertDontSee( 'Load more' );
} );

it( 'falls back to the raw type and a collapsible payload for unknown events', function (): void {
    timelineEntry( $this->order, 'loyalty.points_awarded', [ 'points' => 120 ] );

    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )
        ->assertSee( 'loyalty.points_awarded' )
        ->assertSeeHtml( '<details' )
        ->assertSee( '"points": 120' );
} );

it( 'hides personal and secret values in an unknown event\'s payload', function (): void {
    timelineEntry( $this->order, 'gateway.webhook_received', [
        'event'            => 'charge.updated',
        'description'      => 'Shipping upgraded',
        'customer_email'   => 'ada@example.test',
        'ipAddress'        => '203.0.113.9',
        'client_ip'        => '198.51.100.7',
        'api_token'        => 'tok_live_123',
        'card'             => [ 'brand' => 'visa', 'last4' => '4242' ],
        'cardFingerprint'  => 'fp_abc',
        'nested'           => [ 'billing_address' => [ 'line1' => '1 Analytical Way' ], 'phone' => '+44 20 7946 0000', 'amount' => 500 ],
        'shipping_method'  => 'express',
    ] );

    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )
        ->assertSee( '"event": "charge.updated"' )
        ->assertSee( '"description": "Shipping upgraded"' )
        ->assertSee( '"shipping_method": "express"' )
        ->assertSee( '"amount": 500' )
        ->assertSee( '"customer_email": "(hidden)"' )
        ->assertDontSee( 'ada@example.test' )
        ->assertDontSee( '203.0.113.9' )
        ->assertDontSee( '198.51.100.7' )
        ->assertDontSee( 'tok_live_123' )
        ->assertDontSee( '4242' )
        ->assertDontSee( 'fp_abc' )
        ->assertDontSee( '1 Analytical Way' )
        ->assertDontSee( '7946' );
} );

it( 'describes refund and payment problems in words', function ( string $type, array $payload, string $sentence ): void {
    timelineEntry( $this->order, $type, $payload );

    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )
        ->assertSee( $sentence )
        ->assertDontSeeHtml( '<details' );
} )->with( [
    'refund failed'           => [ 'refund.failed', [ 'refund_id' => 1, 'amount' => 1_500, 'currency' => 'USD', 'gateway' => 'fake', 'error' => 'Card expired' ], 'Refund of $15.00 failed: Card expired' ],
    'refund amount mismatch'  => [ 'refund.amount_mismatch', [ 'refund_id' => 1, 'requested' => '1500', 'refunded' => '1000', 'requested_currency' => 'USD', 'refunded_currency' => 'USD' ], 'The gateway reported $10.00 but $15.00 was requested. Needs reconciliation.' ],
    'payment amount mismatch' => [ 'payment.amount_mismatch', [ 'reference' => 'pi_1', 'expected' => '4500', 'actual' => '4000', 'currency' => 'USD' ], 'The gateway reported $40.00 but $45.00 was requested. Needs reconciliation.' ],
    'payment action required' => [ 'payment.action_required', [ 'gateway' => 'fake', 'reference' => 'pi_2' ], 'Payment action required' ],
] );

it( 'lets a satellite describe its own events', function (): void {
    timelineEntry( $this->order, 'subscription.renewed', [ 'period' => 'October' ] );

    addFilter( 'ap.ecommerceAdminLivewire.timeline.entry', static function ( array $presented, OrderTimelineEntry $entry ): array {
        if ( 'subscription.renewed' !== $entry->event_type ) {
            return $presented;
        }

        return [ 'icon' => 'o-arrow-path', 'description' => 'Subscription renewed for ' . $entry->payload['period'], 'known' => true ];
    } );

    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )
        ->assertSee( 'Subscription renewed for October' )
        ->assertDontSeeHtml( '<details' );
} );

it( 'ignores a malformed filter return', function (): void {
    timelineEntry( $this->order, 'order.placed' );

    addFilter( 'ap.ecommerceAdminLivewire.timeline.entry', static fn (): string => 'nope' );

    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )->assertSee( 'Order placed' );
} );

it( 'reads the activity log for a product', function (): void {
    $product = Product::factory()->create( [ 'name' => 'Linen Shirt' ] );

    ActivityLogEntry::factory()->forSubject( $product )->create( [
        'event_type' => 'price.updated',
        'payload'    => [ 'price_id' => 1, 'currency' => 'USD', 'changes' => [ 'price_amount' => [ 'before' => 1_000, 'after' => 1_200 ] ] ],
        'created_at' => Carbon::now()->addMinute(),
    ] );

    Livewire::test( Timeline::class, [ 'subject' => $product ] )
        ->assertOk()
        ->assertSeeInOrder( [ 'Price changed: price amount', 'Product "Linen Shirt" created' ] );
} );

it( 'describes customer address changes', function (): void {
    grantAbilities( [ 'customer.view' ] );

    $customer = ArtisanPackUI\Ecommerce\Models\Customer::factory()->create();

    foreach ( [
        [ 'address.added', [ 'address_id' => 1 ], 1 ],
        [ 'address.updated', [ 'address_id' => 1, 'fields' => [ 'address1', 'postal_code' ] ], 2 ],
        [ 'address.deleted', [ 'address_id' => 1 ], 3 ],
    ] as [ $type, $payload, $minutes ] ) {
        ActivityLogEntry::factory()->forSubject( $customer )->create( [ 'event_type' => $type, 'payload' => $payload, 'created_at' => Carbon::now()->addMinutes( $minutes ) ] );
    }

    Livewire::test( Timeline::class, [ 'subject' => $customer ] )
        ->assertOk()
        ->assertSeeInOrder( [ 'Address deleted', 'Address changed: address1, postal code', 'Address added' ] );
} );

it( 'is denied a product without product.view', function (): void {
    Gate::define( 'ecommerce.product.view', static fn (): bool => false );

    Livewire::test( Timeline::class, [ 'subject' => Product::factory()->create() ] )->assertForbidden();
} );

it( 'refreshes when an order panel reports a change', function (): void {
    $component = Livewire::test( Timeline::class, [ 'subject' => $this->order ] )->assertDontSee( 'Order placed' );

    timelineEntry( $this->order, 'order.placed' );

    $component->dispatch( 'ecommerce-admin-order-updated' )->assertSee( 'Order placed' );
} );

it( 'does not let the client raise the page size', function (): void {
    $order = Order::factory()->create();

    Livewire::test( Timeline::class, [ 'subject' => $order ] )->set( 'limit', 10_000_000 );
} )->throws( \Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class );

it( 'shows the compact empty state under the card title when a filter leaves nothing', function (): void {
    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )
        ->set( 'filter', 'notes' )
        ->assertSeeHtml( 'data-size="sm"' )
        ->assertSeeHtml( '<h4 data-scope="empty-state" data-part="title">Nothing here yet</h4>' );
} );

it( 'tones a marker only for events that need attention or complete money movement', function (): void {
    timelineEntry( $this->order, 'order.placed', [ 'total' => 1_000 ], null, Carbon::now()->subHours( 4 ) );
    timelineEntry( $this->order, 'payment.captured', [ 'amount' => 1_000, 'gateway' => 'stripe' ], null, Carbon::now()->subHours( 3 ) );
    timelineEntry( $this->order, 'payment.fraud_challenged', [ 'score' => 60 ], null, Carbon::now()->subHours( 2 ) );
    timelineEntry( $this->order, 'payment.capture_failed', [ 'message' => 'card_declined' ], null, Carbon::now()->subHour() );

    $html = Livewire::test( Timeline::class, [ 'subject' => $this->order ] )->html();

    $marker = static fn ( string $type ): string => preg_match( '/data-event-type="' . preg_quote( $type, '/' ) . '".*?<span data-scope="timeline" data-part="marker"([^>]*)>/s', $html, $match ) ? $match[1] : '';

    expect( $marker( 'payment.capture_failed' ) )->toContain( 'data-color="error"' )
        ->and( $marker( 'payment.fraud_challenged' ) )->toContain( 'data-color="warning"' )
        ->and( $marker( 'payment.captured' ) )->toContain( 'data-color="success"' )
        ->and( $marker( 'order.placed' ) )->not->toContain( 'data-color' )
        ->and( $marker( 'order.placed' ) )->toContain( 'aria-hidden="true"' );
} );

it( 'draws markers with the design system\'s Lucide icons', function (): void {
    timelineEntry( $this->order, 'order.placed', [ 'total' => 1_000 ] );
    timelineEntry( $this->order, 'payment.capture_failed', [ 'message' => 'card_declined' ] );

    expect( Livewire::test( Timeline::class, [ 'subject' => $this->order ] )->html() )
        ->toContain( ArtisanPack\LivewireUiComponents\Support\IconRegistry::svg( 'shopping-bag' ) )
        ->toContain( ArtisanPack\LivewireUiComponents\Support\IconRegistry::svg( 'triangle-alert' ) );
} );

it( 'lets a satellite tone its own events, ignoring tones the design system lacks', function ( mixed $tone, ?string $expected ): void {
    addFilter( 'ap.ecommerceAdminLivewire.timeline.entry', static fn ( array $presented ): array => [ ...$presented, 'description' => 'Synced', 'tone' => $tone ] );

    timelineEntry( $this->order, 'erp.synced' );

    $html = Livewire::test( Timeline::class, [ 'subject' => $this->order ] )->html();

    null === $expected
        ? expect( $html )->not->toMatch( '/data-part="marker" data-color/' )
        : expect( $html )->toContain( 'data-part="marker" data-color="' . $expected . '"' );
} )->with( [
    'info'    => [ 'info', 'info' ],
    'unknown' => [ 'magenta', null ],
    'array'   => [ [ 'error' ], null ],
] );

it( 'filters with pressed toggle buttons in a labelled group, not tabs', function (): void {
    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )
        ->assertSeeHtml( 'role="group" aria-label="Filter the timeline"' )
        ->assertSeeHtml( 'aria-pressed="true"' )
        ->assertDontSeeHtml( 'role="tab"' )
        ->set( 'filter', 'system' )
        ->assertSeeHtmlInOrder( [ 'aria-pressed="false"', '>All</button>', 'aria-pressed="false"', '>Notes</button>', 'aria-pressed="true"', '>System events</button>' ] );
} );

it( 'shows a relative time with the full date hidden after it', function (): void {
    timelineEntry( $this->order, 'order.placed', [ 'total' => 1_000 ], null, Carbon::now()->subHours( 2 ) );

    expect( Livewire::test( Timeline::class, [ 'subject' => $this->order ] )->html() )
        ->toMatch( '/<time data-scope="timeline" data-part="time" datetime="[^"]+">2 hours ago<span class="apui-sr-only"> \([^)]+\)<\/span><\/time>/' );
} );

it( 'keeps a raw payload left to right', function (): void {
    timelineEntry( $this->order, 'loyalty.points_awarded', [ 'points' => 120 ] );

    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )
        ->assertSeeHtml( '<pre class="ec-payload" dir="ltr">' );
} );

it( 'marks each entry with its id for the Load more focus hand-off', function (): void {
    $entry = timelineEntry( $this->order, 'note.added', [ 'body' => 'Only note' ] );

    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )
        ->assertSeeHtml( 'data-timeline-entry="' . $entry->id . '"' )
        ->assertSeeHtml( 'tabindex="-1"' );
} );

it( 'announces loading from a status region outside any busy or hidden element', function (): void {
    $html = Livewire::test( Timeline::class, [ 'subject' => $this->order ] )->html();

    expect( $html )->toContain( '<p class="apui-sr-only" role="status" data-timeline-status><span wire:loading wire:target="filter">Loading activity…</span></p>' )
        ->not->toContain( 'aria-busy' );
} );

it( 'renders with no uncompiled component tags', function (): void {
    timelineEntry( $this->order, 'order.placed', [ 'total' => 1_000 ] );

    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )->assertDontSeeHtml( '<x-' );
} );

it( 'sizes the loading skeleton without inline styles the admin CSP would block', function (): void {
    expect( Livewire::test( Timeline::class, [ 'subject' => $this->order ] )->html() )
        ->toContain( 'ec-skel-marker' )
        ->not->toMatch( '/class="apui-skeleton[^"]*"[^>]*style=/' );
} );
