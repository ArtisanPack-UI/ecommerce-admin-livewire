<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderTimelineEntry;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Timeline;
use Illuminate\Support\Carbon;
use Tests\Browser\Support\Locators;

/**
 * Writes a note to an order's timeline, minutes ago.
 */
function browserTimelineNote( Order $order, int $number, int $minutesAgo ): OrderTimelineEntry
{
    return OrderTimelineEntry::query()->create( [
        'order_id'      => $order->id,
        'actor_user_id' => null,
        'event_type'    => 'note.added',
        'payload'       => [ 'excerpt' => 'Note ' . $number ],
        'created_at'    => Carbon::now()->subMinutes( $minutesAgo ),
    ] );
}

it( 'focuses the entry after the last one shown, even when a new event arrives first', function (): void {
    $order = Order::factory()->create( [ 'currency' => 'USD' ] );

    // 25 notes, newest first: the first page shows notes 25 to 6, and note 5 is next.
    $notes = [];
    foreach ( range( 1, Timeline::PAGE_SIZE + 5 ) as $number ) {
        $notes[ $number ] = browserTimelineNote( $order, $number, 1_000 - $number );
    }

    $page = visit( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => $order->id ] ) )
        ->assertSee( '"Note 6"' )
        ->assertDontSee( '"Note 5"' );

    // A newer event lands before the click, so counting entries would now point at note 6.
    browserTimelineNote( $order, 99, 0 );

    $page->click( Locators::role( 'Load more' ) )
        ->waitForText( '"Note 5"' )
        ->assertScript( 'document.activeElement?.dataset.timelineEntry', (string) $notes[5]->id )
        // 26 entries fit in two pages, so this was the final page.
        ->assertScript( 'document.querySelector( "[data-load-more]" ) === null', true )
        ->assertNoJavaScriptErrors();
} );
