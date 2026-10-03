<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use Tests\Browser\Support\Locators;

it( 'creates a simple product', function (): void {
    visit( route( 'artisanpack.ecommerce.admin.products.create' ) )
        ->type( Locators::label( 'Name' ), 'Browser Test Mug' )
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
        ->click( '[data-save]' )
        ->assertSee( 'Product saved.' )
        ->assertNoJavaScriptErrors();

    expect( $product->fresh()->name )->toBe( 'Renamed In The Browser' );
} );
