<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Form;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'product.update' ] );
    ProductMedia::fake( false );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    ProductMedia::fake( null );
} );

it( 'caches results only for paths into the component\'s own public properties', function ( string $field ): void {
    $component = Livewire::test( Form::class )->call( 'searchPicker', 'a', 'category', $field );

    expect( $component->get( 'pickerOptions' ) )->not->toHaveKey( 'category:' . $field );
} )->with( [
    'unknown property'   => 'nothingHere',
    'the cache itself'   => 'pickerOptions.x',
    'a private property' => 'loadedProduct',
    'not a path'         => 'categoryIds[0]',
] );

it( 'caches a known field', function (): void {
    expect( Livewire::test( Form::class )->call( 'searchPicker', 'a', 'category', 'categoryIds' )->get( 'pickerOptions' ) )
        ->toHaveKey( 'category:categoryIds' );
} );

it( 'keeps the cache bounded however many fields the client names', function (): void {
    $component = Livewire::test( Form::class );

    foreach ( range( 0, 40 ) as $index ) {
        $component->call( 'searchPicker', '', 'category', 'prices.' . $index . '.currency' );
    }

    expect( $component->get( 'pickerOptions' ) )->toHaveCount( Form::MAX_PICKER_CACHE )
        ->toHaveKey( 'category:prices.40.currency' )
        ->not->toHaveKey( 'category:prices.0.currency' )
        ->and( Form::MAX_PICKER_CACHE )->toBe( 20 );
} );
