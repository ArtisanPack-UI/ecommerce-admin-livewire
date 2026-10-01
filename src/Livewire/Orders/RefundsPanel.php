<?php

/**
 * Order refunds panel component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders;

use ArtisanPackUI\Ecommerce\Contracts\PaymentGateway;
use ArtisanPackUI\Ecommerce\Exceptions\RefundNotAllowedException;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\Refund;
use ArtisanPackUI\Ecommerce\Models\RefundItem;
use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\Ecommerce\Services\RefundService;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\InteractsWithOrderPanel;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\UserNames;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Throwable;

/**
 * The refunds panel on order detail (spec §7.3): the refund history, and
 * the issue-refund dialog on {@see RefundService::issue()}.
 *
 * A refund is either by item — a quantity, an amount, and a restock toggle
 * per line — or a free amount (shipping, goodwill), which the engine
 * records as an amount-only line (quantity 0) on the first item. Amounts are
 * entered in the order currency and held as minor units. A gateway that
 * cannot refund part of an order only offers the full remaining balance.
 *
 * Needs `order.refund`, and spends a one-time action token, so a double
 * click cannot refund twice. A gateway or engine refusal is shown as the
 * engine reported it; nothing is recorded.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class RefundsPanel extends Component
{
    use AuthorizesEcommerce;
    use InteractsWithOrderPanel;
    use SendsToasts;
    use WithActionToken;

    /**
     * Refund modes.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const MODES = [ 'items', 'amount' ];

    /**
     * Whether the issue-refund dialog is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $refunding = false;

    /**
     * `items` or `amount`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $mode = 'items';

    /**
     * Per-item refund lines, keyed by order item id.
     *
     * @since 1.0.0
     *
     * @var array<int|string, array{quantity: int|string|null, amount: int|string|null, restock: bool}>
     */
    public array $lines = [];

    /**
     * The free amount, in minor units.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $amount = null;

    /**
     * Why the refund is being issued.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $reason = '';

    /**
     * Re-render when another panel changes the order.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    protected $listeners = [ OrderPanelRegistry::ORDER_UPDATED_EVENT => '$refresh' ];

    /**
     * Opens the issue-refund dialog with every line cleared.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function startRefund(): void
    {
        $order = $this->order();

        $this->authorizeEcommerce( 'refund', $order );
        $this->resetValidation();

        $this->mode   = $this->supportsPartial( $order ) ? 'items' : 'amount';
        $this->lines  = [];
        $this->reason = '';
        $this->amount = $this->supportsPartial( $order ) ? null : $this->refundable( $order );

        foreach ( $order->items()->orderBy( 'id' )->get() as $item ) {
            $this->lines[ (int) $item->id ] = [ 'quantity' => 0, 'amount' => 0, 'restock' => false ];
        }

        $this->refunding = true;
    }

    /**
     * Fills a line's amount when its quantity changes: the line's paid
     * total per unit, times the quantity.
     *
     * @since 1.0.0
     *
     * @param  mixed   $value  The new value.
     * @param  string  $key    `{item id}.{field}`.
     *
     * @return void
     */
    public function updatedLines( mixed $value, string $key ): void
    {
        [ $itemId, $field ] = array_pad( explode( '.', $key, 2 ), 2, null );

        if ( 'quantity' !== $field || ! isset( $this->lines[ $itemId ] ) || ! is_numeric( $value ) ) {
            return;
        }

        $item = $this->order()->items()->whereKey( (int) $itemId )->first();

        if ( null === $item || (int) $item->quantity < 1 ) {
            return;
        }

        $quantity = max( 0, min( (int) $value, (int) $item->quantity ) );

        $this->lines[ $itemId ]['amount'] = intdiv( (int) $item->total_amount * $quantity, (int) $item->quantity );

        if ( 0 === $quantity ) {
            $this->lines[ $itemId ]['restock'] = false;
        }
    }

    /**
     * Issues the refund.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the dialog.
     *
     * @return void
     */
    public function refund( string $token ): void
    {
        $order = $this->order();

        $this->authorizeEcommerce( 'refund', $order );

        $gateway = $this->gateway( $order );

        if ( null === $gateway || ! $gateway->supportsRefunds() ) {
            $this->addError( 'refund', $this->gatewayProblem( $order, $gateway ) ?? __( 'This order cannot be refunded.' ) );

            return;
        }

        $lines = $this->validatedLines( $order, $gateway );

        if ( null === $lines ) {
            return;
        }

        $reason = trim( $this->reason );

        try {
            $refund = $this->withActionToken(
                $token,
                'refund',
                fn (): Refund => app( RefundService::class )->issue( $order, $lines, $this->actorId(), '' === $reason ? null : $reason ),
                $order,
            );
        } catch ( RefundNotAllowedException | InvalidArgumentException $exception ) {
            $this->addError( 'refund', $exception->getMessage() );

            return;
        }

        if ( ! $refund instanceof Refund ) {
            return;
        }

        $this->reset( 'refunding', 'lines', 'amount', 'reason' );
        $this->orderChanged();

        $this->toastSuccess( __( 'Refunded :amount.', [ 'amount' => MoneyFormatter::format( (int) $refund->amount, (string) $refund->currency ) ] ) );
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
        $items     = $order->items()->orderBy( 'id' )->get();
        $refunds   = Refund::query()->where( 'order_id', $order->id )->with( 'items' )->orderByDesc( 'id' )->get();
        $gateway   = $this->gateway( $order );
        $canRefund = Authorization::allows( auth()->user(), 'order.refund', $order );
        $problem   = $this->gatewayProblem( $order, $gateway );

        return view( 'ecommerce-admin::livewire.orders.panels.refunds', [
            'order'           => $order,
            'items'           => $items,
            'refunds'         => $refunds,
            'issuers'         => UserNames::for( $refunds->pluck( 'issued_by_user_id' )->all() ),
            'itemNames'       => $items->mapWithKeys( static fn ( OrderItem $item ): array => [ (int) $item->id => Show::itemName( $item ) ] )->all(),
            'refundedUnits'   => $this->refundedUnits( $items ),
            'refundable'      => $this->refundable( $order ),
            'canRefund'       => $canRefund && null === $problem && $this->refundable( $order ) > 0,
            'refundProblem'   => $canRefund ? $problem : null,
            'supportsPartial' => null !== $gateway && $this->supportsPartial( $order ),
            'refundToken'     => $this->refunding && $canRefund ? $this->actionToken( 'refund', $order ) : null,
        ] );
    }

    /**
     * Validates the dialog and turns it into engine refund lines.
     *
     * @since 1.0.0
     *
     * @param  Order           $order    The order.
     * @param  PaymentGateway  $gateway  The order's gateway.
     *
     * @return array<int, array{order_item_id: int, quantity: int, amount: int, restock: bool}>|null Null when validation failed.
     */
    protected function validatedLines( Order $order, PaymentGateway $gateway ): ?array
    {
        $refundable      = $this->refundable( $order );
        $items           = $order->items()->orderBy( 'id' )->get()->keyBy( 'id' );
        $refunded        = $this->refundedUnits( $items );
        $refundedAmounts = RefundItem::query()
            ->whereIn( 'order_item_id', $items->modelKeys() )
            ->selectRaw( 'order_item_id, COALESCE(SUM(amount), 0) AS refunded' )
            ->groupBy( 'order_item_id' )
            ->pluck( 'refunded', 'order_item_id' )
            ->map( static fn ( mixed $amount ): int => (int) $amount )
            ->all();
        $money      = static fn ( int $amount ): string => MoneyFormatter::format( $amount, (string) $order->currency );

        $rules = [
            'mode'   => [ 'required', Rule::in( self::MODES ) ],
            'reason' => [ 'nullable', 'string', 'max:255' ],
        ];

        if ( 'amount' === $this->mode ) {
            $rules['amount'] = [ 'required', 'integer', 'min:1', 'max:' . $refundable ];
        } else {
            $rules['lines'] = [ 'required', 'array' ];

            foreach ( $items as $id => $item ) {
                $remaining = max( 0, (int) $item->quantity - ( $refunded[ $id ] ?? 0 ) );

                $rules[ 'lines.' . $id . '.quantity' ] = [ 'nullable', 'integer', 'min:0', 'max:' . $remaining ];
                $rules[ 'lines.' . $id . '.amount' ]   = [ 'nullable', 'integer', 'min:0', 'max:' . min( $refundable, max( 0, (int) $item->total_amount - ( $refundedAmounts[ $id ] ?? 0 ) ) ) ];
                $rules[ 'lines.' . $id . '.restock' ]  = [ 'boolean' ];
            }
        }

        $this->validate(
            $rules,
            [
                'amount.max'           => __( 'At most :amount can still be refunded.', [ 'amount' => $money( $refundable ) ] ),
                'lines.*.quantity.max' => __( 'Only :max of this item can still be refunded.' ),
                'lines.*.amount.max'   => __( 'That is more than is left to refund on this item.' ),
            ],
            [
                'amount'           => __( 'amount' ),
                'reason'           => __( 'reason' ),
                'lines.*.quantity' => __( 'quantity' ),
                'lines.*.amount'   => __( 'amount' ),
            ],
        );

        if ( 'amount' === $this->mode ) {
            $first = $items->first();

            if ( null === $first ) {
                $this->addError( 'amount', __( 'This order has no items to refund against.' ) );

                return null;
            }

            $lines = [ [ 'order_item_id' => (int) $first->id, 'quantity' => 0, 'amount' => (int) $this->amount, 'restock' => false ] ];
        } else {
            $lines = [];

            foreach ( $this->lines as $id => $line ) {
                if ( ! $items->has( (int) $id ) ) {
                    continue;
                }

                $quantity = (int) ( $line['quantity'] ?? 0 );
                $amount   = (int) ( $line['amount'] ?? 0 );

                if ( 0 === $amount ) {
                    if ( $quantity > 0 ) {
                        $this->addError( 'lines.' . $id . '.amount', __( 'Enter the amount to refund for these units.' ) );

                        return null;
                    }

                    continue;
                }

                $lines[] = [
                    'order_item_id' => (int) $id,
                    'quantity'      => $quantity,
                    'amount'        => $amount,
                    'restock'       => $quantity > 0 && (bool) ( $line['restock'] ?? false ),
                ];
            }

            if ( [] === $lines ) {
                $this->addError( 'lines', __( 'Choose at least one item and amount to refund.' ) );

                return null;
            }
        }

        $total = array_sum( array_column( $lines, 'amount' ) );

        if ( $total > $refundable ) {
            $this->addError( 'refund', __( 'The refund comes to :total, but only :amount can still be refunded.', [ 'total' => $money( $total ), 'amount' => $money( $refundable ) ] ) );

            return null;
        }

        if ( ! $gateway->supportsPartialRefunds() && $total !== $refundable ) {
            $this->addError( 'refund', __( ':gateway can only refund the full remaining balance of :amount.', [ 'gateway' => $gateway->label(), 'amount' => $money( $refundable ) ] ) );

            return null;
        }

        return $lines;
    }

    /**
     * Minor units that can still be refunded.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return int
     */
    protected function refundable( Order $order ): int
    {
        if ( ! in_array( (string) $order->payment_status, [ 'paid', 'partially_refunded' ], true ) ) {
            return 0;
        }

        return max( 0, (int) $order->total_amount - (int) $order->total_refunded_amount );
    }

    /**
     * Units already refunded per order item.
     *
     * @since 1.0.0
     *
     * @param  Collection<int, OrderItem>  $items  The order items.
     *
     * @return array<int, int>
     */
    protected function refundedUnits( Collection $items ): array
    {
        return RefundItem::query()
            ->whereIn( 'order_item_id', $items->modelKeys() )
            ->selectRaw( 'order_item_id, COALESCE(SUM(quantity), 0) AS refunded' )
            ->groupBy( 'order_item_id' )
            ->pluck( 'refunded', 'order_item_id' )
            ->map( static fn ( mixed $quantity ): int => (int) $quantity )
            ->all();
    }

    /**
     * The order's payment gateway, when it is registered.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return PaymentGateway|null
     */
    protected function gateway( Order $order ): ?PaymentGateway
    {
        $key = (string) ( $order->payment_gateway_key ?? '' );

        if ( '' === $key ) {
            return null;
        }

        try {
            $registry = app( PaymentGatewayRegistry::class );

            return $registry->has( $key ) ? $registry->get( $key ) : null;
        } catch ( Throwable ) {
            return null;
        }
    }

    /**
     * Whether the order's gateway can refund part of it.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return bool
     */
    protected function supportsPartial( Order $order ): bool
    {
        return (bool) $this->gateway( $order )?->supportsPartialRefunds();
    }

    /**
     * Why the order cannot be refunded through its gateway, or null.
     *
     * @since 1.0.0
     *
     * @param  Order                $order    The order.
     * @param  PaymentGateway|null  $gateway  Its gateway.
     *
     * @return string|null
     */
    protected function gatewayProblem( Order $order, ?PaymentGateway $gateway ): ?string
    {
        $key = (string) ( $order->payment_gateway_key ?? '' );

        return match ( true ) {
            '' === $key                   => __( 'This order has no payment gateway, so it cannot be refunded here.' ),
            null === $gateway             => __( 'The payment gateway ":gateway" is not installed, so this order cannot be refunded here.', [ 'gateway' => $key ] ),
            ! $gateway->supportsRefunds() => __( ':gateway does not support refunds.', [ 'gateway' => $gateway->label() ] ),
            default                       => null,
        };
    }
}
