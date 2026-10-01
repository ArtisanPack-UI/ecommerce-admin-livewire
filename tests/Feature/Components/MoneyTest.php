<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

it( 'formats through the engine MoneyFormatter in the de locale', function (): void {
    app()->setLocale( 'de' );

    $html = Blade::render( '<x-artisanpack-ec-money :amount="123456" currency="EUR" />' );

    expect( html_entity_decode( strip_tags( $html ) ) )->toMatch( '/1\.234,56\s€/u' );
} );

it( 'formats in an explicit locale', function (): void {
    expect( strip_tags( Blade::render( '<x-artisanpack-ec-money :amount="123456" currency="USD" locale="en" />' ) ) )
        ->toContain( '$1,234.56' );
} );

it( 'respects the currency subunit', function (): void {
    expect( strip_tags( Blade::render( '<x-artisanpack-ec-money :amount="1234" currency="JPY" locale="en" />' ) ) )
        ->toContain( '¥1,234' );
} );

it( 'defaults to the store base currency', function (): void {
    config()->set( 'artisanpack.ecommerce.base_currency', 'GBP' );

    expect( Blade::render( '<x-artisanpack-ec-money :amount="500" locale="en" />' ) )
        ->toContain( '£5.00' )
        ->toContain( 'data-currency="GBP"' );
} );

it( 'renders a dash with accessible text when there is no amount', function (): void {
    expect( Blade::render( '<x-artisanpack-ec-money :amount="null" />' ) )
        ->toContain( '&mdash;' )
        ->toContain( 'No amount' );
} );
