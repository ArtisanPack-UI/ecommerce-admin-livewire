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
use ArtisanPackUI\Ecommerce\Services\StoreCurrencies as EngineStoreCurrencies;

/**
 * The currencies the product form offers a price row for.
 *
 * A store's currencies are the engine's enabled currencies
 * (`artisanpack.ecommerce.currency.enabled`, a store setting, base first)
 * and any currency a product already has a price in. Hosts can change the
 * list with the `ap.ecommerceAdminLivewire.currencies` filter.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class StoreCurrencies
{
    /**
     * The store's base currency (the engine's).
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function base(): string
    {
        return app( EngineStoreCurrencies::class )->base();
    }

    /**
     * The currencies the store sells in (the engine's enabled list, base
     * first), plus any currency `$product` already has prices in, through
     * the `ap.ecommerceAdminLivewire.currencies` filter. Invalid codes are
     * dropped.
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
        $currencies = app( EngineStoreCurrencies::class )->enabled();

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
