<?php

/**
 * cms-framework RBAC permissions.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Auth\AbilityCatalog;
use ArtisanPackUI\Ecommerce\Auth\CmsFrameworkPermissions;

/**
 * The engine's abilities as cms-framework RBAC permissions.
 *
 * The engine owns the catalog (`AbilityCatalog`, including abilities
 * satellites add through `ap.ecommerce.abilities.catalog`) and the sync
 * (`CmsFrameworkPermissions`, `ecommerce:sync-permissions`). It also
 * defines the ability gates and syncs after every forward migration. This
 * class is the admin's view of that: whether RBAC is available, the
 * abilities, and a sync for the install command.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class RbacPermissions
{
    /**
     * The role holding every ecommerce permission (the engine's).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ROLE = CmsFrameworkPermissions::ROLE;

    /**
     * Whether cms-framework is installed, its RBAC helpers exist, and the
     * engine's cms-framework bridge is switched on
     * (`artisanpack.ecommerce.cms_framework.enabled`).
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function available(): bool
    {
        return CmsFramework::isInstalled() && app( CmsFrameworkPermissions::class )->isAvailable();
    }

    /**
     * Every engine ability as `{resource}.{action}`.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return array_map(
            static fn ( string $ability ): string => (string) preg_replace( '/^ecommerce\./', '', $ability ),
            AbilityCatalog::abilities(),
        );
    }

    /**
     * The RBAC permission slug of an ability.
     *
     * @since 1.0.0
     *
     * @param  string  $ability  `{resource}.{action}`.
     *
     * @return string `ecommerce.{resource}.{action}`.
     */
    public static function permissionSlug( string $ability ): string
    {
        return 'ecommerce.' . $ability;
    }

    /**
     * Registers every ability as a permission and gives them all to the
     * shop-manager role, through the engine. Safe to run repeatedly.
     *
     * @since 1.0.0
     *
     * @return int How many permissions were synced; 0 when RBAC is unavailable.
     */
    public static function register(): int
    {
        if ( ! self::available() ) {
            return 0;
        }

        return app( CmsFrameworkPermissions::class )->sync();
    }
}
