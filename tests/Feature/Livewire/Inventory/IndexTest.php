<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\ActivityLogEntry;
use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Inventory\Index;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'product.viewAny', 'product.update' ] );
    $this->actingAs( makeUser() );
} );

/**
 * A stock row for a product or variant.
 */
function inventoryRow( Model $stockable, int $onHand, ?int $threshold = null, int $reserved = 0, bool $tracked = true ): InventoryItem
{
    return InventoryItem::query()->create( [
        'stockable_type'      => $stockable->getMorphClass(),
        'stockable_id'        => $stockable->getKey(),
        'track_inventory'     => $tracked,
        'quantity_on_hand'    => $onHand,
        'quantity_reserved'   => $reserved,
        'low_stock_threshold' => $threshold,
    ] );
}

/**
 * A fake CSV upload.
 */
function stockCsvUpload( string $content ): UploadedFile
{
    return UploadedFile::fake()->createWithContent( 'stock.csv', $content );
}

it( 'renders one row per stock record with on hand, reserved, and available', function (): void {
    $mug     = Product::factory()->create( [ 'name' => 'Blue Mug', 'sku' => 'MUG-1' ] );
    $tee     = Product::factory()->variable()->create( [ 'name' => 'Tee' ] );
    $variant = ProductVariant::factory()->create( [ 'product_id' => $tee->id, 'name' => 'Large', 'sku' => 'TEE-L' ] );
    inventoryRow( $mug, 10, 3, 4 );
    inventoryRow( $variant, 0 );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeHtml( '<caption class="sr-only">Inventory</caption>' )
        ->assertSee( 'Blue Mug' )
        ->assertSee( 'MUG-1' )
        ->assertSee( 'Tee — Large' )
        ->assertSee( 'TEE-L' )
        ->assertSee( 'Out of stock' )
        ->assertSeeInOrder( [ 'Blue Mug', 'MUG-1', '10', '4', '6' ] );
} );

it( 'is denied without inventory.viewAny', function (): void {
    Gate::define( 'ecommerce.product.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'allows the engine ability when it exists', function (): void {
    Gate::define( 'ecommerce.product.viewAny', static fn (): bool => false );
    grantAbilities( [ 'inventory.viewAny' ] );

    Livewire::test( Index::class )->assertOk();
} );

it( 'filters by low stock, out of stock, and tracked only', function (): void {
    inventoryRow( Product::factory()->create( [ 'name' => 'Plenty' ] ), 50, 5 );
    inventoryRow( Product::factory()->create( [ 'name' => 'Running low' ] ), 3, 5 );
    inventoryRow( Product::factory()->create( [ 'name' => 'Sold out' ] ), 2, 5, 2 );
    inventoryRow( Product::factory()->create( [ 'name' => 'Untracked' ] ), 0, null, 0, false );

    Livewire::test( Index::class )
        ->set( 'filters.stock', 'low' )->assertSee( 'Running low' )->assertDontSee( 'Plenty' )->assertDontSee( 'Sold out' )
        ->set( 'filters', [ 'stock' => 'out' ] )->assertSee( 'Sold out' )->assertDontSee( 'Running low' )->assertDontSee( 'Untracked' )
        ->set( 'filters', [ 'tracked' => '1' ] )->assertSee( 'Plenty' )->assertDontSee( 'Untracked' )
        ->set( 'filters', [ 'tracked' => '0' ] )->assertSee( 'Untracked' )->assertDontSee( 'Plenty' );
} );

it( 'searches by product name, variant SKU, and product SKU', function (): void {
    $mug     = Product::factory()->create( [ 'name' => 'Blue Mug', 'sku' => 'MUG-1' ] );
    $tee     = Product::factory()->variable()->create( [ 'name' => 'Tee' ] );
    $variant = ProductVariant::factory()->create( [ 'product_id' => $tee->id, 'sku' => 'TEE-XL' ] );
    inventoryRow( $mug, 1 );
    inventoryRow( $variant, 1 );

    Livewire::test( Index::class )
        ->set( 'search', 'blue' )->assertSee( 'Blue Mug' )->assertDontSee( 'TEE-XL' )
        ->set( 'search', 'tee-xl' )->assertSee( 'TEE-XL' )->assertDontSee( 'Blue Mug' )
        ->set( 'search', 'mug-1' )->assertSee( 'Blue Mug' );
} );

it( 'adjusts stock by a delta with a reason, once per token', function (): void {
    $item = inventoryRow( Product::factory()->create( [ 'name' => 'Mug' ] ), 10 );

    $component = Livewire::test( Index::class )
        ->call( 'startAdjust', $item->id )
        ->assertSet( 'adjusting', true )
        ->assertSeeHtml( 'data-adjust-form' );

    $token = $component->viewData( 'adjustToken' );

    $component->set( 'adjustQuantity', 200 )
        ->set( 'adjustReason', 'Delivery received' )
        ->call( 'adjust', $token )
        ->assertHasNoErrors()
        ->assertSet( 'adjusting', false );

    expect( $item->refresh()->quantity_on_hand )->toBe( 210 );

    $component->call( 'startAdjust', $item->id )
        ->set( 'adjustQuantity', 5 )
        ->set( 'adjustReason', 'Again' )
        ->call( 'adjust', $token );

    expect( $item->refresh()->quantity_on_hand )->toBe( 210 );
} );

it( 'sets the count after a stock take', function (): void {
    $item = inventoryRow( Product::factory()->create(), 37, null, 2 );

    $component = Livewire::test( Index::class )->call( 'startAdjust', $item->id );

    $component->set( 'adjustMode', 'set' )
        ->set( 'adjustQuantity', 30 )
        ->set( 'adjustReason', 'Stock take' )
        ->call( 'adjust', $component->viewData( 'adjustToken' ) )
        ->assertHasNoErrors();

    expect( $item->refresh() )->quantity_on_hand->toBe( 30 )->quantity_reserved->toBe( 2 );
    expect( ActivityLogEntry::query()->count() )->toBeGreaterThan( 0 );
} );

it( 'validates the adjustment', function (): void {
    $item = inventoryRow( Product::factory()->create(), 10 );

    $component = Livewire::test( Index::class )->call( 'startAdjust', $item->id );
    $token     = $component->viewData( 'adjustToken' );

    $component->set( 'adjustQuantity', 0 )
        ->set( 'adjustReason', '' )
        ->call( 'adjust', $token )
        ->assertHasErrors( [ 'adjustQuantity' => 'not_in', 'adjustReason' => 'required' ] )
        ->set( 'adjustMode', 'set' )
        ->set( 'adjustQuantity', -1 )
        ->call( 'adjust', $token )
        ->assertHasErrors( [ 'adjustQuantity' => 'min' ] );

    expect( $item->refresh()->quantity_on_hand )->toBe( 10 );
} );

it( 'refuses to adjust without inventory.adjust', function (): void {
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );
    $item = inventoryRow( Product::factory()->create(), 10 );

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'startAdjust' )
        ->call( 'startAdjust', $item->id )
        ->assertForbidden();
} );

it( 'keeps stock of products whose type is missing read-only', function (): void {
    $item = inventoryRow( Product::factory()->create( [ 'type' => 'subscription' ] ), 10 );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-read-only' )
        ->assertDontSeeHtml( 'startAdjust( ' . $item->id . ' )' )
        ->call( 'startAdjust', $item->id )
        ->assertSet( 'adjusting', false );
} );

it( 'edits the threshold and toggles backorders inline', function (): void {
    $tee     = Product::factory()->variable()->create();
    $variant = ProductVariant::factory()->create( [ 'product_id' => $tee->id ] );
    $item    = inventoryRow( $variant, 10, 2 );

    Livewire::test( Index::class )
        ->call( 'editThreshold', $item->id )
        ->assertSeeHtml( 'data-threshold-form="' . $item->id . '"' )
        ->set( 'thresholdValue', -2 )
        ->call( 'saveThreshold' )
        ->assertHasErrors( [ 'thresholdValue' => 'min' ] )
        ->set( 'thresholdValue', 8 )
        ->call( 'saveThreshold' )
        ->assertHasNoErrors()
        ->assertSet( 'editingThresholdId', null )
        ->call( 'toggleBackorder', $item->id );

    expect( $item->refresh() )->low_stock_threshold->toBe( 8 )->allow_backorder->toBeTrue();
} );

it( 'clears the threshold when left empty', function (): void {
    $item = inventoryRow( Product::factory()->create(), 10, 4 );

    Livewire::test( Index::class )
        ->call( 'editThreshold', $item->id )
        ->set( 'thresholdValue', '' )
        ->call( 'saveThreshold' )
        ->assertHasNoErrors();

    expect( $item->refresh()->low_stock_threshold )->toBeNull();
} );

it( 'dry-runs a bulk adjustment from CSV, then applies the valid rows', function (): void {
    $mug     = Product::factory()->create( [ 'name' => 'Mug', 'sku' => 'MUG-1' ] );
    $tee     = Product::factory()->variable()->create( [ 'name' => 'Tee' ] );
    $variant = ProductVariant::factory()->create( [ 'product_id' => $tee->id, 'sku' => 'TEE-L' ] );
    $mugRow  = inventoryRow( $mug, 10 );
    $teeRow  = inventoryRow( $variant, 5 );

    $csv = "sku,quantity,mode\nMUG-1,25,\nTEE-L,3,delta\nNOPE,1,\nMUG-1,abc,\n";

    $component = Livewire::test( Index::class )
        ->call( 'openBulkAdjust' )
        ->set( 'stockCsv', stockCsvUpload( $csv ) )
        ->set( 'bulkReason', 'Stock take' )
        ->call( 'dryRunBulkAdjust' )
        ->assertHasNoErrors()
        ->assertSeeHtml( 'data-bulk-report' )
        ->assertSee( 'No product or variant has this SKU.' )
        ->assertSee( 'The quantity must be a whole number.' )
        ->assertSee( '+15' );

    expect( $mugRow->refresh()->quantity_on_hand )->toBe( 10 );

    $component->call( 'applyBulkAdjust', $component->viewData( 'bulkToken' ) )
        ->assertSet( 'bulkAdjusting', false )
        ->assertSet( 'stockCsv', null );

    expect( $mugRow->refresh()->quantity_on_hand )->toBe( 25 )
        ->and( $teeRow->refresh()->quantity_on_hand )->toBe( 8 );
} );

it( 'validates the bulk-adjust file and reason', function (): void {
    Livewire::test( Index::class )
        ->call( 'openBulkAdjust' )
        ->call( 'dryRunBulkAdjust' )
        ->assertHasErrors( [ 'stockCsv' => 'required', 'bulkReason' => 'required' ] )
        ->set( 'stockCsv', UploadedFile::fake()->create( 'stock.pdf', 10, 'application/pdf' ) )
        ->set( 'bulkReason', 'Count' )
        ->call( 'dryRunBulkAdjust' )
        ->assertHasErrors( [ 'stockCsv' => 'mimes' ] )
        ->set( 'stockCsv', stockCsvUpload( "name,count\nMug,3\n" ) )
        ->call( 'dryRunBulkAdjust' )
        ->assertHasErrors( [ 'stockCsv' ] )
        ->assertSet( 'bulkReport', null );
} );

it( 'refuses files over the row limit', function (): void {
    config()->set( 'artisanpack.ecommerce-admin-livewire.imports.max_rows', 2 );

    Livewire::test( Index::class )
        ->call( 'openBulkAdjust' )
        ->set( 'stockCsv', stockCsvUpload( "sku,quantity\nA,1\nB,1\nC,1\n" ) )
        ->set( 'bulkReason', 'Count' )
        ->call( 'dryRunBulkAdjust' )
        ->assertHasErrors( [ 'stockCsv' ] );
} );

it( 'does not create stock rows during the dry run', function (): void {
    Product::factory()->create( [ 'sku' => 'NEW-STOCK' ] );

    $component = Livewire::test( Index::class )
        ->call( 'openBulkAdjust' )
        ->set( 'stockCsv', stockCsvUpload( "sku,quantity\nNEW-STOCK,4\n" ) )
        ->set( 'bulkReason', 'Opening count' )
        ->call( 'dryRunBulkAdjust' )
        ->assertSee( '+4' );

    expect( InventoryItem::query()->count() )->toBe( 0 );

    $component->call( 'applyBulkAdjust', $component->viewData( 'bulkToken' ) );

    expect( (int) InventoryItem::query()->value( 'quantity_on_hand' ) )->toBe( 4 );
} );
