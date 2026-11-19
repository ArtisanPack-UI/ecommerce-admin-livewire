<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Dashboard;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\InventoryQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ProductsQuery;

/*
 * Reserved is an unsigned column, and on MySQL a subtraction with an unsigned
 * operand can't go below zero, so an oversold item used to fail the Products,
 * Inventory and dashboard queries outright. SQLite doesn't enforce that, so
 * these tests check the signed cast in each query's SQL as well as the result.
 */

/**
 * An oversold simple product: 2 on hand, 5 reserved.
 */
function oversoldProduct(): Product
{
	$product = Product::factory()->simple()->create( [ 'sku' => 'OVERSOLD' ] );

	InventoryItem::factory()->create( [
		'stockable_type'      => $product->getMorphClass(),
		'stockable_id'        => $product->id,
		'quantity_on_hand'    => 2,
		'quantity_reserved'   => 5,
		'low_stock_threshold' => 4,
	] );

	return $product;
}

it( 'casts on hand to a signed number before subtracting reserved', function (): void {
	expect( InventoryQuery::availableSql( InventoryItem::query() ) )->toMatch( '/^\(CAST\(.+quantity_on_hand.+ AS DECIMAL\(20, 0\)\) - .+quantity_reserved.+\)$/' );
} );

it( 'reports an oversold product as negative stock and out of stock', function (): void {
	oversoldProduct();

	$query = new ProductsQuery();

	expect( (int) $query->build( '', [], '', '' )->where( 'sku', 'OVERSOLD' )->value( 'stock_available' ) )->toBe( -3 )
		->and( $query->build( '', [ 'stock' => 'out' ], '', '' )->pluck( 'sku' )->all() )->toBe( [ 'OVERSOLD' ] )
		->and( $query->build( '', [ 'stock' => 'in' ], '', '' )->pluck( 'sku' )->all() )->toBe( [] )
		->and( $query->build( '', [], '', '' )->toSql() )->toContain( 'AS DECIMAL(20, 0)' );
} );

it( 'reports an oversold inventory row as negative available', function (): void {
	oversoldProduct();

	expect( (int) ( new InventoryQuery() )->build( '', [], '', '' )->value( 'quantity_available' ) )->toBe( -3 );
} );

it( 'filters and sorts oversold stock in the products query', function (): void {
	oversoldProduct();
	$healthy = Product::factory()->simple()->create( [ 'sku' => 'HEALTHY' ] );
	InventoryItem::factory()->create( [ 'stockable_type' => $healthy->getMorphClass(), 'stockable_id' => $healthy->id, 'quantity_on_hand' => 9 ] );

	$query = new ProductsQuery();

	expect( $query->build( '', [ 'stock' => 'low' ], '', '' )->pluck( 'sku' )->all() )->toBe( [] )
		->and( $query->build( '', [], 'stock', 'asc' )->pluck( 'sku' )->all() )->toBe( [ 'OVERSOLD', 'HEALTHY' ] )
		->and( $query->build( '', [ 'stock' => 'low' ], 'stock', 'asc' )->toSql() )->toContain( 'AS DECIMAL(20, 0)' );
} );

it( 'filters and sorts oversold stock in the inventory query', function (): void {
	oversoldProduct();
	$healthy = Product::factory()->simple()->create();
	InventoryItem::factory()->create( [ 'stockable_type' => $healthy->getMorphClass(), 'stockable_id' => $healthy->id, 'quantity_on_hand' => 9 ] );

	$query = new InventoryQuery();

	expect( $query->build( '', [ 'stock' => 'out' ], '', '' )->pluck( 'quantity_reserved' )->map( 'intval' )->all() )->toBe( [ 5 ] )
		->and( $query->build( '', [ 'stock' => 'low' ], '', '' )->count() )->toBe( 0 )
		->and( $query->build( '', [], 'available', 'asc' )->get()->map( static fn ( InventoryItem $item ): int => (int) $item->quantity_available )->all() )->toBe( [ -3, 9 ] )
		->and( $query->build( '', [ 'stock' => 'out' ], '', '' )->toSql() )->toContain( 'AS DECIMAL(20, 0)' );
} );

it( 'lists an oversold item in the dashboard low-stock widget', function (): void {
	oversoldProduct();

	$lowStock = ( new ReflectionMethod( Dashboard::class, 'lowStock' ) )->invoke( new Dashboard() );

	expect( $lowStock->map( static fn ( InventoryItem $item ): int => (int) $item->quantity_available )->all() )->toBe( [ -3 ] );
} );
