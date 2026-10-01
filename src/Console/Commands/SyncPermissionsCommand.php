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
 * Registers the engine abilities as cms-framework RBAC permissions.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class SyncPermissionsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ecommerce-admin:sync-permissions';

    /**
     * @var string
     */
    protected $description = 'Register the ecommerce abilities as cms-framework permissions and create the shop-manager role.';

    /**
     * Runs the command.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function handle(): int
    {
        if ( ! RbacPermissions::available() ) {
            $this->components->warn( __( 'cms-framework is not installed, so there are no RBAC permissions to register. Grant access with the ecommerce.admin gate instead.' ) );

            return self::SUCCESS;
        }

        $count = RbacPermissions::register();

        $this->components->info( trans_choice(
            'Registered :count ecommerce permission and the :role role.|Registered :count ecommerce permissions and the :role role.',
            $count,
            [ 'count' => $count, 'role' => RbacPermissions::ROLE ],
        ) );

        return self::SUCCESS;
    }
}
