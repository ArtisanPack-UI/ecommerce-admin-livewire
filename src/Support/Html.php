<?php

/**
 * Rich-text cleaning.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Services\ProductService;

/**
 * Cleans rich text from the admin's editors with the security package's
 * `kses()` in htmLawed's safe mode, using the engine's own config so the
 * admin and the engine clean descriptions the same way. It drops scripts,
 * styles, stylesheet links, forms, embeds, event-handler and `style`
 * attributes, and non-http(s) URLs. (`kses()` with its default config keeps
 * those.)
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class Html
{
    /**
     * htmLawed config for admin rich text (the engine's).
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    public const KSES_CONFIG = ProductService::KSES_CONFIG;

    /**
     * Cleaned HTML ('' for blank input).
     *
     * @since 1.0.0
     *
     * @param  string|null  $html  Raw HTML.
     *
     * @return string
     */
    public static function clean( ?string $html ): string
    {
        if ( null === $html || '' === trim( $html ) ) {
            return '';
        }

        return trim( security()->kses( $html, self::KSES_CONFIG ) );
    }
}
