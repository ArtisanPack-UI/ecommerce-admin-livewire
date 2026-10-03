<?php

declare( strict_types=1 );

namespace Tests;

use ArtisanPackUI\Ecommerce\Services\NotificationTemplateService;
use ArtisanPackUI\EcommerceAdminLivewire\Support\CmsFramework;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Tests\Fixtures\User;

/**
 * Base case for the browser suite.
 *
 * Serves the Testbench app from `tests/Browser/workbench/public`, where
 * `npm run build:browser` writes the asset bundle, loads that bundle in
 * the standalone admin layout, and seeds a small, repeatable demo store.
 */
abstract class BrowserTestCase extends TestCase
{
    /**
     * The workbench entries the admin layout loads.
     *
     * @var array<int, string>
     */
    public const VITE_ENTRIES = [
        'tests/Browser/workbench/resources/css/app.css',
        'tests/Browser/workbench/resources/js/app.js',
    ];

    /**
     * The signed-in admin.
     *
     * @var User
     */
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if ( ! is_file( public_path( 'build/manifest.json' ) ) ) {
            throw new RuntimeException( 'Build the browser asset bundle first: npm ci && npm run build:browser' );
        }

        CmsFramework::fake( false );

        addFilter( 'ap.ecommerceAdminLivewire.layout.viteEntries', static fn (): array => self::VITE_ENTRIES );

        // A real store has its notification catalogue synced.
        app( NotificationTemplateService::class )->sync();

        $this->artisan( 'ecommerce:seed-demo', [ '--products' => 12, '--orders' => 15, '--seed' => 46, '--force' => true ] )->assertSuccessful();

        $this->admin = User::query()->create( [ 'name' => 'Ada Admin', 'email' => 'admin@example.test', 'password' => 'secret' ] );

        Gate::define( 'ecommerce.admin', static fn ( $user ): bool => 'admin@example.test' === $user->email );

        $this->actingAs( $this->admin );
    }

    protected function tearDown(): void
    {
        removeAllFilters( 'ap.ecommerceAdminLivewire.layout.viteEntries' );

        parent::tearDown();
    }

    protected function defineEnvironment( $app ): void
    {
        parent::defineEnvironment( $app );

        $app->usePublicPath( __DIR__ . '/Browser/workbench/public' );

        $app['config']->set( 'auth.providers.users.model', User::class );
        $app['config']->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    }
}
