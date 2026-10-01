<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\HelperForm;

beforeEach( function (): void {
    view()->share( 'errors', new Illuminate\Support\ViewErrorBag() );
    grantAbilities( [ 'product.viewAny', 'customer.viewAny' ] );
    $this->actingAs( makeUser() );
} );

it( 'scales the money input by the currency subunit', function ( string $currency, int $scale ): void {
    Livewire::test( HelperForm::class )
        ->set( 'currency', $currency )
        ->assertSeeHtml( 'data-scale="' . $scale . '"' )
        ->assertSee( $currency );
} )->with( [
    'JPY' => [ 'JPY', 0 ],
    'USD' => [ 'USD', 2 ],
    'KWD' => [ 'KWD', 3 ],
] );

it( 'binds the integer through entangle and keeps wire:model off the visible field', function (): void {
    $html = Livewire::test( HelperForm::class )->html();

    expect( $html )->toContain( "\$wire.entangle( 'priceAmount', false )" )
        ->toContain( 'x-model="display"' )
        ->toContain( 'inputmode="decimal"' )
        ->not->toContain( 'wire:model="priceAmount"' );
} );

it( 'binds live when the model is live', function (): void {
    expect( Blade::render( '<x-artisanpack-ec-money-input wire:model.live="amount" currency="USD" />' ) )
        ->toContain( "\$wire.entangle( 'amount', true )" );
} );

it( 'renders the percent input on rate_ubps', function (): void {
    expect( Blade::render( '<x-artisanpack-ec-percent-input wire:model="rateUbps" />' ) )
        ->toContain( 'data-scale="7"' )
        ->toContain( 'trimZeros: true' )
        ->toContain( '%' );
} );

it( 'shows validation errors for the bound property', function (): void {
    Livewire::test( HelperForm::class )
        ->set( 'priceAmount', -5 )
        ->call( 'save' )
        ->assertHasErrors( [ 'priceAmount' ] )
        ->assertSee( 'The price amount field must be at least 0.' );
} );

it( 'converts in the browser exactly like MinorUnits', function (): void {
    $node = trim( (string) Process::run( 'command -v node' )->output() );

    if ( '' === $node ) {
        $this->markTestSkipped( 'Node is not installed.' );
    }

    preg_match( '/x-data="(\{.*?\n    \})"/s', Blade::render( '<x-artisanpack-ec-money-input wire:model="amount" currency="USD" />' ), $matches );
    $object = html_entity_decode( $matches[1], ENT_QUOTES );

    $cases = [
        [ 0, '1234', '1234', '1234' ],
        [ 0, '12.5', false, null ],
        [ 0, '1.234.567', '1234567', '1234567' ],
        [ 2, '1.234.5', false, null ],
        [ 2, '12.345', '1234500', '12345.00' ],
        [ 2, '12.3456', false, null ],
        [ 2, '0.500', false, null ],
        [ 2, '0,50', '50', '0.50' ],
        [ 2, '1,234.56', '123456', '1234.56' ],
        [ 2, '1.234,56', '123456', '1234.56' ],
        [ 2, '19.99', '1999', '19.99' ],
        [ 2, '0.29', '29', '0.29' ],
        [ 2, '-10.5', '-1050', '-10.50' ],
        [ 2, '', null, '' ],
        [ 3, '1.234', '1234', '1.234' ],
        [ 3, '1,234.567', '1234567', '1234.567' ],
        [ 3, '1.2345', false, null ],
    ];

    $script = sprintf(
        <<<'JS'
            const $wire = { entangle: () => null };
            const make = () => (%s);
            const results = %s.map( ( [ scale, input ] ) => {
                const field = make();
                field.scale = scale;
                const parsed = field.parse( input );
                return [ parsed, false === parsed || null === parsed ? ( null === parsed ? '' : null ) : field.format( parsed ) ];
            } );
            const percent = make();
            percent.scale = 7;
            percent.trimZeros = true;
            results.push( [ percent.parse( '8.375' ), percent.format( '83750000' ) ] );
            const invalid = make();
            invalid.scale   = 2;
            invalid.value   = 1000;
            invalid.display = '10.5555';
            invalid.commit();
            results.push( [ invalid.value, invalid.display, invalid.invalid ] );
            process.stdout.write( JSON.stringify( results ) );
            JS,
        $object,
        json_encode( array_map( static fn ( array $case ): array => [ $case[0], $case[1] ], $cases ) ),
    );

    $result = Process::input( $script )->run( [ $node, '-' ] );

    expect( $result->successful() )->toBeTrue( $result->errorOutput() );

    $expected   = array_map( static fn ( array $case ): array => [ $case[2], $case[3] ], $cases );
    $expected[] = [ '83750000', '8.375' ];
    $expected[] = [ null, '10.5555', true ];

    expect( json_decode( $result->output(), true ) )->toBe( $expected );
} );
