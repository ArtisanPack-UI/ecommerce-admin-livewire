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

use ArtisanPackUI\EcommerceAdminLivewire\Support\NotificationTemplates;
use ArtisanPackUI\EcommerceAdminLivewire\Support\RbacPermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Publishes the admin config and prints the front-end setup steps.
 *
 * The package ships no CSS build: the host loads the ArtisanPack UI design
 * system (livewire-ui-components 3.0), adds the package views to its
 * Tailwind sources, and installs the drag-and-drop npm package itself.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class InstallCommand extends Command
{
    /**
     * The design-system stylesheet a host imports in its main stylesheet,
     * after Tailwind when it uses Tailwind.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const STYLESHEET_IMPORTS = [
        '@layer theme, base, apui, components, utilities;',
        "@import '@artisanpack-ui/css';",
    ];

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
     * The npm packages the admin's pages depend on: the design system's CSS
     * and behavior engine (the release livewire-ui-components 3.x renders),
     * and drag-and-drop for the reorderable lists.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const NPM_PACKAGES = [ '@artisanpack-ui/css@^1', '@artisanpack-ui/behavior@^1', '@artisanpack-ui/livewire-drag-and-drop' ];

    /**
     * The lines that load the design system's behavior engine and
     * drag-and-drop, for `resources/js/app.js`. Livewire starts Alpine, so
     * the engine registers on `alpine:init`.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const JS_GLOBALS = [
        "import '@artisanpack-ui/livewire-drag-and-drop';",
        "import apuiBehavior from '@artisanpack-ui/behavior/alpine';",
        "document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );",
    ];

    /**
     * The command that publishes TinyMCE for the description editor.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PUBLISH_ASSETS_COMMAND = 'php artisan vendor:publish --tag=artisanpack-assets';

    /**
     * The full front-end setup guide.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const FRONT_END_DOCS = 'https://github.com/ArtisanPack-UI/ecommerce-admin-livewire/blob/main/docs/installation/front-end.md';

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

        foreach ( self::STYLESHEET_IMPORTS as $line ) {
            $this->line( '    ' . $line );
        }

        foreach ( self::TAILWIND_SOURCES as $source ) {
            $this->line( '    ' . $source );
        }

        $this->newLine();
        $this->components->info( __( 'Install the front-end dependencies:' ) );
        $this->line( '    npm install ' . implode( ' ', self::NPM_PACKAGES ) );

        $this->newLine();
        $this->components->info( __( 'Add these lines to your main script (e.g. resources/js/app.js):' ) );

        foreach ( self::JS_GLOBALS as $line ) {
            $this->line( '    ' . $line );
        }

        $this->newLine();
        $this->components->info( __( 'Publish TinyMCE for the product description editor:' ) );
        $this->line( '    ' . self::PUBLISH_ASSETS_COMMAND );

        $this->newLine();
        $this->components->warn( __( 'Remove daisyUI: the admin renders on the ArtisanPack UI design system.' ) );
        $this->components->info( __( 'Full front-end setup: :url', [ 'url' => self::FRONT_END_DOCS ] ) );

        $this->newLine();
        $this->checkAuthorization();

        try {
            NotificationTemplates::sync();
        } catch ( Throwable $exception ) {
            // Usually the engine tables aren't migrated yet; the
            // notifications screen seeds the templates on first open.
            report( $exception );
            $this->components->warn( __( 'The notification templates could not be created yet. Run your migrations; the notifications screen creates them when it first opens.' ) );
        }

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
            $this->call( 'ecommerce:sync-permissions' );
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
