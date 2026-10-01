<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Support\TaxRateMath;
use ArtisanPackUI\EcommerceAdminLivewire\Support\MinorUnits;

it( 'reads the subunit from ISO 4217', function ( string $currency, int $subunit ): void {
    expect( MinorUnits::subunit( $currency ) )->toBe( $subunit );
} )->with( [
    'JPY'       => [ 'JPY', 0 ],
    'USD'       => [ 'USD', 2 ],
    'KWD'       => [ 'KWD', 3 ],
    'lowercase' => [ 'eur', 2 ],
] );

it( 'converts minor units to major units', function ( int $minor, string $currency, string $major ): void {
    expect( MinorUnits::toMajor( $minor, $currency ) )->toBe( $major );
} )->with( [
    'JPY'          => [ 1234, 'JPY', '1234' ],
    'USD'          => [ 123456, 'USD', '1234.56' ],
    'USD cents'    => [ 5, 'USD', '0.05' ],
    'KWD'          => [ 1234567, 'KWD', '1234.567' ],
    'KWD fils'     => [ 7, 'KWD', '0.007' ],
    'negative USD' => [ -1050, 'USD', '-10.50' ],
    'zero'         => [ 0, 'USD', '0.00' ],
] );

it( 'converts major units to minor units without floats', function ( string $major, string $currency, ?int $minor ): void {
    expect( MinorUnits::toMinor( $major, $currency ) )->toBe( $minor );
} )->with( [
    'JPY'                      => [ '1234', 'JPY', 1234 ],
    'JPY grouped'              => [ '1,234', 'JPY', 1234 ],
    'USD'                      => [ '1234.56', 'USD', 123456 ],
    'USD one decimal'          => [ '10.5', 'USD', 1050 ],
    'USD grouped'              => [ '1,234.56', 'USD', 123456 ],
    'USD comma decimal'        => [ '1234,56', 'USD', 123456 ],
    'USD european grouping'    => [ '1.234,56', 'USD', 123456 ],
    'USD thousands only'       => [ '1,234', 'USD', 123400 ],
    'USD float trap'           => [ '0.29', 'USD', 29 ],
    'USD float trap 2'         => [ '19.99', 'USD', 1999 ],
    'KWD'                      => [ '1.234', 'KWD', 1234 ],
    'KWD three decimals'       => [ '12.345', 'KWD', 12345 ],
    'KWD grouped'              => [ '1,234.567', 'KWD', 1234567 ],
    'negative'                 => [ '-10.50', 'USD', -1050 ],
    'spaces and nbsp'          => [ "1 234\u{00A0}567.89", 'USD', 123456789 ],
    'blank'                    => [ '  ', 'USD', null ],
    'leading zeros'            => [ '007.10', 'USD', 710 ],
    'USD ambiguous thousands'  => [ '12.345', 'USD', 1234500 ],
    'JPY dotted thousands'     => [ '1.234.567', 'JPY', 1234567 ],
    'KWD grouped twice'        => [ '1,234,567', 'KWD', 1234567000 ],
] );

it( 'rejects input it cannot convert exactly', function ( string $major, string $currency ): void {
    MinorUnits::toMinor( $major, $currency );
} )->with( [
    'too many decimals for JPY' => [ '12.5', 'JPY' ],
    'too many decimals for USD' => [ '1.234.5', 'USD' ],
    'four decimals for USD'     => [ '12.3456', 'USD' ],
    'four decimals for KWD'     => [ '1.2345', 'KWD' ],
    'letters'                   => [ '12a', 'USD' ],
    'exponent'                  => [ '1e5', 'USD' ],
    'separators only'           => [ '.,', 'USD' ],
    'too large'                 => [ '99999999999999999999', 'USD' ],
] )->throws( InvalidArgumentException::class );

it( 'converts percent to rate_ubps the same way TaxRateMath does', function ( string $percent ): void {
    expect( MinorUnits::fromDecimal( $percent, MinorUnits::PERCENT_SCALE ) )->toBe( TaxRateMath::fromPercent( $percent ) );
} )->with( [ '8.375', '0', '20', '7.25', '0.0000001', '100' ] );

it( 'stores 8.375 percent as 83750000 ubps', function (): void {
    expect( MinorUnits::fromDecimal( '8.375', MinorUnits::PERCENT_SCALE ) )->toBe( 83_750_000 )
        ->and( MinorUnits::toDecimal( 83_750_000, MinorUnits::PERCENT_SCALE, trimZeros: true ) )->toBe( TaxRateMath::toPercent( 83_750_000 ) )
        ->and( 10 ** MinorUnits::PERCENT_SCALE )->toBe( TaxRateMath::UNITS_PER_WHOLE / 100 );
} );
