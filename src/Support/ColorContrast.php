<?php

/**
 * Colour contrast checks for status colours.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

/**
 * Checks whether a status colour keeps its badge text readable (WCAG AA,
 * 4.5:1), when `artisanpack-ui/accessibility` is installed (spec §4.2).
 *
 * The badge (`x-artisanpack-badge` with a hex colour) picks black text when
 * the colour's perceived brightness is above 128 and white text otherwise,
 * so the check runs against that text colour rather than the best possible
 * one: a colour can fail even though some text colour would pass.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class ColorContrast
{
    /**
     * The minimum contrast ratio (WCAG 2 AA, normal text).
     *
     * @since 1.0.0
     *
     * @var float
     */
    public const MIN_RATIO = 4.5;

    /**
     * Overrides detection in tests.
     *
     * @since 1.0.0
     *
     * @var bool|null
     */
    private static ?bool $fake = null;

    /**
     * Whether the accessibility package is installed and booted.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return self::$fake ?? ( function_exists( 'a11yCheckContrastColor' ) && app()->bound( 'a11y' ) );
    }

    /**
     * Forces detection on or off; null restores real detection.
     *
     * @since 1.0.0
     *
     * @param  bool|null  $available  Whether to report the package as installed.
     *
     * @return void
     */
    public static function fake( ?bool $available ): void
    {
        self::$fake = $available;
    }

    /**
     * The text colour the badge renders on `$hex`.
     *
     * @since 1.0.0
     *
     * @param  string  $hex  Background colour, `#RRGGBB`.
     *
     * @return string `#000000` or `#FFFFFF`.
     */
    public static function badgeTextColor( string $hex ): string
    {
        [ $r, $g, $b ] = self::channels( $hex );

        return ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000 > 128 ? '#000000' : '#FFFFFF';
    }

    /**
     * The warning for `$hex`, or null when it passes, is not a colour, or
     * the accessibility package is not installed.
     *
     * @since 1.0.0
     *
     * @param  mixed  $hex  Colour.
     *
     * @return string|null
     */
    public static function warning( mixed $hex ): ?string
    {
        if ( ! self::isAvailable() || ! is_string( $hex ) || 1 !== preg_match( '/^#[0-9a-fA-F]{6}$/D', $hex ) ) {
            return null;
        }

        $text = self::badgeTextColor( $hex );

        if ( self::passes( $hex, $text ) ) {
            return null;
        }

        return __( 'The badge text on this colour has a contrast of :ratio:1, below the 4.5:1 that WCAG AA asks for. Pick a darker or lighter colour so it stays readable.', [
            'ratio' => number_format( self::ratio( $hex, $text ), 2 ),
        ] );
    }

    /**
     * A colour safe to print into a `style` attribute: a six-digit hex
     * colour, or `transparent` for anything else (the engine API checks only
     * the length).
     *
     * @since 1.0.0
     *
     * @param  mixed  $color  The stored colour.
     *
     * @return string
     */
    public static function safeHex( mixed $color ): string
    {
        return is_string( $color ) && 1 === preg_match( '/^#[0-9A-Fa-f]{6}$/D', $color ) ? $color : 'transparent';
    }

    /**
     * Whether two colours reach {@see self::MIN_RATIO}, through the
     * accessibility package when it is booted.
     *
     * @since 1.0.0
     *
     * @param  string  $background  Background colour.
     * @param  string  $text        Text colour.
     *
     * @return bool
     */
    private static function passes( string $background, string $text ): bool
    {
        if ( function_exists( 'a11yCheckContrastColor' ) && app()->bound( 'a11y' ) ) {
            return (bool) a11yCheckContrastColor( $background, $text );
        }

        return self::ratio( $background, $text ) >= self::MIN_RATIO;
    }

    /**
     * The WCAG contrast ratio of two colours.
     *
     * @since 1.0.0
     *
     * @param  string  $first   Colour.
     * @param  string  $second  Colour.
     *
     * @return float
     */
    private static function ratio( string $first, string $second ): float
    {
        $lighter = max( self::luminance( $first ), self::luminance( $second ) );
        $darker  = min( self::luminance( $first ), self::luminance( $second ) );

        return ( $lighter + 0.05 ) / ( $darker + 0.05 );
    }

    /**
     * WCAG relative luminance.
     *
     * @since 1.0.0
     *
     * @param  string  $hex  Colour.
     *
     * @return float
     */
    private static function luminance( string $hex ): float
    {
        $linear = array_map(
            static function ( int $channel ): float {
                $value = $channel / 255;

                return $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
            },
            self::channels( $hex ),
        );

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    /**
     * The red, green, and blue channels of a hex colour.
     *
     * @since 1.0.0
     *
     * @param  string  $hex  Colour.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function channels( string $hex ): array
    {
        $hex = ltrim( $hex, '#' );

        return [ (int) hexdec( substr( $hex, 0, 2 ) ), (int) hexdec( substr( $hex, 2, 2 ) ), (int) hexdec( substr( $hex, 4, 2 ) ) ];
    }
}
