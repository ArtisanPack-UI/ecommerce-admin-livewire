<?php

/**
 * Order edit panel component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders;

use ArtisanPackUI\Ecommerce\Exceptions\OrderNotEditableException;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderEdit;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Models\Shipment;
use ArtisanPackUI\Ecommerce\Services\OrderEditService;
use ArtisanPackUI\Ecommerce\Services\ProductPriceResolver;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\Ecommerce\Support\TaxLabel;
use ArtisanPackUI\Ecommerce\ValueObjects\OrderEditResult;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\InteractsWithOrderPanel;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithPickers;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\RowKeys;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ShippingMethods;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StatusPresenter;
use ArtisanPackUI\EcommerceAdminLivewire\Support\UserNames;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;
use Throwable;

/**
 * The edit panel on order detail (spec §7.3, plan §7.6): builds a draft
 * edit — items, quantities, variants, addresses, shipping method — without
 * touching the order, previews the before/after totals through
 * {@see OrderEditService::preview()}, and applies it with a required reason.
 * The latest edit can be rolled back, and every edit is listed with its diff.
 *
 * Editing after fulfillment has started needs `order.edit-fulfilled`, and
 * the engine then only accepts address changes. Once a shipping label has
 * been printed the order cannot be edited at all (spec §15, open question 4).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class EditPanel extends Component
{
    use AuthorizesEcommerce;
    use InteractsWithOrderPanel;
    use SendsToasts;
    use WithActionToken;
    use WithPickers;

    /**
     * The address fields the draft edits.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const ADDRESS_FIELDS = [ 'first_name', 'last_name', 'company', 'phone', 'address1', 'address2', 'city', 'region', 'postal_code', 'country_code' ];

    /**
     * Whether the editor is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $editing = false;

    /**
     * The existing lines, keyed by order item id.
     *
     * @since 1.0.0
     *
     * @var array<int|string, array{quantity: int|string|null, remove: bool, variant_id: int|string|null}>
     */
    public array $draftItems = [];

    /**
     * Lines to add. Only what the admin chose is held here; the price and
     * snapshot are resolved on the server whenever the draft is previewed,
     * shown, or saved, so a tampered request cannot set its own price.
     *
     * @since 1.0.0
     *
     * @var array<int, array{product_id: int|string, variant_id: int|string|null, quantity: int|string}>
     */
    public array $newItems = [];

    /**
     * The draft shipping address.
     *
     * @since 1.0.0
     *
     * @var array<string, string|null>
     */
    public array $shipping = [];

    /**
     * The draft billing address.
     *
     * @since 1.0.0
     *
     * @var array<string, string|null>
     */
    public array $billing = [];

    /**
     * The draft shipping method key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $shippingMethod = '';

    /**
     * Add item: the product.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $addProductId = null;

    /**
     * Add item: the variant.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $addVariantId = null;

    /**
     * Add item: the quantity.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $addQuantity = 1;

    /**
     * The latest preview, or null when the draft changed since.
     *
     * @since 1.0.0
     *
     * @var array{lines: array<int, string>, before: int, after: int, currency: string, paymentDelta: int|null, refundDelta: int|null}|null
     */
    #[Locked]
    public ?array $preview = null;

    /**
     * Why the order is being edited.
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
     * Opens the editor with a draft of the order as it stands.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function startEdit(): void
    {
        $order = $this->order();

        if ( ! $this->authorizeEdit( $order ) ) {
            return;
        }

        $this->resetValidation();

        $this->draftItems = [];

        foreach ( $order->items()->orderBy( 'id' )->get() as $item ) {
            $this->draftItems[ (int) $item->id ] = [
                'quantity'   => (int) $item->quantity,
                'remove'     => false,
                'variant_id' => null === $item->product_variant_id ? null : (int) $item->product_variant_id,
            ];
        }

        $this->newItems       = [];
        $this->shipping       = $this->addressDraft( $order->shipping_address );
        $this->billing        = $this->addressDraft( $order->billing_address );
        $this->shippingMethod = (string) ( $order->shipping_method_key ?? '' );
        $this->preview        = null;
        $this->reason         = '';
        $this->reset( 'addProductId', 'addVariantId', 'addQuantity' );

        $this->editing = true;
    }

    /**
     * Drops the preview whenever the draft changes.
     *
     * @since 1.0.0
     *
     * @param  string  $property  The property that changed.
     *
     * @return void
     */
    public function updated( string $property ): void
    {
        if ( in_array( Str::before( $property, '.' ), [ 'draftItems', 'shipping', 'billing', 'shippingMethod' ], true ) ) {
            $this->preview = null;
        }
    }

    /**
     * Adds the chosen product (and variant) to the draft at its current
     * price in the order currency.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addItem(): void
    {
        $order = $this->order();

        if ( ! $this->authorizeEdit( $order ) ) {
            return;
        }

        if ( $this->fulfillmentStarted( $order ) ) {
            $this->addError( 'addProductId', __( 'Items cannot change once fulfillment has started.' ) );

            return;
        }

        $this->validate(
            [
                'addProductId' => [ 'required', 'integer', Rule::exists( Product::class, 'id' )->where( 'status', 'active' ) ],
                'addVariantId' => [ 'nullable', 'integer', Rule::exists( ProductVariant::class, 'id' )->where( 'product_id', (int) $this->addProductId ) ],
                'addQuantity'  => [ 'required', 'integer', 'min:1', 'max:9999' ],
            ],
            [],
            [ 'addProductId' => __( 'product' ), 'addVariantId' => __( 'variant' ), 'addQuantity' => __( 'quantity' ) ],
        );

        $product = Product::query()->findOrFail( (int) $this->addProductId );

        $this->authorizeEcommerce( 'view', $product );
        $variant = null === $this->addVariantId || '' === $this->addVariantId ? null : ProductVariant::query()->find( (int) $this->addVariantId );

        if ( null === $variant && $product->variants()->exists() ) {
            $this->addError( 'addVariantId', __( 'Choose which variant to add.' ) );

            return;
        }

        $line = $this->newLine( $order, $product, $variant, (int) $this->addQuantity );

        if ( null === $line ) {
            $this->addError( 'addProductId', __( ':product has no price in :currency.', [ 'product' => $product->name, 'currency' => $order->currency ] ) );

            return;
        }

        $this->newItems[] = [ RowKeys::KEY => RowKeys::make(), 'product_id' => (int) $product->id, 'variant_id' => $line['variant_id'], 'quantity' => (int) $this->addQuantity ];
        $this->preview    = null;
        $this->reset( 'addProductId', 'addVariantId', 'addQuantity' );
    }

    /**
     * Takes an added line back out of the draft.
     *
     * @since 1.0.0
     *
     * @param  int  $index  The line's position in {@see self::$newItems}.
     *
     * @return void
     */
    public function removeNewItem( int $index ): void
    {
        if ( ! $this->authorizeEdit( $this->order() ) ) {
            return;
        }

        unset( $this->newItems[ $index ] );

        $this->newItems = array_values( $this->newItems );
        $this->preview  = null;
    }

    /**
     * Works out the draft's before/after totals without saving anything.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function previewEdit(): void
    {
        $order = $this->order();

        if ( ! $this->authorizeEdit( $order ) ) {
            return;
        }

        // A token outlives the editor; without an open draft the empty
        // address fields would read as "clear the addresses".
        if ( ! $this->editing ) {
            $this->addError( 'edit', __( 'Open the editor before saving changes.' ) );

            return;
        }

        $edit = $this->validatedEdit( $order );

        if ( null === $edit ) {
            return;
        }

        try {
            $result = app( OrderEditService::class )->preview( $order, $edit );
        } catch ( OrderNotEditableException | InvalidArgumentException $exception ) {
            $this->addError( 'edit', $exception->getMessage() );

            return;
        }

        $currency = (string) $order->currency;

        $this->preview = [
            'lines'        => self::describeDiff( $result['diff'], $this->itemNames( $order ), $currency ),
            'before'       => (int) ( $result['diff']['totals']['before']['total_amount'] ?? 0 ),
            'after'        => (int) ( $result['diff']['totals']['after']['total_amount'] ?? 0 ),
            'currency'     => $currency,
            'paymentDelta' => null === $result['paymentActionRequired'] ? null : (int) $result['paymentActionRequired']['delta_amount'],
            'refundDelta'  => null === $result['refundDelta'] ? null : (int) $result['refundDelta']['delta_amount'],
        ];
    }

    /**
     * Applies the draft through {@see OrderEditService::apply()}.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the editor.
     *
     * @return void
     */
    public function applyEdit( string $token ): void
    {
        $order = $this->order();

        if ( ! $this->authorizeEdit( $order ) ) {
            return;
        }

        // A token outlives the editor; without an open draft the empty
        // address fields would read as "clear the addresses".
        if ( ! $this->editing ) {
            $this->addError( 'edit', __( 'Open the editor before saving changes.' ) );

            return;
        }

        $this->validate( [ 'reason' => [ 'required', 'string', 'max:255' ] ], [], [ 'reason' => __( 'reason' ) ] );

        $edit = $this->validatedEdit( $order );

        if ( null === $edit ) {
            return;
        }

        try {
            $result = $this->withActionToken(
                $token,
                'edit',
                fn (): OrderEditResult => app( OrderEditService::class )->apply( $order, $edit, $this->actorId(), trim( $this->reason ) ),
                $order,
            );
        } catch ( OrderNotEditableException | InvalidArgumentException $exception ) {
            $this->addError( 'edit', $exception->getMessage() );

            return;
        }

        if ( ! $result instanceof OrderEditResult ) {
            return;
        }

        $this->reset( 'editing', 'draftItems', 'newItems', 'shipping', 'billing', 'shippingMethod', 'preview', 'reason' );
        $this->orderChanged();

        if ( null !== $result->paymentActionRequired ) {
            $this->toastWarning(
                __( 'Order updated. Payment action required.' ),
                __( 'The total rose by :amount; collect it from the customer.', [ 'amount' => MoneyFormatter::format( (int) $result->paymentActionRequired['delta_amount'], (string) $result->paymentActionRequired['currency'] ) ] ),
            );

            return;
        }

        $this->toastSuccess(
            __( 'Order updated.' ),
            null === $result->refundDelta ? null : __( 'The total fell by :amount; refund it from Refunds if it was paid.', [ 'amount' => MoneyFormatter::format( (int) $result->refundDelta['delta_amount'], (string) $result->refundDelta['currency'] ) ] ),
        );
    }

    /**
     * Rolls back the order's latest edit.
     *
     * @since 1.0.0
     *
     * @param  int     $editId  The edit to roll back; must be the latest.
     * @param  string  $token   The action token minted with the button.
     *
     * @return void
     */
    public function rollbackEdit( int $editId, string $token ): void
    {
        $order = $this->order();

        if ( ! $this->authorizeEdit( $order ) ) {
            return;
        }

        $latest = OrderEdit::query()->where( 'order_id', $order->id )->orderByDesc( 'id' )->first();

        if ( null === $latest || (int) $latest->id !== $editId ) {
            $this->toastError( __( 'Only the latest edit can be rolled back.' ) );

            return;
        }

        try {
            $result = $this->withActionToken(
                $token,
                'rollback',
                fn (): OrderEditResult => app( OrderEditService::class )->rollback( $latest, $this->actorId() ),
                $latest,
            );
        } catch ( OrderNotEditableException | InvalidArgumentException | RuntimeException $exception ) {
            $this->toastError( __( 'The edit could not be rolled back.' ), $exception->getMessage() );

            return;
        }

        if ( ! $result instanceof OrderEditResult ) {
            return;
        }

        $this->orderChanged();

        $this->toastSuccess( __( 'Edit #:id rolled back.', [ 'id' => $editId ] ) );
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
        $order   = $this->order()->load( 'items' );
        $user    = auth()->user();
        $edits   = OrderEdit::query()->where( 'order_id', $order->id )->orderByDesc( 'id' )->get();
        $blocked = $this->blockedReason( $order );
        $canEdit = null === $blocked
            && $this->canEcommerce( 'update', $order )
            && ( ! $this->fulfillmentStarted( $order ) || Authorization::allows( $user, 'order.edit-fulfilled', $order ) );

        return view( 'ecommerce-admin::livewire.orders.panels.edit', [
            'order'              => $order,
            'edits'              => $edits,
            'editLines'          => $edits->mapWithKeys( fn ( OrderEdit $edit ): array => [ (int) $edit->id => self::describeDiff( (array) $edit->diff, $this->itemNames( $order, (array) $edit->pre_edit_snapshot ), (string) $order->currency ) ] )->all(),
            'actors'             => UserNames::for( $edits->pluck( 'actor_user_id' )->all() ),
            'blockedReason'      => $blocked,
            'canEdit'            => $canEdit,
            'fulfillmentStarted' => $this->fulfillmentStarted( $order ),
            'itemNames'          => $this->itemNames( $order ),
            'newLines'           => $this->editing ? array_map( fn ( mixed $chosen ): ?array => $this->resolveNewItem( $order, (array) $chosen ), $this->newItems ) : [],
            'variantOptions'     => $this->editing ? $this->variantOptions( $order ) : [],
            'addVariantOptions'  => $this->editing ? $this->addVariantOptions() : [],
            'methodOptions'      => $this->editing ? ShippingMethods::options( $order->shipping_method_key ) : [],
            'editToken'          => $this->editing && $canEdit ? $this->actionToken( 'edit', $order ) : null,
            'rollbackToken'      => $canEdit && $edits->isNotEmpty() ? $this->actionToken( 'rollback', $edits->first() ) : null,
        ] );
    }

    /**
     * One-line descriptions of an edit's diff (engine spec §3.20).
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $diff      The `{fields, items, totals}` diff.
     * @param  array<int, string>    $names     Item names by order item id.
     * @param  string                $currency  The order currency.
     *
     * @return array<int, string>
     */
    public static function describeDiff( array $diff, array $names, string $currency ): array
    {
        $lines  = [];
        $money  = static fn ( mixed $amount ): string => MoneyFormatter::format( (int) $amount, $currency );
        $nameOf = static function ( array $item ) use ( $names ): string {
            $name = data_get( $item, 'product_snapshot.name' );

            return is_string( $name ) && '' !== $name ? $name : ( $names[ (int) ( $item['id'] ?? 0 ) ] ?? __( 'Item #:id', [ 'id' => $item['id'] ?? '?' ] ) );
        };

        foreach ( (array) ( $diff['fields'] ?? [] ) as $field => $change ) {
            $lines[] = match ( $field ) {
                'shipping_address'    => __( 'Shipping address changed.' ),
                'billing_address'     => __( 'Billing address changed.' ),
                'shipping_method_key' => __( 'Shipping method: :before → :after', [ 'before' => ShippingMethods::label( $change['before'] ?? null ), 'after' => ShippingMethods::label( $change['after'] ?? null ) ] ),
                'email'               => __( 'Email changed.' ),
                'phone'               => __( 'Phone changed.' ),
                'customer_note'       => __( 'Customer note changed.' ),
                default               => __( ':field changed.', [ 'field' => Str::headline( (string) $field ) ] ),
            };
        }

        foreach ( (array) data_get( $diff, 'items.added', [] ) as $item ) {
            $lines[] = __( 'Added :item × :quantity', [ 'item' => $nameOf( (array) $item ), 'quantity' => (int) ( $item['quantity'] ?? 0 ) ] );
        }

        foreach ( (array) data_get( $diff, 'items.removed', [] ) as $item ) {
            $lines[] = __( 'Removed :item × :quantity', [ 'item' => $nameOf( (array) $item ), 'quantity' => (int) ( $item['quantity'] ?? 0 ) ] );
        }

        foreach ( (array) data_get( $diff, 'items.changed', [] ) as $id => $changes ) {
            $name = $names[ (int) $id ] ?? __( 'Item #:id', [ 'id' => $id ] );

            foreach ( (array) $changes as $key => $change ) {
                $before = $change['before'] ?? null;
                $after  = $change['after'] ?? null;

                $line = match ( $key ) {
                    'quantity'          => __( ':item: quantity :before → :after', [ 'item' => $name, 'before' => (int) $before, 'after' => (int) $after ] ),
                    'unit_price_amount' => __( ':item: unit price :before → :after', [ 'item' => $name, 'before' => $money( $before ), 'after' => $money( $after ) ] ),
                    'tax_amount'        => __( ':item: tax :before → :after', [ 'item' => $name, 'before' => $money( $before ), 'after' => $money( $after ) ] ),
                    'discount_amount'   => __( ':item: discount :before → :after', [ 'item' => $name, 'before' => $money( $before ), 'after' => $money( $after ) ] ),
                    'shipping_amount'   => __( ':item: shipping :before → :after', [ 'item' => $name, 'before' => $money( $before ), 'after' => $money( $after ) ] ),
                    default             => null,
                };

                if ( null !== $line ) {
                    $lines[] = $line;
                }
            }
        }

        $before = (array) data_get( $diff, 'totals.before', [] );
        $after  = (array) data_get( $diff, 'totals.after', [] );

        foreach ( [ 'subtotal_amount' => __( 'Subtotal' ), 'discount_amount' => __( 'Discount' ), 'shipping_amount' => __( 'Shipping' ), 'tax_amount' => TaxLabel::for(), 'total_amount' => __( 'Total' ) ] as $key => $label ) {
            if ( array_key_exists( $key, $before ) && (int) $before[ $key ] !== (int) ( $after[ $key ] ?? 0 ) ) {
                $lines[] = __( ':label: :before → :after', [ 'label' => $label, 'before' => $money( $before[ $key ] ), 'after' => $money( $after[ $key ] ?? 0 ) ] );
            }
        }

        return $lines;
    }

    /**
     * Authorizes editing: `order.update`, plus `order.edit-fulfilled` once
     * fulfillment has started. Reports (and refuses) an order that cannot
     * be edited at all.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return bool Whether editing may go ahead.
     */
    protected function authorizeEdit( Order $order ): bool
    {
        $this->authorizeEcommerce( 'update', $order );

        if ( $this->fulfillmentStarted( $order ) ) {
            $this->authorizeEcommerceAbility( 'order.edit-fulfilled', $order );
        }

        $blocked = $this->blockedReason( $order );

        if ( null !== $blocked ) {
            $this->addError( 'edit', $blocked );

            return false;
        }

        return true;
    }

    /**
     * Why the order cannot be edited at all, or null.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return string|null
     */
    protected function blockedReason( Order $order ): ?string
    {
        if ( Shipment::query()->where( 'order_id', $order->id )->whereNotNull( 'label_id' )->exists() ) {
            return __( 'A shipping label has been printed for this order, so it can no longer be edited. Void the label first.' );
        }

        if ( in_array( (string) $order->system_status, [ 'cancelled', 'refunded', 'failed' ], true ) ) {
            return __( 'This order is :status and can no longer be edited.', [ 'status' => StatusPresenter::present( 'system', (string) $order->system_status )['label'] ] );
        }

        return null;
    }

    /**
     * Whether any fulfillment has started.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return bool
     */
    protected function fulfillmentStarted( Order $order ): bool
    {
        return 'unfulfilled' !== (string) $order->fulfillment_status;
    }

    /**
     * Validates the draft and turns it into an engine edit payload holding
     * only what changed.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array<string, mixed>|null Null when validation failed or nothing changed.
     */
    protected function validatedEdit( Order $order ): ?array
    {
        $rules = [
            'draftItems'                => [ 'array' ],
            'draftItems.*.quantity'     => [ 'required', 'integer', 'min:1', 'max:9999' ],
            'draftItems.*.remove'       => [ 'boolean' ],
            'draftItems.*.variant_id'   => [ 'nullable', 'integer' ],
            'shippingMethod'            => [ 'nullable', 'string', Rule::in( array_column( ShippingMethods::options( $order->shipping_method_key ), 'id' ) ) ],
            'shipping'                  => [ 'array' ],
            'billing'                   => [ 'array' ],
        ];

        foreach ( [ 'shipping', 'billing' ] as $address ) {
            foreach ( self::ADDRESS_FIELDS as $field ) {
                $rules[ $address . '.' . $field ] = 'country_code' === $field ? [ 'nullable', 'string', 'size:2' ] : [ 'nullable', 'string', 'max:255' ];
            }
        }

        $this->validate( $rules, [], [ 'draftItems.*.quantity' => __( 'quantity' ), 'shippingMethod' => __( 'shipping method' ) ] );

        $edit  = [];
        $items = $order->items()->orderBy( 'id' )->get()->keyBy( 'id' );

        foreach ( [ 'shipping' => 'shipping_address', 'billing' => 'billing_address' ] as $property => $column ) {
            $address = $this->mergedAddress( $order->{$column}, $this->{$property} );

            if ( $address !== $this->mergedAddress( $order->{$column}, $this->addressDraft( $order->{$column} ) ) ) {
                $edit[ $column ] = $address;
            }
        }

        if ( ! $this->fulfillmentStarted( $order ) ) {
            $method = trim( $this->shippingMethod );

            if ( $method !== (string) ( $order->shipping_method_key ?? '' ) ) {
                $edit['shipping_method_key'] = '' === $method ? null : $method;
            }

            $remove = [];
            $change = [];
            $add    = [];

            foreach ( $this->draftItems as $id => $draft ) {
                $item = $items->get( (int) $id );

                if ( null === $item ) {
                    continue;
                }

                $quantity  = (int) ( $draft['quantity'] ?? 0 );
                $variantId = is_numeric( $draft['variant_id'] ?? null ) ? (int) $draft['variant_id'] : null;

                if ( (bool) ( $draft['remove'] ?? false ) ) {
                    $remove[] = (int) $id;

                    continue;
                }

                if ( $variantId !== ( null === $item->product_variant_id ? null : (int) $item->product_variant_id ) ) {
                    $replacement = $this->replacementLine( $order, $item, $variantId, $quantity );

                    if ( null === $replacement ) {
                        $this->addError( 'draftItems.' . $id . '.variant_id', __( 'That variant has no price in :currency.', [ 'currency' => $order->currency ] ) );

                        return null;
                    }

                    $remove[] = (int) $id;
                    $add[]    = $replacement;

                    continue;
                }

                if ( $quantity !== (int) $item->quantity ) {
                    $change[ (int) $id ] = [ 'quantity' => $quantity ];
                }
            }

            foreach ( array_values( $this->newItems ) as $index => $chosen ) {
                $line = $this->resolveNewItem( $order, (array) $chosen );

                if ( null === $line ) {
                    $this->addError( 'newItems.' . $index, __( 'An added product is no longer available at a price in :currency. Remove it and add it again.', [ 'currency' => $order->currency ] ) );

                    return null;
                }

                $add[] = $this->engineLine( $order, $line );
            }

            if ( [] !== $remove && count( $remove ) === $items->count() && [] === $add ) {
                $this->addError( 'draftItems', __( 'An order needs at least one item. Cancel the order instead.' ) );

                return null;
            }

            $edit['items'] = array_filter( [ 'remove' => $remove, 'change' => $change, 'add' => $add ] );

            if ( [] === $edit['items'] ) {
                unset( $edit['items'] );
            }
        }

        if ( [] === $edit ) {
            $this->addError( 'edit', __( 'Nothing has changed yet.' ) );

            return null;
        }

        return $edit;
    }

    /**
     * An address as the draft form holds it.
     *
     * @since 1.0.0
     *
     * @param  mixed  $address  The stored address.
     *
     * @return array<string, string|null>
     */
    protected function addressDraft( mixed $address ): array
    {
        $address = is_array( $address ) ? $address : [];
        $draft   = [];

        foreach ( self::ADDRESS_FIELDS as $field ) {
            $value           = $address[ $field ] ?? null;
            $draft[ $field ] = is_scalar( $value ) && '' !== (string) $value ? (string) $value : null;
        }

        return $draft;
    }

    /**
     * The stored address with the draft's fields laid over it, so keys the
     * form does not show (e.g. a satellite's) survive the edit.
     *
     * @since 1.0.0
     *
     * @param  mixed                       $stored  The stored address.
     * @param  array<string, mixed>        $draft   The draft fields.
     *
     * @return array<string, mixed>
     */
    protected function mergedAddress( mixed $stored, array $draft ): array
    {
        $address = is_array( $stored ) ? $stored : [];

        foreach ( self::ADDRESS_FIELDS as $field ) {
            $value = $draft[ $field ] ?? null;
            $value = is_string( $value ) ? trim( $value ) : $value;

            $address[ $field ] = null === $value || '' === $value ? null : ( 'country_code' === $field ? strtoupper( (string) $value ) : (string) $value );
        }

        return $address;
    }

    /**
     * A draft line for `$product` / `$variant`, priced in the order currency.
     *
     * @since 1.0.0
     *
     * @param  Order                $order     The order.
     * @param  Product              $product   The product.
     * @param  ProductVariant|null  $variant   The variant.
     * @param  int                  $quantity  Units.
     *
     * @return array{product_id: int, variant_id: int|null, quantity: int, unit_price_amount: int, snapshot: array<string, mixed>}|null Null when there is no price.
     */
    protected function newLine( Order $order, Product $product, ?ProductVariant $variant, int $quantity ): ?array
    {
        try {
            $price = app( ProductPriceResolver::class )->resolve( $variant ?? $product, (string) $order->currency );
        } catch ( Throwable ) {
            $price = null;
        }

        if ( null === $price ) {
            return null;
        }

        return [
            'product_id'        => (int) $product->id,
            'variant_id'        => null === $variant ? null : (int) $variant->id,
            'quantity'          => $quantity,
            'unit_price_amount' => (int) $price->getAmount(),
            'snapshot'          => [
                'type'    => (string) $product->type,
                'name'    => (string) $product->name,
                'sku'     => $variant?->sku ?? $product->sku,
                'options' => null === $variant ? [] : $this->variantSnapshotOptions( $variant ),
            ],
        ];
    }

    /**
     * A chosen added line, re-read from the catalogue and re-priced.
     *
     * @since 1.0.0
     *
     * @param  Order                 $order   The order.
     * @param  array<string, mixed>  $chosen  `product_id`, `variant_id`, `quantity`.
     *
     * @return array{product_id: int, variant_id: int|null, quantity: int, unit_price_amount: int, snapshot: array<string, mixed>}|null
     */
    protected function resolveNewItem( Order $order, array $chosen ): ?array
    {
        $quantity = (int) ( $chosen['quantity'] ?? 0 );
        $product  = is_numeric( $chosen['product_id'] ?? null ) ? Product::query()->where( 'status', 'active' )->find( (int) $chosen['product_id'] ) : null;
        $variant  = is_numeric( $chosen['variant_id'] ?? null )
            ? ProductVariant::query()->where( 'product_id', $product?->id )->find( (int) $chosen['variant_id'] )
            : null;

        if ( null === $product || $quantity < 1 || $quantity > 9999 || ( is_numeric( $chosen['variant_id'] ?? null ) && null === $variant ) ) {
            return null;
        }

        $this->authorizeEcommerce( 'view', $product );

        return $this->newLine( $order, $product, $variant, $quantity );
    }

    /**
     * The engine `items.add` entry replacing `$item` with another variant.
     *
     * @since 1.0.0
     *
     * @param  Order      $order      The order.
     * @param  OrderItem  $item       The line being replaced.
     * @param  int|null   $variantId  The new variant.
     * @param  int        $quantity   Units.
     *
     * @return array<string, mixed>|null
     */
    protected function replacementLine( Order $order, OrderItem $item, ?int $variantId, int $quantity ): ?array
    {
        $product = null === $item->product_id ? null : Product::query()->find( $item->product_id );
        $variant = null === $variantId ? null : ProductVariant::query()->where( 'product_id', $item->product_id )->find( $variantId );

        if ( null === $product || ( null !== $variantId && null === $variant ) ) {
            return null;
        }

        $line = $this->newLine( $order, $product, $variant, $quantity );

        return null === $line ? null : $this->engineLine( $order, $line );
    }

    /**
     * A draft line as an engine `items.add` entry.
     *
     * @since 1.0.0
     *
     * @param  Order                $order  The order.
     * @param  array<string, mixed>  $line   The draft line.
     *
     * @return array<string, mixed>
     */
    protected function engineLine( Order $order, array $line ): array
    {
        return array_filter( [
            'product_id'          => (int) $line['product_id'],
            'product_variant_id'  => $line['variant_id'] ?? null,
            'quantity'            => (int) $line['quantity'],
            'unit_price_amount'   => (int) $line['unit_price_amount'],
            'unit_price_currency' => (string) $order->currency,
            'product_snapshot'    => (array) $line['snapshot'],
        ], static fn ( mixed $value ): bool => null !== $value );
    }

    /**
     * A variant's options as `label => value`, for its snapshot.
     *
     * @since 1.0.0
     *
     * @param  ProductVariant  $variant  The variant.
     *
     * @return array<string, string>
     */
    protected function variantSnapshotOptions( ProductVariant $variant ): array
    {
        $options = [];

        foreach ( $variant->optionValues()->with( [ 'attribute', 'value' ] )->get() as $option ) {
            $label = $option->attribute?->label;
            $value = $option->value?->label ?? $option->value?->value;

            if ( is_string( $label ) && is_string( $value ) ) {
                $options[ $label ] = $value;
            }
        }

        return $options;
    }

    /**
     * Item names by order item id: the current items, plus any only found
     * in a pre-edit snapshot.
     *
     * @since 1.0.0
     *
     * @param  Order                 $order     The order.
     * @param  array<string, mixed>  $snapshot  A pre-edit snapshot.
     *
     * @return array<int, string>
     */
    protected function itemNames( Order $order, array $snapshot = [] ): array
    {
        $names = [];

        foreach ( (array) ( $snapshot['items'] ?? [] ) as $id => $item ) {
            $name = data_get( $item, 'product_snapshot.name' );

            if ( is_string( $name ) && '' !== $name ) {
                $names[ (int) $id ] = $name;
            }
        }

        foreach ( $order->items as $item ) {
            $names[ (int) $item->id ] = Show::itemName( $item );
        }

        return $names;
    }

    /**
     * Variant options per existing line whose product has variants.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array<int, array<int, array{id: int, name: string}>>
     */
    protected function variantOptions( Order $order ): array
    {
        $productIds = $order->items->pluck( 'product_id' )->filter()->unique()->values()->all();
        $variants   = ProductVariant::query()->whereIn( 'product_id', $productIds )->orderBy( 'position' )->orderBy( 'id' )->get()->groupBy( 'product_id' );
        $options    = [];

        foreach ( $order->items as $item ) {
            $list = $variants->get( $item->product_id );

            if ( null !== $list && $list->count() > 1 ) {
                $options[ (int) $item->id ] = $list->map( static fn ( ProductVariant $variant ): array => [ 'id' => (int) $variant->id, 'name' => (string) ( $variant->name ?: $variant->sku ?: __( 'Variant #:id', [ 'id' => $variant->id ] ) ) ] )->values()->all();
            }
        }

        return $options;
    }

    /**
     * Variant options for the product being added.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function addVariantOptions(): array
    {
        if ( ! is_numeric( $this->addProductId ) ) {
            return [];
        }

        return ProductVariant::query()
            ->where( 'product_id', (int) $this->addProductId )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get()
            ->map( static fn ( ProductVariant $variant ): array => [ 'id' => (int) $variant->id, 'name' => (string) ( $variant->name ?: $variant->sku ?: __( 'Variant #:id', [ 'id' => $variant->id ] ) ) ] )
            ->all();
    }
}
