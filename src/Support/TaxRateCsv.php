<?php

/**
 * Tax rate CSV columns, row checks, and export.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\TaxRate;
use ArtisanPackUI\Ecommerce\Support\TaxRateMath;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * The tax rate CSV format (spec §7.6), shared by the import and the export so
 * an exported file imports back unchanged.
 *
 * Columns are the engine's field names, with the rate as a percent
 * (`rate_percent`, e.g. `8.375`) rather than `rate_ubps`. Only
 * `tax_class_key`, `country_code`, `rate_percent`, and `label` are required;
 * the flags accept `1` / `0`, `true` / `false`, and `yes` / `no`.
 *
 * A row matches an existing rate when the class, country, region, postal
 * pattern, and priority are all the same; a match is updated, anything else
 * is created. Priority is part of the match so stacked rates for one place
 * (a federal and a provincial rate, say) stay separate.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class TaxRateCsv
{
    /**
     * The columns, in file order.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const COLUMNS = [
        'tax_class_key',
        'country_code',
        'region_code',
        'postal_pattern',
        'rate_percent',
        'label',
        'is_compound',
        'is_shipping_taxable',
        'priority',
        'is_active',
    ];

    /**
     * Columns every file must have.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const REQUIRED_COLUMNS = [
        'tax_class_key',
        'country_code',
        'rate_percent',
        'label',
    ];

    /**
     * Largest priority a rate may have, either way.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_PRIORITY = 1000000;

    /**
     * Checks one CSV row.
     *
     * @since 1.0.0
     *
     * @param  array<string, int|string>  $row         Header-keyed row from {@see Csv::read()}.
     * @param  array<int, string>         $classKeys   Existing tax class keys.
     *
     * @return array{attributes: array<string, mixed>|null, errors: array<int, string>}
     */
    public static function check( array $row, array $classKeys ): array
    {
        $value  = static fn ( string $key ): string => trim( Csv::uncell( (string) ( $row[ $key ] ?? '' ) ) );
        $errors = [];

        $class   = $value( 'tax_class_key' );
        $country = strtoupper( $value( 'country_code' ) );
        $region  = strtoupper( $value( 'region_code' ) );
        $postal  = $value( 'postal_pattern' );
        $label   = $value( 'label' );
        $percent = str_replace( '%', '', $value( 'rate_percent' ) );

        if ( '' === $class ) {
            $errors[] = __( 'The tax class is missing.' );
        } elseif ( ! in_array( $class, $classKeys, true ) ) {
            $errors[] = __( 'There is no tax class ":class".', [ 'class' => $class ] );
        }

        if ( ! in_array( $country, Countries::CODES, true ) ) {
            $errors[] = __( 'The country must be a two-letter country code.' );
        }

        if ( mb_strlen( $region ) > 10 ) {
            $errors[] = __( 'The region code can be at most :max characters.', [ 'max' => 10 ] );
        }

        if ( mb_strlen( $postal ) > 60 ) {
            $errors[] = __( 'The postal pattern can be at most :max characters.', [ 'max' => 60 ] );
        }

        if ( '' === $label ) {
            $errors[] = __( 'The label is missing.' );
        } elseif ( mb_strlen( $label ) > 120 ) {
            $errors[] = __( 'The label can be at most :max characters.', [ 'max' => 120 ] );
        }

        $ubps = self::ubps( $percent );

        if ( null === $ubps ) {
            $errors[] = __( 'The rate must be a percent between 0 and 100 with at most 7 decimal places.' );
        }

        $flags = [];

        foreach ( [ 'is_compound' => false, 'is_shipping_taxable' => false, 'is_active' => true ] as $flag => $default ) {
            $parsed = self::flag( $value( $flag ), $default );

            if ( null === $parsed ) {
                $errors[] = __( 'The ":column" column must be 1 or 0.', [ 'column' => $flag ] );
            }

            $flags[ $flag ] = (bool) $parsed;
        }

        $priority = $value( 'priority' );

        if ( '' !== $priority && ( 1 !== preg_match( '/^-?\d{1,7}$/', $priority ) || abs( (int) $priority ) > self::MAX_PRIORITY ) ) {
            $errors[] = __( 'The priority must be a whole number.' );
        }

        if ( [] !== $errors ) {
            return [ 'attributes' => null, 'errors' => $errors ];
        }

        return [
            'attributes' => [
                'tax_class_key'  => $class,
                'country_code'   => $country,
                'region_code'    => '' === $region ? null : $region,
                'postal_pattern' => '' === $postal ? null : $postal,
                'rate_ubps'      => $ubps,
                'label'          => $label,
                'priority'       => '' === $priority ? 0 : (int) $priority,
            ] + $flags,
            'errors'     => [],
        ];
    }

    /**
     * The key a row and an existing rate are matched on.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>|TaxRate  $rate  Checked attributes or a rate.
     *
     * @return string
     */
    public static function matchKey( array|TaxRate $rate ): string
    {
        $value = static fn ( string $column ): string => (string) ( $rate instanceof TaxRate ? $rate->{$column} : ( $rate[ $column ] ?? '' ) );

        return implode( '|', [
            $value( 'tax_class_key' ),
            strtoupper( $value( 'country_code' ) ),
            strtoupper( $value( 'region_code' ) ),
            $value( 'postal_pattern' ),
            (string) (int) $value( 'priority' ),
        ] );
    }

    /**
     * Existing rates in the given countries, keyed by {@see self::matchKey()}
     * (the oldest rate wins a tie), so a whole file is matched in one query.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $countries  Country codes in the file.
     *
     * @return array<string, TaxRate>
     */
    public static function existing( array $countries ): array
    {
        $rates = [];

        foreach ( TaxRate::query()->whereIn( 'country_code', array_values( array_unique( $countries ) ) )->orderBy( 'id' )->get() as $rate ) {
            $rates[ self::matchKey( $rate ) ] ??= $rate;
        }

        return $rates;
    }

    /**
     * Builds the CSV for the rates a query returns.
     *
     * @since 1.0.0
     *
     * @param  Builder<TaxRate>  $query  Rates.
     *
     * @return string
     */
    public static function export( Builder $query ): string
    {
        $rows = ( clone $query )->lazy( 500 )->map( static fn ( TaxRate $rate ): array => [
            $rate->tax_class_key,
            $rate->country_code,
            (string) ( $rate->region_code ?? '' ),
            (string) ( $rate->postal_pattern ?? '' ),
            TaxRateMath::toPercent( (int) $rate->rate_ubps ),
            $rate->label,
            (bool) $rate->is_compound,
            (bool) $rate->is_shipping_taxable,
            (int) $rate->priority,
            (bool) $rate->is_active,
        ] );

        return Csv::build( self::COLUMNS, $rows );
    }

    /**
     * A sample file.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function sample(): string
    {
        return Csv::build( self::COLUMNS, [
            [ 'standard', 'US', 'NY', '', '8.875', 'NY sales tax', false, true, 0, true ],
            [ 'standard', 'CA', 'QC', '', '5', 'GST', false, true, 0, true ],
            [ 'standard', 'CA', 'QC', '', '9.975', 'QST', false, true, 1, true ],
            [ 'standard', 'DE', '', '', '19', 'MwSt.', false, true, 0, true ],
            [ 'reduced', 'DE', '', '', '7', 'MwSt. (ermäßigt)', false, false, 0, true ],
        ] );
    }

    /**
     * A percent as `rate_ubps`, or null when it is not a valid rate.
     *
     * @since 1.0.0
     *
     * @param  string  $percent  Percent, e.g. `8.375`.
     *
     * @return int|null
     */
    public static function ubps( string $percent ): ?int
    {
        $percent = str_replace( ',', '.', trim( $percent ) );

        if ( 1 !== preg_match( '/^\d{1,3}(\.\d+)?$/', $percent ) ) {
            return null;
        }

        try {
            $ubps = TaxRateMath::fromPercent( $percent );
        } catch ( InvalidArgumentException ) {
            return null;
        }

        return $ubps <= TaxRateMath::UNITS_PER_WHOLE ? $ubps : null;
    }

    /**
     * A CSV flag as a boolean: null when it is not one.
     *
     * @since 1.0.0
     *
     * @param  string  $value    Cell.
     * @param  bool    $default  Value of an empty cell.
     *
     * @return bool|null
     */
    private static function flag( string $value, bool $default ): ?bool
    {
        return match ( strtolower( $value ) ) {
            ''                    => $default,
            '1', 'true', 'yes'    => true,
            '0', 'false', 'no'    => false,
            default               => null,
        };
    }
}
