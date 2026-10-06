<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Models\Refund;
use ArtisanPackUI\Ecommerce\Registries\ReportRegistry;
use ArtisanPackUI\Ecommerce\Reports\Report;
use ArtisanPackUI\Ecommerce\Reports\ReportRange;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Reports\Show;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'report.view' ] );
    $this->actingAs( makeUser() );

    config()->set( 'app.timezone', 'UTC' );
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    config()->set( 'artisanpack.ecommerce.timezone', 'UTC' );
    config()->set( 'artisanpack.ecommerce.currency.provider', 'config' );
    config()->set( 'artisanpack.ecommerce.currency.rates', [ 'GBP' => [ 'USD' => 125_000_000 ] ] );

    $this->travelTo( Carbon::parse( '2026-03-15 12:00:00', 'UTC' ) );
} );

/**
 * A paid order placed on `$day` with every money column in `$currency`.
 */
function reportsOrder( string $day, int $subtotal, array $attributes = [] ): Order
{
    $currency = (string) ( $attributes['currency'] ?? 'USD' );

    return Order::factory()->create( [
        'placed_at'               => $day . ' 10:00:00',
        'payment_status'          => 'paid',
        'subtotal_amount'         => $subtotal,
        'total_amount'            => $subtotal + (int) ( $attributes['tax_amount'] ?? 0 ),
        'subtotal_currency'       => $currency,
        'discount_currency'       => $currency,
        'tax_currency'            => $currency,
        'shipping_currency'       => $currency,
        'total_currency'          => $currency,
        'total_refunded_currency' => $currency,
        ...$attributes,
    ] );
}

it( 'shows sales for the last 30 days with KPIs, a chart, and a table', function (): void {
    reportsOrder( '2026-03-10', 10_000 );
    reportsOrder( '2026-03-12', 5_000 );
    reportsOrder( '2026-01-01', 99_000 );

    Livewire::test( Show::class, [ 'report' => 'sales' ] )
        ->assertOk()
        ->assertSet( 'preset', 'last-30-days' )
        ->assertSet( 'from', '2026-02-14' )
        ->assertSet( 'to', '2026-03-15' )
        ->assertSee( 'Sales over time' )
        ->assertSeeHtml( 'data-kpi="net"' )
        ->assertSee( '$150.00' )
        ->assertSeeHtml( 'data-chart' )
        ->assertSeeHtml( 'role="img"' )
        ->assertSee( 'Net sales by day from 2026-02-14 to 2026-03-15. The table below lists every value.' )
        ->assertSeeHtml( '<caption class="sr-only">Sales over time</caption>' )
        ->assertSeeHtml( 'data-report-totals' );
} );

it( 'is denied without report.view', function (): void {
    Gate::define( 'ecommerce.report.view', static fn (): bool => false );

    Livewire::test( Show::class, [ 'report' => 'sales' ] )->assertForbidden();
} );

it( 'returns 404 for an unknown report', function (): void {
    Livewire::test( Show::class, [ 'report' => 'nope' ] )->assertNotFound();
} );

it( 'fills the dates from a preset and switches to custom when a date is edited', function (): void {
    expect( Show::presetRange( 'last-month', CarbonImmutable::parse( '2026-03-15' ) ) )->toBe( [ '2026-02-01', '2026-02-28' ] )
        ->and( Show::presetRange( 'last-quarter', CarbonImmutable::parse( '2026-03-15' ) ) )->toBe( [ '2025-10-01', '2025-12-31' ] )
        ->and( Show::presetRange( 'yesterday', CarbonImmutable::parse( '2026-03-15' ) ) )->toBe( [ '2026-03-14', '2026-03-14' ] );

    Livewire::test( Show::class, [ 'report' => 'sales' ] )
        ->set( 'preset', 'this-month' )
        ->assertSet( 'from', '2026-03-01' )
        ->assertSet( 'to', '2026-03-15' )
        ->set( 'preset', 'last-year' )
        ->assertSet( 'from', '2025-01-01' )
        ->assertSet( 'interval', 'month' )
        ->set( 'from', '2025-06-01' )
        ->assertSet( 'preset', 'custom' );
} );

it( 'reads the range from a shared URL', function (): void {
    reportsOrder( '2026-02-10', 4_000 );

    Livewire::withQueryParams( [ 'preset' => 'custom', 'from' => '2026-02-01', 'to' => '2026-02-28', 'interval' => 'week' ] )
        ->test( Show::class, [ 'report' => 'sales' ] )
        ->assertSet( 'from', '2026-02-01' )
        ->assertSet( 'interval', 'week' )
        ->assertSee( 'Week of' )
        ->assertSee( '$40.00' );
} );

it( 'rejects an invalid range', function (): void {
    Livewire::test( Show::class, [ 'report' => 'sales' ] )
        ->set( 'preset', 'custom' )
        ->set( 'from', '2026-03-10' )
        ->set( 'to', '2026-03-01' )
        ->assertHasErrors( [ 'to' ] )
        ->assertSee( 'The end date must be on or after the start date.' )
        ->assertSeeHtml( 'data-report-invalid' )
        ->assertDontSeeHtml( 'data-report-table' );

    Livewire::test( Show::class, [ 'report' => 'sales' ] )
        ->set( 'from', '2020-01-01' )
        ->set( 'to', '2026-03-01' )
        ->assertHasErrors( [ 'from' ] )
        ->assertSee( 'Report ranges are limited to ' . ReportRange::MAX_DAYS . ' days.' );
} );

it( 'compares with the previous period in words', function (): void {
    reportsOrder( '2026-03-10', 12_000 );
    reportsOrder( '2026-02-10', 10_000 );

    Livewire::test( Show::class, [ 'report' => 'sales' ] )
        ->set( 'compare', true )
        ->assertSee( 'Up 20% from $100.00' )
        ->assertSee( 'Net sales, previous period' );
} );

it( 'flags orders converted at today\'s rate', function (): void {
    reportsOrder( '2026-03-10', 2_000, [ 'currency' => 'GBP', 'base_currency' => 'GBP' ] );

    Livewire::test( Show::class, [ 'report' => 'sales' ] )
        ->assertSeeHtml( 'data-converted-orders="1"' )
        ->assertSee( '1 order was placed under a different base currency.' )
        ->assertSee( '$25.00' );
} );

it( 'lists top products with their variants, sorted and limited', function (): void {
    $mug   = Product::factory()->create( [ 'name' => 'Mug', 'sku' => 'MUG' ] );
    $tee   = Product::factory()->create( [ 'name' => 'Tee', 'sku' => 'TEE' ] );
    $green = ProductVariant::factory()->create( [ 'product_id' => $mug->id, 'name' => 'Green', 'sku' => 'MUG-G' ] );
    $order = reportsOrder( '2026-03-10', 20_000 );

    OrderItem::factory()->create( [ 'order_id' => $order->id, 'product_id' => $mug->id, 'product_variant_id' => $green->id, 'quantity' => 1, 'unit_price_amount' => 15_000 ] );
    OrderItem::factory()->create( [ 'order_id' => $order->id, 'product_id' => $tee->id, 'quantity' => 5, 'unit_price_amount' => 1_000 ] );

    Livewire::test( Show::class, [ 'report' => 'top-products' ] )
        ->assertSeeInOrder( [ 'Mug', 'Green', 'Tee' ] )
        ->assertSee( 'Variant:' )
        ->set( 'sort', 'units' )
        ->assertSeeInOrder( [ 'Tee', 'Mug' ] )
        ->set( 'limit', 10 )
        ->assertOk();
} );

it( 'shows revenue by category and tax collected', function (): void {
    $category = ProductCategory::factory()->create( [ 'name' => 'Drinkware' ] );
    $mug      = Product::factory()->create( [ 'name' => 'Mug' ] );
    $mug->categories()->attach( $category->id );
    $order = reportsOrder( '2026-03-10', 3_000, [
        'tax_amount'       => 300,
        'shipping_address' => [ 'country_code' => 'US', 'region_code' => 'CA', 'city' => 'LA', 'address1' => '1 Main' ],
        'meta'             => [ 'tax_breakdown' => [ [ 'label' => 'CA state tax', 'rate_ubps' => 72_500_000, 'amount' => 300 ] ] ],
    ] );
    OrderItem::factory()->create( [ 'order_id' => $order->id, 'product_id' => $mug->id, 'quantity' => 1, 'unit_price_amount' => 3_000 ] );

    Livewire::test( Show::class, [ 'report' => 'revenue-by-category' ] )
        ->assertSee( 'Drinkware' )
        ->assertSee( '$30.00' )
        ->assertSee( '100%' );

    Livewire::test( Show::class, [ 'report' => 'tax' ] )
        ->assertSee( 'US-CA' )
        ->assertSee( 'CA state tax' )
        ->assertSee( '7.25%' )
        ->assertSee( '$3.00' );
} );

it( 'shows inventory levels without date controls, and low stock with a link to adjust', function (): void {
    $mug = Product::factory()->create( [ 'name' => 'Mug', 'sku' => 'MUG' ] );
    InventoryItem::factory()->create( [ 'stockable_type' => $mug->getMorphClass(), 'stockable_id' => $mug->id, 'quantity_on_hand' => 4, 'quantity_reserved' => 1, 'low_stock_threshold' => 5 ] );
    ProductPrice::factory()->create( [ 'priceable_type' => $mug->getMorphClass(), 'priceable_id' => $mug->id, 'currency' => 'USD', 'cost_amount' => 250 ] );

    Livewire::test( Show::class, [ 'report' => 'inventory' ] )
        ->assertDontSeeHtml( 'id="report-preset"' )
        ->assertSee( 'Stock value at cost' )
        ->assertSee( '$10.00' )
        ->assertSeeInOrder( [ 'Mug', 'MUG', '4', '1', '3' ] );

    Livewire::test( Show::class, [ 'report' => 'low-stock' ] )
        ->assertSee( 'Items at or below threshold' )
        ->assertSee( 'Adjust stock' )
        ->assertSeeHtml( route( 'artisanpack.ecommerce.admin.inventory.index', [ 'q' => 'MUG' ] ) );
} );

it( 'exports the table as CSV', function (): void {
    reportsOrder( '2026-03-10', 10_000 );
    Refund::factory()->create( [ 'order_id' => Order::query()->first()->id, 'amount' => 2_500, 'currency' => 'USD', 'created_at' => '2026-03-11 10:00:00' ] );

    Livewire::withQueryParams( [ 'preset' => 'custom', 'from' => '2026-03-10', 'to' => '2026-03-11' ] )
        ->test( Show::class, [ 'report' => 'sales' ] )
        ->call( 'export' )
        ->assertFileDownloaded( 'sales-2026-03-10-to-2026-03-11.csv' );

    $csv = ArtisanPackUI\EcommerceAdminLivewire\Support\Csv::build(
        ...ArtisanPackUI\EcommerceAdminLivewire\Support\ReportPresenter::csv(
            ArtisanPackUI\EcommerceAdminLivewire\Support\ReportPresenter::table( 'sales', app( ArtisanPackUI\Ecommerce\Reports\ReportRunner::class )->run( 'sales', ReportRange::make( '2026-03-10', '2026-03-11' ) ) ),
            'USD',
        ),
    );

    expect( $csv )->toContain( 'Net sales (USD)' )
        ->and( $csv )->toContain( '100.00' )
        ->and( $csv )->toContain( '-25.00' )
        ->and( $csv )->toContain( 'Total,1,100.00,0.00,25.00,75.00' );
} );

it( 'renders a satellite report as a generic table', function (): void {
    app( ReportRegistry::class )->register( 'cohorts', new class extends Report {
        public function run( ?ReportRange $range, array $options = [] ): array
        {
            return $this->result( $range, $this->amounts(), [ 'rows' => [ [ 'cohort' => '2026-03', 'customers' => 4 ] ] ] );
        }
    }, [ 'label' => 'Cohorts' ] );

    Livewire::test( Show::class, [ 'report' => 'cohorts' ] )
        ->assertSee( 'Cohorts' )
        ->assertSee( 'Customers' )
        ->assertSee( '2026-03' );
} );

it( 'serves the report page', function (): void {
    $this->get( route( 'artisanpack.ecommerce.admin.reports.show', [ 'report' => 'sales' ] ) )
        ->assertOk()
        ->assertSeeLivewire( Show::class );
} );

it( 'strips markup from chart labels', function (): void {
    $options = ArtisanPackUI\EcommerceAdminLivewire\Support\ReportPresenter::chartOptions(
        [ 'type' => 'bar', 'categories' => [ '<img src=x onerror=alert(1)>Mug', 'Cup <b>' ], 'series' => [], 'money' => true, 'summary' => '' ],
        'USD',
    );

    expect( $options['xaxis']['categories'] )->toBe( [ 'Mug', 'Cup' ] )
        ->and( $options['tooltip']['x']['show'] )->toBeFalse();
} );

it( 're-checks report.view on every update', function (): void {
    $component = Livewire::test( Show::class, [ 'report' => 'sales' ] )->assertOk();

    Gate::define( 'ecommerce.report.view', static fn (): bool => false );

    $component->set( 'compare', true )->assertForbidden();
} );

it( 'skips the change when a filtered total is not numeric', function (): void {
    $kpis = ArtisanPackUI\EcommerceAdminLivewire\Support\ReportPresenter::kpis( 'sales', [
        'totals'   => [ 'net' => 'n/a', 'orders' => 4 ],
        'previous' => [ 'totals' => [ 'net' => 100, 'orders' => 2 ] ],
    ] );

    expect( collect( $kpis )->firstWhere( 'key', 'net' )['change'] )->toBeNull()
        ->and( collect( $kpis )->firstWhere( 'key', 'orders' )['change'] )->toBe( 100.0 );
} );
