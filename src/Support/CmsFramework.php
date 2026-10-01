<?php

/**
 * cms-framework detection.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

/**
 * Detects whether `artisanpack-ui/cms-framework` is installed.
 *
 * Probes `ArtisanPackUI\CMSFramework\…` with the capital `CMS`: Composer's
 * PSR-4 lookup is case-sensitive, so the `CmsFramework` spelling some sibling
 * packages use only matches when another package already loaded the class.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class CmsFramework
{
    /**
     * The class whose presence means cms-framework is installed.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PROBE = 'ArtisanPackUI\\CMSFramework\\Modules\\Admin\\Managers\\AdminMenuManager';

    /**
     * The cms-framework admin layout the pages extend when it is installed.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const LAYOUT = 'cms::admin.layouts.app';

    /**
     * Test override for the detection result.
     *
     * @since 1.0.0
     *
     * @var bool|null
     */
    private static ?bool $fake = null;

    /**
     * Whether cms-framework is installed.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function isInstalled(): bool
    {
        return self::$fake ?? class_exists( self::PROBE );
    }

    /**
     * Overrides detection, or restores it with null.
     *
     * Aliasing the probe class would be process-wide and irreversible, so
     * tests use this instead.
     *
     * @since 1.0.0
     *
     * @param  bool|null  $installed  The result to report, or null to probe again.
     *
     * @return void
     */
    public static function fake( ?bool $installed ): void
    {
        self::$fake = $installed;
    }
}
