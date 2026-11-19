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

it( 'gives body row headers the same cell box as the design system\'s body cells', function (): void {
    $css  = AdminStyles::css();
    $rule = Illuminate\Support\Str::betweenFirst( $css, '.ec-table th[data-scope="table"][data-part="cell"] {', '}' );

    // The design system only styles body `td` cells, so a `<th scope="row">` needs its padding and rule here.
    expect( $rule )
        ->toContain( 'padding-inline: var(--apui-cell-padding-x)' )
        ->toContain( 'border-block-end: var(--apui-border-width) solid var(--apui-border-muted)' )
        ->toContain( 'block-size: var(--apui-row-height)' )
        ->and( $css )->toContain( 'tr:last-child > th[data-scope="table"][data-part="cell"]' );
} );
