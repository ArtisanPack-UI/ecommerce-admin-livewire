<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\Ecommerce\Models\OrderTimelineEntry;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Index;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view', 'order.update' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    foreach ( [ 'columns', 'filters', 'bulkActions' ] as $surface ) {
        removeAllFilters( 'ap.ecommerceAdminLivewire.table.orders.' . $surface );
    }
} );

/**
 * The CSV a Livewire download effect carries, as rows.
 *
 * @return array<int, array<int, string>>
 */
function downloadedCsv( $component ): array
{
    $download = $component->effects['download'] ?? null;

    expect( $download )->not->toBeNull();

    $content = base64_decode( (string) $download['content'] );
    $content = preg_replace( '/^\xEF\xBB\xBF/', '', $content );

    return array_map( 'str_getcsv', array_values( array_filter( explode( "\n", trim( $content ) ) ) ) );
}

/**
 * The token the confirmation button carries.
 */
function confirmToken( $component ): string
{
    expect( preg_match( "/confirmBulkAction\\( '([^']+)' \\)/", $component->html(), $matches ) )->toBe( 1 );

    return $matches[1];
}

it( 'renders the orders table with a caption, sortable headers, and aria-sort', function (): void {
    $customer = Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Lovelace' ] );
    $order    = Order::factory()->create( [ 'order_number' => 'A1B2C3D4', 'customer_id' => $customer->id, 'payment_status' => 'paid', 'total_amount' => 12345, 'total_currency' => 'USD' ] );
    OrderItem::factory()->count( 2 )->create( [ 'order_id' => $order->id, 'quantity' => 3 ] );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeHtml( '<caption class="apui-sr-only">Orders</caption>' )
        ->assertSeeHtml( 'aria-sort="descending"' )
        ->assertSeeHtml( 'wire:click="sort( \'number\' )"' )
        ->assertSee( '#A1B2C3D4' )
        ->assertSee( 'Ada Lovelace' )
        ->assertSee( 'Paid' )
        ->assertSee( '$123.45' )
        ->assertSeeHtml( 'aria-label="Select order #A1B2C3D4"' )
        ->assertSeeHtml( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => $order->id ] ) );
} );

it( 'is denied without order.viewAny', function (): void {
    Gate::define( 'ecommerce.order.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'is denied on later requests once the ability is revoked', function (): void {
    $component = Livewire::test( Index::class )->assertOk();

    Gate::define( 'ecommerce.order.viewAny', static fn (): bool => false );

    $component->set( 'search', 'x' )->assertForbidden();
} );

it( 'reads search, filters, sort, and per-page from the URL', function (): void {
    Order::factory()->create( [ 'order_number' => 'PAID0001', 'payment_status' => 'paid' ] );
    Order::factory()->create( [ 'order_number' => 'PAID0002', 'payment_status' => 'paid' ] );
    Order::factory()->create( [ 'order_number' => 'PEND0001', 'payment_status' => 'pending' ] );

    Livewire::withQueryParams( [ 'q' => 'PAID', 'filters' => [ 'payment_status' => 'paid' ], 'sort' => 'number', 'dir' => 'asc', 'per_page' => '10' ] )
        ->test( Index::class )
        ->assertSet( 'search', 'PAID' )
        ->assertSet( 'filters', [ 'payment_status' => 'paid' ] )
        ->assertSet( 'perPage', 10 )
        ->assertSeeInOrder( [ '#PAID0001', '#PAID0002' ] )
        ->assertDontSee( '#PEND0001' )
        ->assertSeeHtml( 'aria-sort="ascending"' );
} );

it( 'ignores URL values it does not understand', function (): void {
    Order::factory()->count( 2 )->create();

    Livewire::withQueryParams( [ 'filters' => [ 'payment_status' => 'stolen', 'evil' => '1', 'placed' => [ 'from' => 'DROP TABLE' ] ], 'sort' => 'password', 'dir' => 'sideways', 'per_page' => '100000' ] )
        ->test( Index::class )
        ->assertOk()
        ->assertSet( 'perPage', 25 )
        ->assertSeeHtml( 'aria-sort="descending"' )
        ->assertViewHas( 'tableRows', static fn ( $rows ): bool => 2 === $rows->total() );
} );

it( 'toggles the sort direction and resets to page one', function (): void {
    Order::factory()->count( 3 )->create();

    Livewire::test( Index::class )
        ->call( 'setPage', 2 )
        ->call( 'sort', 'number' )
        ->assertSet( 'sortColumn', 'number' )
        ->assertSet( 'sortDirection', 'asc' )
        ->assertSet( 'paginators.page', 1 )
        ->call( 'sort', 'number' )
        ->assertSet( 'sortDirection', 'desc' )
        ->call( 'sort', 'not-sortable' )
        ->assertSet( 'sortColumn', 'number' );
} );

it( 'shows the "nothing yet" and "no matches" empty states', function (): void {
    Livewire::test( Index::class )
        ->assertSee( 'No orders yet' )
        ->assertDontSee( 'No matches' );

    Order::factory()->create();

    Livewire::test( Index::class )
        ->set( 'search', 'nothing-like-this' )
        ->assertSee( 'No matches' )
        ->assertSee( 'Clear filters' )
        ->call( 'resetFilters' )
        ->assertSet( 'search', '' )
        ->assertDontSee( 'No matches' );
} );

it( 'clears the selection when the search changes', function (): void {
    $order = Order::factory()->create();

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $order->id ] )
        ->assertSee( '1 row selected.' )
        ->set( 'search', 'x' )
        ->assertSet( 'selected', [] );
} );

it( 'offers to select every matching order once the page is selected', function (): void {
    $orders = Order::factory()->count( 12 )->create();

    $component = Livewire::test( Index::class )
        ->set( 'perPage', 10 )
        ->set( 'selected', $orders->sortByDesc( 'id' )->take( 10 )->pluck( 'id' )->map( 'strval' )->values()->all() )
        ->assertSee( 'Select all 12 matching rows' )
        ->call( 'selectAllMatchingRows' )
        ->assertSee( 'All 12 matching rows selected.' );

    expect( $component->get( 'selectAllMatching' ) )->toBeTrue();
} );

it( 'moves the selected orders to a sub-status that fits them all', function (): void {
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    $orders  = Order::factory()->count( 12 )->create( [ 'system_status' => 'processing' ] );

    $component = Livewire::test( Index::class )
        ->set( 'filters.system_status', 'processing' )
        ->call( 'selectAllMatchingRows' )
        ->assertSee( 'Move to sub-status' )
        ->assertSee( 'Packing' )
        ->set( 'bulkSubstatusId', $packing->id )
        ->call( 'runBulkAction', 'change-substatus' )
        ->assertHasNoErrors()
        ->assertSet( 'selected', [] )
        ->assertSet( 'selectAllMatching', false );

    expect( Order::query()->where( 'substatus_id', $packing->id )->count() )->toBe( 12 )
        ->and( OrderTimelineEntry::query()->where( 'event_type', 'order.substatus_changed' )->count() )->toBe( 12 )
        ->and( sentToasts( $component ) )->toContain( '12 orders moved to' )->toContain( 'Packing' );
} );

it( 'moves every selected order even when the move takes them out of the filter', function (): void {
    $awaiting = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'awaiting', 'label' => 'Awaiting' ] );
    $packing  = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    Order::factory()->count( 30 )->create( [ 'system_status' => 'processing', 'substatus_id' => $awaiting->id ] );

    Livewire::test( Index::class )
        ->set( 'filters.substatus', (string) $awaiting->id )
        ->call( 'selectAllMatchingRows' )
        ->set( 'bulkSubstatusId', $packing->id )
        ->call( 'runBulkAction', 'change-substatus' )
        ->assertHasNoErrors();

    expect( Order::query()->where( 'substatus_id', $packing->id )->count() )->toBe( 30 );
} );

it( 'changes nothing when the engine refuses one order', function (): void {
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    $orders  = Order::factory()->count( 3 )->create( [ 'system_status' => 'processing' ] );
    $refused = $orders->last()->id;

    addFilter( 'ap.ecommerce.order.canTransitionSubstatus', static fn ( bool $allowed, Order $order ): bool => $order->id !== $refused );

    $component = Livewire::test( Index::class )
        ->call( 'selectAllMatchingRows' )
        ->set( 'bulkSubstatusId', $packing->id )
        ->call( 'runBulkAction', 'change-substatus' );

    removeAllFilters( 'ap.ecommerce.order.canTransitionSubstatus' );

    expect( Order::query()->whereNotNull( 'substatus_id' )->count() )->toBe( 0 )
        ->and( sentToasts( $component ) )->toContain( 'No orders were changed.' );
} );

it( 'requires a sub-status for the bulk change', function (): void {
    $order = Order::factory()->create( [ 'system_status' => 'processing' ] );

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $order->id ] )
        ->call( 'runBulkAction', 'change-substatus' )
        ->assertHasErrors( [ 'bulkSubstatusId' => 'required' ] );
} );

it( 'refuses a sub-status that does not fit every selected order', function (): void {
    $packing    = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    $processing = Order::factory()->create( [ 'system_status' => 'processing' ] );
    $pending    = Order::factory()->create( [ 'system_status' => 'pending' ] );

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $processing->id, (string) $pending->id ] )
        ->assertSee( 'The selected orders have different statuses, so no sub-status fits all of them.' )
        ->set( 'bulkSubstatusId', $packing->id )
        ->call( 'runBulkAction', 'change-substatus' )
        ->assertHasErrors( [ 'bulkSubstatusId' ] );

    expect( Order::query()->whereNotNull( 'substatus_id' )->count() )->toBe( 0 );
} );

it( 'refuses the bulk change without order.update', function (): void {
    Gate::define( 'ecommerce.order.update', static fn (): bool => false );
    $order = Order::factory()->create();

    Livewire::test( Index::class )
        ->assertDontSee( 'Change sub-status' )
        ->set( 'selected', [ (string) $order->id ] )
        ->call( 'runBulkAction', 'change-substatus' )
        ->assertForbidden();
} );

it( 'warns instead of running a bulk action with nothing selected', function (): void {
    $component = Livewire::test( Index::class )->call( 'runBulkAction', 'export' );

    expect( sentToasts( $component ) )->toContain( 'Select at least one row first.' );
} );

it( 'exports every order matching the filters as CSV', function (): void {
    Order::factory()->create( [ 'order_number' => 'PAID0001', 'payment_status' => 'paid', 'total_amount' => 1050, 'total_currency' => 'USD' ] );
    Order::factory()->create( [ 'order_number' => 'PEND0001', 'payment_status' => 'pending' ] );

    $rows = downloadedCsv( Livewire::test( Index::class )->set( 'filters.payment_status', 'paid' )->call( 'exportCsv' ) );

    expect( $rows[0] )->toBe( [ 'Order', 'Customer', 'Placed', 'Status', 'Sub-status', 'Payment', 'Fulfillment', 'Items', 'Total' ] )
        ->and( $rows )->toHaveCount( 2 )
        ->and( $rows[1][0] )->toBe( 'PAID0001' )
        ->and( $rows[1][5] )->toBe( 'Paid' )
        ->and( $rows[1][8] )->toBe( '10.50 USD' );
} );

it( 'exports only the selection from the bulk bar', function (): void {
    $chosen = Order::factory()->create( [ 'order_number' => 'PICK0001' ] );
    Order::factory()->create( [ 'order_number' => 'SKIP0001' ] );

    $rows = downloadedCsv( Livewire::test( Index::class )->set( 'selected', [ (string) $chosen->id ] )->call( 'runBulkAction', 'export' ) );

    expect( array_column( array_slice( $rows, 1 ), 0 ) )->toBe( [ 'PICK0001' ] );
} );

it( 'returns no rows when the export data is requested directly', function (): void {
    Order::factory()->create();

    Livewire::test( Index::class )
        ->call( 'getTableExportData' )
        ->assertReturned( [] );
} );

it( 'neutralizes spreadsheet formulas in exported cells', function (): void {
    Order::factory()->create( [ 'order_number' => 'EVIL0001', 'email' => '=HYPERLINK("http://evil.test")@example.test' ] );

    $rows = downloadedCsv( Livewire::test( Index::class )->call( 'exportCsv' ) );

    // The engine stores emails lowercased; the guard is what matters.
    expect( strtolower( $rows[1][1] ) )->toStartWith( "'=hyperlink" );
} );

it( 'caps an export at tables.export_max_rows and says so', function (): void {
    config()->set( 'artisanpack.ecommerce-admin-livewire.tables.export_max_rows', 2 );
    Order::factory()->count( 3 )->create();

    $component = Livewire::test( Index::class )->call( 'exportCsv' );

    expect( downloadedCsv( $component ) )->toHaveCount( 3 )
        ->and( sentToasts( $component ) )->toContain( 'The export was cut short.' );
} );

it( 'lets satellites add columns, filters, and bulk actions', function (): void {
    $subscribed = Order::factory()->create( [ 'order_number' => 'SUBS0001', 'meta' => [ 'subscription' => 'monthly' ] ] );
    Order::factory()->create( [ 'order_number' => 'ONCE0001' ] );

    addFilter( 'ap.ecommerceAdminLivewire.table.orders.columns', static fn ( array $columns ): array => [
        ...$columns,
        [ 'key' => 'subscription', 'label' => 'Subscription', 'value' => static fn ( Order $order ): string => (string) data_get( $order->meta, 'subscription', 'None' ) ],
    ] );
    addFilter( 'ap.ecommerceAdminLivewire.table.orders.filters', static fn ( array $filters ): array => [
        ...$filters,
        [ 'key' => 'subscribed', 'label' => 'Subscribed', 'type' => 'boolean', 'apply' => static fn ( $query, string $value ) => '1' === $value ? $query->whereNotNull( $query->qualifyColumn( 'meta' ) . '->subscription' ) : $query->whereNull( $query->qualifyColumn( 'meta' ) . '->subscription' ) ],
    ] );
    addFilter( 'ap.ecommerceAdminLivewire.table.orders.bulkActions', static fn ( array $actions ): array => [
        ...$actions,
        [ 'key' => 'archive', 'label' => 'Archive', 'ability' => 'order.update', 'confirm' => 'Archive the selected orders?', 'handler' => static function ( $selection ): string {
            $selection->update( [ 'customer_note' => 'archived' ] );

            return 'Archived.';
        } ],
    ] );

    $component = Livewire::test( Index::class )
        ->assertSee( 'Subscription' )
        ->assertSee( 'monthly' )
        ->set( 'filters.subscribed', '1' )
        ->assertSee( '#SUBS0001' )
        ->assertDontSee( '#ONCE0001' )
        ->set( 'selected', [ (string) $subscribed->id ] )
        ->call( 'runBulkAction', 'archive' )
        ->assertSee( 'Archive the selected orders?' );

    expect( Order::query()->where( 'customer_note', 'archived' )->count() )->toBe( 0 );

    $component->call( 'confirmBulkAction', confirmToken( $component ) )->assertDontSee( 'Archive the selected orders?' );

    expect( Order::query()->where( 'customer_note', 'archived' )->count() )->toBe( 1 )
        ->and( downloadedCsv( Livewire::test( Index::class )->call( 'exportCsv' ) )[0] )->toContain( 'Subscription' );
} );

it( 'dismisses a pending confirmation when the selection changes', function (): void {
    addFilter( 'ap.ecommerceAdminLivewire.table.orders.bulkActions', static fn ( array $actions ): array => [
        ...$actions,
        [ 'key' => 'delete', 'label' => 'Delete', 'confirm' => 'Delete the selected orders?', 'handler' => static function ( $selection ): string {
            $selection->delete();

            return 'Deleted.';
        } ],
    ] );
    [ $first, $second ] = Order::factory()->count( 2 )->create()->all();

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $first->id ] )
        ->call( 'runBulkAction', 'delete' )
        ->assertSee( 'Delete the selected orders?' )
        ->call( 'selectAllMatchingRows' )
        ->assertSet( 'confirmingBulkAction', null )
        ->assertDontSee( 'Delete the selected orders?' )
        ->call( 'clearSelection' )
        ->set( 'selected', [ (string) $first->id ] )
        ->call( 'runBulkAction', 'delete' )
        ->set( 'selected', [ (string) $first->id, (string) $second->id ] )
        ->assertSet( 'confirmingBulkAction', null );

    expect( Order::query()->count() )->toBe( 2 );
} );

it( 'runs a confirmed bulk action once even when confirm is sent twice', function (): void {
    $counter       = new stdClass();
    $counter->runs = 0;

    addFilter( 'ap.ecommerceAdminLivewire.table.orders.bulkActions', static fn ( array $actions ): array => [
        ...$actions,
        [ 'key' => 'delete', 'label' => 'Delete', 'confirm' => 'Delete?', 'handler' => static function () use ( $counter ): string {
            $counter->runs++;

            return 'Deleted.';
        } ],
    ] );
    $order = Order::factory()->create();

    $component = Livewire::test( Index::class )
        ->set( 'selected', [ (string) $order->id ] )
        ->call( 'runBulkAction', 'delete' );

    $token = confirmToken( $component );

    $component->call( 'confirmBulkAction', $token );
    $component->call( 'runBulkAction', 'delete' )->call( 'confirmBulkAction', $token );

    expect( $counter->runs )->toBe( 1 );
} );

it( 'loads a page of orders in a fixed number of queries', function (): void {
    $packing = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );

    $seed = static function ( int $count ) use ( $packing ): void {
        foreach ( range( 1, $count ) as $index ) {
            $order = Order::factory()->create( [
                'customer_id'  => Customer::factory()->create()->id,
                'substatus_id' => $packing->id,
            ] );
            OrderItem::factory()->create( [ 'order_id' => $order->id ] );
        }
    };

    $count = static function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test( Index::class )->assertOk();

        $queries = count( DB::getQueryLog() );
        DB::disableQueryLog();

        return $queries;
    };

    $seed( 2 );
    $few = $count();

    $seed( 10 );
    $many = $count();

    expect( $many )->toBe( $few );
} );

it( 'translates its strings', function (): void {
    app( 'translator' )->addLines( [ '*.Orders' => 'Pedidos', '*.No orders yet' => 'Todavía no hay pedidos' ], 'es', '*' );
    app()->setLocale( 'es' );

    Livewire::test( Index::class )
        ->assertSee( 'Pedidos' )
        ->assertSee( 'Todavía no hay pedidos' );
} );

it( 'tells screen readers how many results the table holds', function (): void {
    Order::factory()->count( 3 )->create();

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-live-region>3 results</div>' )
        ->set( 'search', 'nothing-matches-this' )
        ->assertSeeHtml( 'data-live-region>0 results</div>' );
} );

it( 'renders the page header with its description and the table in board order', function (): void {
    $customer = Customer::factory()->create( [ 'first_name' => 'Maya', 'last_name' => 'Chen' ] );
    Order::factory()->create( [ 'order_number' => '1048', 'customer_id' => $customer->id, 'email' => 'maya@example.test' ] );

    Livewire::test( Index::class )
        ->assertSee( 'Every order placed in your store, newest first.' )
        ->assertSeeHtml( 'placeholder="Search by number, customer or email"' )
        ->assertSeeInOrder( [ 'Order', 'Customer', 'Placed', 'Status', 'Sub-status', 'Payment', 'Fulfillment', 'Items', 'Total' ] )
        ->assertSeeHtml( 'class="apui-link" data-standalone><bdi dir="ltr" class="ec-mono">#1048</bdi>' )
        ->assertSeeHtml( 'data-part="primary"' )
        ->assertSeeHtml( 'data-part="secondary"><bdi dir="ltr">maya@example.test</bdi>' )
        ->assertSeeHtml( 'data-orders-table' )
        ->assertDontSeeHtml( 'link-hover' );
} );

it( 'shows the new orders at the top of the first page, marked New', function (): void {
    $old = Order::factory()->create( [ 'order_number' => 'OLD0001', 'placed_at' => now()->subDay() ] );

    $component = Livewire::test( Index::class )
        ->set( 'sortColumn', 'total' )
        ->set( 'sortDirection', 'asc' );

    $new = Order::factory()->count( 2 )->create( [ 'placed_at' => now() ] );

    $component->call( 'orderBroadcast' )
        ->assertSeeHtml( 'data-show-new-orders' )
        ->assertDontSeeHtml( 'data-new-order=' )
        ->call( 'showNewOrders' )
        ->assertSet( 'newOrders', 0 )
        ->assertSet( 'sortColumn', '' )
        ->assertSet( 'highlightAfterId', $old->id )
        ->assertSet( 'liveAnnouncement', '2 new orders shown at the top of the table.' )
        ->assertDispatched( 'ecommerce-admin-new-orders-shown' )
        ->assertDontSeeHtml( 'data-new-orders=' );

    expect( substr_count( $component->html(), 'data-new-order=' ) )->toBe( 2 );

    // Orders after that only count toward the next notice.
    Order::factory()->create();

    $component->call( 'orderBroadcast' )->assertSet( 'newOrders', 1 );
} );

it( 'does nothing when there are no new orders to show', function (): void {
    Livewire::test( Index::class )
        ->call( 'showNewOrders' )
        ->assertSet( 'highlightAfterId', null )
        ->assertNotDispatched( 'ecommerce-admin-new-orders-shown' );
} );

it( 'starts the next count from where a dismissed notice left off', function (): void {
    Order::factory()->create();

    $component = Livewire::test( Index::class );

    Order::factory()->create();

    $component->call( 'orderBroadcast' )->call( 'dismissNewOrders' );

    Order::factory()->create();

    $component->call( 'orderBroadcast' )
        ->assertSet( 'newOrders', 1 )
        ->call( 'showNewOrders' );

    expect( substr_count( $component->html(), 'data-new-order=' ) )->toBe( 1 );
} );

it( 'leaves out the sub-status change when no sub-status fits every selected order', function (): void {
    OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'key' => 'packing', 'label' => 'Packing' ] );
    $processing = Order::factory()->create( [ 'system_status' => 'processing' ] );
    $pending    = Order::factory()->create( [ 'system_status' => 'pending' ] );

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $processing->id ] )
        ->assertSeeHtml( 'aria-label="Move to sub-status"' )
        ->assertSee( 'Change sub-status' )
        ->set( 'selected', [ (string) $processing->id, (string) $pending->id ] )
        ->assertSeeHtml( 'data-bulk-substatus-mixed' )
        ->assertDontSee( 'Change sub-status' )
        ->assertSee( 'Export CSV' );
} );

it( 'filters by several fulfillment statuses at once', function (): void {
    Order::factory()->create( [ 'order_number' => 'UNFU0001', 'fulfillment_status' => 'unfulfilled' ] );
    Order::factory()->create( [ 'order_number' => 'PART0001', 'fulfillment_status' => 'partial' ] );
    Order::factory()->create( [ 'order_number' => 'FULL0001', 'fulfillment_status' => 'fulfilled' ] );

    Livewire::test( Index::class )
        ->set( 'filters.fulfillment_status', [ 'unfulfilled', 'partial' ] )
        ->assertSee( '#UNFU0001' )
        ->assertSee( '#PART0001' )
        ->assertDontSee( '#FULL0001' );

    // A single value from an older URL still filters.
    Livewire::withQueryParams( [ 'filters' => [ 'fulfillment_status' => 'fulfilled' ] ] )
        ->test( Index::class )
        ->assertSee( '#FULL0001' )
        ->assertDontSee( '#PART0001' );
} );

it( 'filters by a total range in the store currency', function (): void {
    Order::factory()->create( [ 'order_number' => 'LOW00001', 'total_amount' => 999, 'total_currency' => 'USD' ] );
    Order::factory()->create( [ 'order_number' => 'MID00001', 'total_amount' => 5000, 'total_currency' => 'USD' ] );
    Order::factory()->create( [ 'order_number' => 'HIGH0001', 'total_amount' => 20000, 'total_currency' => 'USD' ] );
    Order::factory()->create( [ 'order_number' => 'EURO0001', 'total_amount' => 5000, 'total_currency' => 'EUR' ] );

    Livewire::test( Index::class )
        ->assertSee( 'Total (USD)' )
        ->set( 'filters.total', [ 'min' => '10', 'max' => '100.00' ] )
        ->assertSee( '#MID00001' )
        ->assertDontSee( '#LOW00001' )
        ->assertDontSee( '#HIGH0001' )
        ->assertDontSee( '#EURO0001' )
        ->set( 'filters.total', [ 'min' => 'abc' ] )
        ->assertSee( '#EURO0001' );
} );

it( 'hides the live region with the design system\'s class, not Tailwind\'s', function (): void {
    Livewire::test( Index::class )
        ->assertSeeHtml( '<div class="apui-sr-only" role="status" aria-live="polite" aria-atomic="true" data-live-region>' );
} );

it( 'marks the first orders New when the page opened with none', function (): void {
    $component = Livewire::test( Index::class )->assertSet( 'noticeSinceId', 0 );

    Order::factory()->count( 2 )->create();

    $component->call( 'orderBroadcast' )
        ->call( 'showNewOrders' )
        ->assertSet( 'highlightAfterId', 0 );

    expect( substr_count( $component->html(), 'data-new-order=' ) )->toBe( 2 );
} );

it( 'leaves orders after the shown range for the next notice', function (): void {
    Order::factory()->create();

    $component = Livewire::test( Index::class );
    $shown     = Order::factory()->create();

    $component->call( 'orderBroadcast' )->call( 'showNewOrders' )->assertSet( 'highlightUpToId', $shown->id );

    $later = Order::factory()->create( [ 'order_number' => 'LATER001' ] );

    $component->call( 'orderBroadcast' )->assertSet( 'newOrders', 1 );

    $rows = collect( explode( '<tr', $component->html() ) );
    $row  = static fn ( string $number ): string => (string) $rows->first( static fn ( string $markup ): bool => str_contains( $markup, '#' . $number . '<' ) );

    expect( substr_count( $component->html(), 'data-new-order=' ) )->toBe( 1 )
        ->and( $row( (string) $shown->order_number ) )->toContain( 'data-new-order=' )
        ->and( $row( 'LATER001' ) )->not->toContain( 'data-new-order=' );
} );

it( 'does not count an order again that "Show new orders" already showed', function (): void {
    Order::factory()->create();

    $component = Livewire::test( Index::class );

    Order::factory()->create();
    $component->call( 'orderBroadcast' )->assertSet( 'newOrders', 1 );

    // Committed after that broadcast, before the click: the table shows it.
    $unannounced = Order::factory()->create();

    $component->call( 'showNewOrders' )
        ->assertSet( 'highlightUpToId', $unannounced->id )
        ->assertSet( 'liveAnnouncement', '2 new orders shown at the top of the table.' );

    // Its own broadcast arrives late.
    $component->call( 'orderBroadcast' )
        ->assertSet( 'newOrders', 0 )
        ->assertDontSeeHtml( 'data-new-orders=' );
} );

it( 'counts only new orders the search and filters let into the table', function (): void {
    Order::factory()->create();

    $component = Livewire::test( Index::class )->set( 'filters.payment_status', 'paid' );

    Order::factory()->create( [ 'payment_status' => 'paid' ] );
    Order::factory()->create( [ 'payment_status' => 'pending' ] );

    $component->call( 'orderBroadcast' )
        ->assertSet( 'newOrders', 1 )
        ->assertSee( '1 new order since you opened this page.' );

    // Only pending orders arrive: the notice doesn't move.
    Order::factory()->create( [ 'payment_status' => 'pending' ] );

    $component->call( 'orderBroadcast' )->assertSet( 'newOrders', 1 );
} );

it( 'says when none of the new orders match by the time they are shown', function (): void {
    Order::factory()->create();

    $component = Livewire::test( Index::class );

    Order::factory()->create( [ 'payment_status' => 'pending' ] );

    $component->call( 'orderBroadcast' )
        ->set( 'filters.payment_status', 'paid' )
        ->call( 'showNewOrders' )
        ->assertSet( 'newOrders', 0 )
        ->assertSet( 'liveAnnouncement', 'None of the new orders match the search and filters.' );
} );
