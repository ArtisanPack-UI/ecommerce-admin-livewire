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

it( 'isolates the figure left to right with tabular figures', function (): void {
    expect( Blade::render( '<x-artisanpack-ec-money :amount="120400" currency="USD" locale="en" />' ) )
        ->toContain( '<bdi dir="ltr" class="ec-money"' )
        ->toContain( '$1,204.00' )
        ->not->toContain( 'data-tone' );
} );

it( 'writes a negative amount with a true minus in the error tone', function (): void {
    $html = Blade::render( '<x-artisanpack-ec-money :amount="-13240" currency="USD" locale="en" />' );

    expect( $html )->toContain( 'data-tone="negative"' )
        ->toContain( "\u{2212}\$132.40" )
        ->not->toContain( '-$132.40' );
} );

it( 'mutes the dash for a missing amount', function (): void {
    expect( Blade::render( '<x-artisanpack-ec-money :amount="null" />' ) )
        ->toContain( 'data-tone="muted"' )
        ->toContain( '<span class="apui-sr-only">No amount</span>' );
} );

it( 'shows a compare-at price after the amount, struck through and read as "Was"', function (): void {
    $html = Blade::render( '<x-artisanpack-ec-money :amount="4800" :compare-at="6400" currency="USD" locale="en" />' );

    expect( $html )->toContain( '<span class="apui-sr-only">Was</span>' )
        ->toContain( '<bdi dir="ltr" class="ec-money ec-money-was">$64.00</bdi>' )
        ->and( strpos( $html, '$48.00' ) )->toBeLessThan( strpos( $html, '$64.00' ) );
} );

it( 'skips a compare-at price equal to the amount', function (): void {
    expect( Blade::render( '<x-artisanpack-ec-money :amount="4800" :compare-at="4800" currency="USD" locale="en" />' ) )
        ->not->toContain( 'ec-money-was' )
        ->not->toContain( 'Was' );
} );

it( 'shows the currency code only when asked', function (): void {
    expect( Blade::render( '<x-artisanpack-ec-money :amount="24800" currency="CAD" locale="en" show-code />' ) )
        ->toContain( '<span class="ec-money-code">CAD</span>' )
        ->and( Blade::render( '<x-artisanpack-ec-money :amount="24800" currency="CAD" locale="en" />' ) )
        ->not->toContain( 'ec-money-code' );
} );

it( 'registers an admin-only alias that ecommerce-storefront-livewire cannot take over', function (): void {
    expect( Blade::getClassComponentAliases() )
        ->toHaveKey( 'artisanpack-ec-admin-money', ArtisanPackUI\EcommerceAdminLivewire\View\Components\Money::class )
        ->toHaveKey( 'artisanpack-ec-money', ArtisanPackUI\EcommerceAdminLivewire\View\Components\Money::class );
} );
