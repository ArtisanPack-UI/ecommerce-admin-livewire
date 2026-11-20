<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Support\TaxLabel;

const ADMIN_TRANSLATED_LOCALES = [ 'es', 'fr', 'de', 'pt_BR', 'ja', 'zh_CN', 'it', 'ar' ];

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

/**
 * The number of `|` a translation of `$key` should have in `$locale`.
 *
 * Arabic has six plural forms (zero, one, two, few, many, other), so a plain
 * `singular|plural` key becomes six segments there. Interval keys
 * (`{0} …|{1} …|[2,*] …`) keep their segments in every locale.
 */
function adminExpectedPipes( string $locale, string $key ): int
{
    $pipes    = substr_count( $key, '|' );
    $interval = 1 === preg_match( '/^\s*[{\[]/', $key );

    if ( 'ar' === $locale && 1 === $pipes && ! $interval ) {
        return 5;
    }

    // Laravel always picks the first segment for Japanese and Chinese, so a
    // second one would never be shown: these locales use a single segment
    // that carries `:count`.
    if ( in_array( $locale, [ 'ja', 'zh_CN' ], true ) && ! $interval ) {
        return 0;
    }

    return $pipes;
}

it( 'has a catalogue entry in every locale for every key in src/ and resources/', function (): void {
    $root = dirname( __DIR__, 3 );

    $this->artisan( 'ecommerce:lint:translations', [
        '--no-engine' => true,
        '--path'      => [ $root . '/src', $root . '/resources' ],
        '--lang'      => $root . '/lang',
    ] )->expectsOutputToContain( 'Translation lint passed.' )->assertExitCode( 0 );
} );

/**
 * Keys the admin translates for strings the engine or livewire-ui-components
 * render on its screens (config-form labels, sub-status delete errors, the
 * rating's accessible name), so they never appear in this package's source.
 */
const ADMIN_UPSTREAM_KEYS = [
    ':count board card|:count board cards',
    ':count kanban column|:count kanban columns',
    ':value out of :max',
    'Days',
    'Field',
    'Job',
    'Match',
];

it( 'ships no keys that src/, resources/, config/, or routes/ no longer use', function (): void {
    $root   = dirname( __DIR__, 3 );
    $source = '';

    foreach ( [ 'src', 'resources', 'config', 'routes' ] as $directory ) {
        if ( ! is_dir( "{$root}/{$directory}" ) ) {
            continue;
        }

        foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "{$root}/{$directory}", FilesystemIterator::SKIP_DOTS ) ) as $file ) {
            if ( str_ends_with( $file->getFilename(), '.php' ) ) {
                $source .= file_get_contents( $file->getPathname() );
            }
        }
    }

    $orphaned = array_values( array_filter(
        array_keys( adminCatalogue( 'en' ) ),
        static fn ( string $key ): bool => ! in_array( $key, ADMIN_UPSTREAM_KEYS, true )
            && ! str_contains( $source, "'" . addcslashes( $key, "'\\" ) . "'" )
            && ! str_contains( $source, '"' . addcslashes( $key, '"\$' ) . '"' ),
    ) );

    expect( $orphaned )->toBe( [] );
} );

it( 'ships identical key sets in every locale', function (): void {
    $en = array_keys( adminCatalogue( 'en' ) );
    sort( $en );

    foreach ( ADMIN_TRANSLATED_LOCALES as $locale ) {
        $keys = array_keys( adminCatalogue( $locale ) );
        sort( $keys );

        expect( $keys )->toBe( $en, $locale );
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
            ->and( substr_count( $value, '|' ) )->toBe( adminExpectedPipes( $locale, $key ), "{$locale}: {$key}" );
    }
} )->with( ADMIN_TRANSLATED_LOCALES );

it( 'pluralizes for 0, 1, and many in each locale', function ( string $locale, array $expected ): void {
    app()->setLocale( $locale );

    $line = ':count order to ship|:count orders to ship';

    expect( [
        trans_choice( $line, 0, [ 'count' => 0 ] ),
        trans_choice( $line, 1, [ 'count' => 1 ] ),
        trans_choice( $line, 5, [ 'count' => 5 ] ),
    ] )->toBe( $expected );
} )->with( [
    'en'    => [ 'en', [ '0 orders to ship', '1 order to ship', '5 orders to ship' ] ],
    'es'    => [ 'es', [ '0 pedidos por enviar', '1 pedido por enviar', '5 pedidos por enviar' ] ],
    'fr'    => [ 'fr', [ '0 commande à expédier', '1 commande à expédier', '5 commandes à expédier' ] ],
    'de'    => [ 'de', [ '0 Bestellungen zu versenden', '1 Bestellung zu versenden', '5 Bestellungen zu versenden' ] ],
    'pt_BR' => [ 'pt_BR', [ '0 pedidos para enviar', '1 pedido para enviar', '5 pedidos para enviar' ] ],
    'ja'    => [ 'ja', [ '発送する注文0件', '発送する注文1件', '発送する注文5件' ] ],
    'zh_CN' => [ 'zh_CN', [ '0 个订单待发货', '1 个订单待发货', '5 个订单待发货' ] ],
    'it'    => [ 'it', [ '0 ordini da spedire', '1 ordine da spedire', '5 ordini da spedire' ] ],
] );

it( 'uses all six Arabic plural forms', function (): void {
    app()->setLocale( 'ar' );

    $line = ':count order to ship|:count orders to ship';

    expect( collect( [ 0, 1, 2, 3, 11, 100 ] )->map( fn ( int $count ): string => trans_choice( $line, $count, [ 'count' => $count ] ) )->all() )
        ->toBe( [
            'لا توجد طلبات للشحن',
            'طلب واحد للشحن',
            'طلبان للشحن',
            '3 طلبات للشحن',
            '11 طلبًا للشحن',
            '100 طلب للشحن',
        ] );
} );

it( 'uses the engine\'s TaxLabel term for "Tax" in each locale', function ( string $locale ): void {
    // Both catalogues load into one JSON namespace and this package's wins,
    // so compare against the engine's file rather than TaxLabel::for().
    $engine = dirname( ( new ReflectionClass( TaxLabel::class ) )->getFileName(), 3 ) . "/lang/{$locale}.json";
    $term   = json_decode( (string) file_get_contents( $engine ), true, flags: JSON_THROW_ON_ERROR )['Tax'];

    expect( adminCatalogue( $locale )['Tax'] )->toBe( $term );
} )->with( [ 'es', 'fr', 'de' ] );
