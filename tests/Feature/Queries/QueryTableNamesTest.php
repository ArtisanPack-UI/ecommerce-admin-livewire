<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Queries\CustomersQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\DigitalFilesQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\InventoryQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\LicenseKeysQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\OrdersQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ProductsQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\PromotionsQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ReviewsQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\TagsQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\TaxRatesQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\WebhookDeliveriesQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\WebhookSubscriptionsQuery;

/*
 * Engine 1.0 prefixes every table with `ecommerce_`. Each index query is run
 * against the real engine migrations with every sort, in both directions,
 * and every filter with a valid value, so a hard-coded table name fails here
 * with a QueryException instead of on a live screen.
 */

/**
 * Valid values per filter key, for each query.
 *
 * @return array<string, array{0: Closure(): ResourceQuery, 1: array<string, array<int, mixed>>}>
 */
function queryTableNameCases(): array
{
    return [
        'orders'                => [ static fn (): ResourceQuery => new OrdersQuery(), [
            'system_status'      => [ 'processing' ],
            'substatus'          => [ 1 ],
            'payment_status'     => [ 'paid' ],
            'fulfillment_status' => [ 'unfulfilled' ],
            'currency'           => [ 'usd' ],
            'awaiting'           => [ '1', '0' ],
            'board'              => [ 1 ],
            'placed'             => [ [ 'from' => '2026-01-01', 'to' => '2026-12-31' ] ],
        ] ],
        'products'              => [ static fn (): ResourceQuery => new ProductsQuery(), [
            'status'   => [ 'active' ],
            'type'     => [ 'simple' ],
            'category' => [ 1 ],
            'tag'      => [ 1 ],
            'stock'    => [ 'out', 'low', 'in', 'untracked' ],
            'featured' => [ '1', '0' ],
        ] ],
        'inventory'             => [ static fn (): ResourceQuery => new InventoryQuery(), [
            'stock'   => [ 'out', 'low', 'reorder' ],
            'tracked' => [ '1', '0' ],
        ] ],
        'customers'             => [ static fn (): ResourceQuery => new CustomersQuery(), [
            'has_account'       => [ '1', '0' ],
            'accepts_marketing' => [ '1' ],
            'orders'            => [ [ 'min' => '1', 'max' => '5' ] ],
            'spent'             => [ [ 'min' => '1.00', 'max' => '50.00' ] ],
            'last_order'        => [ [ 'from' => '2026-01-01', 'to' => '2026-12-31' ] ],
        ] ],
        'promotions'            => [ static fn (): ResourceQuery => new PromotionsQuery(), [
            'state'       => [ 'active', 'scheduled', 'expired', 'disabled' ],
            'source_type' => [ 'manual' ],
            'exclusive'   => [ '1' ],
        ] ],
        'license keys'          => [ static fn (): ResourceQuery => new LicenseKeysQuery( true ), [
            'status' => [ 'active', 'expired', 'revoked' ],
        ] ],
        'reviews'               => [ static fn (): ResourceQuery => new ReviewsQuery(), [
            'status'   => [ 'pending' ],
            'rating'   => [ 5 ],
            'product'  => [ 1 ],
            'verified' => [ '1' ],
        ] ],
        'tax rates'             => [ static fn (): ResourceQuery => new TaxRatesQuery(), [
            'class'   => [ 'standard' ],
            'country' => [ 'us' ],
            'active'  => [ '1' ],
        ] ],
        'tags'                  => [ static fn (): ResourceQuery => new TagsQuery(), [] ],
        'digital files'         => [ static fn (): ResourceQuery => new DigitalFilesQuery(), [
            'product'   => [ 1 ],
            'streaming' => [ '1' ],
        ] ],
        'webhook deliveries'    => [ static fn (): ResourceQuery => new WebhookDeliveriesQuery( 1 ), [
            'event'  => [ 'order.created' ],
            'status' => [ 'delivered', 'failed', 'pending', 'retrying' ],
        ] ],
        'webhook subscriptions' => [ static fn (): ResourceQuery => new WebhookSubscriptionsQuery(), [
            'active' => [ '1' ],
        ] ],
    ];
}

it( 'runs every sort, filter, and search against the prefixed engine tables', function ( string $name ): void {
    [ $make, $filters ] = queryTableNameCases()[ $name ];

    $query = $make();

    foreach ( array_keys( $query->sorts() ) as $sort ) {
        foreach ( [ 'asc', 'desc' ] as $direction ) {
            $make()->build( 'term', [], $sort, $direction )->get();
        }
    }

    foreach ( $filters as $key => $values ) {
        foreach ( $values as $value ) {
            $make()->build( '', [ $key => $value ] )->get();
        }
    }

    expect( true )->toBeTrue();
} )->with( array_keys( queryTableNameCases() ) );

it( 'covers every filter each query declares', function ( string $name ): void {
    [ $make, $filters ] = queryTableNameCases()[ $name ];

    $declared = ( new ReflectionMethod( $make(), 'filters' ) )->invoke( $make() );

    expect( array_keys( $filters ) )->toEqualCanonicalizing( array_keys( $declared ) );
} )->with( array_keys( queryTableNameCases() ) );

it( 'hard-codes no pre-1.0 table names in src or resources', function (): void {
    $root    = dirname( __DIR__, 3 );
    $pattern = "/['\"`(](products|product_[a-z_]+|inventory_items|orders|order_[a-z_]+|customers|customer_[a-z_]+|coupons|promotions|promotion_[a-z_]+|tax_rates|tax_classes|license_keys|digital_files|digital_downloads|webhook_deliveries|webhook_subscriptions|shipping_[a-z_]+|refunds|refund_items|kanban_[a-z_]+)\.([a-z_*]+)/";

    // Route names (`products.edit`), activity-log events, and the order
    // item's `product_snapshot` JSON key share the shape but aren't tables.
    $allowed = static fn ( string $table, string $rest ): bool => in_array( $rest, [ 'index', 'show', 'edit', 'create', 'import', 'generated' ], true )
        || 'product_snapshot' === $table;

    $hits = [];

    foreach ( [ 'src', 'resources' ] as $directory ) {
        $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $directory, FilesystemIterator::SKIP_DOTS ) );

        foreach ( $files as $file ) {
            if ( ! in_array( $file->getExtension(), [ 'php' ], true ) ) {
                continue;
            }

            foreach ( file( $file->getPathname() ) as $number => $line ) {
                if ( 0 === preg_match_all( $pattern, $line, $matches, PREG_SET_ORDER ) ) {
                    continue;
                }

                foreach ( $matches as $match ) {
                    if ( ! $allowed( $match[1], $match[2] ) ) {
                        $hits[] = str_replace( $root . '/', '', $file->getPathname() ) . ':' . ( $number + 1 ) . ' ' . $match[0];
                    }
                }
            }
        }
    }

    expect( $hits )->toBe( [] );
} );
