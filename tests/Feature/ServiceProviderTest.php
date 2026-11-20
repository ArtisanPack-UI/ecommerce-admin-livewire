<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\EcommerceAdminLivewireServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

it( 'boots alongside the engine', function (): void {
    expect( $this->app->getProvider( EcommerceAdminLivewireServiceProvider::class ) )
        ->toBeInstanceOf( EcommerceAdminLivewireServiceProvider::class );
} );

it( 'merges the config under the artisanpack key', function (): void {
    expect( config( 'artisanpack.ecommerce-admin-livewire.admin.route_prefix' ) )->toBe( 'ecommerce-admin' )
        ->and( config( 'artisanpack.ecommerce-admin-livewire.admin.middleware' ) )->toBe( [ 'web', 'auth' ] )
        ->and( config( 'artisanpack.ecommerce-admin-livewire.admin.routes_enabled' ) )->toBeTrue()
        ->and( config( 'artisanpack.ecommerce-admin-livewire.admin.auto_register_cms_nav' ) )->toBeTrue()
        ->and( config( 'artisanpack.ecommerce-admin-livewire.tables.per_page' ) )->toBe( 25 )
        ->and( config( 'artisanpack.ecommerce-admin-livewire.tables.per_page_values' ) )->toBe( [ 10, 25, 50, 100 ] )
        ->and( config( 'artisanpack.ecommerce-admin-livewire.spotlight' ) )->toBe( [ 'enabled' => true, 'shortcut' => 'meta.k', 'limit' => 5 ] )
        ->and( config( 'artisanpack.ecommerce-admin-livewire.realtime.enabled' ) )->toBeFalse()
        ->and( config( 'artisanpack.ecommerce-admin-livewire.imports' ) )->toBe( [ 'disk' => 'local', 'max_rows' => 5000, 'queue' => null ] );
} );

it( 'registers itself with the satellite registry', function (): void {
    $registry = $this->app->make( SatelliteRegistry::class );

    expect( $registry->has( 'artisanpack-ui/ecommerce-admin-livewire' ) )->toBeTrue()
        ->and( $registry->isActive( 'artisanpack-ui/ecommerce-admin-livewire' ) )->toBeTrue();

    $descriptor = $registry->get( 'artisanpack-ui/ecommerce-admin-livewire' );

    expect( $descriptor->version )->toBe( EcommerceAdminLivewireServiceProvider::VERSION )
        ->and( $descriptor->configKeys )->toBe( [ 'artisanpack.ecommerce-admin-livewire' ] )
        ->and( $descriptor->tables )->toBe( [] )
        ->and( $descriptor->migrationPaths )->toBe( [] );
} );

it( 'registers the view namespace', function (): void {
    expect( $this->app['view']->getFinder()->getHints() )->toHaveKey( 'ecommerce-admin' );
} );

it( 'registers the publish tags', function ( string $tag ): void {
    expect( ServiceProvider::pathsToPublish( EcommerceAdminLivewireServiceProvider::class, $tag ) )->not->toBeEmpty();
} )->with( [ 'ecommerce-admin-config', 'ecommerce-admin-views', 'ecommerce-admin-lang' ] );

it( 'registers the install command', function (): void {
    expect( Artisan::all() )->toHaveKey( 'ecommerce-admin:install' );
} );

it( 'makes the host\'s admin middleware persistent, resolving aliases and skipping groups', function (): void {
    app( 'router' )->aliasMiddleware( 'test.verified', Tests\Fixtures\Http\RequireVerifiedName::class );
    config()->set( 'artisanpack.ecommerce-admin-livewire.admin.middleware', [ 'web', 'auth', 'test.verified:extra', Illuminate\Auth\Middleware\RequirePassword::class, 'not-a-thing' ] );

    expect( EcommerceAdminLivewireServiceProvider::hostPersistentMiddleware() )
        ->toContain( Tests\Fixtures\Http\RequireVerifiedName::class, Illuminate\Auth\Middleware\RequirePassword::class )
        ->not->toContain( 'web' );

    app()->getProvider( EcommerceAdminLivewireServiceProvider::class )->registerPersistentMiddleware();

    expect( app( Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware::class )->getPersistentMiddleware() )
        ->toContain( Tests\Fixtures\Http\RequireVerifiedName::class, Illuminate\Auth\Middleware\RequirePassword::class );
} );

it( 'expands host middleware groups recursively, dropping what the web group already runs', function (): void {
    $router = app( 'router' );
    $router->aliasMiddleware( 'test.verified', Tests\Fixtures\Http\RequireVerifiedName::class );
    $router->middlewareGroup( 'test-inner', [ 'test.verified', Illuminate\Session\Middleware\StartSession::class ] );
    $router->middlewareGroup( 'test-admin', [ 'test-inner', Illuminate\Auth\Middleware\RequirePassword::class, 'test-admin' ] );
    config()->set( 'artisanpack.ecommerce-admin-livewire.admin.middleware', [ 'web', 'test-admin' ] );

    $middleware = EcommerceAdminLivewireServiceProvider::hostPersistentMiddleware();

    expect( $middleware )
        ->toContain( Tests\Fixtures\Http\RequireVerifiedName::class, Illuminate\Auth\Middleware\RequirePassword::class )
        ->not->toContain( Illuminate\Session\Middleware\StartSession::class )
        ->and( $middleware )->toBe( array_values( array_unique( $middleware ) ) );
} );

it( 'does not make host throttles persistent', function (): void {
    config()->set( 'artisanpack.ecommerce-admin-livewire.admin.middleware', [
        'web',
        'auth',
        'throttle:60,1',
        Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class . ':login',
    ] );

    expect( EcommerceAdminLivewireServiceProvider::hostPersistentMiddleware() )
        ->not->toContain( Illuminate\Routing\Middleware\ThrottleRequests::class )
        ->not->toContain( Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class );
} );

it( 'reports version 2.0.0', function (): void {
    expect( EcommerceAdminLivewireServiceProvider::VERSION )->toBe( '2.0.0' );
} );

it( 'fingerprints the notification template sync with the package version, so a 1.x upgrade re-syncs', function (): void {
    $fingerprint = ( new ReflectionMethod( ArtisanPackUI\EcommerceAdminLivewire\Support\NotificationTemplates::class, 'fingerprint' ) )->invoke( null );
    $keys        = app( ArtisanPackUI\Ecommerce\Registries\NotificationTemplateRegistry::class )->keys();
    sort( $keys );
    $locale = app( ArtisanPackUI\Ecommerce\Services\NotificationTemplateService::class )->defaultLocale();
    $for    = static fn ( string $version ): string => hash( 'sha256', implode( '|', [ $version, $locale, implode( ',', $keys ) ] ) );

    expect( $fingerprint )->toBe( $for( EcommerceAdminLivewireServiceProvider::VERSION ) )
        ->not->toBe( $for( '1.0.0' ) );
} );

it( 'registers the gift icon the docs recommend for navigation items', function (): void {
    expect( ArtisanPack\LivewireUiComponents\Support\IconRegistry::has( 'gift' ) )->toBeTrue()
        ->and( ArtisanPack\LivewireUiComponents\Support\IconRegistry::render( 'gift' ) )->toContain( '<svg' );
} );

it( 'registers its catalogues before any provider boots, so an early __() cannot cache a locale without them', function (): void {
    $translator = new Illuminate\Translation\Translator(
        new Illuminate\Translation\FileLoader( new Illuminate\Filesystem\Filesystem(), $this->app->langPath() ),
        'es',
    );
    $this->app->instance( 'translator', $translator );

    ( new EcommerceAdminLivewireServiceProvider( $this->app ) )->register();

    expect( $translator->get( 'View all low-stock items' ) )->toBe( json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/lang/es.json' ), true )['View all low-stock items'] )
        ->not->toBe( 'View all low-stock items' );
} );
