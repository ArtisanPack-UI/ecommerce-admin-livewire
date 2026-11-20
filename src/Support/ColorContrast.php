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
 * Checks whether a status colour stays visible, when
 * `artisanpack-ui/accessibility` is installed (spec §4.2).
 *
 * Since 2.0 a status colour is only ever a dot or an icon (the badge text
 * is the neutral text colour), so the check is the WCAG 1.4.11 non-text
 * contrast of 3:1 against the admin's light and dark surfaces, not text
 * contrast on the colour.
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
     * The minimum contrast for icons and dots (WCAG 2 1.4.11, non-text).
     *
     * @since 2.0.0
     *
     * @var float
     */
    public const NON_TEXT_MIN_RATIO = 3.0;

    /**
     * The design system's default surface in its light theme.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const LIGHT_SURFACE = '#FAFCFE';

    /**
     * The design system's default surface in its dark theme.
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const DARK_SURFACE = '#191D22';

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
     * Black or white, whichever has the higher WCAG contrast on `$hex`.
     *
     * @since 1.0.0 Picked by WCAG contrast ratio since 2.0.0 (was YIQ brightness).
     *
     * @param  string  $hex  Background colour, `#RRGGBB`.
     *
     * @return string `#000000` or `#FFFFFF`.
     */
    public static function badgeTextColor( string $hex ): string
    {
        return self::ratio( $hex, '#000000' ) >= self::ratio( $hex, '#FFFFFF' ) ? '#000000' : '#FFFFFF';
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

        $light = self::ratio( $hex, self::LIGHT_SURFACE );
        $dark  = self::ratio( $hex, self::DARK_SURFACE );

        if ( $light < self::NON_TEXT_MIN_RATIO ) {
            return __( 'The icon and dot in this colour have a contrast of :ratio:1 on the light background, below the 3:1 that WCAG asks for. Pick a darker colour so they stay visible.', [
                'ratio' => number_format( $light, 2 ),
            ] );
        }

        if ( $dark < self::NON_TEXT_MIN_RATIO ) {
            return __( 'The icon and dot in this colour have a contrast of :ratio:1 on the dark background, below the 3:1 that WCAG asks for. Pick a lighter colour so they stay visible.', [
                'ratio' => number_format( $dark, 2 ),
            ] );
        }

        return null;
    }

    /**
     * A colour safe to print into an attribute (an SVG `fill` or `color`):
     * a six-digit hex colour, or `transparent` for anything else (the engine API checks only
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
