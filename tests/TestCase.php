<?php

declare( strict_types=1 );

namespace Tests;

use ArtisanPack\LivewireUiComponents\LivewireUiComponentsServiceProvider;
use ArtisanPackUI\Core\CoreServiceProvider;
use ArtisanPackUI\Ecommerce\Providers\EcommerceServiceProvider;
use ArtisanPackUI\EcommerceAdminLivewire\EcommerceAdminLivewireServiceProvider;
use ArtisanPackUI\EcommerceAdminLivewire\Support\CmsFramework;
use ArtisanPackUI\Hooks\Providers\HooksServiceProvider;
use ArtisanPackUI\Security\SecurityServiceProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Rebing\GraphQL\GraphQLServiceProvider;

/**
 * Base Test Case
 *
 * Boots the admin with the engine, Livewire, and livewire-ui-components.
 *
 * @since   1.0.0
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Resets static state between tests.
     */
    protected function tearDown(): void
    {
        CmsFramework::fake( null );

        parent::tearDown();
    }

    /**
     * Gets package providers.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     *
     * @return array<int, class-string> Array of service provider class names.
     */
    protected function getPackageProviders( $app ): array
    {
        return [
            CoreServiceProvider::class,
            HooksServiceProvider::class,
            SecurityServiceProvider::class,
            SanctumServiceProvider::class,
            ScoutServiceProvider::class,
            GraphQLServiceProvider::class,
            EcommerceServiceProvider::class,
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            LivewireUiComponentsServiceProvider::class,
            EcommerceAdminLivewireServiceProvider::class,
        ];
    }

    /**
     * Loads the fixture `users` table migration.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom( __DIR__ . '/Fixtures/migrations' );
    }

    /**
     * Defines environment setup.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     */
    protected function defineEnvironment( $app ): void
    {
        $app['config']->set( 'app.key', 'base64:' . base64_encode( random_bytes( 32 ) ) );

        $app['config']->set( 'database.default', 'testbench' );
        $app['config']->set( 'database.connections.testbench', [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ] );
    }
}
