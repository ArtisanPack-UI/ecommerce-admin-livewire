<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Console\Commands\InstallCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

afterEach( function (): void {
    File::delete( config_path( 'artisanpack/ecommerce-admin-livewire.php' ) );
} );

it( 'publishes the config', function (): void {
    $this->artisan( 'ecommerce-admin:install' )->assertSuccessful();

    expect( File::exists( config_path( 'artisanpack/ecommerce-admin-livewire.php' ) ) )->toBeTrue();
} );

it( 'prints the Tailwind source lines and the npm dependency', function (): void {
    $command = $this->artisan( 'ecommerce-admin:install' );

    foreach ( InstallCommand::TAILWIND_SOURCES as $source ) {
        $command->expectsOutputToContain( $source );
    }

    $command->expectsOutputToContain( 'npm install @artisanpack-ui/livewire-drag-and-drop apexcharts flatpickr' )
        ->assertSuccessful();
} );

it( 'prints the script globals, the asset publish command, the daisyUI pin, and the docs link', function (): void {
    $command = $this->artisan( 'ecommerce-admin:install' );

    foreach ( InstallCommand::JS_GLOBALS as $line ) {
        $command->expectsOutputToContain( $line );
    }

    $command->expectsOutputToContain( 'php artisan vendor:publish --tag=artisanpack-assets' )
        ->expectsOutputToContain( '~5.0' )
        ->expectsOutputToContain( InstallCommand::FRONT_END_DOCS )
        ->assertSuccessful();
} );

it( 'warns when nothing grants access to the admin', function (): void {
    $this->artisan( 'ecommerce-admin:install' )
        ->expectsOutputToContain( 'No ecommerce.admin gate is defined' )
        ->assertSuccessful();
} );

it( 'confirms the umbrella gate when it is defined', function (): void {
    Gate::define( 'ecommerce.admin', static fn (): bool => true );

    $this->artisan( 'ecommerce-admin:install' )
        ->expectsOutputToContain( 'The ecommerce.admin gate is defined.' )
        ->doesntExpectOutputToContain( 'No ecommerce.admin gate is defined' )
        ->assertSuccessful();
} );
