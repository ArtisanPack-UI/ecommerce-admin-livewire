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
    grantAbilities( [ 'inventory.viewAny', 'inventory.adjust' ] );
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
        ->assertSeeHtml( '<caption class="apui-sr-only">Inventory</caption>' )
        ->assertSee( 'Blue Mug' )
        ->assertSee( 'MUG-1' )
        ->assertSee( 'Tee — Large' )
        ->assertSee( 'TEE-L' )
        ->assertSee( 'Out of stock' )
        ->assertSeeInOrder( [ 'Blue Mug', 'MUG-1', '10', '4', '6' ] );
} );

it( 'is denied without inventory.viewAny', function (): void {
    Gate::define( 'ecommerce.inventory.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'does not accept product abilities in place of the inventory ones', function (): void {
    Gate::define( 'ecommerce.inventory.viewAny', static fn (): bool => false );
    grantAbilities( [ 'product.viewAny', 'product.update' ] );

    Livewire::test( Index::class )->assertForbidden();
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
    Gate::define( 'ecommerce.inventory.adjust', static fn (): bool => false );
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
        ->assertSeeHtml( 'x-on:keydown.escape.prevent.stop="$el.querySelector( \'[data-threshold-cancel]\' )?.click()"' )
        ->assertSeeHtml( 'data-threshold-cancel' )
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

it( 'edits the settings of a row in another warehouse through the inventory service', function (): void {
    $mug  = Product::factory()->create();
    $main = inventoryRow( $mug, 10, 2 );
    $east = InventoryItem::query()->create( [
        'stockable_type'      => $mug->getMorphClass(),
        'stockable_id'        => $mug->id,
        'warehouse_id'        => 7,
        'track_inventory'     => true,
        'quantity_on_hand'    => 4,
        'low_stock_threshold' => 1,
    ] );

    expect( $main->refresh()->warehouse_id )->toBe( InventoryItem::DEFAULT_WAREHOUSE );

    Livewire::test( Index::class )
        ->call( 'editThreshold', $east->id )
        ->set( 'thresholdValue', 3 )
        ->call( 'saveThreshold' )
        ->assertHasNoErrors()
        ->call( 'toggleBackorder', $east->id );

    expect( $east->refresh() )->low_stock_threshold->toBe( 3 )->allow_backorder->toBeTrue()
        ->and( $main->refresh() )->low_stock_threshold->toBe( 2 )->allow_backorder->toBeFalse();
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

it( 'carries the projected count across repeated SKUs in the dry run', function (): void {
    $item = inventoryRow( Product::factory()->create( [ 'sku' => 'MUG-1' ] ), 10 );

    $component = Livewire::test( Index::class )
        ->call( 'openBulkAdjust' )
        ->set( 'stockCsv', stockCsvUpload( "sku,quantity,mode\nMUG-1,25,set\nMUG-1,3,delta\n" ) )
        ->set( 'bulkReason', 'Delivery' )
        ->call( 'dryRunBulkAdjust' );

    expect( array_column( $component->get( 'bulkReport' ), 'result' ) )->toBe( [ 25, 28 ] );

    $component->call( 'applyBulkAdjust', $component->viewData( 'bulkToken' ) );

    expect( $item->refresh()->quantity_on_hand )->toBe( 28 );
} );

it( 'filters items at or below their threshold, out of stock included', function (): void {
    inventoryRow( Product::factory()->create( [ 'name' => 'Low Mug' ] ), 2, 5 );
    inventoryRow( Product::factory()->create( [ 'name' => 'Empty Mug' ] ), 0, 5 );
    inventoryRow( Product::factory()->create( [ 'name' => 'Full Mug' ] ), 50, 5 );
    inventoryRow( Product::factory()->create( [ 'name' => 'Loose Mug' ] ), 0, null );

    Livewire::withQueryParams( [ 'filters' => [ 'stock' => 'reorder' ] ] )
        ->test( Index::class )
        ->assertSet( 'filters.stock', 'reorder' )
        ->assertSee( 'Low Mug' )
        ->assertSee( 'Empty Mug' )
        ->assertDontSee( 'Full Mug' )
        ->assertDontSee( 'Loose Mug' );
} );

it( 'draws the design-system rows: product and variant, LTR SKU and counts, and named controls', function (): void {
    $tote    = Product::factory()->variable()->create( [ 'name' => 'Canvas tote' ] );
    $natural = ProductVariant::factory()->create( [ 'product_id' => $tote->id, 'name' => 'Natural', 'sku' => 'TOT-CNV-NAT' ] );
    $item    = inventoryRow( $natural, 4, 15, 2 );
    $item->forceFill( [ 'allow_backorder' => true ] )->save();

    $html = Livewire::test( Index::class )
        ->assertSee( 'Stock for every product and variant that tracks it.' )
        ->assertSeeHtml( 'placeholder="Search by product or SKU"' )
        ->assertSeeHtml( '<span data-scope="table" data-part="secondary" data-variant-name>Natural</span>' )
        ->assertSeeHtml( '<bdi dir="ltr" class="ec-mono">TOT-CNV-NAT</bdi>' )
        ->assertSeeHtml( 'data-low-stock' )
        ->assertSeeHtml( 'data-icon-only' )
        ->assertSeeHtml( '<span class="apui-sr-only">, backorders of Canvas tote — Natural</span>' )
        ->assertSeeHtml( 'aria-label="Adjust stock of Canvas tote — Natural"' )
        ->assertSeeHtml( 'aria-label="Edit the low-stock threshold of Canvas tote — Natural"' )
        ->assertSeeHtml( 'data-focus-key="inventory-threshold-' . $item->id . '"' )
        ->html();

    expect( $html )
        ->toContain( '>Allowed<' )
        ->not->toMatch( '/class="[^"]*\b(btn|badge-|link-hover|text-end|tabular-nums|font-semibold|opacity-75|table-sm|text-error)\b/' );
} );

it( 'shows a disabled backorder switch and no pencil or Adjust on read-only stock', function (): void {
    $item = inventoryRow( Product::factory()->create( [ 'name' => 'Coffee club', 'type' => 'subscription' ] ), 120 );

    $html = Livewire::test( Index::class )->html();

    expect( $html )
        ->toContain( 'data-read-only' )
        ->toMatch( '/<input[^>]*id="inventory-backorder-' . $item->id . '"[^>]*disabled/s' )
        ->toContain( ', backorders of Coffee club</span>' )
        ->not->toContain( 'editThreshold( ' . $item->id . ' )' )
        ->not->toContain( 'toggleBackorder( ' . $item->id . ' )' )
        ->not->toContain( 'data-focus-key="inventory-adjust-' . $item->id . '"' );
} );

it( 'disables the backorder switch for users who may not adjust stock', function (): void {
    Gate::define( 'ecommerce.inventory.adjust', static fn (): bool => false );
    $item = inventoryRow( Product::factory()->create( [ 'name' => 'Mug' ] ), 3 );

    expect( Livewire::test( Index::class )->html() )
        ->toMatch( '/<input[^>]*id="inventory-backorder-' . $item->id . '"[^>]*disabled/s' )
        ->not->toContain( 'toggleBackorder( ' . $item->id . ' )' )
        ->not->toContain( 'data-bulk-adjust-open' );
} );

it( 'previews the new on hand and available counts while adjusting', function (): void {
    $item = inventoryRow( Product::factory()->create( [ 'name' => 'Beanie' ] ), 1, null, 1 );

    Livewire::test( Index::class )
        ->call( 'startAdjust', $item->id )
        ->assertSee( 'On hand: 1. Reserved: 1. Available: 0.' )
        ->assertDontSee( 'New on hand:' )
        ->set( 'adjustQuantity', '+24' )
        ->assertSee( 'New on hand: 25. New available: 24.' )
        ->set( 'adjustMode', 'set' )
        ->set( 'adjustQuantity', 10 )
        ->assertSee( 'New count' )
        ->assertSee( 'New on hand: 10. New available: 9.' )
        ->set( 'adjustQuantity', 'abc' )
        ->assertDontSee( 'New on hand:' );
} );

it( 'previews only quantities the adjustment accepts', function ( string $mode, string $quantity, ?string $preview ): void {
    $item      = inventoryRow( Product::factory()->create(), 5 );
    $component = Livewire::test( Index::class )
        ->call( 'startAdjust', $item->id )
        ->set( 'adjustMode', $mode )
        ->set( 'adjustQuantity', $quantity );

    null === $preview ? $component->assertDontSee( 'New on hand:' ) : $component->assertSee( $preview );
} )->with( [
    'set to zero'             => [ 'set', '0', 'New on hand: 0.' ],
    'set below zero'          => [ 'set', '-1', null ],
    'set past the maximum'    => [ 'set', '1000001', null ],
    'a negative change'       => [ 'delta', '-3', 'New on hand: 2.' ],
    'no change'               => [ 'delta', '0', null ],
    'a change past the limit' => [ 'delta', '-1000001', null ],
] );

it( 'returns focus to the opener from the adjust dialog and the threshold editor', function (): void {
    $item = inventoryRow( Product::factory()->create(), 5, 2 );

    Livewire::test( Index::class )
        ->call( 'startAdjust', $item->id )
        ->assertSeeHtml( 'data-focus-key="inventory-adjust-' . $item->id . '"' )
        ->assertSeeHtml( 'data-focus-return="inventory-adjust-' . $item->id . '"' )
        ->call( 'closeAdjust' )
        ->call( 'editThreshold', $item->id )
        ->assertSeeHtml( 'data-focus-return="inventory-threshold-' . $item->id . '"' )
        ->assertSeeHtml( 'aria-label="Low-stock threshold of ' );
} );

it( 'summarizes the checked CSV in an alert and shows each row error in place of its product', function (): void {
    $mug = Product::factory()->create( [ 'name' => 'Mug', 'sku' => 'MUG-1' ] );
    inventoryRow( $mug, 4 );

    $html = Livewire::test( Index::class )
        ->call( 'openBulkAdjust' )
        ->assertSeeHtml( 'data-focus-return="inventory-bulk-adjust"' )
        ->set( 'bulkMode', 'delta' )
        ->set( 'stockCsv', stockCsvUpload( "sku,quantity\nMUG-1,-1\nXYZ-404,3\n" ) )
        ->set( 'bulkReason', 'Count' )
        ->call( 'dryRunBulkAdjust' )
        ->assertSeeHtml( 'data-bulk-summary' )
        ->assertSee( '1 row will change stock.' )
        ->assertSee( '1 row has an error and will be skipped.' )
        ->assertSee( 'Apply 1 change' )
        ->html();

    expect( $html )
        ->toMatch( '/data-tone="negative"\s*>−1<\/bdi>/u' )
        ->toMatch( '/data-bulk-error.*?<span class="ec-text-error">No product or variant has this SKU\.<\/span>/s' )
        ->not->toContain( '>Problem<' );
} );

it( 'passes the stock row to the inventory.adjust policy, single and in bulk', function (): void {
    $mug    = Product::factory()->create( [ 'name' => 'Mug', 'sku' => 'MUG-1' ] );
    $cup    = Product::factory()->create( [ 'name' => 'Cup', 'sku' => 'CUP-1' ] );
    $mugRow = inventoryRow( $mug, 10 );
    $cupRow = inventoryRow( $cup, 10 );

    // Only the mug's stock row may be adjusted.
    Gate::define( 'ecommerce.inventory.adjust', static fn ( $user, $item = null ): bool => null === $item || ( $item instanceof InventoryItem && $item->is( $mugRow ) ) );

    Livewire::test( Index::class )->call( 'startAdjust', $cupRow->id )->assertForbidden();
    Livewire::test( Index::class )->call( 'toggleBackorder', $cupRow->id )->assertForbidden();
    Livewire::test( Index::class )->call( 'editThreshold', $cupRow->id )->assertForbidden();
    Livewire::test( Index::class )->call( 'startAdjust', $mugRow->id )->assertOk()->assertSet( 'adjustingId', $mugRow->id );

    $component = Livewire::test( Index::class )
        ->call( 'openBulkAdjust' )
        ->set( 'stockCsv', stockCsvUpload( "sku,quantity\nMUG-1,25\nCUP-1,30\n" ) )
        ->set( 'bulkReason', 'Stock take' )
        ->call( 'dryRunBulkAdjust' )
        ->assertSee( 'You may not adjust this item\'s stock.' );

    $component->call( 'applyBulkAdjust', $component->viewData( 'bulkToken' ) );

    expect( $mugRow->refresh()->quantity_on_hand )->toBe( 25 )
        ->and( $cupRow->refresh()->quantity_on_hand )->toBe( 10 );
} );

it( 'refuses bulk rows for product types without stock, and changes over the single-adjust cap', function (): void {
    Product::factory()->create( [ 'name' => 'E-book', 'sku' => 'BOOK-1', 'type' => 'digital' ] );
    $mug = Product::factory()->create( [ 'name' => 'Mug', 'sku' => 'MUG-1' ] );
    inventoryRow( $mug, 10 );

    $component = Livewire::test( Index::class )
        ->call( 'openBulkAdjust' )
        ->set( 'stockCsv', stockCsvUpload( "sku,quantity\nBOOK-1,5\nMUG-1,2000000\n" ) )
        ->set( 'bulkReason', 'Stock take' )
        ->call( 'dryRunBulkAdjust' )
        ->assertSee( 'This product type has no stock of its own.' )
        ->assertSee( 'The quantity must be from -1000000 to 1000000.' );

    $component->call( 'applyBulkAdjust', $component->viewData( 'bulkToken' ) );

    expect( InventoryItem::query()->count() )->toBe( 1 );
} );

it( 'checks a bulk upload in a handful of queries, not several per row', function (): void {
    $rows = [];

    foreach ( range( 1, 40 ) as $n ) {
        inventoryRow( Product::factory()->create( [ 'sku' => "SKU-{$n}" ] ), 1 );
        $rows[] = "SKU-{$n},5";
    }

    $component = Livewire::test( Index::class )
        ->call( 'openBulkAdjust' )
        ->set( 'stockCsv', stockCsvUpload( "sku,quantity\n" . implode( "\n", $rows ) . "\n" ) )
        ->set( 'bulkReason', 'Stock take' );

    Illuminate\Support\Facades\DB::enableQueryLog();
    Illuminate\Support\Facades\DB::flushQueryLog();

    $component->call( 'dryRunBulkAdjust' )->assertHasNoErrors();

    expect( count( Illuminate\Support\Facades\DB::getQueryLog() ) )->toBeLessThan( 25 );
} );

it( 'redraws the backorder switch when a change fails', function (): void {
    $item = inventoryRow( Product::factory()->create( [ 'name' => 'Mug' ] ), 3 );

    $component = Livewire::test( Index::class );

    expect( $component->html() )->toContain( 'wire:key="inventory-backorder-' . $item->id . '-0-0"' );

    // The product's type goes missing between render and click.
    $item->stockable->forceFill( [ 'type' => 'subscription' ] )->saveQuietly();

    $component->call( 'toggleBackorder', $item->id )
        ->assertSet( 'backorderRevision', 1 );

    expect( $item->refresh()->allow_backorder )->toBeFalse();
} );

it( 'reads a missing SKU in the bulk report as "None"', function (): void {
    $component = Livewire::test( Index::class )
        ->call( 'openBulkAdjust' )
        ->set( 'stockCsv', stockCsvUpload( "sku,quantity\n,5\n" ) )
        ->set( 'bulkReason', 'Stock take' )
        ->call( 'dryRunBulkAdjust' );

    preg_match( '/<[a-z]+\b[^>]*\bdata-bulk-summary[^>]*>/', $component->html(), $summary );

    expect( $component->html() )->toContain( '<span class="ec-muted-text" aria-hidden="true">&mdash;</span><span class="apui-sr-only">None</span>' )
        ->and( substr_count( $summary[0] ?? '', 'role=' ) )->toBe( 1 );
} );
