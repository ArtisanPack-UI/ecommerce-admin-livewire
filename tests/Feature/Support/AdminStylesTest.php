<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminStyles;

it( 'reads the admin stylesheet, built on design-system tokens only', function (): void {
    $css = AdminStyles::css();

    expect( $css )->toContain( '.ec-money' )
        ->toContain( 'var(--apui-' )
        ->not->toContain( '--color-base' )
        ->not->toContain( 'daisy' )
        ->not->toMatch( '/<\/style/i' );
} );

it( 'inlines the stylesheet once, with the CSP nonce', function (): void {
    Illuminate\Support\Facades\Vite::useCspNonce( 'nonce-xyz' );

    $html = Illuminate\Support\Facades\Blade::render( "@include( 'ecommerce-admin::partials.styles' )\n@include( 'ecommerce-admin::partials.styles' )" );

    expect( substr_count( $html, 'data-ecommerce-admin-styles' ) )->toBe( 1 )
        ->and( $html )->toContain( 'nonce="nonce-xyz"' );
} );
