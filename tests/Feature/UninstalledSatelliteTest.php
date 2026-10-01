<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\EcommerceAdminLivewireServiceProvider;
use Illuminate\Support\Facades\Cache;

beforeEach( function (): void {
    // Simulate `ecommerce:satellite:uninstall`, then boot a fresh provider.
    Cache::forever( 'artisanpack.ecommerce.satellites.uninstalled', [ EcommerceAdminLivewireServiceProvider::PACKAGE_NAME ] );

    $this->app->forgetInstance( SatelliteRegistry::class );
    $this->app->singleton( SatelliteRegistry::class, static fn ( $app ): SatelliteRegistry => new SatelliteRegistry( $app ) );

    $this->app['view']->getFinder()->replaceNamespace( 'ecommerce-admin', [] );
} );

it( 'wires nothing when the satellite has been uninstalled', function (): void {
    $provider = new EcommerceAdminLivewireServiceProvider( $this->app );
    $provider->boot();

    expect( $this->app->make( SatelliteRegistry::class )->isActive( EcommerceAdminLivewireServiceProvider::PACKAGE_NAME ) )->toBeFalse()
        ->and( $this->app['view']->getFinder()->getHints()['ecommerce-admin'] )->toBe( [] );
} );
