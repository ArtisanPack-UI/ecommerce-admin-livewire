<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Order;

it( 'opens the command palette and jumps to an order', function (): void {
    $order = Order::query()->whereNotNull( 'placed_at' )->firstOrFail();

    visit( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->click( '[data-spotlight-open]' )
        ->type( 'dialog[open] input', (string) $order->order_number )
        // The order itself, not its "Refund order" / "Add note" actions.
        ->click( 'dialog[open] a[href$="/orders/' . $order->id . '"]' )
        ->assertPathIs( '/ecommerce-admin/orders/' . $order->id )
        ->assertSee( 'Order #' . $order->order_number )
        ->assertNoJavaScriptErrors();
} );
