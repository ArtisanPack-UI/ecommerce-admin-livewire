<?php

declare( strict_types=1 );

/*
 * The design-system Alert sets its own live-region role (alert for errors,
 * status otherwise), so a `role` passed to it renders a second, duplicate
 * attribute (L-F1).
 */

it( 'passes no role to design-system alerts', function ( string $view ): void {
    $source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/resources/views/' . $view );

    expect( $source )->not->toBe( '' );

    preg_match_all( '/<x-artisanpack-alert\b(?:[^>"]|"[^"]*")*>/', $source, $tags );

    foreach ( $tags[0] as $tag ) {
        expect( $tag )->not->toMatch( '/\srole="/' );
    }
} )->with( [
    'livewire/tax/index.blade.php',
    'livewire/order-statuses/index.blade.php',
    'livewire/webhooks/show.blade.php',
    'livewire/webhooks/index.blade.php',
    'livewire/webhooks/secret-dialogs.blade.php',
    'livewire/reports/show.blade.php',
    'components/config-form.blade.php',
] );
