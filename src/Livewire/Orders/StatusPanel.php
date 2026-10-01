<?php

/**
 * Order status panel component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders;

use ArtisanPackUI\Ecommerce\Exceptions\IncompatibleBoardSubstatusException;
use ArtisanPackUI\Ecommerce\Exceptions\InvalidOrderStatusTransitionException;
use ArtisanPackUI\Ecommerce\Exceptions\OrderNotCancellableException;
use ArtisanPackUI\Ecommerce\Exceptions\SubstatusTransitionRejectedException;
use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\Ecommerce\Services\OrderCancellationService;
use ArtisanPackUI\Ecommerce\Services\OrderStatusMachine;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\Ecommerce\ValueObjects\OrderCancellationSummary;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\InteractsWithOrderPanel;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StatusPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

/**
 * The status panel on order detail (spec §7.3): moves the order between
 * system statuses, sets its sub-status, and cancels it.
 *
 * Only the transitions {@see OrderStatusMachine::ALLOWED_TRANSITIONS}
 * permits are offered, and every change goes through the machine so it
 * lands on the timeline. Cancelling is its own flow — a reason, a summary
 * of what will be released and whether a refund is still owed, and a
 * one-time action token — through {@see OrderCancellationService}.
 *
 * Sub-statuses that belong to another system status are listed but
 * disabled, and a sub-status or board column the order has outgrown is
 * explained rather than silently refused.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class StatusPanel extends Component
{
    use AuthorizesEcommerce;
    use InteractsWithOrderPanel;
    use SendsToasts;
    use WithActionToken;

    /**
     * The system status to move to.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $targetStatus = '';

    /**
     * Why the status is changing (optional, shown on the timeline).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $statusReason = '';

    /**
     * The sub-status to set.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $substatusId = null;

    /**
     * Whether the cancel confirmation is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $confirmingCancel = false;

    /**
     * Why the order is being cancelled.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $cancelReason = '';

    /**
     * Re-render when another panel changes the order.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    protected $listeners = [ OrderPanelRegistry::ORDER_UPDATED_EVENT => '$refresh' ];

    /**
     * Moves the order to {@see self::$targetStatus}.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function changeStatus(): void
    {
        $order = $this->order();

        $this->authorizeEcommerce( 'update', $order );

        $this->validate(
            [
                'targetStatus' => [ 'required', 'string', Rule::in( $this->statusTargets( $order ) ) ],
                'statusReason' => [ 'nullable', 'string', 'max:500' ],
            ],
            [ 'targetStatus.in' => __( 'The order cannot move to that status from :status.', [ 'status' => $this->statusLabel( (string) $order->system_status ) ] ) ],
            [ 'targetStatus' => __( 'status' ), 'statusReason' => __( 'reason' ) ],
        );

        $reason = trim( $this->statusReason );

        try {
            app( OrderStatusMachine::class )->transition( $order, $this->targetStatus, $this->actorId(), '' === $reason ? null : $reason );
        } catch ( InvalidOrderStatusTransitionException $exception ) {
            $this->addError( 'targetStatus', $exception->getMessage() );

            return;
        }

        $label = $this->statusLabel( $this->targetStatus );

        $this->reset( 'targetStatus', 'statusReason' );
        $this->orderChanged();

        $this->toastSuccess( __( 'Order moved to :status.', [ 'status' => $label ] ) );
    }

    /**
     * Sets the order's sub-status to {@see self::$substatusId}.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function changeSubstatus(): void
    {
        $order = $this->order();

        $this->authorizeEcommerce( 'update', $order );

        $this->validate(
            [ 'substatusId' => [ 'required', 'integer', Rule::exists( OrderSubstatus::class, 'id' ) ] ],
            [],
            [ 'substatusId' => __( 'sub-status' ) ],
        );

        $substatus = OrderSubstatus::query()->findOrFail( (int) $this->substatusId );

        if ( (string) $substatus->system_status !== (string) $order->system_status ) {
            $this->addError( 'substatusId', $this->substatusMismatch( $substatus, $order ) );

            return;
        }

        try {
            app( OrderStatusMachine::class )->setSubstatus( $order, $substatus, $this->actorId() );
        } catch ( IncompatibleBoardSubstatusException | SubstatusTransitionRejectedException $exception ) {
            $this->addError( 'substatusId', $exception->getMessage() );

            return;
        }

        $this->reset( 'substatusId' );
        $this->orderChanged();

        $this->toastSuccess( __( 'Sub-status set to ":substatus".', [ 'substatus' => $substatus->label ] ) );
    }

    /**
     * Cancels the order through the engine's cancellation service.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the confirmation.
     *
     * @return void
     */
    public function cancelOrder( string $token ): void
    {
        $order = $this->order();

        $this->authorizeEcommerce( 'cancel', $order );

        $this->validate(
            [ 'cancelReason' => [ 'required', 'string', 'max:500' ] ],
            [],
            [ 'cancelReason' => __( 'reason' ) ],
        );

        try {
            $summary = $this->withActionToken(
                $token,
                'cancel',
                fn (): OrderCancellationSummary => app( OrderCancellationService::class )->cancel( $order, $this->cancelReason, $this->actorId() ),
                $order,
            );
        } catch ( OrderNotCancellableException | InvalidArgumentException $exception ) {
            $this->addError( 'cancelReason', $exception->getMessage() );

            return;
        }

        if ( ! $summary instanceof OrderCancellationSummary ) {
            return;
        }

        $this->reset( 'confirmingCancel', 'cancelReason' );
        $this->orderChanged();

        $this->toastSuccess(
            __( 'Order #:number cancelled.', [ 'number' => $order->order_number ] ),
            $summary->refundOwed()
                ? __( ':amount is still owed to the customer. Issue it from Refunds.', [ 'amount' => self::formatMoney( $summary->refundOwedAmount, $summary->currency ) ] )
                : null,
        );
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
        $order       = $this->order()->load( [ 'substatus', 'boardAssignments' => static fn ( $query ) => $query->whereNull( 'removed_at' )->with( [ 'board', 'substatus' ] ) ] );
        $user        = auth()->user();
        $canUpdate   = Authorization::allows( $user, 'order.update', $order );
        $canCancel   = Authorization::allows( $user, 'order.cancel', $order );
        $assessment  = $canCancel ? app( OrderCancellationService::class )->assess( $order ) : null;
        $substatuses = OrderSubstatus::query()->orderBy( 'system_status' )->orderBy( 'position' )->orderBy( 'id' )->get();

        return view( 'ecommerce-admin::livewire.orders.panels.status', [
            'order'             => $order,
            'canUpdate'         => $canUpdate,
            'canCancel'         => $canCancel && null !== $assessment && $assessment->cancellable,
            'statusOptions'     => $this->statusOptions( $order ),
            'targetWarnings'    => '' === $this->targetStatus ? [] : $this->boardWarnings( $order, $this->targetStatus ),
            'substatusOptions'  => $this->substatusOptions( $order, $substatuses ),
            'hasOtherSubstatus' => $substatuses->contains( static fn ( OrderSubstatus $substatus ): bool => (string) $substatus->system_status !== (string) $order->system_status ),
            'substatusWarning'  => null !== $order->substatus && (string) $order->substatus->system_status !== (string) $order->system_status
                ? $this->substatusMismatch( $order->substatus, $order )
                : null,
            'boardWarnings'     => $this->boardWarnings( $order, (string) $order->system_status ),
            'cancellation'      => $this->confirmingCancel && null !== $assessment ? $this->presentCancellation( $assessment ) : null,
            'cancelToken'       => $this->confirmingCancel && $canCancel ? $this->actionToken( 'cancel', $order ) : null,
        ] );
    }

    /**
     * Formats minor units for display.
     *
     * @since 1.0.0
     *
     * @param  int     $amount    Minor units.
     * @param  string  $currency  Currency code.
     *
     * @return string
     */
    public static function formatMoney( int $amount, string $currency ): string
    {
        return MoneyFormatter::format( $amount, $currency );
    }

    /**
     * The statuses the picker offers: the machine's edges from the current
     * status, minus `cancelled` (its own flow) and `failed` (set by the
     * payment flow). `refunded` is only offered to users who may refund,
     * since marking an order refunded blocks further refunds and shipping.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array<int, string>
     */
    protected function statusTargets( Order $order ): array
    {
        return array_values( array_filter(
            OrderStatusMachine::ALLOWED_TRANSITIONS[ (string) $order->system_status ] ?? [],
            static fn ( string $status ): bool => ! in_array( $status, [ 'cancelled', 'failed' ], true )
                && ( 'refunded' !== $status || Authorization::allows( auth()->user(), 'order.refund', $order ) ),
        ) );
    }

    /**
     * The status picker options.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected function statusOptions( Order $order ): array
    {
        return array_map(
            fn ( string $status ): array => [ 'id' => $status, 'name' => $this->statusLabel( $status ) ],
            $this->statusTargets( $order ),
        );
    }

    /**
     * The sub-status picker options: the order's own status's sub-statuses,
     * then the rest disabled and labelled with the status they belong to.
     *
     * @since 1.0.0
     *
     * @param  Order                           $order        The order.
     * @param  Collection<int, OrderSubstatus>  $substatuses  Every sub-status.
     *
     * @return array<int, array{id: int, name: string, disabled?: bool}>
     */
    protected function substatusOptions( Order $order, Collection $substatuses ): array
    {
        $own    = [];
        $others = [];

        foreach ( $substatuses as $substatus ) {
            if ( (string) $substatus->system_status === (string) $order->system_status ) {
                $own[] = [ 'id' => (int) $substatus->id, 'name' => (string) $substatus->label ];

                continue;
            }

            $others[] = [
                'id'       => (int) $substatus->id,
                'name'     => __( ':substatus (:status)', [ 'substatus' => $substatus->label, 'status' => $this->statusLabel( (string) $substatus->system_status ) ] ),
                'disabled' => true,
            ];
        }

        return [ ...$own, ...$others ];
    }

    /**
     * Explanations for each board column that would not fit the order if it
     * were on `$status`.
     *
     * @since 1.0.0
     *
     * @param  Order   $order   The order.
     * @param  string  $status  The (prospective) system status.
     *
     * @return array<int, string>
     */
    protected function boardWarnings( Order $order, string $status ): array
    {
        $machine  = app( OrderStatusMachine::class );
        $proposed = ( clone $order )->forceFill( [ 'system_status' => $status ] );
        $warnings = [];

        /** @var OrderBoardAssignment $assignment */
        foreach ( $order->boardAssignments as $assignment ) {
            $substatus = $assignment->substatus;

            if ( null === $substatus || $machine->isBoardAssignmentCompatible( $proposed, $substatus ) ) {
                continue;
            }

            $warnings[] = __( 'On ":board" the order sits in ":substatus", which belongs to :belongs. A :status order cannot stay there; move its card on the board.', [
                'board'     => $assignment->board?->name ?? __( 'Deleted board' ),
                'substatus' => $substatus->label,
                'belongs'   => $this->statusLabel( (string) $substatus->system_status ),
                'status'    => $this->statusLabel( $status ),
            ] );
        }

        return $warnings;
    }

    /**
     * Why a sub-status cannot be used on the order as it stands.
     *
     * @since 1.0.0
     *
     * @param  OrderSubstatus  $substatus  The sub-status.
     * @param  Order           $order      The order.
     *
     * @return string
     */
    protected function substatusMismatch( OrderSubstatus $substatus, Order $order ): string
    {
        return __( '":substatus" belongs to :belongs, but the order is :status. Pick one of the :status sub-statuses, or move the order to :belongs first.', [
            'substatus' => $substatus->label,
            'belongs'   => $this->statusLabel( (string) $substatus->system_status ),
            'status'    => $this->statusLabel( (string) $order->system_status ),
        ] );
    }

    /**
     * The cancellation summary for the confirmation dialog.
     *
     * @since 1.0.0
     *
     * @param  OrderCancellationSummary  $summary  The engine's assessment.
     *
     * @return array{reservations: array<int, array{label: string, quantity: int}>, units: int, voidsPayment: bool, refundOwed: string|null}
     */
    protected function presentCancellation( OrderCancellationSummary $summary ): array
    {
        $items = InventoryItem::query()
            ->with( 'stockable' )
            ->findMany( array_column( $summary->reservations, 'inventory_item_id' ) )
            ->keyBy( 'id' );

        $reservations = array_map( static function ( array $reservation ) use ( $items ): array {
            $stockable = $items->get( $reservation['inventory_item_id'] )?->stockable;
            $name      = $stockable?->name ?? null;
            $sku       = $stockable?->sku ?? null;

            return [
                'label'    => match ( true ) {
                    is_string( $name ) && '' !== $name && is_string( $sku ) && '' !== $sku => $name . ' (' . $sku . ')',
                    is_string( $name ) && '' !== $name                                     => $name,
                    is_string( $sku ) && '' !== $sku                                       => $sku,
                    default                                                                => __( 'Inventory item #:id', [ 'id' => $reservation['inventory_item_id'] ] ),
                },
                'quantity' => $reservation['quantity'],
            ];
        }, $summary->reservations );

        return [
            'reservations' => $reservations,
            'units'        => $summary->reservedUnits(),
            'voidsPayment' => $summary->voidsPayment,
            'refundOwed'   => $summary->refundOwed() ? self::formatMoney( $summary->refundOwedAmount, $summary->currency ) : null,
        ];
    }

    /**
     * A system status's label.
     *
     * @since 1.0.0
     *
     * @param  string  $status  The status.
     *
     * @return string
     */
    protected function statusLabel( string $status ): string
    {
        return StatusPresenter::present( 'system', $status )['label'];
    }
}
