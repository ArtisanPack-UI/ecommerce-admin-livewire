<?php

/**
 * Sync permissions command.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Console\Commands;

use ArtisanPackUI\EcommerceAdminLivewire\Support\RbacPermissions;
use Illuminate\Console\Command;

/**
 * Deprecated alias of the engine's `ecommerce:sync-permissions`.
 *
 * The engine now owns the ability catalog and the cms-framework sync. This
 * command prints a notice and forwards to it.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 * @deprecated 1.0.0 Use `ecommerce:sync-permissions`.
 */
class SyncPermissionsCommand extends Command
{
    /**
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'ecommerce-admin:sync-permissions';

    /**
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Deprecated: use ecommerce:sync-permissions. Registers the ecommerce abilities as cms-framework permissions.';

    /**
     * Forwards to the engine command.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function handle(): int
    {
        $this->components->warn( __( 'ecommerce-admin:sync-permissions is deprecated. Run ecommerce:sync-permissions instead.' ) );

        if ( ! RbacPermissions::available() ) {
            $this->components->warn( __( 'cms-framework is not installed, so there are no RBAC permissions to register. Grant access with the ecommerce.admin gate instead.' ) );

            return self::SUCCESS;
        }

        return $this->call( 'ecommerce:sync-permissions' );
    }
}
