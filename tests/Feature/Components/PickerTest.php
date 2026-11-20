<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\EcommerceAdminLivewire\Pickers\VariantPickerSource;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\PickerSourceRegistry;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\HelperForm;

beforeEach( function (): void {
    view()->share( 'errors', new ViewErrorBag() );
} );

it( 'renders the pickers on the design-system Combobox with server search', function (): void {
    grantAbilities( [ 'product.viewAny', 'customer.viewAny' ] );
    Product::factory()->create( [ 'name' => 'Blue Mug', 'sku' => 'MUG-BLUE' ] );

    Livewire::actingAs( makeUser() )
        ->test( HelperForm::class )
        ->assertSeeHtml( 'searchPicker&#039;, $event.detail.search, &#039;product&#039;, &#039;productIds&#039;)' )
        ->assertSeeHtml( 'searchPicker&#039;, $event.detail.search, &#039;customer&#039;, &#039;customerId&#039;)' )
        ->assertSee( 'Blue Mug' );
} );

it( 'shows multi picks as chips and a single pick as a clearable value', function (): void {
    grantAbilities( [ 'product.viewAny', 'customer.viewAny' ] );

    $html = Livewire::actingAs( makeUser() )->test( HelperForm::class )->html();

    preg_match( '/<div[^>]*data-picker="product"[^>]*>/', $html, $product );
    preg_match( '/<div[^>]*data-picker="customer"[^>]*>/', $html, $customer );

    expect( $product[0] )->toContain( 'apui-combobox' )
        ->toContain( 'data-multiple' )
        ->toContain( 'x-on:search-change.debounce.300ms' )
        ->and( $customer[0] )->not->toContain( 'data-multiple' )
        ->and( $html )->toContain( 'Search products or SKUs' )
        ->toContain( 'Search customers by name or email' )
        // Laravel 12's Js::from() writes "…" as \\u2026 inside JSON.parse(); Laravel 13 leaves it as is.
        ->toMatch( '/Searching(?:…|\\\\+u2026)/u' )
        ->toContain( 'No results found.' )
        ->not->toContain( 'x-artisanpack-choices' );
} );

it( 'lists results with their sub-label', function (): void {
    grantAbilities( [ 'product.viewAny' ] );
    Product::factory()->create( [ 'name' => 'Blue Mug', 'sku' => 'MUG-BLUE' ] );

    Livewire::actingAs( makeUser() )
        ->test( HelperForm::class )
        ->assertSee( 'Blue Mug' )
        ->assertSee( 'SKU: MUG-BLUE' );
} );

it( 'becomes an info alert when no source is registered', function (): void {
    app()->instance( PickerSourceRegistry::class, new PickerSourceRegistry() );

    expect( Illuminate\Support\Facades\Blade::render( '<x-artisanpack-ec-variant-picker model="variantIds" label="Variants" />' ) )
        ->toContain( 'data-picker-unavailable="variant"' )
        ->toContain( 'This picker is not available yet.' )
        ->toContain( 'role="status"' )
        ->not->toContain( 'apui-combobox' );
} );

it( 'leaves the unavailable alert\'s role to the design system, so it renders once', function (): void {
    app()->instance( PickerSourceRegistry::class, new PickerSourceRegistry() );

    $html = Illuminate\Support\Facades\Blade::render( '<x-artisanpack-ec-variant-picker model="variantIds" label="Variants" />' );

    expect( substr_count( $html, 'role=' ) )->toBe( 1 );
} );

it( 'takes a placeholder override', function (): void {
    grantAbilities( [ 'product.viewAny' ] );

    expect( Illuminate\Support\Facades\Blade::render( '<x-artisanpack-ec-product-picker model="productIds" label="Products" placeholder="Find a gift" />' ) )
        ->toContain( 'Find a gift' )
        ->not->toContain( 'Search products or SKUs' );
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

it( 'labels at most PickerSource::MAX_IDS selected ids, however many the client sends', function (): void {
    grantAbilities( [ 'product.viewAny' ] );
    $mug = Product::factory()->create( [ 'name' => 'Blue Mug' ] );
    $ids = [ $mug->getKey(), ...range( 1000000, 1001000 ) ];

    Illuminate\Support\Facades\DB::enableQueryLog();

    $component = Livewire::actingAs( makeUser() )->test( HelperForm::class )
        ->set( 'productIds', $ids )
        ->call( 'searchPicker', 'zzz-no-match', 'product', 'productIds' );

    $findQuery = collect( Illuminate\Support\Facades\DB::getQueryLog() )->first( static fn ( array $query ): bool => str_contains( $query['query'], ' in (' ) && str_contains( $query['query'], ( new Product() )->getTable() ) );

    expect( collect( $component->get( 'pickerOptions' )['product:productIds'] )->pluck( 'name' )->all() )->toBe( [ 'Blue Mug' ] )
        ->and( $findQuery )->not->toBeNull()
        ->and( count( $findQuery['bindings'] ) )->toBeLessThanOrEqual( ArtisanPackUI\EcommerceAdminLivewire\Pickers\PickerSource::MAX_IDS + 1 );
} );

it( 'caps the ids an Eloquent picker source looks up', function (): void {
    grantAbilities( [ 'product.viewAny' ] );
    $products = Product::factory()->count( 3 )->create();
    $ids      = [ ...range( 1000000, 1000000 + ArtisanPackUI\EcommerceAdminLivewire\Pickers\PickerSource::MAX_IDS - 1 ), ...$products->modelKeys() ];

    expect( app( PickerSourceRegistry::class )->get( 'product' )->find( $ids ) )->toBe( [] )
        ->and( app( PickerSourceRegistry::class )->get( 'product' )->find( $products->modelKeys() ) )->toHaveCount( 3 );
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

it( 'searches categories and tags now that the engine stores them', function (): void {
    grantAbilities( [ 'product.viewAny' ] );

    $parent = ProductCategory::factory()->create( [ 'name' => 'Prints' ] );
    $child  = ProductCategory::factory()->create( [ 'name' => 'Posters', 'parent_id' => $parent->id ] );
    $tag    = ProductTag::factory()->create( [ 'name' => 'Sale' ] );

    expect( app( PickerSourceRegistry::class )->get( 'category' )->search( 'post', 10 ) )
        ->toBe( [ [ 'id' => $child->id, 'name' => 'Posters', 'description' => 'In Prints' ] ] )
        ->and( app( PickerSourceRegistry::class )->get( 'tag' )->find( [ $tag->id ] ) )
        ->toBe( [ [ 'id' => $tag->id, 'name' => 'Sale', 'description' => null ] ] )
        ->and( ( new ArtisanPackUI\EcommerceAdminLivewire\View\Components\CategoryPicker( model: 'categoryIds' ) )->available() )->toBeTrue()
        ->and( ( new ArtisanPackUI\EcommerceAdminLivewire\View\Components\TagPicker( model: 'tagIds' ) )->available() )->toBeTrue();
} );

it( 'rejects a picker model that is not a property path', function (): void {
    new ArtisanPackUI\EcommerceAdminLivewire\View\Components\ProductPicker( model: "ids'); alert(1); ('" );
} )->throws( InvalidArgumentException::class, 'must be a property path' );
