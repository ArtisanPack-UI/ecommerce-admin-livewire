<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Order;

it( 'opens the command palette and jumps to an order', function (): void {
    $order = Order::query()->whereNotNull( 'placed_at' )->firstOrFail();

    visit( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->click( '[data-spotlight-open] [data-scope="spotlight"][data-part="trigger"]' )
        ->type( '[data-scope="spotlight"][data-part="content"][data-state="open"] input', (string) $order->order_number )
        // The order itself, not its "Refund order" / "Add note" actions.
        ->click( '[data-scope="spotlight"][data-part="content"][data-state="open"] a[data-part="item"][href$="/orders/' . $order->id . '"]' )
        ->waitForText( 'Order #' . $order->order_number )
        ->assertPathIs( '/ecommerce-admin/orders/' . $order->id )
        ->assertNoJavaScriptErrors();
} );
