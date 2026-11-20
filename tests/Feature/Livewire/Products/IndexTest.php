<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Index;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    config()->set( 'artisanpack.ecommerce.features.scout', false );
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'product.update', 'product.delete' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerce.product.listQuery' );
} );

/**
 * A price row for a product or variant.
 */
function priceFor( Model $priceable, int $amount, string $currency = 'USD' ): ProductPrice
{
    return ProductPrice::factory()->forPriceable( $priceable )->create( [ 'currency' => $currency, 'price_amount' => $amount ] );
}

/**
 * A tracked stock row for a product or variant.
 */
function stockFor( Model $stockable, int $onHand, ?int $threshold = null, int $reserved = 0 ): InventoryItem
{
    return InventoryItem::query()->create( [
        'stockable_type'      => $stockable->getMorphClass(),
        'stockable_id'        => $stockable->getKey(),
        'track_inventory'     => true,
        'quantity_on_hand'    => $onHand,
        'quantity_reserved'   => $reserved,
        'low_stock_threshold' => $threshold,
    ] );
}

/**
 * The CSV a Livewire download effect carries, as rows.
 *
 * @return array<int, array<int, string>>
 */
function productsCsv( $component ): array
{
    $content = base64_decode( (string) ( $component->effects['download']['content'] ?? '' ) );
    $content = preg_replace( '/^\xEF\xBB\xBF/', '', $content );

    return array_map( 'str_getcsv', array_values( array_filter( explode( "\n", trim( $content ) ) ) ) );
}

it( 'renders products with price, stock, categories, and type', function (): void {
    $mug      = Product::factory()->create( [ 'name' => 'Blue Mug', 'sku' => 'MUG-1', 'status' => 'active' ] );
    $category = ProductCategory::factory()->create( [ 'name' => 'Kitchen' ] );
    $mug->categories()->attach( $category->id );
    priceFor( $mug, 1250 );
    priceFor( $mug, 1100, 'EUR' );
    stockFor( $mug, 7 );

    $html = Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeHtml( '<caption class="apui-sr-only">Products</caption>' )
        ->assertSee( 'Price (USD)' )
        ->assertSee( 'Blue Mug' )
        ->assertSee( 'MUG-1' )
        ->assertSee( 'Simple product' )
        ->assertSee( 'Active' )
        ->assertSee( '$12.50' )
        ->assertDontSee( '€11.00' )
        ->html();

    preg_match( '/<tbody>(.*?)<\/tbody>/s', $html, $body );

    expect( $body[1] )->not->toContain( 'Kitchen' );

    $rows = productsCsv( Livewire::test( Index::class )->set( 'selected', [ (string) $mug->id ] )->call( 'runBulkAction', 'export' ) );

    expect( $rows[0] )->toContain( 'Categories' )->and( $rows[1] )->toContain( 'Kitchen' );
} );

it( 'shows a price range and summed stock for products with variants', function (): void {
    $tee = Product::factory()->variable()->create( [ 'name' => 'Tee' ] );

    foreach ( [ [ 1500, 3 ], [ 1800, 4 ], [ 2200, 0 ] ] as [ $price, $stock ] ) {
        $variant = ProductVariant::factory()->create( [ 'product_id' => $tee->id ] );
        priceFor( $variant, $price );
        stockFor( $variant, $stock );
    }

    Livewire::test( Index::class )
        ->assertSee( '3 variants' )
        ->assertSeeHtml( 'data-price-range' )
        ->assertSee( '$15.00' )
        ->assertSee( '$22.00' )
        ->assertSeeInOrder( [ 'Tee', '7' ] );
} );

it( 'flags products whose type is missing', function (): void {
    Product::factory()->create( [ 'name' => 'Box', 'type' => 'subscription' ] );

    Livewire::test( Index::class )
        ->assertSee( 'Missing type' )
        ->assertSeeHtml( 'data-missing-type' )
        ->assertSee( 'subscription (missing)' );
} );

it( 'is denied without product.viewAny', function (): void {
    Gate::define( 'ecommerce.product.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'searches by name, SKU, and variant SKU', function (): void {
    $mug = Product::factory()->create( [ 'name' => 'Blue Mug', 'sku' => 'MUG-1' ] );
    $tee = Product::factory()->create( [ 'name' => 'Tee', 'sku' => null ] );
    ProductVariant::factory()->create( [ 'product_id' => $tee->id, 'sku' => 'TEE-XL' ] );

    Livewire::test( Index::class )
        ->set( 'search', 'blue' )->assertSee( 'Blue Mug' )->assertDontSee( 'Tee' )
        ->set( 'search', 'mug-1' )->assertSee( 'Blue Mug' )
        ->set( 'search', 'tee-xl' )->assertSee( 'Tee' )->assertDontSee( 'Blue Mug' );
} );

it( 'filters by status, type, category, and tag', function (): void {
    $draft    = Product::factory()->draft()->create( [ 'name' => 'Draft Print' ] );
    $archived = Product::factory()->create( [ 'name' => 'Old Print', 'status' => 'archived' ] );
    $digital  = Product::factory()->digital()->create( [ 'name' => 'Ebook' ] );
    $category = ProductCategory::factory()->create( [ 'name' => 'Prints' ] );
    $tag      = ProductTag::factory()->create( [ 'name' => 'Sale' ] );
    $archived->categories()->attach( $category->id );
    $draft->tags()->attach( $tag->id );

    Livewire::test( Index::class )
        ->set( 'filters.status', 'archived' )->assertSee( 'Old Print' )->assertDontSee( 'Draft Print' )
        ->set( 'filters', [ 'type' => 'digital' ] )->assertSee( 'Ebook' )->assertDontSee( 'Old Print' )
        ->set( 'filters', [ 'category' => (string) $category->id ] )->assertSee( 'Old Print' )->assertDontSee( 'Ebook' )
        ->set( 'filters', [ 'tag' => (string) $tag->id ] )->assertSee( 'Draft Print' )->assertDontSee( 'Old Print' )
        ->set( 'filters', [ 'status' => 'archived', 'category' => (string) $category->id ] )->assertSee( 'Old Print' );
} );

it( 'filters by stock state', function ( string $state, array $expected ): void {
    $plenty    = Product::factory()->create( [ 'name' => 'Plenty' ] );
    $low       = Product::factory()->create( [ 'name' => 'Running Low' ] );
    $out       = Product::factory()->create( [ 'name' => 'Sold Out' ] );
    $reserved  = Product::factory()->create( [ 'name' => 'All Reserved' ] );
    $untracked = Product::factory()->create( [ 'name' => 'Untracked' ] );
    stockFor( $plenty, 50, 5 );
    stockFor( $low, 3, 5 );
    stockFor( $out, 0, 5 );
    stockFor( $reserved, 2, null, 2 );

    $component = Livewire::test( Index::class )->set( 'filters.stock', $state );

    foreach ( [ 'Plenty', 'Running Low', 'Sold Out', 'All Reserved', 'Untracked' ] as $name ) {
        in_array( $name, $expected, true ) ? $component->assertSee( $name ) : $component->assertDontSee( $name );
    }
} )->with( [
    'in'        => [ 'in', [ 'Plenty', 'Running Low' ] ],
    'low'       => [ 'low', [ 'Running Low' ] ],
    'out'       => [ 'out', [ 'Sold Out', 'All Reserved' ] ],
    'untracked' => [ 'untracked', [ 'Untracked' ] ],
] );

it( 'runs the engine product list-query filter', function (): void {
    Product::factory()->create( [ 'name' => 'Visible' ] );
    Product::factory()->create( [ 'name' => 'Hidden by a satellite' ] );

    addFilter( 'ap.ecommerce.product.listQuery', static fn ( Builder $query, array $filters ): Builder => $query->where( $query->qualifyColumn( 'name' ), '!=', 'Hidden by a satellite' ) );

    Livewire::test( Index::class )->assertSee( 'Visible' )->assertDontSee( 'Hidden by a satellite' );
} );

it( 'publishes and archives the selection, skipping read-only products', function (): void {
    $draft   = Product::factory()->draft()->create();
    $missing = Product::factory()->draft()->create( [ 'type' => 'subscription' ] );

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $draft->id, (string) $missing->id ] )
        ->call( 'runBulkAction', 'publish' )
        ->assertSet( 'selected', [] );

    expect( $draft->refresh()->status )->toBe( 'active' )->and( $missing->refresh()->status )->toBe( 'draft' );

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $draft->id ] )
        ->call( 'runBulkAction', 'archive' );

    expect( $draft->refresh()->status )->toBe( 'archived' );
} );

it( 'reports skipped read-only products in the toast', function (): void {
    $missing = Product::factory()->draft()->create( [ 'type' => 'subscription' ] );

    $component = Livewire::test( Index::class )
        ->set( 'selected', [ (string) $missing->id ] )
        ->call( 'runBulkAction', 'publish' );

    expect( sentToasts( $component ) )->toContain( '0 products published. 1 read-only product was skipped.' );
} );

it( 'adds and removes a category and a tag on the selection', function (): void {
    $products = Product::factory()->count( 2 )->create();
    $category = ProductCategory::factory()->create( [ 'name' => 'Prints' ] );
    $tag      = ProductTag::factory()->create( [ 'name' => 'Sale' ] );
    $ids      = $products->pluck( 'id' )->map( 'strval' )->all();

    $component = Livewire::test( Index::class )
        ->set( 'selected', $ids )
        ->assertSee( 'Category to add or remove' )
        ->set( 'bulkCategoryId', (string) $category->id )
        ->call( 'runBulkAction', 'add-category' )
        ->assertSet( 'bulkCategoryId', null );

    expect( sentToasts( $component ) )->toContain( 'Prints' )->toContain( 'to 2 products.' )
        ->and( $category->products()->count() )->toBe( 2 );

    Livewire::test( Index::class )
        ->set( 'selected', $ids )
        ->set( 'bulkTagId', (string) $tag->id )
        ->call( 'runBulkAction', 'add-tag' );

    expect( $tag->products()->count() )->toBe( 2 );

    Livewire::test( Index::class )
        ->set( 'selected', [ $ids[0] ] )
        ->set( 'bulkCategoryId', (string) $category->id )
        ->call( 'runBulkAction', 'remove-category' )
        ->set( 'selected', [ $ids[0] ] )
        ->set( 'bulkTagId', (string) $tag->id )
        ->call( 'runBulkAction', 'remove-tag' );

    expect( $category->products()->count() )->toBe( 1 )->and( $tag->products()->count() )->toBe( 1 );
} );

it( 'requires a category or tag for those bulk actions', function ( string $action, string $field ): void {
    $product = Product::factory()->create();

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $product->id ] )
        ->set( $field, '999' )
        ->call( 'runBulkAction', $action )
        ->assertHasErrors( $field );
} )->with( [
    'add category' => [ 'add-category', 'bulkCategoryId' ],
    'add tag'      => [ 'add-tag', 'bulkTagId' ],
] );

it( 'deletes the selection after confirmation', function (): void {
    $product = Product::factory()->create( [ 'name' => 'Doomed' ] );
    priceFor( $product, 100 );

    $component = Livewire::test( Index::class )
        ->set( 'selected', [ (string) $product->id ] )
        ->call( 'runBulkAction', 'delete' )
        ->assertSee( 'Delete the selected products?' );

    expect( Product::query()->count() )->toBe( 1 );

    preg_match( "/confirmBulkAction\\( '([^']+)' \\)/", $component->html(), $matches );

    $component->call( 'confirmBulkAction', $matches[1] );

    expect( Product::query()->count() )->toBe( 0 )->and( ProductPrice::query()->count() )->toBe( 0 );
} );

it( 'hides and refuses the edit actions without product.update and product.delete', function (): void {
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );
    Gate::define( 'ecommerce.product.delete', static fn (): bool => false );
    $product = Product::factory()->draft()->create();

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $product->id ] )
        ->assertDontSeeHtml( "runBulkAction( 'publish' )" )
        ->call( 'runBulkAction', 'publish' )
        ->assertForbidden();

    expect( $product->refresh()->status )->toBe( 'draft' );
} );

it( 'exports the selection as CSV', function (): void {
    $mug = Product::factory()->create( [ 'name' => 'Blue Mug', 'sku' => 'MUG-1', 'status' => 'active' ] );
    priceFor( $mug, 1250 );
    stockFor( $mug, 4 );

    $rows = productsCsv( Livewire::test( Index::class )->set( 'selected', [ (string) $mug->id ] )->call( 'runBulkAction', 'export' ) );

    expect( $rows[0] )->toContain( 'Product', 'SKU', 'Type', 'Status', 'Price (USD)', 'Stock', 'Featured', 'Position', 'Categories', 'Updated' )
        ->and( $rows[1] )->toContain( 'Blue Mug', 'MUG-1', 'Simple product', 'Active', '12.50', '4' );
} );

it( 'links to the create and edit screens', function (): void {
    $product = Product::factory()->create( [ 'name' => 'Blue Mug' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( route( 'artisanpack.ecommerce.admin.products.create' ) )
        ->assertSeeHtml( route( 'artisanpack.ecommerce.admin.products.edit', [ 'product' => $product->id ] ) );
} );

it( 'serves the products page', function (): void {
    $this->get( route( 'artisanpack.ecommerce.admin.products.index' ) )
        ->assertOk()
        ->assertSeeLivewire( Index::class );
} );

it( 'translates its strings', function (): void {
    app( 'translator' )->addLines( [ '*.Products' => 'Productos', '*.No products yet' => 'Todavía no hay productos' ], 'es', '*' );
    app()->setLocale( 'es' );

    Livewire::test( Index::class )->assertSee( 'Productos' )->assertSee( 'Todavía no hay productos' );
} );

it( 'exports the catalog with one row per product or variant and a price pair per currency', function (): void {
    config()->set( 'artisanpack.ecommerce.currency.rates.USD', [ 'EUR' => 92_000_000 ] );
    config()->set( 'artisanpack.ecommerce.currency.enabled', [ 'EUR' ] );
    $mug = Product::factory()->create( [ 'name' => 'Blue Mug', 'sku' => 'MUG-1', 'status' => 'active' ] );
    ProductPrice::factory()->forPriceable( $mug )->create( [ 'currency' => 'USD', 'price_amount' => 1250, 'compare_at_amount' => 1500 ] );
    ProductPrice::factory()->forPriceable( $mug )->create( [ 'currency' => 'EUR', 'price_amount' => 1100 ] );
    stockFor( $mug, 7 );
    $tee     = Product::factory()->variable()->create( [ 'name' => 'Tee', 'sku' => 'TEE' ] );
    $variant = ProductVariant::factory()->create( [ 'product_id' => $tee->id, 'sku' => 'TEE-S', 'name' => 'Small' ] );
    priceFor( $variant, 2000 );
    Product::factory()->create( [ 'name' => '=HYPERLINK("x")', 'status' => 'draft' ] );

    $component = Livewire::test( Index::class )
        ->set( 'filters.status', 'active' )
        ->call( 'exportCatalog' )
        ->assertFileDownloaded();

    $rows    = productsCsv( $component );
    $headers = $rows[0];
    $byName  = static fn ( string $column, array $row ): string => $row[ array_search( $column, $headers, true ) ];

    expect( $headers )->toContain( 'price_USD', 'compare_at_price_USD', 'price_EUR', 'compare_at_price_EUR', 'variant_sku' )
        ->and( count( $rows ) )->toBe( 4 );

    $mugRow = collect( $rows )->first( static fn ( array $row ): bool => 'MUG-1' === $byName( 'sku', $row ) );

    expect( $byName( 'price_USD', $mugRow ) )->toBe( '12.50' )
        ->and( $byName( 'compare_at_price_USD', $mugRow ) )->toBe( '15.00' )
        ->and( $byName( 'price_EUR', $mugRow ) )->toBe( '11.00' )
        ->and( $byName( 'quantity_on_hand', $mugRow ) )->toBe( '7' );

    $variantRow = collect( $rows )->first( static fn ( array $row ): bool => 'TEE-S' === $byName( 'variant_sku', $row ) );

    expect( $byName( 'sku', $variantRow ) )->toBe( 'TEE' )
        ->and( $byName( 'variant_name', $variantRow ) )->toBe( 'Small' )
        ->and( $byName( 'price_USD', $variantRow ) )->toBe( '20.00' );

    expect( collect( $rows )->contains( static fn ( array $row ): bool => str_contains( implode( ',', $row ), 'HYPERLINK' ) ) )->toBeFalse();
} );

it( 'exports the selected products as catalog CSV, escaping formulas', function (): void {
    $risky = Product::factory()->create( [ 'name' => '=HYPERLINK("x")' ] );
    Product::factory()->create( [ 'name' => 'Not selected' ] );

    $component = Livewire::test( Index::class )
        ->set( 'selected', [ (string) $risky->id ] )
        ->call( 'runBulkAction', 'export-catalog' )
        ->assertFileDownloaded();

    $rows = productsCsv( $component );

    expect( $rows )->toHaveCount( 2 )
        ->and( $rows[1][ array_search( 'name', $rows[0], true ) ] )->toBe( "'=HYPERLINK(\"x\")" );
} );

it( 'links to the import screen for users who may create or update products', function (): void {
    Livewire::test( Index::class )->assertSee( 'Import' )->assertSeeHtml( 'products/import' );

    Gate::define( 'ecommerce.product.create', static fn (): bool => false );

    Livewire::test( Index::class )->assertSeeHtml( 'products/import' );

    Gate::define( 'ecommerce.product.update', static fn (): bool => false );

    Livewire::test( Index::class )->assertDontSeeHtml( 'products/import' );
} );

it( 'caps a crafted selection instead of loading it whole', function (): void {
    $component = Livewire::test( Index::class )->set( 'selected', range( 1, 70_000 ) );

    expect( $component->get( 'selected' ) )->toHaveCount( 100 * 50 );

    config()->set( 'artisanpack.ecommerce-admin-livewire.tables.max_selection', 10 );

    expect( Livewire::test( Index::class )->set( 'selected', range( 1, 500 ) )->get( 'selected' ) )->toHaveCount( 10 );
} );

it( 'shows the price the storefront charges when scheduled prices overlap', function (): void {
    $product = Product::factory()->create();

    priceFor( $product, 2_000 );
    ProductPrice::factory()->forPriceable( $product )->create( [ 'currency' => 'USD', 'price_amount' => 1_500, 'starts_at' => now()->subDays( 10 ), 'ends_at' => now()->addDays( 10 ) ] );
    ProductPrice::factory()->forPriceable( $product )->create( [ 'currency' => 'USD', 'price_amount' => 1_200, 'starts_at' => now()->subDays( 2 ), 'ends_at' => now()->addDays( 30 ) ] );
    ProductPrice::factory()->forPriceable( $product )->create( [ 'currency' => 'USD', 'price_amount' => 1_100, 'starts_at' => now()->subDays( 2 ), 'ends_at' => now()->addDays( 5 ) ] );

    $loaded   = ( new ArtisanPackUI\EcommerceAdminLivewire\Queries\ProductsQuery() )->build()->whereKey( $product->id )->sole();
    $resolved = app( ArtisanPackUI\Ecommerce\Services\ProductPriceResolver::class )->resolve( $product, 'USD' );

    expect( ArtisanPackUI\EcommerceAdminLivewire\Queries\ProductsQuery::priceRange( $loaded )['min'] )->toBe( 1_100 )
        ->and( (int) $resolved->getAmount() )->toBe( 1_100 );
} );

it( 'renders the page header with one solid action', function (): void {
    $html = Livewire::test( Index::class )
        ->assertSee( 'Everything you sell, with its type, status, price and stock.' )
        ->assertSeeHtml( 'data-export-catalog' )
        ->assertSeeHtml( 'data-import-products' )
        ->assertSeeHtml( 'data-new-product' )
        ->html();

    expect( $html )->not->toContain( 'btn' )
        ->and( substr_count( $html, 'data-variant="outline"' ) )->toBeGreaterThanOrEqual( 2 );
} );

it( 'shows the board columns and keeps SKU, position, categories and updated for the CSV', function (): void {
    Product::factory()->create( [ 'name' => 'Blue Mug' ] );

    $html = Livewire::test( Index::class )->html();

    preg_match( '/<thead>(.*?)<\/thead>/s', $html, $head );
    $headers = array_map( static fn ( string $cell ): string => trim( strip_tags( $cell ) ), explode( '</th>', $head[1] ) );

    expect( array_values( array_filter( $headers ) ) )->toBe( [ 'Image', 'Product', 'Type', 'Status', 'Price (USD)', 'Stock', 'Featured' ] )
        ->and( $head[1] )->toContain( '<span class="apui-sr-only">Image</span>' )
        ->and( $head[1] )->toContain( '<span class="apui-sr-only">Featured</span>' )
        ->and( $head[1] )->not->toContain( 'Updated' )
        ->and( $head[1] )->not->toContain( 'Position' );
} );

it( 'puts the variant count or the SKU under the product name', function (): void {
    $tee = Product::factory()->variable()->create( [ 'name' => 'Tee', 'sku' => 'TEE' ] );
    ProductVariant::factory()->count( 2 )->create( [ 'product_id' => $tee->id ] );
    Product::factory()->create( [ 'name' => 'Blue Mug', 'sku' => 'MUG-1' ] );

    Livewire::test( Index::class )
        ->assertSee( '2 variants' )
        ->assertSeeHtml( '<bdi dir="ltr" class="ec-mono" data-product-sku>MUG-1</bdi>' )
        ->assertDontSeeHtml( 'data-product-sku>TEE</bdi>' );
} );

it( 'shows the type as an outline badge and a missing type as a warning badge', function (): void {
    Product::factory()->create( [ 'name' => 'Mug' ] );
    Product::factory()->create( [ 'name' => 'Box', 'type' => 'subscription' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-variant="outline"' )
        ->assertSeeHtml( '<bdi dir="ltr">subscription</bdi> &middot; Missing type' )
        ->assertSeeHtml( 'data-color="warning"' );
} );

it( 'shows out of stock as a badge, keeping an oversold count', function (): void {
    $empty = Product::factory()->create( [ 'name' => 'Empty' ] );
    stockFor( $empty, 0 );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-out-of-stock' )
        ->assertSee( 'Out of stock' )
        ->assertDontSeeHtml( '<bdi dir="ltr">0</bdi>' );

    $empty->delete();
    $oversold = Product::factory()->create( [ 'name' => 'Oversold' ] );
    stockFor( $oversold, 2, null, 5 );

    Livewire::test( Index::class )
        ->assertSeeHtml( '<bdi dir="ltr">-3</bdi>' )
        ->assertSeeHtml( 'data-out-of-stock' );
} );

it( 'names the featured star and the thumbnail placeholder', function (): void {
    Product::factory()->create( [ 'name' => 'Star', 'is_featured' => true ] );
    Product::factory()->create( [ 'name' => 'Plain', 'is_featured' => false ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'role="img" aria-label="Featured" data-featured' )
        ->assertSeeHtml( 'role="img" aria-label="Not featured" data-not-featured' )
        ->assertSeeHtml( 'aria-label="Star: no image"' );
} );

it( 'tells search what it matches', function (): void {
    Livewire::test( Index::class )->assertSeeHtml( 'placeholder="Search by name or SKU"' );
} );

it( 'offers New product and Import in the empty state', function (): void {
    Livewire::test( Index::class )
        ->assertSee( 'No products yet' )
        ->assertSeeHtml( 'data-empty-new-product' )
        ->assertSeeHtml( 'data-empty-import' );
} );

it( 'leaves the empty state actions out for a user who may not add products', function (): void {
    Gate::define( 'ecommerce.product.create', static fn (): bool => false );
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );

    Livewire::test( Index::class )
        ->assertSee( 'No products yet' )
        ->assertDontSeeHtml( 'data-empty-new-product' )
        ->assertDontSeeHtml( 'data-empty-import' );
} );

it( 'puts Add and Remove beside the category and tag selects, once each', function (): void {
    $product = Product::factory()->create();
    ProductCategory::factory()->create( [ 'name' => 'Prints' ] );
    ProductTag::factory()->create( [ 'name' => 'Sale' ] );

    $html = Livewire::test( Index::class )->set( 'selected', [ (string) $product->id ] )->html();

    foreach ( [ 'add-category' => 'Add category', 'remove-category' => 'Remove category', 'add-tag' => 'Add tag', 'remove-tag' => 'Remove tag' ] as $key => $label ) {
        expect( substr_count( $html, "runBulkAction( '{$key}' )" ) )->toBe( 1 )
            ->and( $html )->toContain( 'aria-label="' . $label . '"' );
    }

    expect( $html )->toContain( 'aria-label="Category to add or remove"' )
        ->and( $html )->toContain( "runBulkAction( 'publish' )" )
        ->and( $html )->toContain( "runBulkAction( 'archive' )" )
        ->and( $html )->toContain( "runBulkAction( 'delete' )" );
} );

it( 'disables the category controls when there are no categories yet', function (): void {
    $product = Product::factory()->create();

    $html = Livewire::test( Index::class )->set( 'selected', [ (string) $product->id ] )->html();

    $start = strpos( $html, 'aria-label="Category to add or remove"' );
    $group = substr( $html, (int) $start, (int) strpos( $html, 'aria-label="Tag to add or remove"' ) - (int) $start );

    expect( $start )->not->toBeFalse()
        ->and( $group )->toContain( 'No categories yet' )
        ->and( substr_count( $group, 'disabled' ) )->toBeGreaterThanOrEqual( 3 );
} );

it( 'hides the category and tag controls without product.update', function (): void {
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );
    $product = Product::factory()->create();

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $product->id ] )
        ->assertDontSeeHtml( 'ec-products-bulk-group' )
        ->assertDontSeeHtml( "runBulkAction( 'add-category' )" );
} );

it( 'draws each category and tag bulk action once, in the bulk controls, and keeps the rest in the bar', function (): void {
    $mug = Product::factory()->create( [ 'name' => 'Mug' ] );

    $html = Livewire::test( Index::class )->set( 'selected', [ (string) $mug->id ] )->html();

    foreach ( [ ...Index::INLINE_BULK_ACTIONS, 'publish', 'archive', 'delete' ] as $action ) {
        expect( preg_match_all( '/runBulkAction\(\s*(?:\'|&quot;|")' . preg_quote( $action, '/' ) . '(?:\'|&quot;|")\s*\)/', $html ) )->toBe( 1, $action );
    }
} );

it( 'still sorts by columns that left the grid, from the URL', function ( string $column, string $direction, array $expected ): void {
    $apron = Product::factory()->create( [ 'name' => 'Apron', 'sku' => 'B-2', 'position' => 2 ] );
    $mug   = Product::factory()->create( [ 'name' => 'Mug', 'sku' => 'A-1', 'position' => 1 ] );
    $apron->forceFill( [ 'updated_at' => now()->subDay() ] )->saveQuietly();

    $html = Livewire::withQueryParams( [ 'sort' => $column, 'dir' => $direction ] )->test( Index::class )->html();

    expect( strpos( $html, $expected[0] ) )->toBeLessThan( strpos( $html, $expected[1] ) );
} )->with( [
    'sku'              => [ 'sku', 'asc', [ 'Mug', 'Apron' ] ],
    'position'         => [ 'position', 'desc', [ 'Apron', 'Mug' ] ],
    'updated, default' => [ '', '', [ 'Mug', 'Apron' ] ],
] );

it( 'resolves the page\'s thumbnails at once instead of a gallery lookup per row', function (): void {
    $products = Product::factory()->count( 10 )->create();

    foreach ( $products as $index => $product ) {
        ArtisanPackUI\Ecommerce\Models\ProductImage::query()->create( [ 'product_id' => $product->id, 'image_url' => "https://example.com/{$index}.jpg", 'alt_text' => "Photo {$index}", 'position' => 0 ] );
    }

    $component = Livewire::test( Index::class );

    $thumbnails = $component->viewData( 'thumbnails' );

    expect( $thumbnails )->toHaveCount( 10 )
        ->and( $thumbnails[ $products[3]->id ] )->toBe( [ 'url' => 'https://example.com/3.jpg', 'alt' => 'Photo 3' ] )
        ->and( $component->html() )->toContain( 'src="https://example.com/3.jpg"' );

    Illuminate\Support\Facades\DB::enableQueryLog();
    Illuminate\Support\Facades\DB::flushQueryLog();

    $component->call( '$refresh' );

    $imageQueries = array_filter( Illuminate\Support\Facades\DB::getQueryLog(), static fn ( array $query ): bool => str_contains( $query['query'], 'product_images' ) );

    expect( $imageQueries )->toHaveCount( 1 );
} );
