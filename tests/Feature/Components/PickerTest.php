<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\EcommerceAdminLivewire\Pickers\VariantPickerSource;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\PickerSourceRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\HelperForm;

beforeEach( function (): void {
    view()->share( 'errors', new ViewErrorBag() );
} );

it( 'renders the pickers on x-artisanpack-choices with server search', function (): void {
    grantAbilities( [ 'product.viewAny', 'customer.viewAny' ] );
    Product::factory()->create( [ 'name' => 'Blue Mug', 'sku' => 'MUG-BLUE' ] );

    Livewire::actingAs( makeUser() )
        ->test( HelperForm::class )
        ->assertSeeHtml( '.searchPicker(value, &#039;product&#039;, &#039;productIds&#039;)' )
        ->assertSeeHtml( '.searchPicker(value, &#039;customer&#039;, &#039;customerId&#039;)' )
        ->assertSee( 'Blue Mug' );
} );

it( 'searches products by name and SKU', function (): void {
    grantAbilities( [ 'product.viewAny' ] );
    $mug = Product::factory()->create( [ 'name' => 'Blue Mug', 'sku' => 'MUG-BLUE' ] );
    Product::factory()->create( [ 'name' => 'Red Shirt', 'sku' => 'SHIRT-RED' ] );

    $component = Livewire::actingAs( makeUser() )->test( HelperForm::class );

    $component->call( 'searchPicker', 'mug', 'product', 'productIds' );
    expect( collect( $component->get( 'pickerOptions' )['product:productIds'] )->pluck( 'name' )->all() )->toBe( [ 'Blue Mug' ] );

    $component->call( 'searchPicker', 'shirt-red', 'product', 'productIds' );
    expect( collect( $component->get( 'pickerOptions' )['product:productIds'] )->pluck( 'name' )->all() )->toBe( [ 'Red Shirt' ] )
        ->and( $component->get( 'pickerOptions' )['product:productIds'][0]['description'] )->toBe( 'SKU: SHIRT-RED' )
        ->and( $mug->exists )->toBeTrue();
} );

it( 'keeps selected options alongside the results', function (): void {
    grantAbilities( [ 'product.viewAny' ] );
    $mug = Product::factory()->create( [ 'name' => 'Blue Mug' ] );
    Product::factory()->create( [ 'name' => 'Red Shirt' ] );

    $component = Livewire::actingAs( makeUser() )->test( HelperForm::class )
        ->set( 'productIds', [ $mug->getKey() ] )
        ->call( 'searchPicker', 'shirt', 'product', 'productIds' );

    expect( collect( $component->get( 'pickerOptions' )['product:productIds'] )->pluck( 'name' )->all() )->toBe( [ 'Blue Mug', 'Red Shirt' ] );
} );

it( 'treats LIKE wildcards literally', function (): void {
    grantAbilities( [ 'product.viewAny' ] );
    Product::factory()->create( [ 'name' => '100% Cotton Tee' ] );
    Product::factory()->create( [ 'name' => 'Plain Tee' ] );

    $component = Livewire::actingAs( makeUser() )->test( HelperForm::class )
        ->call( 'searchPicker', '%', 'product', 'productIds' );

    expect( collect( $component->get( 'pickerOptions' )['product:productIds'] )->pluck( 'name' )->all() )->toBe( [ '100% Cotton Tee' ] );
} );

it( 'searches customers by name and email', function (): void {
    grantAbilities( [ 'customer.viewAny' ] );
    Customer::factory()->create( [ 'first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@example.test' ] );
    Customer::factory()->create( [ 'first_name' => 'Alan', 'last_name' => 'Turing', 'email' => 'alan@example.test' ] );

    $component = Livewire::actingAs( makeUser() )->test( HelperForm::class )
        ->call( 'searchPicker', 'grace@', 'customer', 'customerId' );

    expect( $component->get( 'pickerOptions' )['customer:customerId'] )->toBe( [
        [ 'id' => Customer::query()->where( 'email', 'grace@example.test' )->value( 'id' ), 'name' => 'Grace Hopper', 'description' => 'grace@example.test' ],
    ] );
} );

it( 'labels variants with their product', function (): void {
    $product = Product::factory()->create( [ 'name' => 'T-shirt' ] );
    ProductVariant::factory()->create( [ 'product_id' => $product->getKey(), 'name' => 'Large', 'sku' => 'TS-L' ] );

    expect( ( new VariantPickerSource() )->search( 'large', 10 )[0] )->toMatchArray( [ 'name' => 'T-shirt — Large', 'description' => 'SKU: TS-L' ] )
        ->and( ( new VariantPickerSource() )->search( 't-shirt', 10 ) )->toHaveCount( 1 );
} );

it( 'refuses a search without the source ability', function (): void {
    grantAbilities( [ 'customer.viewAny' ] );

    Livewire::actingAs( makeUser() )
        ->test( HelperForm::class )
        ->call( 'searchPicker', 'mug', 'product', 'productIds' )
        ->assertForbidden();
} );

it( 'refuses an unknown picker type', function (): void {
    grantAbilities( [ 'product.viewAny' ] );

    Livewire::actingAs( makeUser() )
        ->test( HelperForm::class )
        ->call( 'searchPicker', 'x', 'secret-type', 'productIds' )
        ->assertForbidden();
} );

it( 'returns no options to a user who may not search the source', function (): void {
    grantAbilities( [ 'customer.viewAny' ] );
    Product::factory()->create( [ 'name' => 'Blue Mug' ] );

    Livewire::actingAs( makeUser() )
        ->test( HelperForm::class )
        ->assertDontSee( 'Blue Mug' )
        ->call( 'optionsForPicker', 'product', 'productIds' )
        ->assertReturned( [] );
} );

it( 'shows a notice for category and tag pickers until the engine stores them', function (): void {
    expect( app( PickerSourceRegistry::class )->has( 'category' ) )->toBeFalse();

    expect( Blade::render( '<x-artisanpack-ec-category-picker model="categoryIds" label="Categories" />' ) )
        ->toContain( 'Picking categories becomes available once the store engine manages categories.' );

    expect( Blade::render( '<x-artisanpack-ec-tag-picker model="tagIds" label="Tags" />' ) )
        ->toContain( 'Picking tags becomes available once the store engine manages tags.' );
} );

it( 'rejects a picker model that is not a property path', function (): void {
    new ArtisanPackUI\EcommerceAdminLivewire\View\Components\ProductPicker( model: "ids'); alert(1); ('" );
} )->throws( InvalidArgumentException::class, 'must be a property path' );
