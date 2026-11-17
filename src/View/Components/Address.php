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
 * Renders an address in an `<address>` element, one line per part in the
 * country's own line order, the name first. Accepts the engine's order
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
     * Countries that write the postal code before the city ("69003 Lyon").
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const POSTAL_CODE_FIRST = [
        'AT', 'BE', 'CH', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'IS',
        'IT', 'LI', 'LT', 'LU', 'LV', 'MC', 'NL', 'NO', 'PL', 'PT', 'SE', 'SI', 'SK',
    ];

    /**
     * Countries that put the town, the county and the postcode on lines of
     * their own.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const SEPARATE_LOCALITY_LINES = [ 'GB', 'IE', 'IM', 'JE', 'GG' ];

    /**
     * The recipient's name, shown first in a heavier weight.
     *
     * @since 2.0.0
     *
     * @var string|null
     */
    public ?string $name = null;

    /**
     * The address lines after the name.
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

        $name       = trim( ( $fields['first_name'] ?? '' ) . ' ' . ( $fields['last_name'] ?? '' ) );
        $this->name = '' === $name ? null : $name;

        $this->lines = array_values( array_filter( [
            $fields['company'] ?? '',
            $fields['address1'] ?? '',
            $fields['address2'] ?? '',
            ...self::localityLines( $fields ),
            isset( $fields['country_code'] ) ? Countries::name( (string) $fields['country_code'] ) : '',
        ], static fn ( mixed $line ): bool => is_string( $line ) && '' !== trim( $line ) ) );

        $this->phone = $this->showPhone && ! empty( $fields['phone'] ) ? (string) $fields['phone'] : null;
    }

    /**
     * Whether there is nothing to show but the phone.
     *
     * @since 2.0.0
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return null === $this->name && [] === $this->lines;
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
     * The city, region and postal code in the country's order.
     *
     * @since 2.0.0
     *
     * @param  array<string, string>  $fields  The address fields.
     *
     * @return array<int, string> The lines.
     */
    private static function localityLines( array $fields ): array
    {
        $country = strtoupper( $fields['country_code'] ?? '' );
        $city    = $fields['city'] ?? '';
        $region  = ( $fields['region'] ?? '' ) ?: ( $fields['region_code'] ?? '' );
        $postal  = $fields['postal_code'] ?? '';

        if ( in_array( $country, self::SEPARATE_LOCALITY_LINES, true ) ) {
            return [ $city, $region, $postal ];
        }

        if ( in_array( $country, self::POSTAL_CODE_FIRST, true ) ) {
            return [ trim( $postal . ' ' . $city ), $region ];
        }

        return [ trim( implode( ' ', array_filter( [
            implode( ', ', array_filter( [ $city, $region ] ) ),
            $postal,
        ] ) ) ) ];
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
