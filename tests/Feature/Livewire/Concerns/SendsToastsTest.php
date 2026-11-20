<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Show;
use ArtisanPackUI\EcommerceAdminLivewire\Support\CmsFramework;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view', 'kanbanCard.move' ] );
    $this->actingAs( makeUser() );
} );

it( 'sends stored names to the toaster as plain text, not pre-escaped HTML', function (): void {
    $markup  = '<img src=x onerror=alert(1)> & Tom\'s "Board"';
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    $board   = KanbanBoard::factory()->create( [ 'name' => $markup ] );
    $order   = Order::factory()->create( [ 'system_status' => 'processing' ] );
    OrderBoardAssignment::factory()->create( [ 'order_id' => $order->id, 'board_id' => $board->id, 'substatus_id' => $packing->id ] );

    $toasts = sentToasts( Livewire::test( Show::class, [ 'order' => $order->id ] )->call( 'removeFromBoard', $board->id ) );

    // The toaster inserts text, so escaping here would show the entities.
    expect( $toasts )
        ->toContain( 'Removed from \"' . str_replace( '"', '\"', $markup ) . '\".' )
        ->not->toContain( '&lt;' )
        ->not->toContain( '&amp;' )
        ->not->toContain( '&#039;' );
} );

it( 'renders toast titles and descriptions as text in the layout\'s toaster', function (): void {
    CmsFramework::fake( false );

    $html = $this->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )->assertOk()->getContent();

    expect( $html )
        ->toContain( 'x-text="entry.title"' )
        ->toContain( 'x-text="entry.description"' )
        ->not->toContain( 'x-html="entry.title"' )
        ->not->toContain( 'x-html="entry.description"' );
} );
