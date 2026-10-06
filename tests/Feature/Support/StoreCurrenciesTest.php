<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductCsv;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StoreCurrencies;

beforeEach( function (): void {
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceAdminLivewire.currencies' );
} );

it( 'offers the engine\'s enabled currencies, base first, without a rate table', function (): void {
    config()->set( 'artisanpack.ecommerce.currency.enabled', [ 'EUR', 'GBP' ] );
    config()->set( 'artisanpack.ecommerce.currency.rates', [] );

    expect( StoreCurrencies::enabled() )->toBe( [ 'USD', 'EUR', 'GBP' ] )
        ->and( StoreCurrencies::base() )->toBe( 'USD' );
} );

it( 'does not offer currencies that only have a conversion rate', function (): void {
    config()->set( 'artisanpack.ecommerce.currency.enabled', [] );
    config()->set( 'artisanpack.ecommerce.currency.rates', [ 'USD' => [ 'EUR' => 92_000_000 ] ] );

    expect( StoreCurrencies::enabled() )->toBe( [ 'USD' ] );
} );

it( 'adds the currencies a product is already priced in', function (): void {
    config()->set( 'artisanpack.ecommerce.currency.enabled', [ 'EUR' ] );

    $product = Product::factory()->create();
    ProductPrice::query()->create( [ 'priceable_type' => $product->getMorphClass(), 'priceable_id' => $product->id, 'currency' => 'JPY', 'price_amount' => 1000 ] );

    expect( StoreCurrencies::enabled( $product ) )->toBe( [ 'USD', 'EUR', 'JPY' ] );
} );

it( 'drops invalid codes a filter adds', function (): void {
    config()->set( 'artisanpack.ecommerce.currency.enabled', [ 'EUR' ] );
    addFilter( 'ap.ecommerceAdminLivewire.currencies', static fn ( array $codes ): array => [ ...$codes, 'eu', 'CHF', '<b>' ] );

    expect( StoreCurrencies::enabled() )->toBe( [ 'USD', 'EUR', 'CHF' ] );
} );

it( 'gives the catalog CSV a price column per enabled currency', function (): void {
    config()->set( 'artisanpack.ecommerce.currency.enabled', [ 'EUR' ] );
    config()->set( 'artisanpack.ecommerce.currency.rates', [] );

    expect( array_keys( ProductCsv::columns() ) )->toContain( 'price_USD', 'price_EUR', 'compare_at_price_EUR' );
} );
