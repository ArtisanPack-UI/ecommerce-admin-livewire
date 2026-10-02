<?php

/**
 * Orders index component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders;

use ArtisanPackUI\Ecommerce\Exceptions\IncompatibleBoardSubstatusException;
use ArtisanPackUI\Ecommerce\Exceptions\SubstatusTransitionRejectedException;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\Ecommerce\Services\OrderStatusMachine;
use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\ListensForAdminBroadcasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithResourceTable;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\OrdersQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminBroadcasts;
use ArtisanPackUI\EcommerceAdminLivewire\Support\MinorUnits;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StatusPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The orders index (spec §7.3): find and triage orders without the kanban
 * board.
 *
 * Bulk actions: change sub-status (offered only for a sub-status that fits
 * every selected order) and export. There is no bulk refund or cancel.
 *
 * With real-time updates on (see {@see AdminBroadcasts}), the list
 * re-renders when the engine broadcasts an order change, so new orders
 * appear at the top of the default (newest first) sort, and a notice says
 * how many arrived since the page was opened or the notice was dismissed.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Index extends Component
{
    use AuthorizesEcommerce;
    use ListensForAdminBroadcasts;
    use SendsToasts;
    use WithActionToken;
    use WithResourceTable;

    /**
     * The sub-status the "change sub-status" bulk action moves orders to.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $bulkSubstatusId = null;

    /**
     * The newest order id the user has been told about.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $latestOrderId = 0;

    /**
     * Orders that arrived since the notice was last dismissed.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $newOrders = 0;

    /**
     * Authorizes the screen.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeTable();

        $this->latestOrderId = (int) Order::query()->max( 'id' );
    }

    /**
     * Re-renders after an order broadcast and counts orders that are new
     * since the last look. The count comes from the database, not the
     * broadcast payload.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function orderBroadcast(): void
    {
        $latest = (int) Order::query()->max( 'id' );

        if ( $latest <= $this->latestOrderId ) {
            return;
        }

        $arrived              = Order::query()->where( 'id', '>', $this->latestOrderId )->where( 'id', '<=', $latest )->count();
        $this->latestOrderId  = $latest;
        $this->newOrders += $arrived;

        $this->liveAnnouncement = trans_choice( ':count new order arrived.|:count new orders arrived.', $arrived, [ 'count' => $arrived ] );
    }

    /**
     * Dismisses the new-orders notice.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function dismissNewOrders(): void
    {
        $this->newOrders        = 0;
        $this->liveAnnouncement = '';
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $data = $this->resourceTableData();

        return view( 'ecommerce-admin::livewire.orders.index', $data + [
            'bulkSubstatusOptions' => $data['tableSelectionCount'] > 0 ? $this->bulkSubstatusOptions() : [],
        ] );
    }

    /**
     * The customer's full name, or null for a guest.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return string|null
     */
    public static function customerName( Order $order ): ?string
    {
        $name = trim( ( $order->customer?->first_name ?? '' ) . ' ' . ( $order->customer?->last_name ?? '' ) );

        return '' === $name ? null : $name;
    }

    /**
     * The broadcasts the list refreshes on.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function adminBroadcasts(): array
    {
        return [
            AdminBroadcasts::ORDER_STATUS_CHANGED => 'orderBroadcast',
            AdminBroadcasts::PAYMENT_SUCCEEDED    => 'orderBroadcast',
        ];
    }

    /**
     * Moves the selected orders to `$bulkSubstatusId`.
     *
     * All or nothing: every order must be on the sub-status's system status,
     * and one refused change rolls back the rest.
     *
     * @since 1.0.0
     *
     * @param  Builder  $selection  The selected orders.
     *
     * @return string|null The success message.
     */
    protected function changeSubstatus( Builder $selection ): ?string
    {
        $this->validate(
            [ 'bulkSubstatusId' => [ 'required', 'integer', Rule::exists( OrderSubstatus::class, 'id' ) ] ],
            [],
            [ 'bulkSubstatusId' => __( 'sub-status' ) ],
        );

        $substatus = OrderSubstatus::query()->findOrFail( (int) $this->bulkSubstatusId );

        if ( ( clone $selection )->where( 'orders.system_status', '!=', $substatus->system_status )->exists() ) {
            $this->addError( 'bulkSubstatusId', __( '":substatus" does not fit every selected order. Pick a sub-status of the orders\' current status.', [ 'substatus' => $substatus->label ] ) );

            return null;
        }

        // Collect the ids first: paging through the selection while moving
        // orders would skip rows whenever the move takes them out of a filter.
        $ids     = ( clone $selection )->reorder()->pluck( 'orders.id' )->all();
        $machine = app( OrderStatusMachine::class );
        $actorId = auth()->id();

        foreach ( array_chunk( $ids, 200 ) as $chunk ) {
            foreach ( Order::query()->whereKey( $chunk )->get() as $order ) {
                $this->authorizeEcommerce( 'update', $order );
            }
        }

        try {
            DB::transaction( static function () use ( $ids, $substatus, $machine, $actorId ): void {
                foreach ( array_chunk( $ids, 200 ) as $chunk ) {
                    foreach ( Order::query()->whereKey( $chunk )->get() as $order ) {
                        $machine->setSubstatus( $order, $substatus, is_numeric( $actorId ) ? (int) $actorId : null, __( 'Bulk change from the orders list' ) );
                    }
                }
            } );
        } catch ( IncompatibleBoardSubstatusException | SubstatusTransitionRejectedException $exception ) {
            $this->toastError( __( 'No orders were changed.' ), $exception->getMessage() );

            return null;
        }

        $moved = count( $ids );

        $this->bulkSubstatusId = null;

        return trans_choice(
            ':count order moved to ":substatus".|:count orders moved to ":substatus".',
            $moved,
            [ 'count' => $moved, 'substatus' => $substatus->label ],
        );
    }

    /**
     * The sub-statuses that fit every selected order: those of their shared
     * system status, or none when the selection spans several.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function bulkSubstatusOptions(): array
    {
        $statuses = $this->selectionQuery()->reorder()->select( 'orders.system_status' )->distinct()->pluck( 'system_status' );

        if ( 1 !== $statuses->count() ) {
            return [];
        }

        return OrderSubstatus::query()
            ->where( 'system_status', (string) $statuses->first() )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get()
            ->map( static fn ( OrderSubstatus $substatus ): array => [ 'id' => (int) $substatus->id, 'name' => (string) $substatus->label ] )
            ->all();
    }

    /**
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeTable(): void
    {
        $this->authorizeEcommerce( 'viewAny', Order::class );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableScreen(): string
    {
        return 'orders';
    }

    /**
     * @since 1.0.0
     *
     * @return ResourceQuery
     */
    protected function tableQuery(): ResourceQuery
    {
        return new OrdersQuery();
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableCaption(): string
    {
        return __( 'Orders' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableColumns(): array
    {
        $cells = 'ecommerce-admin::livewire.orders.cells.';

        return [
            [
                'key'      => 'number',
                'label'    => __( 'Order' ),
                'sortable' => true,
                'view'     => $cells . 'number',
                'export'   => static fn ( Order $order ): string => (string) $order->order_number,
            ],
            [
                'key'      => 'placed',
                'label'    => __( 'Placed' ),
                'sortable' => true,
                'value'    => static fn ( Order $order ): string => null === $order->placed_at ? '' : LocalizedDate::format( $order->placed_at ),
                'export'   => static fn ( Order $order ): string => $order->placed_at?->format( DATE_ATOM ) ?? '',
            ],
            [
                'key'      => 'customer',
                'label'    => __( 'Customer' ),
                'sortable' => true,
                'view'     => $cells . 'customer',
                'export'   => static fn ( Order $order ): string => self::customerName( $order ) ?? (string) $order->email,
            ],
            [
                'key'      => 'system_status',
                'label'    => __( 'Status' ),
                'sortable' => true,
                'view'     => $cells . 'system-status',
                'export'   => static fn ( Order $order ): string => StatusPresenter::present( 'system', (string) $order->system_status )['label'],
            ],
            [
                'key'    => 'substatus',
                'label'  => __( 'Sub-status' ),
                'view'   => $cells . 'substatus',
                'export' => static fn ( Order $order ): string => (string) ( $order->substatus?->label ?? '' ),
            ],
            [
                'key'      => 'payment_status',
                'label'    => __( 'Payment' ),
                'sortable' => true,
                'view'     => $cells . 'payment-status',
                'export'   => static fn ( Order $order ): string => StatusPresenter::present( 'payment', (string) $order->payment_status )['label'],
            ],
            [
                'key'      => 'fulfillment_status',
                'label'    => __( 'Fulfillment' ),
                'sortable' => true,
                'view'     => $cells . 'fulfillment-status',
                'export'   => static fn ( Order $order ): string => StatusPresenter::present( 'fulfillment', (string) $order->fulfillment_status )['label'],
            ],
            [
                'key'      => 'total',
                'label'    => __( 'Total' ),
                'sortable' => true,
                'class'    => 'text-end',
                'view'     => $cells . 'total',
                'export'   => static fn ( Order $order ): string => MinorUnits::toMajor( (int) $order->total_amount, (string) $order->total_currency ) . ' ' . $order->total_currency,
            ],
            [
                'key'      => 'items',
                'label'    => __( 'Items' ),
                'sortable' => true,
                'class'    => 'text-end',
                'value'    => static fn ( Order $order ): int => (int) ( $order->items_sum_quantity ?? 0 ),
            ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableFilters(): array
    {
        return [
            [ 'key' => 'system_status', 'label' => __( 'Status' ), 'type' => 'select', 'options' => self::statusOptions( 'system' ) ],
            [ 'key' => 'substatus', 'label' => __( 'Sub-status' ), 'type' => 'select', 'options' => self::substatusOptions() ],
            [ 'key' => 'payment_status', 'label' => __( 'Payment' ), 'type' => 'select', 'options' => self::statusOptions( 'payment' ) ],
            [ 'key' => 'fulfillment_status', 'label' => __( 'Fulfillment' ), 'type' => 'select', 'options' => self::statusOptions( 'fulfillment' ) ],
            [ 'key' => 'awaiting', 'label' => __( 'Awaiting fulfillment' ), 'type' => 'boolean' ],
            [ 'key' => 'placed', 'label' => __( 'Placed' ), 'type' => 'date-range' ],
            [ 'key' => 'currency', 'label' => __( 'Currency' ), 'type' => 'select', 'options' => self::currencyOptions() ],
            [ 'key' => 'board', 'label' => __( 'Board' ), 'type' => 'select', 'options' => self::boardOptions() ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableBulkActions(): array
    {
        return [
            [
                'key'     => 'change-substatus',
                'label'   => __( 'Change sub-status' ),
                'icon'    => 'o-arrows-right-left',
                'ability' => 'order.update',
                'handler' => fn ( Builder $selection ): ?string => $this->changeSubstatus( $selection ),
            ],
            $this->exportBulkAction(),
        ];
    }

    /**
     * The known values of a status type as filter options.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The status type.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function statusOptions( string $type ): array
    {
        return array_values( array_map(
            static fn ( string $value, array $status ): array => [ 'id' => $value, 'name' => $status[0] ],
            array_keys( StatusPresenter::statuses( $type ) ),
            StatusPresenter::statuses( $type ),
        ) );
    }

    /**
     * Every sub-status, labelled with its system status.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function substatusOptions(): array
    {
        return OrderSubstatus::query()
            ->orderBy( 'system_status' )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get()
            ->map( static fn ( OrderSubstatus $substatus ): array => [
                'id'   => (string) $substatus->id,
                'name' => __( ':status: :substatus', [
                    'status'    => StatusPresenter::present( 'system', (string) $substatus->system_status )['label'],
                    'substatus' => $substatus->label,
                ] ),
            ] )
            ->all();
    }

    /**
     * The currencies orders were placed in.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function currencyOptions(): array
    {
        /** @var Collection<int, string> $currencies */
        $currencies = Order::query()->distinct()->orderBy( 'currency' )->pluck( 'currency' );

        return $currencies->map( static fn ( string $currency ): array => [ 'id' => $currency, 'name' => $currency ] )->values()->all();
    }

    /**
     * The kanban boards.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function boardOptions(): array
    {
        return KanbanBoard::query()
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get( [ 'id', 'name' ] )
            ->map( static fn ( KanbanBoard $board ): array => [ 'id' => (string) $board->id, 'name' => (string) $board->name ] )
            ->all();
    }
}
