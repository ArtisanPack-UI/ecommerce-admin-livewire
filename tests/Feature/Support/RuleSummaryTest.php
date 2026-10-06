<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\EcommerceAdminLivewire\Support\RuleSummary;

beforeEach( function (): void {
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
} );

it( 'formats a per-currency amount as each currency\'s amount', function (): void {
    expect( RuleSummary::describe( 'promotion-condition', 'min-subtotal', [ 'amount' => [ 'USD' => 5_000, 'EUR' => 4_500 ] ] ) )
        ->toBe( 'the subtotal is at least $50.00 / €45.00' );
} );

it( 'describes every engine condition and action in words', function ( string $registry, string $type, Closure $config, string $expected ): void {
    expect( RuleSummary::describe( $registry, $type, $config() ) )->toBe( $expected );
} )->with( [
    'fixed off product'      => [ 'promotion-action', 'fixed-off-product', static fn (): array => [ 'amount' => 500, 'product_ids' => [ Product::factory()->create( [ 'name' => 'Mug' ] )->id ] ], '$5.00 off each Mug' ],
    'fixed off product line' => [ 'promotion-action', 'fixed-off-product', static fn (): array => [ 'amount' => 500, 'per' => 'line' ], '$5.00 off each line of any product' ],
    'category any'           => [ 'promotion-condition', 'cart-contains-category', static fn (): array => [ 'category_ids' => [ ProductCategory::factory()->create( [ 'name' => 'Prints' ] )->id ] ], 'the cart contains an item from Prints (or their sub-categories)' ],
    'tag all'                => [ 'promotion-condition', 'cart-contains-tag', static fn (): array => [ 'match' => 'all', 'tag_ids' => [ ProductTag::query()->create( [ 'name' => 'Sale', 'slug' => 'sale' ] )->id, ProductTag::query()->create( [ 'name' => 'New', 'slug' => 'new' ] )->id ] ], 'the cart contains items tagged each of Sale and New' ],
    'currency'               => [ 'promotion-condition', 'currency-is', static fn (): array => [ 'currencies' => [ 'EUR', 'GBP' ] ], 'the cart is in EUR or GBP' ],
    'lifetime value'         => [ 'promotion-condition', 'customer-lifetime-value-over', static fn (): array => [ 'amount' => 100_00 ], 'the customer has spent more than $100.00' ],
    'date range'             => [ 'promotion-condition', 'date-range', static fn (): array => [ 'starts_on' => '2026-11-27', 'ends_on' => '2026-11-30' ], 'it is between 2026-11-27 and 2026-11-30' ],
    'min quantity'           => [ 'promotion-condition', 'min-quantity', static fn (): array => [ 'quantity' => 3 ], 'the cart has at least 3 of any product' ],
] );
