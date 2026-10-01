<?php

/**
 * Order detail component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders;

use ArtisanPackUI\Ecommerce\Exceptions\IncompatibleBoardSubstatusException;
use ArtisanPackUI\Ecommerce\Exceptions\KanbanOperationException;
use ArtisanPackUI\Ecommerce\Models\DigitalDownload;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\LicenseKey;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\PromotionUsage;
use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\Ecommerce\Services\KanbanRoutingService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\MinorUnits;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The order detail page (spec §7.3): items, totals, customer, addresses,
 * payment, board assignments, digital deliveries, and the panels other
 * issues and satellites register with {@see OrderPanelRegistry}.
 *
 * Everything renders from the order and its `product_snapshot`s, so an
 * order whose products were deleted still renders in full.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Show extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;

    /**
     * The order id.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $orderId = 0;

    /**
     * The board chosen in "add to board".
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $boardToAdd = null;

    /**
     * Re-render when a panel changes the order.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    protected $listeners = [ OrderPanelRegistry::ORDER_UPDATED_EVENT => '$refresh' ];

    /**
     * The order, loaded once per request.
     *
     * @since 1.0.0
     *
     * @var Order|null
     */
    private ?Order $loadedOrder = null;

    /**
     * Loads and authorizes the order.
     *
     * @since 1.0.0
     *
     * @param  int|string  $order  The order id.
     *
     * @return void
     */
    public function mount( int|string $order ): void
    {
        $this->orderId = (int) $order;

        $this->authorizeEcommerce( 'view', $this->order() );
    }

    /**
     * Re-authorizes on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeEcommerce( 'view', $this->order() );
    }

    /**
     * Puts the order on a board, in the board's entry column.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addToBoard(): void
    {
        $order = $this->order();

        $this->authorizeEcommerceAbility( 'kanbanCard.move', $order );

        $this->validate(
            [
                'boardToAdd' => [
                    'required',
                    'integer',
                    Rule::exists( KanbanBoard::class, 'id' )->where( 'is_active', true ),
                    Rule::notIn( $this->activeBoardIds() ),
                ],
            ],
            [ 'boardToAdd.not_in' => __( 'The order is already on that board.' ) ],
            [ 'boardToAdd' => __( 'board' ) ],
        );

        $board = KanbanBoard::query()->findOrFail( (int) $this->boardToAdd );

        try {
            app( KanbanRoutingService::class )->assign( $order, $board, null, $this->actorId() );
        } catch ( KanbanOperationException | IncompatibleBoardSubstatusException $exception ) {
            $this->addError( 'boardToAdd', $exception->getMessage() );

            return;
        }

        $this->boardToAdd  = null;
        $this->loadedOrder = null;

        $this->toastSuccess( __( 'Added to ":board".', [ 'board' => $board->name ] ) );
    }

    /**
     * Takes the order off a board.
     *
     * @since 1.0.0
     *
     * @param  int  $boardId  The board id.
     *
     * @return void
     */
    public function removeFromBoard( int $boardId ): void
    {
        $order = $this->order();

        $this->authorizeEcommerceAbility( 'kanbanCard.move', $order );

        $board = KanbanBoard::query()->find( $boardId );

        if ( null === $board || ! in_array( $boardId, $this->activeBoardIds(), true ) ) {
            $this->toastError( __( 'The order is not on that board.' ) );

            return;
        }

        try {
            app( KanbanRoutingService::class )->remove( $order, $board, $this->actorId() );
        } catch ( KanbanOperationException $exception ) {
            $this->toastError( __( 'The order could not be taken off ":board".', [ 'board' => $board->name ] ), $exception->getMessage() );

            return;
        }

        $this->loadedOrder = null;

        $this->toastSuccess( __( 'Removed from ":board".', [ 'board' => $board->name ] ) );
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
        $order     = $this->order();
        $panels    = app( OrderPanelRegistry::class );
        $canMove   = Authorization::allows( auth()->user(), 'kanbanCard.move', $order );
        $onBoards  = $this->activeBoardIds();
        $boardList = $canMove
            ? KanbanBoard::query()->where( 'is_active', true )->whereNotIn( 'id', $onBoards )->orderBy( 'position' )->orderBy( 'id' )->get( [ 'id', 'name' ] )
            : new Collection();

        return view( 'ecommerce-admin::livewire.orders.show', [
            'order'           => $order,
            'items'           => $order->items,
            'totals'          => $this->totals( $order ),
            'customerName'    => Index::customerName( $order ),
            'customerUrl'     => $this->customerUrl( $order ),
            'isGuest'         => null === $order->customer || null === $order->customer->user_id,
            'paymentGateway'  => $this->gatewayLabel( $order ),
            'assignments'     => $order->boardAssignments,
            'canMoveBoards'   => $canMove,
            'boardOptions'    => $boardList->map( static fn ( KanbanBoard $board ): array => [ 'id' => (int) $board->id, 'name' => (string) $board->name ] )->all(),
            'downloads'       => $this->downloads( $order ),
            'licenseKeys'     => $this->licenseKeys( $order ),
            'canViewKeys'     => Authorization::allows( auth()->user(), 'licenseKey.view' ),
            'mainPanels'      => $panels->forColumn( 'main' ),
            'sidePanels'      => $panels->forColumn( 'side' ),
        ] );
    }

    /**
     * The snapshot name of an item.
     *
     * @since 1.0.0
     *
     * @param  OrderItem  $item  The item.
     *
     * @return string
     */
    public static function itemName( OrderItem $item ): string
    {
        $name = data_get( $item->product_snapshot, 'name' );

        return is_string( $name ) && '' !== $name ? $name : __( 'Item #:id', [ 'id' => $item->id ] );
    }

    /**
     * The snapshot options of an item as `label: value` strings.
     *
     * @since 1.0.0
     *
     * @param  OrderItem  $item  The item.
     *
     * @return array<int, string>
     */
    public static function itemOptions( OrderItem $item ): array
    {
        $options = [];

        foreach ( (array) data_get( $item->product_snapshot, 'options', [] ) as $label => $value ) {
            if ( is_array( $value ) && isset( $value['label'], $value['value'] ) ) {
                $options[] = $value['label'] . ': ' . ( is_scalar( $value['value'] ) ? $value['value'] : '' );
            } elseif ( is_scalar( $value ) && 'variant_id' !== $label ) {
                $options[] = is_string( $label ) ? $label . ': ' . $value : (string) $value;
            }
        }

        return $options;
    }

    /**
     * The order with everything the page shows.
     *
     * @since 1.0.0
     *
     * @return Order
     */
    protected function order(): Order
    {
        return $this->loadedOrder ??= Order::query()
            ->with( [
                'items',
                'customer',
                'substatus',
                'promotionUsages.promotion',
                'boardAssignments' => static fn ( $query ) => $query->whereNull( 'removed_at' )->with( [ 'board', 'substatus' ] )->orderBy( 'assigned_at' ),
            ] )
            ->findOrFail( $this->orderId );
    }

    /**
     * The totals block: lines in the order currency, plus the base-currency
     * equivalent when the order was placed in another currency.
     *
     * Discounts are listed per promotion when the order records usages. The
     * tax breakdown comes from the order's `meta.tax_lines`
     * (`[ { label, amount } ]`) when present, filtered through
     * `ap.ecommerceAdminLivewire.order.taxBreakdown`, else one "Tax" line.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array{currency: string, baseCurrency: string|null, lines: array<int, array{key: string, label: string, amount: int, base: int|null, emphasis: bool}>}
     */
    protected function totals( Order $order ): array
    {
        $currency = (string) $order->currency;
        $base     = (string) $order->base_currency;
        $convert  = $currency !== $base && (int) $order->fx_rate_to_base_e8 > 0;
        $toBase   = static fn ( int $amount ): ?int => $convert ? MinorUnits::convertWithRateE8( $amount, $currency, $base, (int) $order->fx_rate_to_base_e8 ) : null;
        $lines    = [];

        $add = static function ( string $key, string $label, int $amount, bool $emphasis = false ) use ( &$lines, $toBase ): void {
            $lines[] = [ 'key' => $key, 'label' => $label, 'amount' => $amount, 'base' => $toBase( $amount ), 'emphasis' => $emphasis ];
        };

        $add( 'subtotal', __( 'Subtotal' ), (int) $order->subtotal_amount );

        $usages = $order->promotionUsages->filter( static fn ( PromotionUsage $usage ): bool => (int) $usage->amount_discounted > 0 );

        if ( $usages->isNotEmpty() ) {
            foreach ( $usages as $usage ) {
                $add( 'discount-' . $usage->id, __( 'Discount: :promotion', [ 'promotion' => $usage->promotion?->name ?? __( 'Deleted promotion' ) ] ), -(int) $usage->amount_discounted );
            }
        } elseif ( (int) $order->discount_amount > 0 ) {
            $add( 'discount', __( 'Discount' ), -(int) $order->discount_amount );
        }

        $add( 'shipping', __( 'Shipping' ), (int) $order->shipping_amount );

        foreach ( $this->taxLines( $order ) as $index => $tax ) {
            $add( 'tax-' . $index, $tax['label'], $tax['amount'] );
        }

        $add( 'total', __( 'Total' ), (int) $order->total_amount, true );

        if ( (int) $order->total_refunded_amount > 0 ) {
            $add( 'refunded', __( 'Refunded' ), -(int) $order->total_refunded_amount );
            $add( 'net', __( 'Net' ), (int) $order->total_amount - (int) $order->total_refunded_amount, true );
        }

        return [
            'currency'     => $currency,
            'baseCurrency' => $convert ? $base : null,
            'lines'        => $lines,
        ];
    }

    /**
     * The tax lines.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array<int, array{label: string, amount: int}>
     */
    protected function taxLines( Order $order ): array
    {
        $lines = [];

        foreach ( (array) data_get( $order->meta, 'tax_lines', [] ) as $line ) {
            if ( is_array( $line ) && isset( $line['label'], $line['amount'] ) && is_numeric( $line['amount'] ) ) {
                $lines[] = [ 'label' => (string) $line['label'], 'amount' => (int) $line['amount'] ];
            }
        }

        if ( [] === $lines ) {
            $lines[] = [ 'label' => __( 'Tax' ), 'amount' => (int) $order->tax_amount ];
        }

        return array_values( array_filter(
            (array) applyFilters( 'ap.ecommerceAdminLivewire.order.taxBreakdown', $lines, $order ),
            static fn ( mixed $line ): bool => is_array( $line ) && isset( $line['label'], $line['amount'] ),
        ) );
    }

    /**
     * The download grants for the order's items. Tokens are never shown.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return Collection<int, DigitalDownload>
     */
    protected function downloads( Order $order ): Collection
    {
        return DigitalDownload::query()
            ->whereIn( 'order_item_id', $order->items->modelKeys() )
            ->with( 'file' )
            ->orderBy( 'id' )
            ->get();
    }

    /**
     * The license keys issued for the order's items.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return Collection<int, LicenseKey>
     */
    protected function licenseKeys( Order $order ): Collection
    {
        return LicenseKey::query()
            ->whereIn( 'order_item_id', $order->items->modelKeys() )
            ->with( 'file' )
            ->orderBy( 'id' )
            ->get();
    }

    /**
     * The payment gateway's label, falling back to its key.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return string|null
     */
    protected function gatewayLabel( Order $order ): ?string
    {
        $key = (string) $order->payment_gateway_key;

        if ( '' === $key ) {
            return null;
        }

        try {
            return app( PaymentGatewayRegistry::class )->find( $key )?->label() ?? $key;
        } catch ( Throwable ) {
            return $key;
        }
    }

    /**
     * The customer detail URL, once that screen exists.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return string|null
     */
    protected function customerUrl( Order $order ): ?string
    {
        $route = AdminNav::ROUTE_PREFIX . 'customers.show';

        if ( null === $order->customer_id || ! Route::has( $route ) || ! $this->canEcommerce( 'view', $order->customer ) ) {
            return null;
        }

        return route( $route, [ 'customer' => $order->customer_id ] );
    }

    /**
     * The ids of the boards the order is on.
     *
     * @since 1.0.0
     *
     * @return array<int, int>
     */
    private function activeBoardIds(): array
    {
        return $this->order()->boardAssignments
            ->map( static fn ( OrderBoardAssignment $assignment ): int => (int) $assignment->board_id )
            ->values()
            ->all();
    }

    /**
     * The signed-in user's id, for timeline entries.
     *
     * @since 1.0.0
     *
     * @return int|null
     */
    private function actorId(): ?int
    {
        $id = auth()->id();

        return is_numeric( $id ) ? (int) $id : null;
    }
}
