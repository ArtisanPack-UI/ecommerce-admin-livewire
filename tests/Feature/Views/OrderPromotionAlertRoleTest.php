<?php

declare( strict_types=1 );

/*
 * The design-system Alert sets its own live-region role (alert for errors,
 * status otherwise), so a `role` passed to it renders a second, duplicate
 * attribute (L-F1). Covers the order and promotion views.
 */

it( 'passes no role to design-system alerts on the order and promotion screens', function ( string $view ): void {
    $source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/resources/views/' . $view );

    expect( $source )->not->toBe( '' );

    preg_match_all( '/<x-artisanpack-alert\b(?:[^>"]|"[^"]*")*>/', $source, $tags );

    expect( $tags[0] )->not->toBeEmpty();

    foreach ( $tags[0] as $tag ) {
        expect( $tag )->not->toMatch( '/\srole="/' );
    }
} )->with( [
    'livewire/orders/panels/status.blade.php',
    'livewire/orders/panels/edit.blade.php',
    'livewire/orders/panels/refunds.blade.php',
    'livewire/orders/panels/fulfillment.blade.php',
    'livewire/promotions/form.blade.php',
    'livewire/promotions/coupons.blade.php',
] );
