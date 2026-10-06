<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Support\UnsavedChanges;

it( 'gives the Alpine members without a double quote, so they fit in an x-data attribute', function (): void {
    expect( UnsavedChanges::members() )
        ->toContain( 'dirty: false,' )
        ->toContain( 'warnBeforeUnload( event )' )
        ->toContain( 'confirmNavigate( event )' )
        ->not->toContain( '"' );
} );

it( 'clears the warning on the given saved event', function (): void {
    expect( UnsavedChanges::attributes() )->toContain( 'x-on:' . UnsavedChanges::SAVED_EVENT . '.window="dirty = false"' )
        ->and( UnsavedChanges::attributes( 'custom-saved' ) )->toContain( 'x-on:custom-saved.window="dirty = false"' )
        ->and( UnsavedChanges::attributes() )->toContain( 'x-on:livewire:navigate.document="confirmNavigate( $event )"' );
} );
