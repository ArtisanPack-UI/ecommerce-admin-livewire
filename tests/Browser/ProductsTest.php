<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use Tests\Browser\Support\Locators;

it( 'creates a simple product', function (): void {
    visit( route( 'artisanpack.ecommerce.admin.products.create' ) )
        ->type( Locators::label( 'Name' ), 'Browser Test Mug' )
        // The name syncs on a debounce; on Livewire 3 a click does not flush it.
        ->assertValue( Locators::label( 'Slug' ), 'browser-test-mug' )
        ->click( Locators::role( 'Pricing', 'tab' ) )
        ->type( Locators::label( 'Price' ), '12.50' )
        ->click( '[data-save]' )
        ->assertSee( 'Edit Browser Test Mug' )
        ->assertNoJavaScriptErrors();

    $product = Product::query()->where( 'name', 'Browser Test Mug' )->sole();

    expect( $product->type )->toBe( 'simple' )
        ->and( $product->prices()->sole()->price_amount )->toBe( 1250 );
} );

it( 'creates a variable product with variants', function (): void {
    $page = visit( route( 'artisanpack.ecommerce.admin.products.create' ) )
        ->type( Locators::label( 'Name' ), 'Browser Test Tee' )
        ->assertValue( Locators::label( 'Slug' ), 'browser-test-tee' )
        ->select( Locators::field( 'product-type' ), 'variable' )
        ->click( Locators::role( 'Variants', 'tab' ) )
        ->click( Locators::role( 'Add attribute' ) )
        ->type( Locators::field( 'attribute-0-label' ), 'Size' )
        ->click( Locators::role( 'Add value' ) )
        ->type( Locators::field( 'value-0-0-label' ), 'Small' )
        ->click( Locators::role( 'Add value' ) )
        ->type( Locators::field( 'value-0-1-label' ), 'Large' )
        ->click( Locators::role( 'Generate variants' ) )
        ->assertSee( '2 variants' );

    $page->click( '[data-save]' )
        ->assertSee( 'Edit Browser Test Tee' )
        ->assertNoJavaScriptErrors();

    $product = Product::query()->where( 'name', 'Browser Test Tee' )->sole();

    expect( $product->type )->toBe( 'variable' )
        ->and( $product->variants()->count() )->toBe( 2 );
} );

it( 'edits a product', function (): void {
    $product = Product::query()->where( 'type', 'simple' )->firstOrFail();

    visit( route( 'artisanpack.ecommerce.admin.products.edit', [ 'product' => $product->id ] ) )
        ->clear( Locators::label( 'Name' ) )
        ->type( Locators::label( 'Name' ), 'Renamed In The Browser' )
        // The name syncs on a debounce; on Livewire 3 a click does not flush it.
        ->wait( 0.6 )
        ->click( '[data-save]' )
        ->assertSee( 'Product saved.' )
        ->assertNoJavaScriptErrors();

    expect( $product->fresh()->name )->toBe( 'Renamed In The Browser' );
} );

it( 'selects only the current page\'s rows after paging, keeping earlier pages\' selection', function (): void {
    $selectedCount = '( () => { const root = document.querySelector( "[data-select-page]" ).closest( "[wire\\\\:id]" ); return Livewire.find( root.getAttribute( "wire:id" ) ).selected.length } )()';

    $page = visit( route( 'artisanpack.ecommerce.admin.products.index' ) )
        ->select( Locators::field( 'resource-table-per-page' ), '10' )
        ->assertScript( 'document.querySelectorAll( "[data-resource-table] tbody tr" ).length', 10 )
        ->check( '[data-select-page]' )
        ->assertScript( $selectedCount, 10 )
        ->click( 'button[wire\\:click^="gotoPage(2"], button[wire\\:click^="nextPage"]' )
        ->assertScript( 'document.querySelectorAll( "[data-resource-table] tbody tr" ).length', Product::query()->count() - 10 )
        ->assertScript( 'document.querySelector( "[data-select-page]" ).checked', false );

    $page->check( '[data-select-page]' )
        ->assertScript( $selectedCount, Product::query()->count() )
        ->assertNoJavaScriptErrors();
} );

it( 'reorders an upsell list from the keyboard', function (): void {
    $product = Product::query()->where( 'type', 'simple' )->firstOrFail();
    $others  = Product::query()->whereKeyNot( $product->id )->orderBy( 'id' )->limit( 2 )->get();

    app( ArtisanPackUI\Ecommerce\Services\ProductService::class )->syncProductRelations( $product, 'upsell', $others->pluck( 'id' )->all() );

    visit( route( 'artisanpack.ecommerce.admin.products.edit', [ 'product' => $product->id ] ) )
        ->click( Locators::role( 'Linked products', 'tab' ) )
        ->click( Locators::role( 'Move ' . $others[1]->name . ' up' ) )
        ->assertScript( 'document.getElementById( "ecommerce-admin-announcer" ).textContent', 'Moved ' . $others[1]->name . ' to position 1 of 2.' )
        ->click( '[data-save]' )
        ->assertSee( 'Product saved.' )
        ->assertNoJavaScriptErrors();

    expect( ArtisanPackUI\Ecommerce\Models\ProductRelation::query()->where( 'product_id', $product->id )->where( 'type', 'upsell' )->orderBy( 'position' )->pluck( 'related_product_id' )->map( static fn ( $id ): int => (int) $id )->all() )
        ->toBe( [ (int) $others[1]->id, (int) $others[0]->id ] );
} );
