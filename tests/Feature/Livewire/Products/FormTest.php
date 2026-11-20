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
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'product.update', 'product.delete', 'inventory.adjust' ] );
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

/**
 * The component's HTML with whitespace collapsed and Livewire's block
 * markers removed, so markup assertions don't depend on line breaks.
 */
function formMarkup( $component ): string
{
    return trim( (string) preg_replace( [ '/<!--\[if (BLOCK|ENDBLOCK)\]><!\[endif\]-->/', '/\s+/' ], [ '', ' ' ], $component->html() ) );
}

it( 'renders the design-system header: an outline back link, one solid Save and when it was saved', function (): void {
    // A frozen clock and a fixed save time, so the relative label can't tick over mid-test.
    $this->freezeTime();
    $product = existingProduct();
    $product->forceFill( [ 'updated_at' => now()->subHours( 3 ) ] )->saveQuietly();

    $component = Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->assertSee( 'Edit Blue Mug' )
        ->assertSee( 'Saved 3 hours ago' );

    expect( formMarkup( $component ) )
        ->toContain( 'data-back-link="data-back-link" data-variant="outline"' )
        ->and( substr_count( formMarkup( $component ), 'data-variant="solid"' ) )->toBe( 1 );

    Livewire::test( Form::class )->assertDontSee( 'Saved ' );
} );

it( 'puts every tab in one card as underline tabs named on the tablist', function (): void {
    $markup = formMarkup( Livewire::test( Form::class ) );

    expect( $markup )
        ->toContain( 'class="apui-card' )
        ->toContain( 'class="apui-tabs ec-product-form-tabs"' )
        ->toContain( 'data-variant="underline"' )
        ->toContain( 'aria-label="Product details" class="ec-product-form-tablist"' )
        ->not->toContain( 'data-part="count"' )
        ->not->toContain( 'rounded-box' )
        ->not->toContain( 'opacity-75' );
} );

it( 'names the tabs with errors in the alert and gives each a dot that says so', function (): void {
    $product = existingProduct();

    $component = Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'name', '' )
        ->set( 'prices.0.compare_at_amount', 100 )
        ->call( 'save' )
        ->assertSee( 'Fix the errors in: General, Pricing.' );

    $markup = formMarkup( $component );

    expect( $markup )
        ->toContain( 'data-error-tabs="general pricing"' )
        ->toMatch( '/data-value="general"[^>]*> General <span data-scope="tabs" data-part="count">\(has errors\)<\/span>/' )
        ->toMatch( '/data-value="pricing"[^>]*> Pricing <span data-scope="tabs" data-part="count">\(has errors\)<\/span>/' )
        ->and( substr_count( $markup, 'data-part="count"' ) )->toBe( 2 );
} );

it( 'links the publish date, slug and cost hints to their fields', function (): void {
    expect( formMarkup( Livewire::test( Form::class ) ) )
        ->toMatch( '/id="product-slug"[^>]*aria-describedby="product-slug-hint"/' )
        ->toContain( 'id="product-slug-hint"' )
        ->toMatch( '/id="product-published-at"[^>]*aria-describedby="product-published-at-hint"/' )
        ->toContain( 'id="product-published-at-hint"' )
        ->toContain( 'price-0-cost-hint' );
} );

it( 'groups each price row under its currency and suggests the conversion for empty ones', function (): void {
    $component = Livewire::test( Form::class )->assertSee( 'Scheduled prices' );

    expect( formMarkup( $component ) )
        ->toContain( 'role="group" aria-label="USD"' )
        ->toContain( 'role="group" aria-label="EUR"' )
        ->toContain( 'placeholder="From USD"' );

    $component->call( 'addScheduledPrice' );

    expect( formMarkup( $component ) )
        ->toContain( 'aria-label="Scheduled price 1"' )
        ->toContain( 'aria-label="Remove scheduled price 1"' );
} );

it( 'shows how many units a stock change adds, and asks for the reason', function (): void {
    $product = existingProduct();

    $component = Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->assertDontSeeHtml( 'data-stock-change' )
        ->set( 'quantity', 14 )
        ->assertSeeHtml( 'data-stock-change="4"' )
        ->assertSee( 'Saving adds 4 units.' )
        ->assertSee( 'The change is recorded in the activity log.' );

    expect( formMarkup( $component ) )->toMatch( '/id="product-stock-reason"[^>]* required/' );

    $component->set( 'quantity', 9 )
        ->assertSee( 'Saving removes 1 unit.' )
        ->set( 'quantity', 10 )
        ->assertDontSeeHtml( 'data-stock-change' );
} );

it( 'flags gallery images that have no alt text', function (): void {
    $product = existingProduct( [ 'images' => [
        [ 'image_url' => 'https://e.test/1.jpg', 'alt_text' => 'Front of the mug' ],
        [ 'image_url' => 'https://e.test/2.jpg' ],
    ] ] );

    $component = Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->assertSee( 'Missing alt text' )
        ->assertSee( 'No alt text yet.' );

    $markup = formMarkup( $component );

    expect( $markup )
        ->toContain( 'data-gallery-row="1" data-missing-alt' )
        ->not->toContain( 'data-gallery-row="0" data-missing-alt' )
        ->and( substr_count( $markup, 'data-alt-missing=' ) )->toBe( 1 );

    $component->set( 'gallery.1.alt_text', 'Back of the mug' )
        ->assertDontSeeHtml( 'data-missing-alt' )
        ->assertDontSee( 'Missing alt text' );
} );

it( 'puts every validated field on its tab', function ( string $field, string $tab ): void {
    expect( Form::tabFor( $field ) )->toBe( $tab );
} )->with( [
    'name'             => [ 'name', 'general' ],
    'published at'     => [ 'publishedAt', 'general' ],
    'scheduled start'  => [ 'prices.1.starts_at', 'pricing' ],
    'stock reason'     => [ 'stockReason', 'inventory' ],
    'dimension unit'   => [ 'dimUnit', 'shipping' ],
    'tax class'        => [ 'taxClassKey', 'tax' ],
    'tags'             => [ 'tagNames', 'organization' ],
    'linked product'   => [ 'relations.0.related_id', 'linked' ],
    'gallery alt text' => [ 'gallery.2.alt_text', 'media' ],
    'type panel'       => [ 'panelState.variants.0.sku', 'panel' ],
] );

it( 'sends a product service error to the field and tab it belongs to', function ( string $serviceField, string $formField, string $tab ): void {
    $mapped = ( new ReflectionMethod( Form::class, 'formField' ) )->invoke( new Form(), $serviceField );

    expect( $mapped )->toBe( $formField )
        ->and( Form::tabFor( $mapped ) )->toBe( $tab );
} )->with( [
    'short description' => [ 'short_description', 'shortDescription', 'general' ],
    'publish date'      => [ 'published_at', 'publishedAt', 'general' ],
    'barcode'           => [ 'barcode', 'barcode', 'inventory' ],
    'stock quantity'    => [ 'inventory.quantity', 'quantity', 'inventory' ],
    'backorders'        => [ 'inventory.allow_backorder', 'allowBackorder', 'inventory' ],
    'weight'            => [ 'weight', 'weight', 'shipping' ],
    'dimension unit'    => [ 'dim_unit', 'dimUnit', 'shipping' ],
    'taxable'           => [ 'is_taxable', 'isTaxable', 'tax' ],
    'featured media'    => [ 'featured_image_media_id', 'featuredMediaId', 'media' ],
    'gallery image'     => [ 'images.1.alt_text', 'gallery.1.alt_text', 'media' ],
    'unknown field'     => [ 'something_new', 'name', 'general' ],
] );

it( 'does not change stock without inventory.adjust, and shows the stock fields read-only', function (): void {
    $product = existingProduct();

    Gate::define( 'ecommerce.inventory.adjust', static fn (): bool => false );

    $component = Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->assertSeeHtml( 'data-stock-read-only' )
        ->set( 'quantity', 99 )
        ->set( 'allowBackorder', true )
        ->set( 'lowStockThreshold', 3 )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( app( ProductService::class )->inventoryItemFor( $product ) )
        ->quantity_on_hand->toBe( 10 )
        ->allow_backorder->toBeFalse()
        ->low_stock_threshold->toBeNull();

    expect( formMarkup( $component ) )->toMatch( '/<input[^>]*id="product-quantity"[^>]*readonly/' );
} );

it( 'gives a new product no opening stock without inventory.adjust', function (): void {
    Gate::define( 'ecommerce.inventory.adjust', static fn (): bool => false );

    Livewire::test( Form::class )
        ->set( 'name', 'Mug' )
        ->set( 'quantity', 25 )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( InventoryItem::query()->sum( 'quantity_on_hand' ) )->toBe( 0 );
} );

it( 'does not ask for a stock reason while inventory tracking is off', function (): void {
    $product = existingProduct();

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'quantity', 3 )
        ->set( 'trackInventory', false )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( app( ProductService::class )->inventoryItemFor( $product ) )
        ->quantity_on_hand->toBe( 10 )
        ->track_inventory->toBeFalse();
} );

it( 'clears a scheduled price row\'s amounts when its currency changes', function (): void {
    $component = Livewire::test( Form::class )->call( 'addScheduledPrice' );
    $index     = array_key_last( $component->get( 'prices' ) );

    $component->set( "prices.{$index}.price_amount", 1250 )
        ->set( "prices.{$index}.compare_at_amount", 1500 )
        ->set( "prices.{$index}.cost_amount", 400 )
        ->set( "prices.{$index}.currency", 'EUR' )
        ->assertSet( "prices.{$index}.price_amount", null )
        ->assertSet( "prices.{$index}.compare_at_amount", null )
        ->assertSet( "prices.{$index}.cost_amount", null )
        ->assertSeeHtml( 'wire:key="price-' );

    expect( formMarkup( $component ) )->toContain( '-amount-EUR"' );
} );

it( 'creates new tags only for a user who may create them, as on the Tags screen', function (): void {
    $product = existingProduct();
    app( ArtisanPackUI\Ecommerce\Services\ProductTagService::class )->findOrCreate( 'Summer' );

    Gate::define( 'ecommerce.product.create', static fn (): bool => false );

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'tagNames', [ 'summer' ] )
        ->call( 'save' )
        ->assertHasNoErrors();

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'tagNames', [ 'Summer', 'Winter' ] )
        ->call( 'save' )
        ->assertHasErrors( 'tagNames' );

    expect( ProductTag::query()->where( 'slug', 'winter' )->exists() )->toBeFalse()
        ->and( $product->refresh()->tags->pluck( 'slug' )->all() )->toBe( [ 'summer' ] );
} );

it( 'refuses media items the user may not view, but keeps ones already on the product', function (): void {
    ArtisanPackUI\EcommerceAdminLivewire\Support\DigitalDisks::fakeViewableMedia( [] );

    try {
        $product = existingProduct( [ 'featured_image_media_id' => 5 ] );

        Livewire::test( Form::class, [ 'product' => $product->id ] )
            ->call( 'save' )
            ->assertHasNoErrors();

        Livewire::test( Form::class, [ 'product' => $product->id ] )
            ->set( 'featuredMediaId', 6 )
            ->set( 'gallery', [ [ 'uid' => 'x', 'id' => null, 'media_id' => 7, 'image_url' => '', 'alt_text' => 'Side' ] ] )
            ->call( 'save' )
            ->assertHasErrors( [ 'featuredMediaId', 'gallery.0.media_id' ] );

        expect( $product->refresh()->featured_image_media_id )->toBe( 5 );
    } finally {
        ArtisanPackUI\EcommerceAdminLivewire\Support\DigitalDisks::fakeViewableMedia( null );
    }
} );

it( 'uses h2 section headings under the h1 and square icon-only row buttons', function (): void {
    $product = existingProduct();
    $markup  = formMarkup(
        Livewire::test( Form::class, [ 'product' => $product->id ] )
            ->set( 'gallery', [ [ 'uid' => 'x', 'id' => null, 'media_id' => null, 'image_url' => 'https://example.com/a.jpg', 'alt_text' => 'Side' ] ] ),
    );

    expect( $markup )->not->toMatch( '/<h3[^>]*class="ec-subheading"/' )
        ->toContain( '<h2 id="product-scheduled-heading"' )
        ->toContain( '<h2 id="product-gallery-heading"' )
        ->toMatch( '/<button(?=[^>]*data-icon-only)(?=[^>]*aria-label="Remove image 1")[^>]*>/' )
        ->toMatch( '/<button(?=[^>]*data-icon-only)(?=[^>]*aria-label="Move image 1 up")[^>]*>/' );
} );
