<?php

/**
 * Store currencies.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;

/**
 * The currencies the product form offers a price row for.
 *
 * The engine has no "enabled currencies" setting, so a store's currencies
 * are its base currency, the currencies its static rate table converts the
 * base currency into (`artisanpack.ecommerce.currency.rates.{BASE}`), and
 * any currency a product already has a price in. Hosts can change the list
 * with the `ap.ecommerceAdminLivewire.currencies` filter.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class StoreCurrencies
{
    /**
     * The store's base currency.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function base(): string
    {
        return strtoupper( (string) config( 'artisanpack.ecommerce.base_currency', 'USD' ) );
    }

    /**
     * Enabled currencies, base first.
     *
     * @since 1.0.0
     *
     * @param  Product|null  $product  Adds the currencies this product is priced in.
     *
     * @return array<int, string>
     */
    public static function enabled( ?Product $product = null ): array
    {
        $base       = self::base();
        $currencies = [ $base ];

        foreach ( array_keys( (array) config( 'artisanpack.ecommerce.currency.rates.' . $base, [] ) ) as $code ) {
            $currencies[] = strtoupper( (string) $code );
        }

        if ( null !== $product ) {
            foreach ( ProductPrice::query()->where( 'priceable_type', $product->getMorphClass() )->where( 'priceable_id', $product->id )->distinct()->pluck( 'currency' ) as $code ) {
                $currencies[] = strtoupper( (string) $code );
            }
        }

        $currencies = (array) applyFilters( 'ap.ecommerceAdminLivewire.currencies', $currencies, $product );
        $valid      = array_filter( array_map( 'strval', $currencies ), static fn ( string $code ): bool => 1 === preg_match( '/^[A-Z]{3}$/', $code ) );

        return array_values( array_unique( [ $base, ...$valid ] ) );
    }
}
