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
            'on_hand'   => static::column( InventoryItem::class, 'quantity_on_hand' ),
            'reserved'  => static::column( InventoryItem::class, 'quantity_reserved' ),
            'available' => 'quantity_available',
            'threshold' => static::column( InventoryItem::class, 'low_stock_threshold' ),
            'updated'   => static::column( InventoryItem::class, 'updated_at' ),
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
     * `(on hand - reserved)` for raw SQL, with the columns qualified.
     *
     * @since 1.0.0
     *
     * @param  Builder<InventoryItem>  $query  The query whose grammar wraps the columns.
     *
     * @return string
     */
    public static function availableSql( Builder $query ): string
    {
        return '(' . static::raw( $query, InventoryItem::class, 'quantity_on_hand' ) . ' - ' . static::raw( $query, InventoryItem::class, 'quantity_reserved' ) . ')';
    }

    /**
     * @since 1.0.0
     *
     * @return Builder<InventoryItem>
     */
    protected function baseQuery(): Builder
    {
        $query = InventoryItem::query();

        return $query
            ->select( static::column( InventoryItem::class, '*' ) )
            ->selectRaw( self::availableSql( $query ) . ' as quantity_available' )
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
                    static::orWhereContains( $match, static::column( Product::class, 'name' ), $search );
                    static::orWhereContains( $match, static::column( Product::class, 'sku' ), $search );
                } );
            } )->orWhereHasMorph( 'stockable', [ ProductVariant::class ], static function ( Builder $variant ) use ( $search ): void {
                $variant->where( static function ( Builder $match ) use ( $search ): void {
                    static::orWhereContains( $match, static::column( ProductVariant::class, 'name' ), $search );
                    static::orWhereContains( $match, static::column( ProductVariant::class, 'sku' ), $search );

                    $match->orWhereHas( 'product', static fn ( Builder $product ) => $product->where( static fn ( Builder $name ) => static::orWhereContains( $name, static::column( Product::class, 'name' ), $search ) ) );
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
                $available = self::availableSql( $query );
                $threshold = static::raw( $query, InventoryItem::class, 'low_stock_threshold' );
                $onHand    = static::raw( $query, InventoryItem::class, 'quantity_on_hand' );
                $reserved  = static::raw( $query, InventoryItem::class, 'quantity_reserved' );

                match ( (string) $value ) {
                    'out'   => $query->where( static::column( InventoryItem::class, 'track_inventory' ), true )->whereRaw( $available . ' <= 0' ),
                    'low'   => $query->where( static::column( InventoryItem::class, 'track_inventory' ), true )
                        ->whereNotNull( static::column( InventoryItem::class, 'low_stock_threshold' ) )
                        ->whereRaw( $available . ' > 0' )
                        ->whereRaw( $available . ' <= ' . $threshold ),
                    'reorder' => $query->where( static::column( InventoryItem::class, 'track_inventory' ), true )
                        ->whereNotNull( static::column( InventoryItem::class, 'low_stock_threshold' ) )
                        ->whereRaw( $onHand . ' <= ' . $threshold . ' + ' . $reserved ),
                    default => null,
                };
            },
            'tracked' => static fn ( Builder $query, mixed $value ) => $query->where( static::column( InventoryItem::class, 'track_inventory' ), '1' === (string) $value ),
        ];
    }
}
