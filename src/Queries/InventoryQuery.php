<?php

/**
 * Inventory table query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Queries;

use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Search, filters, and sorts for the inventory table (spec §7.2): one row
 * per `inventory_items` record, with its product or variant (and the
 * variant's product) eager-loaded and `quantity_available` (on hand minus
 * reserved) selected.
 *
 * Search matches the product name and SKU, and the variant name and SKU.
 *
 * - `stock` — `low` (tracked, something available, at or below the
 *   threshold), `out` (tracked, nothing available), or `reorder` (tracked
 *   and at or below the threshold, out of stock included — the engine's
 *   low-stock definition, which the dashboard and nav badge count);
 * - `tracked` — `1` for tracked rows only, `0` for untracked rows only.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class InventoryQuery extends ResourceQuery
{
    /**
     * Stock states offered by the stock filter.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const STOCK_STATES = [ 'low', 'out', 'reorder' ];

    /**
     * @since 1.0.0
     *
     * @return array<string, Closure|string>
     */
    public function sorts(): array
    {
        return [
            'on_hand'   => 'inventory_items.quantity_on_hand',
            'reserved'  => 'inventory_items.quantity_reserved',
            'available' => 'quantity_available',
            'threshold' => 'inventory_items.low_stock_threshold',
            'updated'   => 'inventory_items.updated_at',
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array{0: string, 1: string}
     */
    public function defaultSort(): array
    {
        return [ 'available', 'asc' ];
    }

    /**
     * @since 1.0.0
     *
     * @return Builder<InventoryItem>
     */
    protected function baseQuery(): Builder
    {
        return InventoryItem::query()
            ->select( 'inventory_items.*' )
            ->selectRaw( '(inventory_items.quantity_on_hand - inventory_items.quantity_reserved) as quantity_available' )
            ->with( [
                'stockable' => static fn ( MorphTo $morph ) => $morph->morphWith( [
                    ProductVariant::class => [ 'product:id,name,type,sku' ],
                ] ),
            ] );
    }

    /**
     * Matches product and variant names and SKUs.
     *
     * @since 1.0.0
     *
     * @param  Builder<InventoryItem>  $query   The query.
     * @param  string                  $search  Search text.
     *
     * @return void
     */
    protected function applySearch( Builder $query, string $search ): void
    {
        $query->where( static function ( Builder $where ) use ( $search ): void {
            $where->whereHasMorph( 'stockable', [ Product::class ], static function ( Builder $product ) use ( $search ): void {
                $product->where( static function ( Builder $match ) use ( $search ): void {
                    static::orWhereContains( $match, 'products.name', $search );
                    static::orWhereContains( $match, 'products.sku', $search );
                } );
            } )->orWhereHasMorph( 'stockable', [ ProductVariant::class ], static function ( Builder $variant ) use ( $search ): void {
                $variant->where( static function ( Builder $match ) use ( $search ): void {
                    static::orWhereContains( $match, 'product_variants.name', $search );
                    static::orWhereContains( $match, 'product_variants.sku', $search );

                    $match->orWhereHas( 'product', static fn ( Builder $product ) => $product->where( static fn ( Builder $name ) => static::orWhereContains( $name, 'products.name', $search ) ) );
                } );
            } );
        } );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, Closure>
     */
    protected function filters(): array
    {
        return [
            'stock'   => static function ( Builder $query, mixed $value ): void {
                $available = '(inventory_items.quantity_on_hand - inventory_items.quantity_reserved)';

                match ( (string) $value ) {
                    'out'   => $query->where( 'inventory_items.track_inventory', true )->whereRaw( $available . ' <= 0' ),
                    'low'   => $query->where( 'inventory_items.track_inventory', true )
                        ->whereNotNull( 'inventory_items.low_stock_threshold' )
                        ->whereRaw( $available . ' > 0' )
                        ->whereRaw( $available . ' <= inventory_items.low_stock_threshold' ),
                    'reorder' => $query->where( 'inventory_items.track_inventory', true )
                        ->whereNotNull( 'inventory_items.low_stock_threshold' )
                        ->whereRaw( 'inventory_items.quantity_on_hand <= inventory_items.low_stock_threshold + inventory_items.quantity_reserved' ),
                    default => null,
                };
            },
            'tracked' => static fn ( Builder $query, mixed $value ) => $query->where( 'inventory_items.track_inventory', '1' === (string) $value ),
        ];
    }
}
