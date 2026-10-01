<?php

/**
 * Address display component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

use ArtisanPackUI\EcommerceAdminLivewire\Support\Countries;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use JsonSerializable;

/**
 * `<x-artisanpack-ec-address :address="$order->shipping_address" />`
 *
 * Renders an address in an `<address>` element. Accepts the engine's order
 * address array, the `Address` value object, or a `CustomerAddress` model —
 * they share the keys `first_name`, `last_name`, `company`, `phone`,
 * `address1`, `address2`, `city`, `region`, `region_code`, `postal_code`,
 * and `country_code`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Address extends Component
{
    /**
     * The address lines to show.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public array $lines = [];

    /**
     * The phone number, shown on its own line.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    public ?string $phone = null;

    /**
     * @since 1.0.0
     *
     * @param  mixed  $address    An address array, or an object with `toArray()`.
     * @param  bool   $showPhone  Whether to show the phone number.
     */
    public function __construct(
        public mixed $address = null,
        public bool $showPhone = true,
    ) {
        $fields = self::fields( $this->address );

        if ( [] === $fields ) {
            return;
        }

        $locality = trim( implode( ' ', array_filter( [
            implode( ', ', array_filter( [ $fields['city'] ?? null, $fields['region'] ?? $fields['region_code'] ?? null ] ) ),
            $fields['postal_code'] ?? null,
        ] ) ) );

        $this->lines = array_values( array_filter( [
            trim( ( $fields['first_name'] ?? '' ) . ' ' . ( $fields['last_name'] ?? '' ) ),
            $fields['company'] ?? '',
            $fields['address1'] ?? '',
            $fields['address2'] ?? '',
            $locality,
            isset( $fields['country_code'] ) ? Countries::name( (string) $fields['country_code'] ) : '',
        ], static fn ( mixed $line ): bool => is_string( $line ) && '' !== trim( $line ) ) );

        $this->phone = $this->showPhone && ! empty( $fields['phone'] ) ? (string) $fields['phone'] : null;
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
        return view( 'ecommerce-admin::components.address' );
    }

    /**
     * The address as a plain array of strings.
     *
     * @since 1.0.0
     *
     * @param  mixed  $address  The address.
     *
     * @return array<string, string>
     */
    private static function fields( mixed $address ): array
    {
        $fields = match ( true ) {
            $address instanceof Arrayable                                 => $address->toArray(),
            is_object( $address ) && method_exists( $address, 'toArray' ) => (array) $address->toArray(),
            $address instanceof JsonSerializable                          => (array) $address->jsonSerialize(),
            is_array( $address )                                          => $address,
            default                                                       => [],
        };

        return array_map(
            static fn ( mixed $value ): string => is_scalar( $value ) ? trim( (string) $value ) : '',
            array_filter( $fields, static fn ( mixed $value ): bool => is_scalar( $value ) ),
        );
    }
}
