<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\EcommerceAdminLivewire\Jobs\RunProductImport;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Import;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductImports;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach( function (): void {
    Storage::fake( 'local' );
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    config()->set( 'artisanpack.ecommerce.features.scout', false );
    grantAbilities( [ 'product.viewAny', 'product.create', 'product.update' ] );
    $this->actingAs( makeUser() );
} );

/**
 * A fake CSV upload.
 */
function importUpload( string $content, string $name = 'products.csv' ): UploadedFile
{
    return UploadedFile::fake()->createWithContent( $name, $content );
}

/**
 * The import's stored state.
 *
 * @return array<string, mixed>
 */
function importState( $component ): array
{
    return (array) ProductImports::find( (string) $component->get( 'importId' ) );
}

it( 'renders the upload step', function (): void {
    Livewire::test( Import::class )
        ->assertOk()
        ->assertSeeHtml( 'data-import-upload' )
        ->assertSee( 'Download a sample file' );
} );

it( 'is denied without product.create or product.update', function (): void {
    Gate::define( 'ecommerce.product.create', static fn (): bool => false );
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );

    Livewire::test( Import::class )->assertForbidden();
} );

it( 'lets a user who may only update products import updates', function (): void {
    Gate::define( 'ecommerce.product.create', static fn (): bool => false );
    Product::factory()->create( [ 'sku' => 'MUG-1', 'name' => 'Mug' ] );

    $component = Livewire::test( Import::class )
        ->assertOk()
        ->set( 'csv', importUpload( "sku,name\nMUG-1,Renamed\nNEW-1,New\n" ) )
        ->call( 'upload' )
        ->call( 'check' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) );

    expect( Product::query()->where( 'sku', 'MUG-1' )->value( 'name' ) )->toBe( 'Renamed' )
        ->and( Product::query()->where( 'sku', 'NEW-1' )->exists() )->toBeFalse()
        ->and( importState( $component )['errors'][0]['message'] )->toBe( 'You may not create products.' );
} );

it( 'validates the upload by type, size, and row count', function (): void {
    config()->set( 'artisanpack.ecommerce-admin-livewire.imports.max_rows', 2 );

    Livewire::test( Import::class )
        ->call( 'upload' )
        ->assertHasErrors( [ 'csv' => 'required' ] )
        ->set( 'csv', UploadedFile::fake()->create( 'products.pdf', 10, 'application/pdf' ) )
        ->call( 'upload' )
        ->assertHasErrors( [ 'csv' => 'mimes' ] )
        ->set( 'csv', UploadedFile::fake()->create( 'products.csv', Import::MAX_UPLOAD_KB + 1, 'text/csv' ) )
        ->call( 'upload' )
        ->assertHasErrors( [ 'csv' => 'max' ] )
        ->set( 'csv', importUpload( "name\nA\nB\nC\n" ) )
        ->call( 'upload' )
        ->assertHasErrors( [ 'csv' ] )
        ->assertSet( 'importId', null );
} );

it( 'stores the upload and guesses the column mapping', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "Name,SKU,Price (USD),Notes\nMug,MUG-1,12.50,x\n" ) )
        ->call( 'upload' )
        ->assertHasNoErrors()
        ->assertSeeHtml( 'data-import-mapping' )
        ->assertSet( 'mapping', [ 'name', 'sku', 'price_USD', '' ] )
        ->assertSet( 'csv', null );

    $state = importState( $component );

    expect( $state['status'] )->toBe( 'mapping' )
        ->and( $state['total'] )->toBe( 1 )
        ->and( Storage::disk( 'local' )->exists( 'ecommerce-admin/imports/' . $state['id'] . '/source.csv' ) )->toBeTrue();
} );

it( 'validates the mapping', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "a,b\n1,2\n" ) )
        ->call( 'upload' )
        ->call( 'check' )
        ->assertHasErrors( [ 'mapping' ] )
        ->set( 'mapping', [ 'name', 'name' ] )
        ->call( 'check' )
        ->assertHasErrors( [ 'mapping' ] )
        ->set( 'mapping', [ 'bogus', '' ] )
        ->call( 'check' )
        ->assertHasErrors( [ 'mapping.0' => 'in' ] );

    expect( importState( $component )['status'] )->toBe( 'mapping' );
} );

it( 'dry-runs the file: creates, updates, and errors per row, without writing', function (): void {
    Product::factory()->create( [ 'name' => 'Old mug', 'sku' => 'MUG-1' ] );
    Product::factory()->create( [ 'name' => 'Taken', 'sku' => 'TAKEN' ] );

    $csv = "name,sku,slug,price_USD,status\n"
        . "Blue Mug,MUG-1,,13.00,active\n"
        . "Red Mug,MUG-2,,9.50,\n"
        . "Bad price,MUG-3,,abc,\n"
        . "Dupe,MUG-2,,1.00,\n"
        . ",,,,\n"
        . "No name,,no-name,,bogus\n";

    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( $csv ) )
        ->call( 'upload' )
        ->call( 'check' )
        ->assertHasNoErrors()
        ->assertSeeHtml( 'data-import-report' )
        ->assertSee( '1 new product or variant' )
        ->assertSee( '1 update' )
        ->assertSee( '3 rows with errors' )
        ->assertSee( 'must be an amount' )
        ->assertSee( 'used by an earlier row' )
        ->assertSee( 'Status must be draft, active, or archived.' );

    expect( Product::query()->count() )->toBe( 2 )
        ->and( importState( $component )['status'] )->toBe( 'checked' );
} );

it( 'reports a SKU taken by a variant', function (): void {
    $tee = Product::factory()->variable()->create();
    ProductVariant::factory()->create( [ 'product_id' => $tee->id, 'sku' => 'TAKEN' ] );

    Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,sku\nNew product,TAKEN\n" ) )
        ->call( 'upload' )
        ->call( 'check' )
        ->assertSee( 'Another product or variant already uses the SKU "TAKEN".' );
} );

it( 'imports products, variants, prices, categories, tags, and stock', function (): void {
    ProductCategory::factory()->create( [ 'slug' => 'kitchen' ] );

    $csv = "type,name,slug,sku,status,categories,tags,quantity_on_hand,variant_sku,variant_name,price_USD,compare_at_price_USD\n"
        . "simple,Blue Mug,blue-mug,MUG-1,active,kitchen,gift|sale,12,,,12.50,15.00\n"
        . "variable,Tee,tee,TEE,active,,,,,,,\n"
        . "variable,Tee,tee,TEE,,,,5,TEE-S,Small,20.00,\n";

    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( $csv ) )
        ->call( 'upload' )
        ->call( 'check' )
        ->assertSee( '3 new products or variants' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) )
        ->assertSeeHtml( 'data-import-progress="completed"' );

    $mug = Product::query()->where( 'sku', 'MUG-1' )->firstOrFail();
    $tee = Product::query()->where( 'sku', 'TEE' )->firstOrFail();

    expect( $mug->status )->toBe( 'active' )
        ->and( $mug->categories()->pluck( 'slug' )->all() )->toBe( [ 'kitchen' ] )
        ->and( $mug->tags()->pluck( 'name' )->sort()->values()->all() )->toBe( [ 'gift', 'sale' ] )
        ->and( ProductPrice::query()->where( 'priceable_id', $mug->id )->where( 'priceable_type', $mug->getMorphClass() )->first() )
        ->price_amount->toBe( 1250 )->compare_at_amount->toBe( 1500 );

    $variant = ProductVariant::query()->where( 'sku', 'TEE-S' )->firstOrFail();

    expect( $variant->product_id )->toBe( $tee->id )
        ->and( (int) InventoryItem::query()->where( 'stockable_id', $variant->id )->where( 'stockable_type', $variant->getMorphClass() )->value( 'quantity_on_hand' ) )->toBe( 5 );

    $state = importState( $component );

    expect( $state )
        ->status->toBe( 'completed' )
        ->processed->toBe( 3 )
        ->and( $state['counts'] )->toBe( [ 'created' => 3, 'updated' => 0, 'failed' => 0 ] )
        ->and( Storage::disk( 'local' )->exists( 'ecommerce-admin/imports/' . $state['id'] . '/source.csv' ) )->toBeFalse();
} );

it( 'updates prices of exported products on re-import, leaving blank cells alone', function (): void {
    $mug = Product::factory()->create( [ 'name' => 'Blue Mug', 'sku' => 'MUG-1', 'status' => 'active' ] );
    ProductPrice::factory()->forPriceable( $mug )->create( [ 'currency' => 'USD', 'price_amount' => 1250 ] );

    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "sku,name,status,price_USD\nMUG-1,,,14.00\n" ) )
        ->call( 'upload' )
        ->call( 'check' )
        ->assertSee( '1 update' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) );

    expect( $mug->refresh() )->name->toBe( 'Blue Mug' )->status->toBe( 'active' )
        ->and( ProductPrice::query()->where( 'priceable_id', $mug->id )->count() )->toBe( 1 )
        ->and( ProductPrice::query()->where( 'priceable_id', $mug->id )->value( 'price_amount' ) )->toBe( 1400 );
} );

it( 'records failed rows and offers the error report', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,sku\nGood,G-1\n,G-2\n" ) )
        ->call( 'upload' )
        ->call( 'check' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) )
        ->assertSee( 'Download the error report' )
        ->call( 'downloadErrors' )
        ->assertFileDownloaded();

    expect( importState( $component )['counts'] )->toBe( [ 'created' => 1, 'updated' => 0, 'failed' => 1 ] );
} );

it( 'applies once per token', function (): void {
    Queue::fake();

    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name\nMug\n" ) )
        ->call( 'upload' )
        ->call( 'check' );

    $token = $component->viewData( 'applyToken' );

    $component->call( 'apply', $token )
        ->assertSeeHtml( 'data-import-progress="queued"' )
        ->assertSeeHtml( 'wire:poll.2s' );

    Queue::assertPushed( RunProductImport::class, 1 );

    $state           = importState( $component );
    $state['status'] = 'checked';
    ProductImports::save( $state );

    $component->call( 'apply', $token );

    Queue::assertPushed( RunProductImport::class, 1 );
} );

it( 'resumes a stopped import from the next row', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,sku\nFirst,A-1\nSecond,A-2\n" ) )
        ->call( 'upload' )
        ->call( 'check' );

    $state              = importState( $component );
    $state['status']    = 'failed';
    $state['processed'] = 1;
    $state['message']   = 'Stopped';
    ProductImports::save( $state );

    Livewire::test( Import::class )
        ->assertSet( 'importId', $state['id'] )
        ->assertSee( 'Resume import' )
        ->call( 'resume' )
        ->assertSeeHtml( 'data-import-progress="completed"' );

    expect( Product::query()->pluck( 'sku' )->all() )->toBe( [ 'A-2' ] );
} );

it( 'stops when the importing user loses access', function (): void {
    Queue::fake();

    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name\nMug\n" ) )
        ->call( 'upload' )
        ->call( 'check' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) );

    Gate::define( 'ecommerce.product.create', static fn (): bool => false );
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );

    ( new RunProductImport( (string) $component->get( 'importId' ) ) )->handle();

    expect( importState( $component )['status'] )->toBe( 'failed' )
        ->and( Product::query()->count() )->toBe( 0 );
} );

it( 'refuses rows the user may not write', function (): void {
    Product::factory()->create( [ 'sku' => 'MUG-1' ] );
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );

    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "sku,name\nMUG-1,Renamed\nNEW-1,New\n" ) )
        ->call( 'upload' )
        ->call( 'check' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) );

    expect( Product::query()->where( 'sku', 'MUG-1' )->value( 'name' ) )->not->toBe( 'Renamed' )
        ->and( Product::query()->where( 'sku', 'NEW-1' )->exists() )->toBeTrue()
        ->and( importState( $component )['errors'][0]['message'] )->toBe( 'You may not edit this product.' );
} );

it( 'discards an import and its file', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name\nMug\n" ) )
        ->call( 'upload' );

    $id = $component->get( 'importId' );

    $component->call( 'discard' )
        ->assertSet( 'importId', null )
        ->assertSeeHtml( 'data-import-upload' );

    expect( Storage::disk( 'local' )->exists( 'ecommerce-admin/imports/' . $id ) )->toBeFalse();
} );

it( 'does not show another user\'s import', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name\nMug\n" ) )
        ->call( 'upload' );

    $this->actingAs( makeUser() );

    Livewire::test( Import::class )->assertSet( 'importId', null );
    expect( $component->get( 'importId' ) )->not->toBeNull();
} );

it( 'downloads the sample file', function (): void {
    Livewire::test( Import::class )
        ->call( 'downloadSample' )
        ->assertFileDownloaded( 'products-import-sample.csv' );
} );

it( 'imports the shipped sample file cleanly', function (): void {
    foreach ( [ 'kitchen', 'apparel', 'books' ] as $slug ) {
        ProductCategory::factory()->create( [ 'slug' => $slug ] );
    }

    $sample = (string) file_get_contents( __DIR__ . '/../../../../resources/samples/products-import-sample.csv' );

    Livewire::test( Import::class )
        ->set( 'csv', importUpload( $sample ) )
        ->call( 'upload' )
        ->call( 'check' )
        ->assertSee( '0 rows with errors' )
        ->assertSee( '5 new products or variants' );
} );

it( 'ignores a header that would overwrite the row line number', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,__line\nMug,99\n" ) )
        ->call( 'upload' )
        ->assertSet( 'mapping', [ 'name' ] );

    expect( importState( $component )['headers'] )->toBe( [ 'name' ] );
} );

it( 'strips the export\'s formula guard on re-import', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "sku,name,short_description\nPLUS-1,'+Plus,'- Feature list\n" ) )
        ->call( 'upload' )
        ->call( 'check' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) );

    expect( Product::query()->where( 'sku', 'PLUS-1' )->firstOrFail() )
        ->name->toBe( '+Plus' )
        ->short_description->toContain( '- Feature list' )
        ->short_description->not->toContain( "'" );
} );

it( 'reports a repeated slug as an update, as the apply will treat it', function (): void {
    Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,slug\nMug,mug\nMug again,mug\n" ) )
        ->call( 'upload' )
        ->call( 'check' )
        ->assertSee( '1 new product or variant' )
        ->assertSee( '1 update' );
} );

it( 'lets only one worker run an import at a time', function (): void {
    Queue::fake();

    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,sku\nMug,MUG-1\n" ) )
        ->call( 'upload' )
        ->call( 'check' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) );

    $id   = (string) $component->get( 'importId' );
    $lock = Illuminate\Support\Facades\Cache::lock( ProductImports::lockKey( $id ), 60 );
    $lock->get();

    ( new RunProductImport( $id ) )->handle();
    ( new RunProductImport( $id ) )->failed( null );

    expect( importState( $component )['status'] )->toBe( 'queued' )
        ->and( Product::query()->count() )->toBe( 0 );

    $lock->release();

    ( new RunProductImport( $id ) )->handle();

    expect( importState( $component )['status'] )->toBe( 'completed' )
        ->and( Product::query()->count() )->toBe( 1 );
} );

it( 'dry-runs 500 rows in a handful of queries', function (): void {
    ArtisanPackUI\Ecommerce\Models\TaxClass::query()->firstOrCreate( [ 'key' => 'reduced' ], [ 'label' => 'Reduced' ] );
    ProductCategory::factory()->create( [ 'slug' => 'kitchen' ] );
    Product::factory()->create( [ 'sku' => 'MUG-1', 'slug' => 'mug-1' ] );

    $rows = array_map( static fn ( int $n ): array => [
        'name'          => "Mug {$n}",
        'sku'           => "MUG-{$n}",
        'slug'          => "mug-{$n}",
        'categories'    => 'kitchen',
        'tax_class_key' => 'reduced',
        'price_USD'     => '12.50',
    ], range( 1, 500 ) );

    $context = [];

    Illuminate\Support\Facades\DB::flushQueryLog();
    Illuminate\Support\Facades\DB::enableQueryLog();

    ArtisanPackUI\EcommerceAdminLivewire\Support\ProductCsv::preload( $rows, $context );

    $actions = array_map( static fn ( array $row ): string => ArtisanPackUI\EcommerceAdminLivewire\Support\ProductCsv::check( $row, $context )['action'], $rows );

    $queries = count( Illuminate\Support\Facades\DB::getQueryLog() );
    Illuminate\Support\Facades\DB::disableQueryLog();

    expect( $queries )->toBeLessThan( 20 )
        ->and( $actions[0] )->toBe( 'update' )
        ->and( array_count_values( $actions ) )->toBe( [ 'update' => 1, 'create' => 499 ] );
} );
