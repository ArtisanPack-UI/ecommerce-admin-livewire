<?php

/**
 * Core config form schemas.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Registries\ProductTypeRegistry;
use ArtisanPackUI\Ecommerce\Registries\ShippingLabelProviderRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;
use Closure;
use DateTimeZone;
use Throwable;

/**
 * The schemas this package ships for every core engine registry key
 * (spec §8.4), written against the config keys each engine class reads.
 *
 * Amounts are integer minor units in the store's base currency, as the
 * engine's `CurrencyConverter::fromConfigured()` expects. Percentages are
 * plain percentages (`12.5`), as `AbstractPromotionAction::fraction()`
 * expects. Weekdays are ISO numbers (1 = Monday).
 *
 * An entry that reads no config gets an empty schema, so the form says it has
 * no settings rather than showing the JSON fallback.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class ConfigSchemas
{
    /**
     * Registers every core schema.
     *
     * Each schema is a closure, so labels are translated in the request's
     * locale and dynamic options (product types, label providers, jobs) are
     * read when the form renders.
     *
     * @since 1.0.0
     *
     * @param  ConfigFormRegistry  $registry  The registry.
     *
     * @return void
     */
    public static function register( ConfigFormRegistry $registry ): void
    {
        foreach ( self::definitions() as $name => $entries ) {
            foreach ( $entries as $key => $schema ) {
                $registry->register( $name, $key, $schema );
            }
        }
    }

    /**
     * The schemas, as registry name => entry key => closure.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, Closure(): array<int, array<string, mixed>>>>
     */
    public static function definitions(): array
    {
        $none = static fn (): array => [];

        return [
            'promotion-condition' => [
                'min-subtotal'               => static fn (): array => [
                    self::money( 'amount', __( 'Minimum subtotal' ), true ),
                ],
                'cart-contains-product'      => static fn (): array => [
                    self::products( 'product_ids', __( 'Products' ) ),
                    self::products( 'variant_ids', __( 'Variants' ), 'variant' ),
                    [ 'name' => 'min_quantity', 'type' => 'number', 'label' => __( 'Minimum quantity' ), 'default' => 1, 'rules' => [ 'integer', 'min:1' ] ],
                    [ 'name' => 'match', 'type' => 'select', 'label' => __( 'Match' ), 'default' => 'any', 'options' => [
                        'any' => __( 'Any of these' ),
                        'all' => __( 'All of these' ),
                    ] ],
                ],
                'cart-contains-product-type' => static fn (): array => [
                    [ 'name' => 'types', 'type' => 'multiselect', 'label' => __( 'Product types' ), 'options' => self::productTypes(), 'rules' => [ 'required', 'min:1' ] ],
                    [ 'name' => 'match', 'type' => 'select', 'label' => __( 'Match' ), 'default' => 'any', 'options' => [
                        'any'  => __( 'The cart has any of these types' ),
                        'all'  => __( 'The cart has all of these types' ),
                        'only' => __( 'The cart has only these types' ),
                    ] ],
                ],
                'customer-in-group'          => static fn (): array => [
                    [ 'name' => 'groups', 'type' => 'tag', 'label' => __( 'Customer groups' ), 'hint' => __( 'The customer must be in at least one of these groups.' ), 'rules' => [ 'required', 'min:1' ] ],
                ],
                'day-of-week'                => static fn (): array => [
                    [ 'name' => 'days', 'type' => 'weekday', 'label' => __( 'Days' ), 'rules' => [ 'required', 'min:1' ] ],
                    [ 'name' => 'timezone', 'type' => 'select', 'label' => __( 'Time zone' ), 'hint' => __( 'Leave empty to use the store time zone.' ), 'options' => self::timezones() ],
                ],
                'customer-first-order'       => $none,
            ],

            'promotion-action' => [
                'percent-off-cart'    => static fn (): array => [
                    self::percent( 'percent', __( 'Percent off' ), true ),
                ],
                'fixed-off-cart'      => static fn (): array => [
                    self::money( 'amount', __( 'Amount off' ), true ),
                ],
                'percent-off-product' => static fn (): array => [
                    self::percent( 'percent', __( 'Percent off' ), true ),
                    self::products( 'product_ids', __( 'Products' ) ),
                    self::products( 'variant_ids', __( 'Variants' ), 'variant' ),
                ],
                'free-shipping'       => $none,
                'buy-x-get-y'         => static fn (): array => [
                    [ 'name' => 'buy_quantity', 'type' => 'number', 'label' => __( 'Buy quantity' ), 'default' => 1, 'rules' => [ 'required', 'integer', 'min:1' ] ],
                    self::products( 'buy_product_ids', __( 'Buy products' ) ),
                    self::products( 'buy_variant_ids', __( 'Buy variants' ), 'variant' ),
                    [ 'name' => 'get_quantity', 'type' => 'number', 'label' => __( 'Get quantity' ), 'default' => 1, 'rules' => [ 'required', 'integer', 'min:1' ] ],
                    self::products( 'get_product_ids', __( 'Get products' ), 'product', __( 'Leave empty to reward the same products.' ) ),
                    self::products( 'get_variant_ids', __( 'Get variants' ), 'variant' ),
                    self::percent( 'percent', __( 'Percent off the reward' ), false, 100 ),
                    [ 'name' => 'max_applications', 'type' => 'number', 'label' => __( 'Maximum times per order' ), 'hint' => __( 'Leave empty for no limit.' ), 'rules' => [ 'integer', 'min:0' ] ],
                ],
                'add-free-item'       => static fn (): array => [
                    [ 'name' => 'product_id', 'type' => 'product', 'label' => __( 'Product' ), 'multiple' => false, 'rules' => [ 'required' ] ],
                    [ 'name' => 'variant_id', 'type' => 'product', 'label' => __( 'Variant' ), 'multiple' => false, 'source' => 'variant' ],
                    [ 'name' => 'quantity', 'type' => 'number', 'label' => __( 'Quantity' ), 'default' => 1, 'rules' => [ 'integer', 'min:1' ] ],
                ],
                'tiered-discount'     => static fn (): array => [
                    [ 'name' => 'tiers', 'type' => 'repeater', 'label' => __( 'Tiers' ), 'hint' => __( 'The highest tier the subtotal reaches applies. Give each tier a percent or an amount.' ), 'rules' => [ 'required', 'min:1' ], 'fields' => [
                        self::money( 'min_subtotal', __( 'From subtotal' ), true ),
                        [ ...self::percent( 'percent', __( 'Percent off' ) ), 'rules' => [ 'gt:0', 'required_without:@amount' ] ],
                        [ ...self::money( 'amount', __( 'Amount off' ) ), 'rules' => [ 'required_without:@percent' ] ],
                    ] ],
                ],
            ],

            'shipping-method' => [
                'flat-rate'    => static fn (): array => [
                    self::money( 'amount', __( 'Rate' ), true ),
                    self::money( 'per_item_amount', __( 'Extra per item' ) ),
                ],
                'free-shipping' => static fn (): array => [
                    self::money( 'min_subtotal', __( 'Minimum subtotal' ), false, __( 'Leave empty to offer free shipping on every order.' ) ),
                ],
                'local-pickup' => static fn (): array => [
                    self::money( 'amount', __( 'Pickup fee' ), false, __( 'Leave empty for free pickup.' ) ),
                ],
                'weight-based' => static fn (): array => [
                    [ 'name' => 'unit', 'type' => 'select', 'label' => __( 'Weight unit' ), 'default' => 'kg', 'options' => [
                        'g'  => __( 'Grams' ),
                        'kg' => __( 'Kilograms' ),
                        'oz' => __( 'Ounces' ),
                        'lb' => __( 'Pounds' ),
                    ] ],
                    [ 'name' => 'tiers', 'type' => 'repeater', 'label' => __( 'Tiers' ), 'hint' => __( 'The lightest tier the cart fits in applies. Leave the last tier\'s weight empty to cover everything heavier.' ), 'rules' => [ 'required', 'min:1' ], 'fields' => [
                        [ 'name' => 'max_weight', 'type' => 'number', 'label' => __( 'Up to weight' ), 'rules' => [ 'min:0' ] ],
                        self::money( 'amount', __( 'Rate' ), true ),
                    ] ],
                ],
                'price-based'  => static fn (): array => [
                    [ 'name' => 'tiers', 'type' => 'repeater', 'label' => __( 'Tiers' ), 'hint' => __( 'The highest tier the subtotal reaches applies.' ), 'rules' => [ 'required', 'min:1' ], 'fields' => [
                        self::money( 'min_subtotal', __( 'From subtotal' ), true ),
                        self::money( 'amount', __( 'Rate' ), true ),
                    ] ],
                ],
            ],

            'kanban-trigger' => [
                'send-email'           => static fn (): array => [
                    [ 'name' => 'to', 'type' => 'tag', 'label' => __( 'Recipients' ), 'hint' => __( 'Email addresses, or "customer" for the order\'s email.' ), 'rules' => [ 'required', 'min:1' ] ],
                    [ 'name' => 'subject', 'type' => 'text', 'label' => __( 'Subject' ), 'rules' => [ 'required' ] ],
                    [ 'name' => 'body', 'type' => 'template', 'label' => __( 'Body' ), 'hint' => self::placeholderHint() ],
                ],
                'dispatch-job'         => static fn (): array => [
                    [ 'name' => 'job', 'type' => 'select', 'label' => __( 'Job' ), 'hint' => __( 'Only jobs listed in the ecommerce kanban.dispatchable_jobs config can run.' ), 'options' => self::dispatchableJobs(), 'rules' => [ 'required' ] ],
                ],
                'webhook'              => static fn (): array => [
                    [ 'name' => 'url', 'type' => 'text', 'label' => __( 'URL' ), 'rules' => [ 'required', 'url' ] ],
                    [ 'name' => 'secret', 'type' => 'text', 'label' => __( 'Signing secret' ), 'hint' => __( 'Optional. When set, the request is signed like outbound webhooks.' ) ],
                ],
                'update-order-field'   => static fn (): array => [
                    [ 'name' => 'field', 'type' => 'text', 'label' => __( 'Field' ), 'hint' => __( 'meta.<path>, or a column the store allows automations to write.' ), 'rules' => [ 'required' ] ],
                    [ 'name' => 'value', 'type' => 'text', 'label' => __( 'Value' ) ],
                ],
                'create-shipment'      => static fn (): array => [
                    [ 'name' => 'method_key', 'type' => 'text', 'label' => __( 'Shipping method key' ), 'hint' => __( 'Leave empty to use the order\'s shipping method.' ) ],
                    [ 'name' => 'carrier', 'type' => 'text', 'label' => __( 'Carrier' ) ],
                    [ 'name' => 'service', 'type' => 'text', 'label' => __( 'Service' ) ],
                ],
                'print-shipping-label' => static fn (): array => [
                    [ 'name' => 'provider', 'type' => 'select', 'label' => __( 'Label provider' ), 'options' => self::labelProviders(), 'rules' => [ 'required' ] ],
                    [ 'name' => 'method_key', 'type' => 'text', 'label' => __( 'Shipping method key' ), 'hint' => __( 'Used only when a shipment has to be created first. Leave empty to use the order\'s shipping method.' ) ],
                ],
            ],

            'kanban-widget' => [
                'total'              => $none,
                'item-count'         => $none,
                'customer'           => $none,
                'shipping-method'    => $none,
                'tags'               => $none,
                'days-in-column'     => $none,
                'payment-status'     => $none,
                'fulfillment-status' => $none,
            ],
        ];
    }

    /**
     * A money field in the base currency.
     *
     * @since 1.0.0
     *
     * @param  string       $name      The config key.
     * @param  string       $label     The label.
     * @param  bool         $required  Whether it is required.
     * @param  string|null  $hint      The hint; defaults to naming the currency.
     *
     * @return array<string, mixed>
     */
    private static function money( string $name, string $label, bool $required = false, ?string $hint = null ): array
    {
        return [
            'name'  => $name,
            'type'  => 'money',
            'label' => $label,
            'hint'  => $hint ?? __( 'In the store currency (:currency); other currencies are converted.', [ 'currency' => (string) config( 'artisanpack.ecommerce.base_currency', 'USD' ) ] ),
            'rules' => $required ? [ 'required' ] : [],
        ];
    }

    /**
     * A percent field.
     *
     * @since 1.0.0
     *
     * @param  string      $name      The config key.
     * @param  string      $label     The label.
     * @param  bool        $required  Whether it is required.
     * @param  int|null    $default   The default.
     *
     * @return array<string, mixed>
     */
    private static function percent( string $name, string $label, bool $required = false, ?int $default = null ): array
    {
        return [
            'name'    => $name,
            'type'    => 'percent',
            'label'   => $label,
            'default' => $default,
            'rules'   => $required ? [ 'required', 'gt:0' ] : [ 'gt:0' ],
        ];
    }

    /**
     * A product or variant picker.
     *
     * @since 1.0.0
     *
     * @param  string       $name    The config key.
     * @param  string       $label   The label.
     * @param  string       $source  `product` or `variant`.
     * @param  string|null  $hint    The hint.
     *
     * @return array<string, mixed>
     */
    private static function products( string $name, string $label, string $source = 'product', ?string $hint = null ): array
    {
        return [ 'name' => $name, 'type' => 'product', 'label' => $label, 'source' => $source, 'hint' => $hint ];
    }

    /**
     * The registered product types.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    private static function productTypes(): array
    {
        $options = [];

        try {
            foreach ( app( ProductTypeRegistry::class )->keys() as $key ) {
                $options[ $key ] = app( ProductTypeRegistry::class )->get( $key )->label();
            }
        } catch ( Throwable $exception ) {
            report( $exception );
        }

        return $options;
    }

    /**
     * The registered shipping label providers.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    private static function labelProviders(): array
    {
        try {
            $keys = app( ShippingLabelProviderRegistry::class )->keys();
        } catch ( Throwable ) {
            return [];
        }

        return array_combine( $keys, $keys );
    }

    /**
     * The jobs the `dispatch-job` trigger may queue.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    private static function dispatchableJobs(): array
    {
        $jobs = array_values( array_filter( (array) config( 'artisanpack.ecommerce.kanban.dispatchable_jobs', [] ), 'is_string' ) );

        return array_combine( $jobs, $jobs );
    }

    /**
     * Every time zone identifier.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    private static function timezones(): array
    {
        $zones = DateTimeZone::listIdentifiers();

        return array_combine( $zones, $zones );
    }

    /**
     * The placeholders kanban emails accept.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private static function placeholderHint(): string
    {
        return __( 'Placeholders: :placeholders', [ 'placeholders' => '{order_number}, {order_id}, {email}, {status}, {board}, {column}' ] );
    }
}
