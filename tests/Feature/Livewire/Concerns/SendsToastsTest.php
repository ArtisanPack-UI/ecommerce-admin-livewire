<?php

declare( strict_types=1 );

use Livewire\Livewire;
use Tests\Fixtures\Livewire\ToastScreen;

/**
 * The toast payloads a component queued, decoded from its `toast( … )` calls.
 *
 * @return array<int, array<string, mixed>>
 */
function queuedToastPayloads( $component ): array
{
    $payloads = [];

    foreach ( $component->effects['xjs'] ?? [] as $script ) {
        $script = is_array( $script ) ? (string) ( $script['expression'] ?? '' ) : (string) $script;

        if ( 1 === preg_match( '/toast\( (\{.*\}) \);/s', $script, $match ) ) {
            $payloads[] = json_decode( $match[1], true, 512, JSON_THROW_ON_ERROR )['toast'];
        }
    }

    return $payloads;
}

it( 'HTML-escapes the title and description for the x-html toast', function ( string $type ): void {
    $markup = '<img src=x onerror=alert(document.cookie)>';

    $component = Livewire::test( ToastScreen::class )->call( 'notify', $type, 'Saved "' . $markup . '".', 'Tom\'s & ' . $markup );
    $toast     = queuedToastPayloads( $component )[0];

    expect( $toast['type'] )->toBe( $type )
        ->and( $toast['title'] )->toBe( 'Saved &quot;&lt;img src=x onerror=alert(document.cookie)&gt;&quot;.' )
        ->and( $toast['description'] )->toBe( 'Tom&#039;s &amp; &lt;img src=x onerror=alert(document.cookie)&gt;' )
        ->and( sentToasts( $component ) )->not->toContain( '<img' );
} )->with( [ 'success', 'warning', 'error' ] );

it( 'keeps a missing description null', function (): void {
    $toast = queuedToastPayloads( Livewire::test( ToastScreen::class )->call( 'notify', 'success', 'Saved.' ) )[0];

    expect( $toast['title'] )->toBe( 'Saved.' )
        ->and( $toast['description'] )->toBeNull();
} );

it( 'keeps the script safe from quotes and closing tags', function (): void {
    $component = Livewire::test( ToastScreen::class )->call( 'notify', 'error', '</script><script>alert(1)</script>', "'); alert(1); ('" );

    expect( sentToasts( $component ) )
        ->not->toContain( '</script>' )
        ->not->toContain( "'); alert(1)" );
} );
