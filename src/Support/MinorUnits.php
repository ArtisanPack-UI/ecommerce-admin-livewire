<?php

/**
 * Major / minor unit conversion.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use InvalidArgumentException;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Throwable;

/**
 * Converts between a decimal string and an integer scaled by 10^decimals.
 *
 * Money uses the currency's subunit as the scale (JPY 0, USD 2, KWD 3) and
 * tax rates use 7 (percent → `rate_ubps`). The conversion only moves digits
 * around in strings, so no value ever passes through a float. The money and
 * percent inputs run the same algorithm in Alpine.
 *
 * Parsing accepts `.` or `,` as the decimal separator and either as a
 * thousands separator:
 *
 * - When both appear, the last one is the decimal separator.
 * - When one appears and splits the number into valid thousands groups
 *   (`1,234`, `1.234.567`), it groups thousands, unless it appears once and
 *   the scale is 3 or more (`1.234` KWD is one dinar and 234 fils).
 * - Otherwise a single occurrence is the decimal separator, and several are
 *   invalid.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class MinorUnits
{
    /**
     * The number of decimals a percent carries in `rate_ubps`.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const PERCENT_SCALE = 7;

    /**
     * The currency's subunit (number of decimals).
     *
     * @since 1.0.0
     *
     * @param  string  $currency  The ISO 4217 code.
     *
     * @return int
     */
    public static function subunit( string $currency ): int
    {
        try {
            return ( new ISOCurrencies() )->subunitFor( new Currency( strtoupper( $currency ) ) );
        } catch ( Throwable ) {
            return 2;
        }
    }

    /**
     * Formats a scaled integer as a plain decimal string, e.g. 123456 → "1234.56".
     *
     * @since 1.0.0
     *
     * @param  int   $value      The scaled integer.
     * @param  int   $scale      The number of decimals.
     * @param  bool  $trimZeros  Drop trailing fractional zeros (83750000 at scale 7 → "8.375").
     *
     * @return string
     */
    public static function toDecimal( int $value, int $scale, bool $trimZeros = false ): string
    {
        $negative = $value < 0;
        $digits   = ltrim( (string) $value, '-' );

        if ( 0 === $scale ) {
            return ( $negative ? '-' : '' ) . $digits;
        }

        $digits   = str_pad( $digits, $scale + 1, '0', STR_PAD_LEFT );
        $fraction = substr( $digits, -$scale );

        if ( $trimZeros ) {
            $fraction = rtrim( $fraction, '0' );
        }

        return ( $negative ? '-' : '' ) . substr( $digits, 0, -$scale ) . ( '' === $fraction ? '' : '.' . $fraction );
    }

    /**
     * Parses a decimal string into a scaled integer, e.g. "1,234.5" (scale 2) → 123450.
     *
     * @since 1.0.0
     *
     * @param  string  $input  The decimal string.
     * @param  int     $scale  The number of decimals.
     *
     * @throws InvalidArgumentException When the input is not a number, or has more decimals than the scale.
     *
     * @return int|null Null for blank input.
     */
    public static function fromDecimal( string $input, int $scale ): ?int
    {
        $text = preg_replace( "/[\\s\u{00A0}\u{202F}']/u", '', $input ) ?? '';

        if ( '' === $text ) {
            return null;
        }

        $negative = str_starts_with( $text, '-' );
        $text     = ltrim( $text, '-' );

        if ( 1 !== preg_match( '/^[0-9.,]+$/', $text ) || 1 !== preg_match( '/[0-9]/', $text ) ) {
            throw new InvalidArgumentException( sprintf( '"%s" is not a number.', $input ) );
        }

        $separator = self::decimalSeparator( $text, $scale );

        if ( null === $separator ) {
            $whole    = $text;
            $fraction = '';
        } else {
            $position = strrpos( $text, $separator );
            $whole    = substr( $text, 0, (int) $position );
            $fraction = substr( $text, (int) $position + 1 );
        }

        $whole = str_replace( [ '.', ',' ], '', $whole );

        if ( 1 === preg_match( '/[.,]/', $fraction ) || strlen( $fraction ) > $scale ) {
            throw new InvalidArgumentException( sprintf( '"%s" has more than %d decimal places.', $input, $scale ) );
        }

        $digits = ltrim( ( '' === $whole ? '0' : $whole ) . str_pad( $fraction, $scale, '0' ), '0' );
        $digits = '' === $digits ? '0' : $digits;

        if ( strlen( $digits ) > 18 ) {
            throw new InvalidArgumentException( sprintf( '"%s" is too large.', $input ) );
        }

        return ( $negative ? -1 : 1 ) * (int) $digits;
    }

    /**
     * Formats an integer minor-unit amount, e.g. 1050 USD → "10.50".
     *
     * @since 1.0.0
     *
     * @param  int     $minor     The amount in minor units.
     * @param  string  $currency  The ISO 4217 code.
     *
     * @return string
     */
    public static function toMajor( int $minor, string $currency ): string
    {
        return self::toDecimal( $minor, self::subunit( $currency ) );
    }

    /**
     * Parses a major-unit amount into minor units, e.g. "10.5" USD → 1050.
     *
     * @since 1.0.0
     *
     * @param  string  $major     The amount.
     * @param  string  $currency  The ISO 4217 code.
     *
     * @throws InvalidArgumentException When the amount is not valid for the currency.
     *
     * @return int|null Null for blank input.
     */
    public static function toMinor( string $major, string $currency ): ?int
    {
        return self::fromDecimal( $major, self::subunit( $currency ) );
    }

    /**
     * Which character is the decimal separator, if any.
     *
     * @since 1.0.0
     *
     * @param  string  $text   Digits, dots, and commas.
     * @param  int     $scale  The number of decimals.
     *
     * @throws InvalidArgumentException When the separators make no sense (`1.234.5`).
     *
     * @return string|null
     */
    private static function decimalSeparator( string $text, int $scale ): ?string
    {
        $lastDot   = strrpos( $text, '.' );
        $lastComma = strrpos( $text, ',' );

        if ( false !== $lastDot && false !== $lastComma ) {
            return $lastDot > $lastComma ? '.' : ',';
        }

        $separator = false !== $lastDot ? '.' : ( false !== $lastComma ? ',' : null );

        if ( null === $separator ) {
            return null;
        }

        $groups      = explode( $separator, $text );
        $occurrences = count( $groups ) - 1;
        $isGrouping  = strlen( $groups[0] ) >= 1 && strlen( $groups[0] ) <= 3;

        foreach ( array_slice( $groups, 1 ) as $group ) {
            $isGrouping = $isGrouping && 3 === strlen( $group );
        }

        if ( $isGrouping && ( $occurrences > 1 || $scale < 3 ) ) {
            return null;
        }

        if ( 1 === $occurrences ) {
            return $separator;
        }

        throw new InvalidArgumentException( sprintf( '"%s" is not a number.', $text ) );
    }
}
