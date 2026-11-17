<?php

/**
 * The admin's inline stylesheet.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

/**
 * Reads `resources/css/admin.css`, the token-only helpers the design system
 * has no component for, so the layouts can inline it without a build step.
 *
 * The file is the package's own and never holds user input; a `</style>`
 * sequence is still escaped so the contents can't close the element early.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      2.0.0
 */
final class AdminStyles
{
    /**
     * The stylesheet, once read.
     *
     * @since 2.0.0
     *
     * @var string|null
     */
    private static ?string $css = null;

    /**
     * The stylesheet's path.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public static function path(): string
    {
        return dirname( __DIR__, 2 ) . '/resources/css/admin.css';
    }

    /**
     * The stylesheet's contents, safe to print inside a `<style>` element.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public static function css(): string
    {
        if ( null === self::$css ) {
            $contents  = is_file( self::path() ) ? (string) file_get_contents( self::path() ) : '';
            self::$css = str_ireplace( '</style', '<\/style', $contents );
        }

        return self::$css;
    }
}
