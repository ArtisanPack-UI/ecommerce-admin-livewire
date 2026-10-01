<?php

/**
 * Order fulfillment panel component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders;

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\Shipment;
use ArtisanPackUI\Ecommerce\Registries\ShippingLabelProviderRegistry;
use ArtisanPackUI\Ecommerce\Services\ShipmentService;
use ArtisanPackUI\Ecommerce\ValueObjects\ShippingLabel;
use ArtisanPackUI\Ecommerce\ValueObjects\TrackingStatus;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\InteractsWithOrderPanel;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ShippingMethods;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StatusPresenter;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Throwable;

/**
 * The fulfillment panel on order detail (spec §7.3, plan §10.2): the
 * order's shipments, item-level shipping on {@see ShipmentService}, tracking
 * updates, and label purchase when a label provider is registered.
 *
 * Quantities are capped by {@see ShipmentService::remainingQuantities()}, so
 * an order can ship in parts and no unit ships twice. Cancelled, refunded,
 * and failed orders show why they cannot ship instead of the form.
 * Creating a shipment and buying a label spend one-time action tokens.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class FulfillmentPanel extends Component
{
    use AuthorizesEcommerce;
    use InteractsWithOrderPanel;
    use SendsToasts;
    use WithActionToken;

    /**
     * Whether the create-shipment form is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $creating = false;

    /**
     * Units to ship, keyed by order item id.
     *
     * @since 1.0.0
     *
     * @var array<int|string, int|string|null>
     */
    public array $quantities = [];

    /**
     * Shipping method key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $method = '';

    /**
     * Carrier.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $carrier = '';

    /**
     * Carrier service.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $service = '';

    /**
     * Tracking number.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $trackingNumber = '';

    /**
     * Tracking link.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $trackingUrl = '';

    /**
     * The shipment whose tracking is being edited.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    public ?int $editingShipmentId = null;

    /**
     * Tracking edit: status.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $editStatus = '';

    /**
     * Tracking edit: number.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $editTrackingNumber = '';

    /**
     * Tracking edit: link.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $editTrackingUrl = '';

    /**
     * The label provider to buy through.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $labelProvider = '';

    /**
     * Re-render when another panel changes the order.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    protected $listeners = [ OrderPanelRegistry::ORDER_UPDATED_EVENT => '$refresh' ];

    /**
     * Opens the create-shipment form with every remaining unit selected.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function startShipment(): void
    {
        $order = $this->order();

        $this->authorizeEcommerce( 'update', $order );
        $this->resetValidation();

        if ( null !== $this->unshippableReason( $order ) ) {
            return;
        }

        $this->quantities = array_map( 'intval', app( ShipmentService::class )->remainingQuantities( $order ) );
        $this->method     = (string) ( $order->shipping_method_key ?? '' );
        $this->reset( 'carrier', 'service', 'trackingNumber', 'trackingUrl' );
        $this->creating = true;
    }

    /**
     * Creates a shipment for the chosen units.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the form.
     *
     * @return void
     */
    public function createShipment( string $token ): void
    {
        $order = $this->order();

        $this->authorizeEcommerce( 'update', $order );

        $reason = $this->unshippableReason( $order );

        if ( null !== $reason ) {
            $this->addError( 'shipment', $reason );

            return;
        }

        $remaining = app( ShipmentService::class )->remainingQuantities( $order );
        $rules     = [
            'quantities'     => [ 'array' ],
            'method'         => [ 'required', 'string', Rule::in( array_column( ShippingMethods::options( $order->shipping_method_key ), 'id' ) ) ],
            'carrier'        => [ 'nullable', 'string', 'max:120' ],
            'service'        => [ 'nullable', 'string', 'max:120' ],
            'trackingNumber' => [ 'nullable', 'string', 'max:255' ],
            'trackingUrl'    => [ 'nullable', 'url:http,https', 'max:500' ],
        ];

        foreach ( $remaining as $id => $max ) {
            $rules[ 'quantities.' . $id ] = [ 'nullable', 'integer', 'min:0', 'max:' . $max ];
        }

        $this->validate(
            $rules,
            [ 'quantities.*.max' => __( 'Only :max of this item are left to ship.' ) ],
            [
                'quantities.*'   => __( 'quantity' ),
                'method'         => __( 'shipping method' ),
                'trackingNumber' => __( 'tracking number' ),
                'trackingUrl'    => __( 'tracking link' ),
            ],
        );

        $quantities = [];

        foreach ( $this->quantities as $id => $quantity ) {
            if ( array_key_exists( (int) $id, $remaining ) && (int) $quantity > 0 ) {
                $quantities[ (int) $id ] = (int) $quantity;
            }
        }

        if ( [] === $quantities ) {
            $this->addError( 'quantities', __( 'Choose at least one item to ship.' ) );

            return;
        }

        $attributes = array_filter( [
            'carrier'         => trim( $this->carrier ),
            'service'         => trim( $this->service ),
            'tracking_number' => trim( $this->trackingNumber ),
            'tracking_url'    => trim( $this->trackingUrl ),
        ], static fn ( string $value ): bool => '' !== $value );

        try {
            $shipment = $this->withActionToken(
                $token,
                'shipment',
                fn (): Shipment => app( ShipmentService::class )->create( $order, $this->method, $quantities, $attributes ),
                $order,
            );
        } catch ( InvalidArgumentException $exception ) {
            $this->addError( 'shipment', $exception->getMessage() );

            return;
        }

        if ( ! $shipment instanceof Shipment ) {
            return;
        }

        $this->reset( 'creating', 'quantities', 'method', 'carrier', 'service', 'trackingNumber', 'trackingUrl' );
        $this->orderChanged();

        $this->toastSuccess( trans_choice( 'Shipment created with :count unit.|Shipment created with :count units.', array_sum( $quantities ), [ 'count' => array_sum( $quantities ) ] ) );
    }

    /**
     * Opens the tracking form for a shipment.
     *
     * @since 1.0.0
     *
     * @param  int  $shipmentId  The shipment id.
     *
     * @return void
     */
    public function editTracking( int $shipmentId ): void
    {
        $this->authorizeEcommerce( 'update', $this->order() );

        $shipment = $this->shipment( $shipmentId );

        if ( null === $shipment ) {
            $this->toastError( __( 'That shipment is not on this order.' ) );

            return;
        }

        $this->resetValidation();

        $this->editingShipmentId  = (int) $shipment->id;
        $this->editStatus         = (string) $shipment->status;
        $this->editTrackingNumber = (string) ( $shipment->tracking_number ?? '' );
        $this->editTrackingUrl    = (string) ( $shipment->tracking_url ?? '' );
    }

    /**
     * Saves the tracking form through {@see ShipmentService::updateTracking()}.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updateTracking(): void
    {
        $this->authorizeEcommerce( 'update', $this->order() );

        $shipment = null === $this->editingShipmentId ? null : $this->shipment( $this->editingShipmentId );

        if ( null === $shipment ) {
            $this->toastError( __( 'That shipment is not on this order.' ) );

            return;
        }

        $this->validate(
            [
                'editStatus'         => [ 'required', Rule::in( Shipment::STATUSES ) ],
                'editTrackingNumber' => [ 'nullable', 'string', 'max:255' ],
                'editTrackingUrl'    => [ 'nullable', 'url:http,https', 'max:500' ],
            ],
            [],
            [
                'editStatus'         => __( 'status' ),
                'editTrackingNumber' => __( 'tracking number' ),
                'editTrackingUrl'    => __( 'tracking link' ),
            ],
        );

        $number = trim( $this->editTrackingNumber );
        $url    = trim( $this->editTrackingUrl );

        try {
            app( ShipmentService::class )->updateTracking( $shipment, new TrackingStatus(
                $this->editStatus,
                null,
                null,
                $number,
                $url,
            ) );
        } catch ( InvalidArgumentException $exception ) {
            $this->addError( 'editStatus', $exception->getMessage() );

            return;
        }

        $this->reset( 'editingShipmentId', 'editStatus', 'editTrackingNumber', 'editTrackingUrl' );
        $this->orderChanged();

        $this->toastSuccess( __( 'Tracking updated.' ) );
    }

    /**
     * Buys a carrier label for a shipment.
     *
     * @since 1.0.0
     *
     * @param  int     $shipmentId  The shipment id.
     * @param  string  $token       The action token minted with the button.
     *
     * @return void
     */
    public function buyLabel( int $shipmentId, string $token ): void
    {
        $this->authorizeEcommerce( 'update', $this->order() );

        $providers = $this->labelProviders();
        $shipment  = $this->shipment( $shipmentId );

        if ( [] === $providers ) {
            $this->toastError( __( 'No shipping label provider is installed.' ) );

            return;
        }

        if ( null === $shipment ) {
            $this->toastError( __( 'That shipment is not on this order.' ) );

            return;
        }

        if ( null !== $shipment->label_id ) {
            $this->toastError( __( 'This shipment already has a label.' ) );

            return;
        }

        $provider = '' !== $this->labelProvider ? $this->labelProvider : (string) array_key_first( $providers );

        if ( ! array_key_exists( $provider, $providers ) ) {
            $this->addError( 'labelProvider', __( 'Choose a label provider.' ) );

            return;
        }

        try {
            $label = $this->withActionToken(
                $token,
                'label',
                static fn (): ShippingLabel => app( ShipmentService::class )->buyLabel( $shipment, $provider ),
                $shipment,
            );
        } catch ( Exception $exception ) {
            $this->toastError( __( 'The label could not be bought.' ), $exception->getMessage() );

            return;
        }

        if ( ! $label instanceof ShippingLabel ) {
            return;
        }

        $this->orderChanged();

        $this->toastSuccess(
            __( 'Label bought.' ),
            null === $label->trackingNumber ? null : __( 'Tracking number :number.', [ 'number' => $label->trackingNumber ] ),
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
        $order      = $this->order()->load( 'items' );
        $canUpdate  = Authorization::allows( auth()->user(), 'order.update', $order );
        $shipments  = Shipment::query()->where( 'order_id', $order->id )->with( 'items' )->orderBy( 'id' )->get();
        $remaining  = app( ShipmentService::class )->remainingQuantities( $order );
        $unshipable = $this->unshippableReason( $order );
        $providers  = $this->labelProviders();

        return view( 'ecommerce-admin::livewire.orders.panels.fulfillment', [
            'order'             => $order,
            'shipments'         => $shipments,
            'itemNames'         => $order->items->mapWithKeys( static fn ( $item ): array => [ (int) $item->id => Show::itemName( $item ) ] )->all(),
            'remaining'         => $remaining,
            'unshippableReason' => $unshipable,
            'canShip'           => $canUpdate && null === $unshipable && array_sum( $remaining ) > 0,
            'canUpdate'         => $canUpdate,
            'methodOptions'     => ShippingMethods::options( $order->shipping_method_key ),
            'statusOptions'     => array_map( static fn ( string $status ): array => [ 'id' => $status, 'name' => StatusPresenter::present( 'shipment', $status )['label'] ], Shipment::STATUSES ),
            'labelProviders'    => $providers,
            'shipmentToken'     => $this->creating && $canUpdate ? $this->actionToken( 'shipment', $order ) : null,
            'labelTokens'       => $canUpdate && [] !== $providers
                ? $shipments->whereNull( 'label_id' )->mapWithKeys( fn ( Shipment $shipment ): array => [ (int) $shipment->id => $this->actionToken( 'label', $shipment ) ] )->all()
                : [],
        ] );
    }

    /**
     * Why `$order` cannot ship, or null.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return string|null
     */
    protected function unshippableReason( Order $order ): ?string
    {
        return match ( (string) $order->system_status ) {
            'cancelled' => __( 'This order was cancelled, so nothing more can ship.' ),
            'refunded'  => __( 'This order was refunded, so nothing more can ship.' ),
            'failed'    => __( 'This order\'s payment failed, so it cannot ship.' ),
            default     => in_array( (string) $order->system_status, ShipmentService::UNSHIPPABLE_STATUSES, true )
                ? __( 'This order cannot ship in its current status.' )
                : null,
        };
    }

    /**
     * A shipment of this order.
     *
     * @since 1.0.0
     *
     * @param  int  $shipmentId  The shipment id.
     *
     * @return Shipment|null
     */
    protected function shipment( int $shipmentId ): ?Shipment
    {
        return Shipment::query()->where( 'order_id', $this->orderId )->find( $shipmentId );
    }

    /**
     * The registered label providers, as `key => label`.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function labelProviders(): array
    {
        try {
            $keys = app( ShippingLabelProviderRegistry::class )->keys();
        } catch ( Throwable ) {
            return [];
        }

        return array_combine( $keys, array_map( static fn ( string $key ): string => Str::headline( $key ), $keys ) ) ?: [];
    }
}
