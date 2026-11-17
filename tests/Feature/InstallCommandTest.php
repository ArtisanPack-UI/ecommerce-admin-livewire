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

it( 'prints the design-system import, the Tailwind source lines and the npm dependencies', function (): void {
    $command = $this->artisan( 'ecommerce-admin:install' );

    foreach ( [ ...InstallCommand::STYLESHEET_IMPORTS, ...InstallCommand::TAILWIND_SOURCES ] as $source ) {
        $command->expectsOutputToContain( $source );
    }

    $command->expectsOutputToContain( 'npm install @artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1 @artisanpack-ui/livewire-drag-and-drop' )
        ->assertSuccessful();
} );

it( 'prints the script lines, the asset publish command, the daisyUI removal, and the docs link', function (): void {
    $command = $this->artisan( 'ecommerce-admin:install' );

    foreach ( InstallCommand::JS_GLOBALS as $line ) {
        $command->expectsOutputToContain( $line );
    }

    $command->expectsOutputToContain( 'php artisan vendor:publish --tag=artisanpack-assets' )
        ->expectsOutputToContain( 'Remove daisyUI' )
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
