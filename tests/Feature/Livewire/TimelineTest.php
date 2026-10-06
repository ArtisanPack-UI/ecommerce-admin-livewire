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
        ->assertSeeHtml( '<ol class="list-none"' )
        ->assertSeeHtml( '<time datetime="' );
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
