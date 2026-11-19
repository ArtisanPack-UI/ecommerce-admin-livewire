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

/**
 * The check step's counts, read from its Stat tiles.
 *
 * @return array<string, int>
 */
function importCounts( $component ): array
{
    $counts = [];

    preg_match_all( '/data-import-count="(\w+)".*?data-part="value"[^>]*>\s*(\d+)/s', $component->html(), $matches, PREG_SET_ORDER );

    foreach ( $matches as $match ) {
        $counts[ $match[1] ] = (int) $match[2];
    }

    return $counts;
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
        ->call( 'uploadCsv' )
        ->call( 'check' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) );

    expect( Product::query()->where( 'sku', 'MUG-1' )->value( 'name' ) )->toBe( 'Renamed' )
        ->and( Product::query()->where( 'sku', 'NEW-1' )->exists() )->toBeFalse()
        ->and( importState( $component )['errors'][0]['message'] )->toBe( 'You may not create products.' );
} );

it( 'validates the upload by type, size, and row count', function (): void {
    config()->set( 'artisanpack.ecommerce-admin-livewire.imports.max_rows', 2 );

    Livewire::test( Import::class )
        ->call( 'uploadCsv' )
        ->assertHasErrors( [ 'csv' => 'required' ] )
        ->set( 'csv', UploadedFile::fake()->create( 'products.pdf', 10, 'application/pdf' ) )
        ->call( 'uploadCsv' )
        ->assertHasErrors( [ 'csv' => 'mimes' ] )
        ->set( 'csv', UploadedFile::fake()->create( 'products.csv', Import::MAX_UPLOAD_KB + 1, 'text/csv' ) )
        ->call( 'uploadCsv' )
        ->assertHasErrors( [ 'csv' => 'max' ] )
        ->set( 'csv', importUpload( "name\nA\nB\nC\n" ) )
        ->call( 'uploadCsv' )
        ->assertHasErrors( [ 'csv' ] )
        ->assertSet( 'importId', null );
} );

it( 'stores the upload and guesses the column mapping', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "Name,SKU,Price (USD),Notes\nMug,MUG-1,12.50,x\n" ) )
        ->call( 'uploadCsv' )
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
        ->call( 'uploadCsv' )
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
        ->call( 'uploadCsv' )
        ->call( 'check' )
        ->assertHasNoErrors()
        ->assertSeeHtml( 'data-import-report' )
        ->assertSee( 'new product or variant' )
        ->assertSee( 'rows with errors' )
        ->assertSee( 'must be an amount' )
        ->assertSee( 'used by an earlier row' )
        ->assertSee( 'Status must be draft, active, or archived.' );

    expect( importCounts( $component ) )->toBe( [ 'create' => 1, 'update' => 1, 'error' => 3 ] )
        ->and( Product::query()->count() )->toBe( 2 )
        ->and( importState( $component )['status'] )->toBe( 'checked' );
} );

it( 'reports a SKU taken by a variant', function (): void {
    $tee = Product::factory()->variable()->create();
    ProductVariant::factory()->create( [ 'product_id' => $tee->id, 'sku' => 'TAKEN' ] );

    Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,sku\nNew product,TAKEN\n" ) )
        ->call( 'uploadCsv' )
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
        ->call( 'uploadCsv' )
        ->call( 'check' );

    expect( importCounts( $component ) )->toBe( [ 'create' => 3, 'update' => 0, 'error' => 0 ] );

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
        ->call( 'uploadCsv' )
        ->call( 'check' );

    expect( importCounts( $component ) )->toMatchArray( [ 'create' => 0, 'update' => 1 ] );

    $component->call( 'apply', $component->viewData( 'applyToken' ) );

    expect( $mug->refresh() )->name->toBe( 'Blue Mug' )->status->toBe( 'active' )
        ->and( ProductPrice::query()->where( 'priceable_id', $mug->id )->count() )->toBe( 1 )
        ->and( ProductPrice::query()->where( 'priceable_id', $mug->id )->value( 'price_amount' ) )->toBe( 1400 );
} );

it( 'records failed rows and offers the error report', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,sku\nGood,G-1\n,G-2\n" ) )
        ->call( 'uploadCsv' )
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
        ->call( 'uploadCsv' )
        ->call( 'check' );

    $token = $component->viewData( 'applyToken' );

    $component->call( 'apply', $token )
        ->assertSeeHtml( 'data-import-progress="queued"' )
        ->assertSeeHtml( 'wire:poll.5s' );

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
        ->call( 'uploadCsv' )
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
        ->call( 'uploadCsv' )
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
        ->call( 'uploadCsv' )
        ->call( 'check' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) );

    expect( Product::query()->where( 'sku', 'MUG-1' )->value( 'name' ) )->not->toBe( 'Renamed' )
        ->and( Product::query()->where( 'sku', 'NEW-1' )->exists() )->toBeTrue()
        ->and( importState( $component )['errors'][0]['message'] )->toBe( 'You may not edit this product.' );
} );

it( 'discards an import and its file', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name\nMug\n" ) )
        ->call( 'uploadCsv' );

    $id = $component->get( 'importId' );

    $component->call( 'discard' )
        ->assertSet( 'importId', null )
        ->assertSeeHtml( 'data-import-upload' );

    expect( Storage::disk( 'local' )->exists( 'ecommerce-admin/imports/' . $id ) )->toBeFalse();
} );

it( 'does not show another user\'s import', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name\nMug\n" ) )
        ->call( 'uploadCsv' );

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

    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( $sample ) )
        ->call( 'uploadCsv' )
        ->call( 'check' );

    expect( importCounts( $component ) )->toMatchArray( [ 'create' => 5, 'error' => 0 ] );
} );

it( 'ignores a header that would overwrite the row line number', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,__line\nMug,99\n" ) )
        ->call( 'uploadCsv' )
        ->assertSet( 'mapping', [ 'name' ] );

    expect( importState( $component )['headers'] )->toBe( [ 'name' ] );
} );

it( 'strips the export\'s formula guard on re-import', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "sku,name,short_description\nPLUS-1,'+Plus,'- Feature list\n" ) )
        ->call( 'uploadCsv' )
        ->call( 'check' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) );

    expect( Product::query()->where( 'sku', 'PLUS-1' )->firstOrFail() )
        ->name->toBe( '+Plus' )
        ->short_description->toContain( '- Feature list' )
        ->short_description->not->toContain( "'" );
} );

it( 'reports a repeated slug as an update, as the apply will treat it', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,slug\nMug,mug\nMug again,mug\n" ) )
        ->call( 'uploadCsv' )
        ->call( 'check' );

    expect( importCounts( $component ) )->toMatchArray( [ 'create' => 1, 'update' => 1 ] );
} );

it( 'lets only one worker run an import at a time', function (): void {
    Queue::fake();

    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,sku\nMug,MUG-1\n" ) )
        ->call( 'uploadCsv' )
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

/**
 * The opening tag of the element carrying a data attribute.
 */
function importTag( $component, string $attribute ): string
{
    preg_match( '/<[a-z]+\b[^>]*\b' . preg_quote( $attribute, '/' ) . '[^>]*>/', $component->html(), $match );

    return $match[0] ?? '';
}

it( 'builds the upload step from design-system steps and a dropzone', function (): void {
    $component = Livewire::test( Import::class )
        ->assertSee( 'Add or update products in bulk from a CSV file.' )
        ->assertSee( 'Drop a CSV here or choose a file' )
        ->assertSee( '.csv up to 10 MB' )
        ->assertSeeHtml( 'class="apui-steps' )
        ->assertSeeHtml( 'data-import-upload-submit' )
        ->assertDontSeeHtml( 'step-primary' )
        ->assertDontSeeHtml( 'table-sm' );

    $html = $component->html();

    expect( substr_count( $html, 'aria-current="step"' ) )->toBe( 1 )
        ->and( $html )->toMatch( '/data-state="current"\s+aria-current="step"\s*>.*?<span aria-hidden="true">1<\/span>.*?data-part="title">Upload</s' )
        ->and( substr_count( $html, 'data-state="upcoming"' ) )->toBe( 3 );
} );

it( 'matches columns in a table with the file\'s first row', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "sku,colour,notes\n'=MER-1,Navy,\n" ) )
        ->call( 'uploadCsv' );

    expect( importState( $component )['sample'] )->toBe( [ '=MER-1', 'Navy', '' ] );

    $component->assertSeeHtml( 'data-import-columns' )
        ->assertSee( 'Header in the file' )
        ->assertSee( 'Navy' )
        ->assertSee( '=MER-1' )
        ->assertSee( 'Column for colour' )
        ->assertSee( 'Ignore this column' )
        ->assertDontSee( 'SKU (sku)' );

    expect( substr_count( $component->html(), 'data-state="complete"' ) )->toBe( 1 );
} );

it( 'keeps "Check the file" unavailable until a key column is mapped', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "colour,notes\nNavy,x\n" ) )
        ->call( 'uploadCsv' )
        ->assertSet( 'mapping', [ '', '' ] )
        ->assertSeeHtml( 'data-import-needs-key' )
        ->assertSee( 'Map at least one of SKU, slug, name, or variant SKU so rows can be matched.' );

    expect( importTag( $component, 'data-import-check' ) )
        ->toContain( 'aria-disabled="true"' )
        ->toContain( 'aria-describedby="import-needs-key"' )
        ->not->toContain( 'type="submit"' );

    $component->set( 'mapping.0', 'sku' )
        ->assertDontSeeHtml( 'data-import-needs-key' );

    expect( importTag( $component, 'data-import-check' ) )
        ->toContain( 'type="submit"' )
        ->not->toContain( 'aria-disabled' );

    expect( importTag( $component, 'data-import-start-over' ) )->toContain( 'data-variant="ghost"' );
} );

it( 'clears a stale mapping error when a column changes, so the key warning shows', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "sku,code\nA,B\n" ) )
        ->call( 'uploadCsv' )
        ->set( 'mapping', [ 'sku', 'sku' ] )
        ->call( 'check' )
        ->assertSee( 'Each column can be mapped from only one header.' );

    // The button only points at the warning while the warning is on the page.
    expect( importTag( $component, 'data-import-check' ) )->not->toContain( 'aria-describedby' );

    $component->set( 'mapping.0', '' )
        ->set( 'mapping.1', '' )
        ->assertDontSee( 'Each column can be mapped from only one header.' )
        ->assertSeeHtml( 'id="import-needs-key"' );

    expect( importTag( $component, 'data-import-check' ) )->toContain( 'aria-describedby="import-needs-key"' );
} );

it( 'renders when the client sends a malformed mapping value, and check refuses it', function (): void {
    Livewire::test( Import::class )
        ->set( 'csv', importUpload( "sku,colour\nA,Navy\n" ) )
        ->call( 'uploadCsv' )
        ->set( 'mapping.0', [ 'sku' ] )
        ->assertSeeHtml( 'data-import-needs-key' )
        ->call( 'check' )
        ->assertHasErrors( 'mapping.0' );
} );

it( 'cuts the first row to 80 characters, counting multibyte text by character', function ( string $character ): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name\n" . str_repeat( $character, 100 ) . "\n" ) )
        ->call( 'uploadCsv' );

    $sample = importState( $component )['sample'][0];

    expect( mb_strlen( $sample ) )->toBe( 80 )
        ->and( $sample )->toBe( str_repeat( $character, 79 ) . '…' );
} )->with( [
    'latin'    => 'a',
    'accented' => 'é',
    'cjk'      => '漢',
] );

it( 'keeps the import when the first row has a non-UTF-8 byte in an ignored column', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "sku,notes\nA-1,caf\xE9\n" ) )
        ->call( 'uploadCsv' )
        ->assertHasNoErrors()
        ->assertSeeHtml( 'data-import-mapping' );

    $sample = importState( $component )['sample'];

    expect( $sample[0] )->toBe( 'A-1' )
        ->and( mb_check_encoding( $sample[1], 'UTF-8' ) )->toBeTrue()
        ->and( $sample[1] )->toStartWith( 'caf' );
} );

it( 'shows a dash for the first row of an import saved before the sample was kept', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "sku,colour\nA-1,Navy\n" ) )
        ->call( 'uploadCsv' );

    $state = importState( $component );
    unset( $state['sample'] );
    ProductImports::save( $state );

    $component->call( '$refresh' )
        ->assertSeeHtml( 'data-import-columns' )
        ->assertDontSee( 'Navy' )
        ->assertSee( '—' );
} );

it( 'shows the check step as stat counts and action badges, with one solid action', function (): void {
    Product::factory()->create( [ 'name' => 'Old mug', 'sku' => 'MUG-1' ] );

    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,sku,price_USD\nBlue Mug,MUG-1,13.00\nRed Mug,MUG-2,9.50\nBad,MUG-3,abc\n" ) )
        ->call( 'uploadCsv' )
        ->call( 'check' )
        ->assertSee( 'Rows with errors (skipped on import)' )
        ->assertSee( 'Rows to import' )
        ->assertSee( 'Import 2 rows' )
        ->assertSeeHtml( 'data-import-action="update"' )
        ->assertSeeHtml( 'data-import-action="create"' );

    expect( importCounts( $component ) )->toBe( [ 'create' => 1, 'update' => 1, 'error' => 1 ] )
        ->and( importTag( $component, 'data-import-count="error"' ) )->toContain( 'data-emphasis' )
        ->and( importTag( $component, 'data-import-apply' ) )->toContain( 'data-variant="solid"' )
        ->and( importTag( $component, 'data-import-edit-mapping' ) )->toContain( 'data-variant="outline"' )
        ->and( importTag( $component, 'data-import-start-over' ) )->toContain( 'data-variant="ghost"' );
} );

it( 'makes changing the matching the solid action when no row can be imported', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,sku,price_USD\nBad,MUG-3,abc\n" ) )
        ->call( 'uploadCsv' )
        ->call( 'check' )
        ->assertDontSeeHtml( 'data-import-apply' );

    expect( importTag( $component, 'data-import-edit-mapping' ) )->toContain( 'data-variant="solid"' );
} );

it( 'shows queued progress as a labelled progressbar with a live sentence', function (): void {
    Queue::fake();

    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name\nMug\nCup\n" ) )
        ->call( 'uploadCsv' )
        ->call( 'check' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) );

    $state              = importState( $component );
    $state['processed'] = 1;
    ProductImports::save( $state );

    $component->call( '$refresh' )
        ->assertSee( 'Import progress' )
        ->assertSee( '1 of 2 rows done: 0 created, 0 updated, 0 failed.' );

    expect( importTag( $component, 'data-import-progressbar' ) )
        ->toContain( 'role="progressbar"' )
        ->toContain( 'aria-valuenow="50"' )
        ->toContain( 'aria-labelledby=' )
        ->toContain( 'data-color="primary"' )
        ->not->toContain( 'ec-w-' )
        ->and( importTag( $component, 'data-import-sentence' ) )->toContain( 'role="status"' )
        ->and( substr_count( $component->html(), 'aria-current="step"' ) )->toBe( 1 );
} );

it( 'offers the error report as the primary action when an import finishes with failures', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,sku\nGood,G-1\n,G-2\n" ) )
        ->call( 'uploadCsv' )
        ->call( 'check' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) )
        ->assertSee( 'Import finished' )
        ->assertSee( '1 row failed. Download the report to fix and re-upload it.' )
        ->assertDontSeeHtml( 'data-import-discard' );

    expect( importTag( $component, 'data-import-download-errors' ) )->toContain( 'data-variant="solid"' )
        ->and( importTag( $component, 'data-import-another' ) )->toContain( 'data-variant="outline"' )
        ->and( importTag( $component, 'data-import-progressbar' ) )->toContain( 'data-color="success"' );
} );

it( 'makes importing another file the primary action after a clean import', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,sku\nGood,G-1\n" ) )
        ->call( 'uploadCsv' )
        ->call( 'check' );

    $component->call( 'apply', $component->viewData( 'applyToken' ) )
        ->assertDontSee( 'Import finished' )
        ->assertDontSeeHtml( 'data-import-download-errors' );

    expect( importTag( $component, 'data-import-another' ) )->toContain( 'data-variant="solid"' );
} );

it( 'puts resume first on a stopped import, with the report and a ghost discard', function (): void {
    $component = Livewire::test( Import::class )
        ->set( 'csv', importUpload( "name,sku\nFirst,A-1\nSecond,A-2\n" ) )
        ->call( 'uploadCsv' )
        ->call( 'check' );

    $state                     = importState( $component );
    $state['status']           = 'failed';
    $state['processed']        = 1;
    $state['counts']['failed'] = 1;
    $state['message']          = 'Stopped';
    ProductImports::save( $state );

    $component = Livewire::test( Import::class )
        ->assertSee( 'Stopped' );

    expect( importTag( $component, 'data-import-resume' ) )->toContain( 'data-variant="solid"' )
        ->and( importTag( $component, 'data-import-download-errors' ) )->toContain( 'data-variant="outline"' )
        ->and( importTag( $component, 'data-import-discard' ) )->toContain( 'data-variant="ghost"' )->toContain( 'data-color="error"' )
        ->and( importTag( $component, 'data-import-progressbar' ) )->toContain( 'data-color="error"' );
} );
