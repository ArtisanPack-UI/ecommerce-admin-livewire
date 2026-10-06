<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductImage;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Models\TaxClass;
use ArtisanPackUI\Ecommerce\Services\ProductService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Form;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Fixtures\User;

beforeEach( function (): void {
    config()->set( 'auth.providers.users.model', User::class );
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    config()->set( 'artisanpack.ecommerce.currency.rates', [ 'USD' => [ 'EUR' => 92_500_000 ] ] );
    config()->set( 'artisanpack.ecommerce.currency.enabled', [ 'EUR' ] );
    config()->set( 'artisanpack.ecommerce.features.scout', false );
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'product.update', 'product.delete' ] );
    ProductMedia::fake( false );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    ProductMedia::fake( null );
} );

/**
 * A product created through the engine with a USD price and stock.
 */
function existingProduct( array $data = [] ): Product
{
    return app( ProductService::class )->create( $data + [
        'type'      => 'simple',
        'name'      => 'Blue Mug',
        'sku'       => 'MUG-1',
        'status'    => 'active',
        'prices'    => [ [ 'currency' => 'USD', 'price_amount' => 1250 ] ],
        'inventory' => [ 'quantity_on_hand' => 10 ],
    ] );
}

it( 'renders every tab for a new product with a row per enabled currency', function (): void {
    Livewire::test( Form::class )
        ->assertOk()
        ->assertSee( 'New product' )
        ->assertSee( [ 'General', 'Pricing', 'Inventory', 'Shipping', 'Tax', 'Organization', 'Media' ] )
        ->assertSet( 'prices.0.currency', 'USD' )
        ->assertSet( 'prices.1.currency', 'EUR' )
        ->assertDontSee( 'Activity' );
} );

it( 'is denied without product.create, and without product.view when editing', function (): void {
    $product = existingProduct();

    Gate::define( 'ecommerce.product.create', static fn (): bool => false );
    Livewire::test( Form::class )->assertForbidden();

    Gate::define( 'ecommerce.product.view', static fn (): bool => false );
    Livewire::test( Form::class, [ 'product' => $product->id ] )->assertForbidden();
} );

it( 'fills the slug from the name until the slug is edited', function (): void {
    Livewire::test( Form::class )
        ->set( 'name', 'Linen Shirt' )
        ->assertSet( 'slug', 'linen-shirt' )
        ->set( 'slug', 'shirt' )
        ->set( 'name', 'Linen Shirt Blue' )
        ->assertSet( 'slug', 'shirt' )
        ->set( 'slug', '' )
        ->assertSet( 'slug', 'linen-shirt-blue' );
} );

it( 'creates a simple product with prices in two currencies, a sale, stock, taxonomy, and images', function (): void {
    $category = ProductCategory::factory()->create( [ 'name' => 'Shirts' ] );
    TaxClass::query()->firstOrCreate( [ 'key' => 'reduced' ], [ 'label' => 'Reduced' ] );

    $component = Livewire::test( Form::class )
        ->set( 'name', 'Linen Shirt' )
        ->set( 'status', 'active' )
        ->set( 'description', '<p>Soft linen.</p><script>alert(1)</script>' )
        ->set( 'prices.0.price_amount', 4500 )
        ->set( 'prices.0.compare_at_amount', 5000 )
        ->set( 'prices.0.cost_amount', 1800 )
        ->set( 'prices.1.price_amount', 4200 )
        ->call( 'addScheduledPrice' )
        ->set( 'prices.2.price_amount', 3900 )
        ->set( 'prices.2.starts_at', '2026-11-01T00:00' )
        ->set( 'prices.2.ends_at', '2026-11-08T00:00' )
        ->set( 'sku', 'SHIRT-1' )
        ->set( 'quantity', 25 )
        ->set( 'lowStockThreshold', 5 )
        ->set( 'weight', '0.4' )
        ->set( 'weightUnit', 'kg' )
        ->set( 'taxClassKey', 'reduced' )
        ->set( 'categoryIds', [ $category->id ] )
        ->set( 'tagNames', [ 'Summer', 'Linen' ] )
        ->set( 'featuredImageUrl', 'https://example.test/shirt.jpg' )
        ->call( 'addGalleryUrl' )
        ->set( 'gallery.0.image_url', 'https://example.test/back.jpg' )
        ->set( 'gallery.0.alt_text', 'Back of the shirt' )
        ->call( 'save' )
        ->assertHasNoErrors();

    $product = Product::query()->where( 'slug', 'linen-shirt' )->sole();

    $component->assertRedirect( route( 'artisanpack.ecommerce.admin.products.edit', [ 'product' => $product->id ] ) );

    expect( $product->type )->toBe( 'simple' )
        ->and( $product->status )->toBe( 'active' )
        ->and( $product->description )->toContain( 'Soft linen.' )->not->toContain( '<script>' )
        ->and( $product->sku )->toBe( 'SHIRT-1' )
        ->and( $product->weight )->toBe( 0.4 )
        ->and( $product->tax_class_key )->toBe( 'reduced' )
        ->and( $product->meta['featured_image_url'] )->toBe( 'https://example.test/shirt.jpg' )
        ->and( $product->prices()->count() )->toBe( 3 )
        ->and( $product->prices()->where( 'currency', 'EUR' )->value( 'price_amount' ) )->toBe( 4200 )
        ->and( $product->prices()->whereNotNull( 'starts_at' )->value( 'price_amount' ) )->toBe( 3900 )
        ->and( $product->categories->pluck( 'name' )->all() )->toBe( [ 'Shirts' ] )
        ->and( $product->tags->pluck( 'name' )->sort()->values()->all() )->toBe( [ 'Linen', 'Summer' ] )
        ->and( ProductImage::query()->sole()->alt_text )->toBe( 'Back of the shirt' )
        ->and( app( ProductService::class )->inventoryItemFor( $product )->quantity_on_hand )->toBe( 25 );
} );

it( 'loads an existing product into the form', function (): void {
    $product = existingProduct();
    $product->tags()->attach( ProductTag::factory()->create( [ 'name' => 'Sale' ] )->id );

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->assertSee( 'Edit Blue Mug' )
        ->assertSet( 'name', 'Blue Mug' )
        ->assertSet( 'slug', 'blue-mug' )
        ->assertSet( 'prices.0.price_amount', 1250 )
        ->assertSet( 'prices.1.price_amount', null )
        ->assertSet( 'quantity', 10 )
        ->assertSet( 'tagNames', [ 'Sale' ] )
        ->assertSee( 'Activity' );
} );

it( 'adjusts stock on an existing product only with a reason', function (): void {
    $product = existingProduct();

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'quantity', 7 )
        ->assertSee( 'Reason for the stock change' )
        ->call( 'save' )
        ->assertHasErrors( 'stockReason' )
        ->assertSet( 'tab', 'inventory' )
        ->set( 'stockReason', 'Three broke in the stockroom' )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'quantity', 7 )
        ->assertDispatched( Form::SAVED_EVENT );

    expect( app( ProductService::class )->inventoryItemFor( $product )->quantity_on_hand )->toBe( 7 );
} );

it( 'validates every tab and switches to the first one with an error', function ( array $set, string $field, string $tab ): void {
    $component = Livewire::test( Form::class )->set( 'name', 'Shirt' );

    foreach ( $set as $property => $value ) {
        $component->set( $property, $value );
    }

    $component->call( 'save' )
        ->assertHasErrors( $field )
        ->assertSet( 'tab', $tab )
        ->assertDispatched( Form::INVALID_EVENT );

    expect( Product::query()->count() )->toBe( 0 );
} )->with( [
    'missing name'        => [ [ 'name' => '' ], 'name', 'general' ],
    'bad slug'            => [ [ 'slug' => 'Not A Slug!' ], 'slug', 'general' ],
    'unknown type'        => [ [ 'type' => 'nope' ], 'type', 'general' ],
    'negative price'      => [ [ 'prices.0.price_amount' => -5 ], 'prices.0.price_amount', 'pricing' ],
    'sale without start'  => [ [ 'prices' => [ [ 'currency' => 'USD', 'price_amount' => 100, 'compare_at_amount' => null, 'cost_amount' => null, 'starts_at' => '', 'ends_at' => '', 'scheduled' => true ] ] ], 'prices.0.starts_at', 'pricing' ],
    'negative threshold'  => [ [ 'lowStockThreshold' => -1 ], 'lowStockThreshold', 'inventory' ],
    'weight without unit' => [ [ 'weight' => 1 ], 'weightUnit', 'shipping' ],
    'unknown tax class'   => [ [ 'taxClassKey' => 'luxury' ], 'taxClassKey', 'tax' ],
    'unknown category'    => [ [ 'categoryIds' => [ 999 ] ], 'categoryIds.0', 'organization' ],
    'bad image URL'       => [ [ 'featuredImageUrl' => 'javascript:alert(1)' ], 'featuredImageUrl', 'media' ],
] );

it( 'requires the compare-at price to be higher than the price', function ( ?int $compareAt, bool $valid ): void {
    $component = Livewire::test( Form::class )
        ->set( 'name', 'Shirt' )
        ->set( 'prices.0.price_amount', 1_000 )
        ->set( 'prices.0.compare_at_amount', $compareAt )
        ->call( 'save' );

    if ( $valid ) {
        $component->assertHasNoErrors();

        expect( Product::query()->count() )->toBe( 1 );

        return;
    }

    $component->assertHasErrors( 'prices.0.compare_at_amount' )
        ->assertSee( 'The compare-at price must be higher than the price.' )
        ->assertSet( 'tab', 'pricing' );

    expect( Product::query()->count() )->toBe( 0 );
} )->with( [
    'lower'  => [ 900, false ],
    'equal'  => [ 1_000, false ],
    'higher' => [ 1_200, true ],
    'empty'  => [ null, true ],
] );

it( 'puts engine refusals on the matching field', function (): void {
    existingProduct();

    Livewire::test( Form::class )
        ->set( 'name', 'Another Mug' )
        ->set( 'sku', 'MUG-1' )
        ->call( 'save' )
        ->assertHasErrors( 'sku' )
        ->assertSet( 'tab', 'inventory' );

    expect( Product::query()->count() )->toBe( 1 );
} );

it( 'refuses to save without product.update', function (): void {
    $product = existingProduct();
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->assertSet( 'readOnly', true )
        ->assertDontSeeHtml( 'data-save' )
        ->set( 'name', 'Hacked' )
        ->call( 'save' )
        ->assertForbidden();

    expect( $product->refresh()->name )->toBe( 'Blue Mug' );
} );

it( 'renders a missing-type product read-only with the warning', function (): void {
    $product = Product::factory()->create( [ 'type' => 'subscription', 'name' => 'Monthly Box' ] );

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->assertSet( 'readOnly', true )
        ->assertSee( 'This product is read-only' )
        ->assertSeeHtml( '<fieldset disabled' )
        ->assertSee( 'has no settings panel' )
        ->call( 'save' )
        ->assertForbidden();
} );

it( 'replaces gallery images and keeps their order', function (): void {
    $product = existingProduct( [ 'images' => [ [ 'image_url' => 'https://e.test/1.jpg' ], [ 'image_url' => 'https://e.test/2.jpg' ] ] ] );

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->call( 'moveGalleryImage', 0, 1 )
        ->call( 'addGalleryUrl' )
        ->set( 'gallery.2.image_url', 'https://e.test/3.jpg' )
        ->call( 'removeGalleryImage', 1 )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $product->images()->pluck( 'image_url' )->all() )->toBe( [ 'https://e.test/2.jpg', 'https://e.test/3.jpg' ] );
} );

it( 'takes images from the media library when it is installed', function (): void {
    ProductMedia::fake( true, Tests\Fixtures\Livewire\FakeMediaModal::class );
    $product = existingProduct();

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->call( 'mediaSelected', [ [ 'id' => 41 ] ], Form::MEDIA_FEATURED )
        ->call( 'mediaSelected', [ [ 'id' => 42, 'alt_text' => 'Side' ], [ 'id' => 43 ] ], Form::MEDIA_GALLERY )
        ->call( 'mediaSelected', [ [ 'id' => 99 ] ], 'someone-else' )
        ->assertSet( 'featuredMediaId', 41 )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $product->refresh()->featured_image_media_id )->toBe( 41 )
        ->and( $product->images()->pluck( 'media_id' )->all() )->toBe( [ 42, 43 ] )
        ->and( $product->images()->first()->alt_text )->toBe( 'Side' );
} );

it( 'serves the create and edit pages', function (): void {
    $product = existingProduct();

    $this->get( route( 'artisanpack.ecommerce.admin.products.create' ) )->assertOk()->assertSeeLivewire( Form::class );
    $this->get( route( 'artisanpack.ecommerce.admin.products.edit', [ 'product' => $product->id ] ) )->assertOk()->assertSeeLivewire( Form::class );
} );

it( 'gives types without stock (digital) no stock row', function (): void {
    Livewire::test( Form::class )
        ->set( 'name', 'Ebook' )
        ->set( 'type', 'digital' )
        ->set( 'panelState.files', [] )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( InventoryItem::query()->count() )->toBe( 0 );
} );

it( 'reports a missing stock reason together with the other errors', function (): void {
    $product = existingProduct();

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'name', '' )
        ->set( 'quantity', 3 )
        ->call( 'save' )
        ->assertHasErrors( [ 'name', 'stockReason' ] )
        ->assertSee( 'Saving removes 7 units.' );
} );

it( 'keeps end-only and scheduled prices through an edit', function (): void {
    $product = existingProduct( [ 'prices' => [
        [ 'currency' => 'USD', 'price_amount' => 1250 ],
        [ 'currency' => 'USD', 'price_amount' => 999, 'ends_at' => '2030-01-01 00:00:00' ],
    ] ] );

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'name', 'Renamed Mug' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $product->prices()->count() )->toBe( 2 )
        ->and( $product->prices()->whereNotNull( 'ends_at' )->value( 'price_amount' ) )->toBe( 999 );
} );

it( 'puts engine price errors on the row they came from', function (): void {
    Livewire::test( Form::class )
        ->set( 'name', 'Shirt' )
        ->set( 'prices.0.price_amount', 100 )
        ->call( 'addScheduledPrice' )
        ->set( 'prices.2.price_amount', 90 )
        ->set( 'prices.2.ends_at', '2030-01-01T00:00' )
        ->call( 'addScheduledPrice' )
        ->set( 'prices.3.price_amount', 80 )
        ->set( 'prices.3.ends_at', '2030-01-01T00:00' )
        ->call( 'save' )
        ->assertHasErrors( 'prices.3.currency' )
        ->assertHasNoErrors( 'prices.1.currency' );
} );

it( 'ignores another product\'s gallery ids', function (): void {
    $other   = existingProduct( [ 'name' => 'Other', 'sku' => 'OTHER', 'images' => [ [ 'image_url' => 'https://e.test/other.jpg' ] ] ] );
    $product = existingProduct();
    $foreign = $other->images()->first();

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'gallery', [ [ 'id' => $foreign->id, 'media_id' => null, 'image_url' => 'https://e.test/mine.jpg', 'alt_text' => '' ] ] )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $foreign->refresh()->image_url )->toBe( 'https://e.test/other.jpg' )
        ->and( $product->images()->pluck( 'image_url' )->all() )->toBe( [ 'https://e.test/mine.jpg' ] );
} );

it( 'translates its strings', function (): void {
    app( 'translator' )->addLines( [ '*.New product' => 'Nuevo producto', '*.Pricing' => 'Precios' ], 'es', '*' );
    app()->setLocale( 'es' );

    Livewire::test( Form::class )->assertSee( 'Nuevo producto' )->assertSee( 'Precios' );
} );
