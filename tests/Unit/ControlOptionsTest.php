<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;

it( 'maps the 2.x id and name keys onto value and label', function (): void {
    expect( ControlOptions::from( [ [ 'id' => 3, 'name' => 'Prints' ], [ 'id' => 'mugs', 'name' => 'Mugs', 'hint' => 'Ceramic', 'disabled' => true ] ] ) )->toBe( [
        [ 'value' => 3, 'label' => 'Prints' ],
        [ 'disabled' => true, 'value' => 'mugs', 'label' => 'Mugs', 'description' => 'Ceramic' ],
    ] );
} );

it( 'keeps options already in the 3.0 shape, and plain strings', function (): void {
    expect( ControlOptions::from( [ [ 'value' => 'a', 'label' => 'A', 'icon' => 'star' ], 'b' ] ) )->toBe( [
        [ 'value' => 'a', 'label' => 'A', 'icon' => 'star' ],
        'b',
    ] );
} );

it( 'maps the options inside a select group', function (): void {
    expect( ControlOptions::from( [ [ 'label' => 'Fruit', 'options' => [ [ 'id' => 1, 'name' => 'Apple' ] ] ] ] ) )->toBe( [
        [ 'label' => 'Fruit', 'options' => [ [ 'value' => 1, 'label' => 'Apple' ] ] ],
    ] );
} );

it( 'takes a collection or nothing', function (): void {
    expect( ControlOptions::from( collect( [ [ 'id' => 1, 'name' => 'One' ] ] ) ) )->toBe( [ [ 'value' => 1, 'label' => 'One' ] ] )
        ->and( ControlOptions::from( null ) )->toBe( [] );
} );
