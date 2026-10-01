<?php

/**
 * Install command.
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
use Illuminate\Support\Facades\Gate;

/**
 * Publishes the admin config and prints the front-end setup steps.
 *
 * The package ships no CSS build: the host adds the package views to its
 * Tailwind sources and installs the drag-and-drop npm package itself.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class InstallCommand extends Command
{
    /**
     * The Tailwind `@source` lines a host adds to its main stylesheet.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const TAILWIND_SOURCES = [
        '@source "../../vendor/artisanpack-ui/ecommerce-admin-livewire/resources/views/**/*.blade.php";',
        '@source "../../vendor/artisanpack-ui/ecommerce-admin-livewire/src/**/*.php";',
    ];

    /**
     * The npm package the admin's reorderable lists depend on.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const NPM_PACKAGE = '@artisanpack-ui/livewire-drag-and-drop';

    /**
     * @var string
     */
    protected $signature = 'ecommerce-admin:install
        {--force : Overwrite a previously published config file.}';

    /**
     * @var string
     */
    protected $description = 'Publish the ecommerce admin config and print the front-end setup steps.';

    /**
     * Runs the command.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function handle(): int
    {
        $this->components->info( __( 'Installing the ecommerce admin.' ) );

        $this->call( 'vendor:publish', [
            '--tag'   => 'ecommerce-admin-config',
            '--force' => (bool) $this->option( 'force' ),
        ] );

        $this->newLine();
        $this->components->info( __( 'Add these lines to your main stylesheet (e.g. resources/css/app.css):' ) );

        foreach ( self::TAILWIND_SOURCES as $source ) {
            $this->line( '    ' . $source );
        }

        $this->newLine();
        $this->components->info( __( 'Install the front-end dependency:' ) );
        $this->line( '    npm install ' . self::NPM_PACKAGE );

        $this->newLine();
        $this->checkAuthorization();

        return self::SUCCESS;
    }

    /**
     * Warns when nothing grants access to the admin.
     *
     * The engine denies every ability by default. A host grants access with
     * the umbrella `ecommerce.admin` gate or, with cms-framework, through RBAC
     * permissions, which this step registers.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function checkAuthorization(): void
    {
        $granted = false;

        if ( RbacPermissions::available() ) {
            $this->call( 'ecommerce-admin:sync-permissions' );
            $this->components->info( __( 'Assign the shop-manager role, or individual ecommerce permissions, to your staff.' ) );
            $granted = true;
        }

        if ( Gate::has( 'ecommerce.admin' ) ) {
            $this->components->info( __( 'The ecommerce.admin gate is defined.' ) );
            $granted = true;
        }

        if ( $granted ) {
            return;
        }

        $this->components->warn( __( 'No ecommerce.admin gate is defined, so every admin screen will return 403. Define it in a service provider, for example:' ) );
        $this->line( "    Gate::define( 'ecommerce.admin', fn ( \$user ) => \$user->is_admin );" );
    }
}
