<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Browser\Support\Locators;
use Tests\Fixtures\Livewire\ConfigFormHost;

beforeEach( function (): void {
    app( ConfigFormRegistry::class )->register( 'promotion-action', 'browser-weekdays', [
        [ 'name' => 'slots', 'type' => 'repeater', 'label' => 'Slots', 'fields' => [
            [ 'name' => 'days', 'type' => 'weekday', 'label' => 'Days' ],
        ] ],
    ] );

    Livewire::component( 'browser-config-form-host', ConfigFormHost::class );

    Route::middleware( 'web' )->get( 'browser-config-form', static fn (): string => Blade::render(
        "@extends( 'ecommerce-admin::layouts.app' )\n@section( 'content' )\n<livewire:browser-config-form-host registry=\"promotion-action\" entry=\"browser-weekdays\" :stored=\"\$stored\" />\n@endsection",
        [ 'stored' => [ 'slots' => [ [ 'days' => [ 1 ] ], [ 'days' => [ 2 ] ] ] ] ],
    ) );
} );

it( 'toggles weekday chips on the row they belong to, even after a row above is removed', function (): void {
    $page = visit( '/browser-config-form' )
        ->assertScript( 'document.querySelectorAll( "[data-config-path] [aria-pressed=\'true\']" ).length', 2 )
        ->click( '[data-config-path="config.slots.1.days"] [aria-label="Friday"]' )
        ->assertAttribute( '[data-config-path="config.slots.1.days"] [aria-label="Friday"]', 'aria-pressed', 'true' )
        ->click( Locators::role( 'Remove Slots row 1' ) )
        ->assertScript( 'document.querySelectorAll( "[data-config-path]" ).length', 1 )
        ->assertAttribute( '[data-config-path="config.slots.0.days"] [aria-label="Tuesday"]', 'aria-pressed', 'true' )
        ->click( '[data-config-path="config.slots.0.days"] [aria-label="Sunday"]' )
        ->assertAttribute( '[data-config-path="config.slots.0.days"] [aria-label="Sunday"]', 'aria-pressed', 'true' );

    $page->assertScript( '( () => { const root = document.querySelector( "[data-config-form]" ).closest( "[wire\\\\:id]" ); return JSON.stringify( Livewire.find( root.getAttribute( "wire:id" ) ).config.slots.map( ( slot ) => slot.days ) ) } )()', '[[2,5,7]]' )
        ->assertNoJavaScriptErrors();
} );
