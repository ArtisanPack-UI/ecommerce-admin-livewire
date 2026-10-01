<?php

/**
 * Shipping method options.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Registries\ShippingMethodTypeRegistry;
use Illuminate\Support\Str;
use Throwable;

/**
 * The shipping methods an order screen may pick: every registered method
 * type, plus the order's own method when it is no longer registered.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class ShippingMethods
{
    /**
     * Select options.
     *
     * @since 1.0.0
     *
     * @param  string|null  $current  The order's method key.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function options( ?string $current = null ): array
    {
        $options = [];

        try {
            foreach ( app( ShippingMethodTypeRegistry::class )->all() as $key => $type ) {
                $options[ $key ] = [ 'id' => (string) $key, 'name' => (string) $type->label() ];
            }
        } catch ( Throwable ) {
            $options = [];
        }

        if ( null !== $current && '' !== $current && ! isset( $options[ $current ] ) ) {
            $options[ $current ] = [ 'id' => $current, 'name' => Str::headline( $current ) ];
        }

        return array_values( $options );
    }

    /**
     * A method key's label.
     *
     * @since 1.0.0
     *
     * @param  mixed  $key  The key.
     *
     * @return string
     */
    public static function label( mixed $key ): string
    {
        if ( ! is_string( $key ) || '' === $key ) {
            return __( 'None' );
        }

        foreach ( self::options() as $option ) {
            if ( $key === $option['id'] ) {
                return $option['name'];
            }
        }

        return Str::headline( $key );
    }
}
