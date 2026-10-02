<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Support\TaxLabel;

/**
 * The package's decoded catalogue for `$locale`.
 *
 * @return array<string, string>
 */
function adminCatalogue( string $locale ): array
{
    return json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . "/lang/{$locale}.json" ), true, flags: JSON_THROW_ON_ERROR );
}

/**
 * The sorted, unique `:placeholders` in `$value`.
 *
 * @return array<int, string>
 */
function adminPlaceholders( string $value ): array
{
    preg_match_all( '/:[A-Za-z_]+/', $value, $matches );

    return collect( $matches[0] )->unique()->sort()->values()->all();
}

it( 'has a catalogue entry in every locale for every key in src/ and resources/', function (): void {
    $root = dirname( __DIR__, 3 );

    $this->artisan( 'ecommerce:lint:translations', [
        '--no-engine' => true,
        '--path'      => [ $root . '/src', $root . '/resources' ],
        '--lang'      => $root . '/lang',
    ] )->expectsOutputToContain( 'Translation lint passed.' )->assertExitCode( 0 );
} );

it( 'ships identical key sets in every locale', function (): void {
    $en = array_keys( adminCatalogue( 'en' ) );

    foreach ( [ 'es', 'fr', 'de' ] as $locale ) {
        expect( array_keys( adminCatalogue( $locale ) ) )->toBe( $en, $locale );
    }
} );

it( 'keeps English as its own translation', function (): void {
    foreach ( adminCatalogue( 'en' ) as $key => $value ) {
        expect( $value )->toBe( $key );
    }
} );

it( 'preserves placeholders and plural segments in every translation', function ( string $locale ): void {
    foreach ( adminCatalogue( $locale ) as $key => $value ) {
        expect( trim( $value ) )->not->toBe( '', "{$locale}: {$key}" )
            ->and( adminPlaceholders( $value ) )->toBe( adminPlaceholders( $key ), "{$locale}: {$key}" )
            ->and( substr_count( $value, '|' ) )->toBe( substr_count( $key, '|' ), "{$locale}: {$key}" );
    }
} )->with( [ 'es', 'fr', 'de' ] );

it( 'pluralizes for 0, 1, and many in each locale', function ( string $locale, array $expected ): void {
    app()->setLocale( $locale );

    $line = ':count order to ship|:count orders to ship';

    expect( [
        trans_choice( $line, 0, [ 'count' => 0 ] ),
        trans_choice( $line, 1, [ 'count' => 1 ] ),
        trans_choice( $line, 5, [ 'count' => 5 ] ),
    ] )->toBe( $expected );
} )->with( [
    'en' => [ 'en', [ '0 orders to ship', '1 order to ship', '5 orders to ship' ] ],
    'es' => [ 'es', [ '0 pedidos por enviar', '1 pedido por enviar', '5 pedidos por enviar' ] ],
    'fr' => [ 'fr', [ '0 commande à expédier', '1 commande à expédier', '5 commandes à expédier' ] ],
    'de' => [ 'de', [ '0 Bestellungen zu versenden', '1 Bestellung zu versenden', '5 Bestellungen zu versenden' ] ],
] );

it( 'uses the engine\'s TaxLabel term for "Tax" in each locale', function ( string $locale ): void {
    // Both catalogues load into one JSON namespace and this package's wins,
    // so compare against the engine's file rather than TaxLabel::for().
    $engine = dirname( ( new ReflectionClass( TaxLabel::class ) )->getFileName(), 3 ) . "/lang/{$locale}.json";
    $term   = json_decode( (string) file_get_contents( $engine ), true, flags: JSON_THROW_ON_ERROR )['Tax'];

    expect( adminCatalogue( $locale )['Tax'] )->toBe( $term );
} )->with( [ 'es', 'fr', 'de' ] );
