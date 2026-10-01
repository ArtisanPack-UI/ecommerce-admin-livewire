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
