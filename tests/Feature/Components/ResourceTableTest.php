<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Index;
use ArtisanPackUI\EcommerceAdminLivewire\View\Components\ResourceTable;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view', 'order.update' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceAdminLivewire.table.orders.bulkActions' );
} );

/**
 * A resource table over `$orders`, with `$selected` keys selected.
 *
 * @param  array<int, Order>       $orders    The page.
 * @param  array<int, int|string>  $selected  The selected keys.
 */
function ordersTable( array $orders, array $selected = [] ): ResourceTable
{
    return new ResourceTable(
        columns: [ [ 'key' => 'order_number', 'label' => 'Order', 'sortable' => true, 'class' => '', 'numeric' => false, 'view' => null, 'value' => null, 'export' => null, 'exportable' => true ] ],
        rows: new LengthAwarePaginator( $orders, count( $orders ), 25 ),
        caption: 'Orders',
        selectable: true,
        selected: $selected,
    );
}

it( 'works out the select-all state from the selected keys on the page', function (): void {
    $orders = Order::factory()->count( 3 )->create()->all();

    expect( ordersTable( $orders )->pageSelectionState() )->toBe( 'unchecked' )
        ->and( ordersTable( $orders, [ $orders[0]->id ] )->pageSelectionState() )->toBe( 'indeterminate' )
        ->and( ordersTable( $orders, array_map( static fn ( Order $order ): string => (string) $order->id, $orders ) )->pageSelectionState() )->toBe( 'checked' )
        ->and( ordersTable( [] )->pageSelectionState() )->toBe( 'unchecked' )
        ->and( ordersTable( $orders, [ (string) $orders[1]->id ] )->isSelected( $orders[1] ) )->toBeTrue()
        ->and( ordersTable( $orders, [ (string) $orders[1]->id ] )->isSelected( $orders[0] ) )->toBeFalse();
} );

it( 'uses the design system icons for the sort state', function (): void {
    $table       = ordersTable( [] );
    $table->sort = 'order_number';

    expect( $table->sortIcon( [ 'key' => 'total' ] ) )->toBe( 'chevrons-up-down' )
        ->and( $table->sortIcon( [ 'key' => 'order_number' ] ) )->toBe( 'arrow-up' );

    $table->direction = 'desc';

    expect( $table->sortIcon( [ 'key' => 'order_number' ] ) )->toBe( 'arrow-down' );
} );

it( 'renders one design-system table with a toolbar, the grid and the pager', function (): void {
    Order::factory()->count( 2 )->create();

    Livewire::test( Index::class )
        ->assertSeeHtml( 'class="apui-table ec-table"' )
        ->assertSeeHtml( 'data-scope="table" data-part="toolbar"' )
        ->assertSeeHtml( 'data-scope="table" data-part="scroll"' )
        ->assertSeeHtml( 'data-scope="table" data-part="footer"' )
        ->assertSeeHtml( 'data-part="sort"' )
        ->assertSeeHtml( 'aria-label="Search"' )
        ->assertSeeHtml( 'aria-label="Rows per page"' )
        ->assertSee( '25 per page' )
        ->assertSee( 'Export CSV' )
        ->assertSee( 'Showing 1 to 2 of 2 results' )
        ->assertDontSeeHtml( 'class="table ' )
        ->assertDontSeeHtml( 'checkbox-sm' );
} );

it( 'aligns numeric columns to the inline end', function (): void {
    Order::factory()->create();

    expect( substr_count( Livewire::test( Index::class )->html(), 'data-numeric' ) )->toBeGreaterThanOrEqual( 4 );
} );

it( 'marks selected rows and makes the header box mixed while part of the page is selected', function (): void {
    $orders = Order::factory()->count( 2 )->create();

    $html = Livewire::test( Index::class )
        ->set( 'selected', [ (string) $orders->first()->id ] )
        ->html();

    expect( $html )->toContain( 'aria-selected="true"' )
        ->toContain( 'aria-selected="false"' )
        ->toMatch( '/class="apui-checkbox"[^>]*data-state="indeterminate"/' );
} );

it( 'announces only the selection count, not the whole bulk-action bar', function (): void {
    $orders = Order::factory()->count( 2 )->create();

    $html = Livewire::test( Index::class )
        ->set( 'selected', [ (string) $orders->first()->id ] )
        ->html();

    expect( $html )->toMatch( '/<div class="apui-sr-only" role="status" aria-live="polite" aria-atomic="true" data-live-region>1 row selected\.<\/div>/' )
        ->and( $html )->toContain( 'data-bulk-action-bar' )
        ->and( preg_match( '/aria-live="polite">\s*<div[^>]*data-bulk-action-bar/', $html ) )->toBe( 0 );
} );

it( 'keeps the filter drawer closed until a filter is on, and counts the filters on', function (): void {
    Order::factory()->create( [ 'payment_status' => 'paid' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'aria-controls="resource-table-filters"' )
        ->assertSeeHtml( 'x-data="{ filtersOpen: false }"' )
        ->assertSeeHtmlInOrder( [ 'id="resource-table-filters"', 'hidden' ] )
        ->assertDontSeeHtml( 'class="ec-filter-count"' )
        ->set( 'filters.payment_status', 'paid' )
        ->assertSeeHtml( 'x-data="{ filtersOpen: true }"' )
        ->assertSeeHtml( '<span class="ec-filter-count" aria-hidden="true">1</span>' )
        ->assertSee( '(1 filter on)' );
} );

it( 'colors destructive bulk actions as errors and confirms them inline', function (): void {
    addFilter( 'ap.ecommerceAdminLivewire.table.orders.bulkActions', static fn ( array $actions ): array => [
        ...$actions,
        [ 'key' => 'purge', 'label' => 'Purge orders', 'icon' => 'trash', 'confirm' => 'Purged orders are gone for good.', 'handler' => static fn (): null => null ],
    ] );

    $order = Order::factory()->create();

    $component = Livewire::test( Index::class )->set( 'selected', [ (string) $order->id ] );

    expect( $component->html() )->toMatch( '/data-color="error"[^>]*>\s*(<[^>]+>\s*)*Purge orders/s' );

    $component->call( 'runBulkAction', 'purge' )
        ->assertSeeHtml( 'class="ec-confirm"' )
        ->assertSeeHtml( 'role="alertdialog"' )
        ->assertSee( 'Purged orders are gone for good.' );
} );

it( 'shows the no-matches empty state inside the table', function (): void {
    Order::factory()->create();

    Livewire::test( Index::class )
        ->set( 'search', 'nothing-like-this' )
        ->assertSeeHtml( 'apui-empty-state' )
        ->assertSee( 'No matches' )
        ->assertDontSeeHtml( 'data-resource-table>' );
} );

it( 'keeps the sort button on a sortable column whose label is visually hidden', function (): void {
    $table = new ResourceTable(
        columns: [ [ 'key' => 'order_number', 'label' => 'Order', 'sortable' => true, 'labelHidden' => true, 'class' => '', 'numeric' => false, 'view' => null, 'value' => null, 'export' => null, 'exportable' => true ] ],
        rows: new LengthAwarePaginator( [], 0, 25 ),
        caption: 'Orders',
    );

    $html = $table->render()->with( $table->data() )->render();

    expect( $html )->toMatch( '/<button[^>]*data-part="sort"[^>]*>\s*<span class="apui-sr-only">Order<\/span>/s' )
        ->and( $html )->toContain( 'aria-sort=' );
} );

it( 'localizes a date cell that has no view, in the store time zone', function (): void {
    config()->set( 'artisanpack.ecommerce.timezone', 'America/Chicago' );
    $order = Order::factory()->create();
    $order->setAttribute( 'placed_on', Illuminate\Support\Carbon::parse( '2026-03-05 18:30:00', 'UTC' ) );

    $column = [ 'key' => 'placed_on', 'label' => 'Placed', 'sortable' => false, 'class' => '', 'numeric' => false, 'view' => null, 'value' => null, 'export' => null, 'exportable' => true ];

    expect( ordersTable( [ $order ] )->cellText( $order, $column ) )->toBe( 'Mar 5, 2026 12:30 PM' );

    app()->setLocale( 'de' );

    expect( ordersTable( [ $order ] )->cellText( $order, $column ) )->toBe( '5. Mär 2026, 12:30' );
} );
