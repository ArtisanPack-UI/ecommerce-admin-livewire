<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

it( 'renders the design-system empty state as a status region', function (): void {
    $html = Blade::render( '<x-artisanpack-ec-empty-state icon="inbox" title="No orders yet" description="Orders appear here as soon as a customer checks out." />' );

    expect( $html )->toContain( 'apui-empty-state' )
        ->toContain( 'role="status"' )
        ->toContain( 'data-size="md"' )
        ->toContain( '<h2 data-scope="empty-state" data-part="title">No orders yet</h2>' )
        ->toContain( 'Orders appear here as soon as a customer checks out.' )
        ->toContain( 'data-part="icon" aria-hidden="true"' );
} );

it( 'takes the compact size inside a card, and falls back to md', function (): void {
    expect( Blade::render( '<x-artisanpack-ec-empty-state title="Nothing here yet" size="sm" />' ) )->toContain( 'data-size="sm"' )
        ->and( Blade::render( '<x-artisanpack-ec-empty-state title="Nothing here yet" size="xl" />' ) )->toContain( 'data-size="md"' );
} );

it( 'puts the slot in the actions part', function (): void {
    $html = Blade::render( '<x-artisanpack-ec-empty-state title="No products yet"><x-artisanpack-button>Add product</x-artisanpack-button></x-artisanpack-ec-empty-state>' );

    expect( $html )->toContain( 'data-part="actions"' )
        ->toContain( 'Add product' );
} );

it( 'leaves out the description and actions when there are none', function (): void {
    $html = Blade::render( '<x-artisanpack-ec-empty-state title="No products yet" />' );

    expect( $html )->not->toContain( 'data-part="description"' )
        ->not->toContain( 'data-part="actions"' );
} );

it( 'takes a heading level', function (): void {
    expect( Blade::render( '<x-artisanpack-ec-empty-state title="No notes" :heading-level="4" />' ) )
        ->toContain( '<h4 data-scope="empty-state" data-part="title">No notes</h4>' );
} );

it( 'registers an admin-only alias that ecommerce-storefront-livewire cannot take over', function (): void {
    expect( Blade::getClassComponentAliases() )
        ->toHaveKey( 'artisanpack-ec-admin-empty-state', ArtisanPackUI\EcommerceAdminLivewire\View\Components\EmptyState::class )
        ->toHaveKey( 'artisanpack-ec-empty-state', ArtisanPackUI\EcommerceAdminLivewire\View\Components\EmptyState::class );
} );

it( 'renders the admin empty state in the admin views even when another package owns the shared name', function (): void {
    Blade::component( 'artisanpack-ec-empty-state', Illuminate\View\AnonymousComponent::class );

    expect( Blade::render( '<x-artisanpack-ec-admin-empty-state title="No orders yet" />' ) )->toContain( 'apui-empty-state' );
} );
