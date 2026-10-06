<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;

it( 'is a singleton', function (): void {
    expect( app( OrderPanelRegistry::class ) )->toBe( app( OrderPanelRegistry::class ) );
} );

it( 'sorts each column by position, then key', function (): void {
    $registry = new OrderPanelRegistry();
    $registry->register( 'b', 'panel-b', 'side', 20 );
    $registry->register( 'a', 'panel-a', 'side', 20 );
    $registry->register( 'first', 'panel-first', 'side', 10 );
    $registry->register( 'main', 'panel-main' );

    expect( array_column( $registry->forColumn( 'side' ), 'key' ) )->toBe( [ 'first', 'a', 'b' ] )
        ->and( array_column( $registry->forColumn( 'main' ), 'component' ) )->toBe( [ 'panel-main' ] );
} );

it( 'replaces and removes panels by key', function (): void {
    $registry = new OrderPanelRegistry();
    $registry->register( 'refunds', 'old-refunds' );
    $registry->register( 'refunds', 'new-refunds', 'side' );

    expect( $registry->all() )->toHaveCount( 1 )
        ->and( $registry->forColumn( 'side' )[0]['component'] )->toBe( 'new-refunds' );

    $registry->unregister( 'refunds' );

    expect( $registry->has( 'refunds' ) )->toBeFalse();
} );

it( 'rejects an unknown column or a blank key', function ( string $key, string $component, string $column ): void {
    expect( fn () => ( new OrderPanelRegistry() )->register( $key, $component, $column ) )->toThrow( InvalidArgumentException::class );
} )->with( [
    'unknown column' => [ 'x', 'panel-x', 'footer' ],
    'blank key'      => [ ' ', 'panel-x', 'main' ],
    'no component'   => [ 'x', '', 'main' ],
] );
