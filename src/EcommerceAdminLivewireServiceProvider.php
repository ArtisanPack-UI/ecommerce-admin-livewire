<?php

/**
 * Ecommerce admin (Livewire) service provider.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire;

use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Console\Commands\InstallCommand;
use ArtisanPackUI\EcommerceAdminLivewire\Http\Middleware\EnsureAdminAccess;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Dashboard;
use ArtisanPackUI\EcommerceAdminLivewire\Support\CmsFramework;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Bootstraps the Livewire admin satellite.
 *
 * Registers the package with the engine's `SatelliteRegistry` first. When the
 * satellite has been uninstalled (`register()` returns false) nothing else is
 * wired: no views, routes, components, navigation, or hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class EcommerceAdminLivewireServiceProvider extends ServiceProvider
{
    /**
     * The package version reported to the engine's `SatelliteRegistry`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const VERSION = '1.0.0';

    /**
     * The Composer package name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PACKAGE_NAME = 'artisanpack-ui/ecommerce-admin-livewire';

    /**
     * The view and translation namespace.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const VIEW_NAMESPACE = 'ecommerce-admin';

    /**
     * The package's Livewire components, keyed by component name.
     *
     * @since 1.0.0
     *
     * @var array<string, class-string>
     */
    public const LIVEWIRE_COMPONENTS = [
        'artisanpack-ecommerce-admin-dashboard' => Dashboard::class,
    ];

    /**
     * Registers the package configuration.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/artisanpack/ecommerce-admin-livewire.php',
            'artisanpack.ecommerce-admin-livewire',
        );
    }

    /**
     * Registers the satellite and, when it is active, wires the admin.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function boot(): void
    {
        if ( ! $this->registerSatellite() ) {
            return;
        }

        $this->registerPublishing();
        $this->registerViews();
        $this->registerTranslations();
        $this->registerCommands();
        $this->registerLayoutResolver();
        $this->registerLivewireComponents();
        $this->registerRoutes();
    }

    /**
     * The descriptor this package registers with the engine (spec §13).
     *
     * The admin owns no tables, columns, migrations, meta namespaces, or
     * product types, so it ships no uninstaller.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public static function satelliteDescriptor(): array
    {
        return [
            'package_name'    => self::PACKAGE_NAME,
            'version'         => self::VERSION,
            'label'           => __( 'Admin (Livewire)' ),
            'migration_paths' => [],
            'config_keys'     => [ 'artisanpack.ecommerce-admin-livewire' ],
            'meta_namespaces' => [],
            'tables'          => [],
            'columns'         => [],
            'product_types'   => [],
        ];
    }

    /**
     * Registers the package with the engine's `SatelliteRegistry`.
     *
     * @since 1.0.0
     *
     * @return bool True when the satellite is active.
     */
    protected function registerSatellite(): bool
    {
        return $this->app->make( SatelliteRegistry::class )->register( self::satelliteDescriptor() );
    }

    /**
     * Registers the config, views, and translations publish tags.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerPublishing(): void
    {
        $this->publishes(
            [
                __DIR__ . '/../config/artisanpack/ecommerce-admin-livewire.php' => config_path( 'artisanpack/ecommerce-admin-livewire.php' ),
            ],
            'ecommerce-admin-config',
        );

        $this->publishes(
            [
                __DIR__ . '/../resources/views' => resource_path( 'views/vendor/' . self::VIEW_NAMESPACE ),
            ],
            'ecommerce-admin-views',
        );

        $this->publishes(
            [
                __DIR__ . '/../lang' => $this->app->langPath( 'vendor/' . self::VIEW_NAMESPACE ),
            ],
            'ecommerce-admin-lang',
        );
    }

    /**
     * Loads the package views under the `ecommerce-admin` namespace.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom( __DIR__ . '/../resources/views', self::VIEW_NAMESPACE );
    }

    /**
     * Loads the JSON translation catalogues.
     *
     * Published catalogues in `lang/vendor/ecommerce-admin` are loaded too so a
     * host can override individual strings.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerTranslations(): void
    {
        $this->loadJsonTranslationsFrom( __DIR__ . '/../lang' );
        $this->loadJsonTranslationsFrom( $this->app->langPath( 'vendor/' . self::VIEW_NAMESPACE ) );
    }

    /**
     * Registers the Artisan commands.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        if ( ! $this->app->runningInConsole() ) {
            return;
        }

        $this->commands( [
            InstallCommand::class,
        ] );
    }

    /**
     * Shares the layout every page view extends.
     *
     * Pages extend `$ecommerceAdminLayout`: cms-framework's admin layout when
     * it is installed, else the package's standalone layout. Under the CMS
     * layout, `$ecommerceAdminPushesAssets` tells the page to push the
     * Livewire assets onto the layout's stacks.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerLayoutResolver(): void
    {
        View::composer( self::VIEW_NAMESPACE . '::pages.*', static function ( ViewContract $view ): void {
            $usesCms = CmsFramework::isInstalled();

            $view->with( [
                'ecommerceAdminLayout'       => $usesCms ? CmsFramework::LAYOUT : self::VIEW_NAMESPACE . '::layouts.app',
                'ecommerceAdminPushesAssets' => $usesCms,
            ] );
        } );
    }

    /**
     * Registers the Livewire components and the persistent access middleware.
     *
     * The route middleware only guards the initial page load. Registering it
     * as persistent makes Livewire re-run it on every update request from an
     * admin page.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerLivewireComponents(): void
    {
        if ( ! class_exists( Livewire::class ) ) {
            return;
        }

        foreach ( self::LIVEWIRE_COMPONENTS as $name => $class ) {
            Livewire::component( $name, $class );
        }

        Livewire::addPersistentMiddleware( [ EnsureAdminAccess::class ] );
    }

    /**
     * Registers the admin routes and the access middleware alias.
     *
     * Skipped when the routes are cached (the cache already holds them) or
     * when `admin.routes_enabled` is false.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        Route::aliasMiddleware( EnsureAdminAccess::ALIAS, EnsureAdminAccess::class );

        if ( $this->app->routesAreCached() ) {
            return;
        }

        if ( ! (bool) config( 'artisanpack.ecommerce-admin-livewire.admin.routes_enabled', true ) ) {
            return;
        }

        $this->loadRoutesFrom( __DIR__ . '/../routes/admin.php' );
    }
}
