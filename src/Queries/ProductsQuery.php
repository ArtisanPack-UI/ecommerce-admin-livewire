<?php

/**
 * Products index query.
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
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Search, filters, and sorts for the products table (spec §7.2).
 *
 * Every row carries `stock_available` (on hand minus reserved, summed over
 * the product's own stock row and its variants' tracked rows),
 * `stock_tracked` (1 when any of those rows tracks inventory), and
 * `variants_count`. Prices in the store's base currency are eager-loaded
 * for the product and its variants.
 *
 * Search matches name, slug, and product or variant SKU in SQL; when the
 * engine's Scout feature is on, Scout's matches are added (Scout indexes
 * only active products, so drafts are still found by name and SKU).
 *
 * The built query runs through the engine's `ap.ecommerce.product.listQuery`
 * filter (engine spec §6.9) with the active filter values.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class ProductsQuery extends ResourceQuery
{
    /**
     * Stock states offered by the stock filter.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const STOCK_STATES = [ 'in', 'low', 'out', 'untracked' ];

    /**
     * Most Scout ids folded into a search.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const SCOUT_LIMIT = 500;

    /**
     * Builds the query, then hands it to `ap.ecommerce.product.listQuery`.
     *
     * @since 1.0.0
     *
     * @param  string                $search     Search text.
     * @param  array<string, mixed>  $filters    Filter values.
     * @param  string                $sort       Sort key.
     * @param  string                $direction  `asc` or `desc`.
     *
     * @return Builder<Product>
     */
    public function build( string $search = '', array $filters = [], string $sort = '', string $direction = '' ): Builder
    {
        $query    = parent::build( $search, $filters, $sort, $direction );
        $filtered = applyFilters( 'ap.ecommerce.product.listQuery', $query, $filters + [ 'search' => $search ] );

        return $filtered instanceof Builder ? $filtered : $query;
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, Closure|string>
     */
    public function sorts(): array
    {
        return [
            'name'    => 'products.name',
            'sku'     => 'products.sku',
            'type'    => 'products.type',
            'status'  => 'products.status',
            'stock'   => 'stock_available',
            'updated' => 'products.updated_at',
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array{0: string, 1: string}
     */
    public function defaultSort(): array
    {
        return [ 'updated', 'desc' ];
    }

    /**
     * The effective price among eager-loaded rows of one currency: the
     * scheduled row covering now, else the unscheduled base row (the engine's
     * `ProductPriceResolver` rule).
     *
     * @since 1.0.0
     *
     * @param  Collection<int, ProductPrice>  $rows  Price rows.
     *
     * @return int|null Minor units.
     */
    public static function effectivePrice( Collection $rows ): ?int
    {
        $now       = Carbon::now();
        $base      = null;
        $scheduled = null;

        foreach ( $rows as $row ) {
            if ( null === $row->starts_at && null === $row->ends_at ) {
                $base ??= $row;
                continue;
            }

            $started = null === $row->starts_at || $row->starts_at->lessThanOrEqualTo( $now );
            $running = null === $row->ends_at || $row->ends_at->greaterThanOrEqualTo( $now );

            if ( $started && $running && null === $scheduled ) {
                $scheduled = $row;
            }
        }

        $row = $scheduled ?? $base;

        return null === $row ? null : (int) $row->price_amount;
    }

    /**
     * The product's price in the base currency, or the min/max range of its
     * variants' prices when it has variants.
     *
     * @since 1.0.0
     *
     * @param  Product  $product  Product with `prices` and `variants.prices` loaded.
     *
     * @return array{min: int|null, max: int|null}
     */
    public static function priceRange( Product $product ): array
    {
        if ( $product->variants->isNotEmpty() ) {
            $prices = $product->variants
                ->map( static fn ( ProductVariant $variant ): ?int => self::effectivePrice( $variant->prices ) )
                ->filter( static fn ( ?int $price ): bool => null !== $price );

            if ( $prices->isNotEmpty() ) {
                return [ 'min' => (int) $prices->min(), 'max' => (int) $prices->max() ];
            }
        }

        $price = self::effectivePrice( $product->prices );

        return [ 'min' => $price, 'max' => $price ];
    }

    /**
     * The store's base currency.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function baseCurrency(): string
    {
        return strtoupper( (string) config( 'artisanpack.ecommerce.base_currency', 'USD' ) );
    }

    /**
     * @since 1.0.0
     *
     * @return Builder<Product>
     */
    protected function baseQuery(): Builder
    {
        $currency = self::baseCurrency();
        $prices   = static fn ( $query ) => $query->where( 'currency', $currency );

        return Product::query()
            ->select( 'products.*' )
            ->selectSub( $this->stockRows()->selectRaw( 'COALESCE(SUM(quantity_on_hand - quantity_reserved), 0)' ), 'stock_available' )
            ->selectSub( $this->stockRows()->selectRaw( 'COUNT(*)' ), 'stock_tracked' )
            ->withCount( 'variants' )
            ->with( [
                'categories:id,name',
                'images'          => static fn ( $query ) => $query->limit( 1 ),
                'prices'          => $prices,
                'variants:id,product_id',
                'variants.prices' => $prices,
            ] );
    }

    /**
     * @since 1.0.0
     *
     * @param  Builder<Product>  $query   The query.
     * @param  string            $search  Search text.
     *
     * @return void
     */
    protected function applySearch( Builder $query, string $search ): void
    {
        $scoutIds = $this->scoutIds( $search );

        $query->where( static function ( Builder $where ) use ( $search, $scoutIds ): void {
            static::orWhereContains( $where, 'products.name', $search );
            static::orWhereContains( $where, 'products.slug', $search );
            static::orWhereContains( $where, 'products.sku', $search );

            $where->orWhereHas( 'variants', static fn ( Builder $variant ) => $variant->where( static fn ( Builder $sku ) => static::orWhereContains( $sku, 'product_variants.sku', $search ) ) );

            if ( [] !== $scoutIds ) {
                $where->orWhereIn( 'products.id', $scoutIds );
            }
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
            'status'   => static fn ( Builder $query, mixed $value ) => $query->where( 'products.status', (string) $value ),
            'type'     => static fn ( Builder $query, mixed $value ) => $query->where( 'products.type', (string) $value ),
            'category' => static fn ( Builder $query, mixed $value ) => $query->whereHas( 'categories', static fn ( Builder $category ) => $category->whereKey( (int) $value ) ),
            'tag'      => static fn ( Builder $query, mixed $value ) => $query->whereHas( 'tags', static fn ( Builder $tag ) => $tag->whereKey( (int) $value ) ),
            'stock'    => fn ( Builder $query, mixed $value ) => $this->applyStockState( $query, (string) $value ),
        ];
    }

    /**
     * Narrows to a stock state.
     *
     * - `out` — tracked, and nothing available;
     * - `low` — tracked, something available, and a row at or below its
     *   low-stock threshold;
     * - `in` — tracked and something available;
     * - `untracked` — no tracked stock row.
     *
     * @since 1.0.0
     *
     * @param  Builder<Product>  $query  The query.
     * @param  string            $state  Stock state.
     *
     * @return void
     */
    protected function applyStockState( Builder $query, string $state ): void
    {
        $available = $this->stockRows()->selectRaw( 'COALESCE(SUM(quantity_on_hand - quantity_reserved), 0)' );

        match ( $state ) {
            'untracked' => $query->whereNotExists( $this->stockRows()->toBase() ),
            'out'       => $query->whereExists( $this->stockRows()->toBase() )->where( $available->toBase(), '<=', 0 ),
            'in'        => $query->whereExists( $this->stockRows()->toBase() )->where( $available->toBase(), '>', 0 ),
            'low'       => $query->where( $available->toBase(), '>', 0 )->whereExists(
                $this->stockRows()
                    ->whereNotNull( 'inventory_items.low_stock_threshold' )
                    ->whereRaw( '(inventory_items.quantity_on_hand - inventory_items.quantity_reserved) <= inventory_items.low_stock_threshold' )
                    ->toBase(),
            ),
            default     => null,
        };
    }

    /**
     * Tracked stock rows of the outer product and its variants.
     *
     * @since 1.0.0
     *
     * @return Builder<InventoryItem>
     */
    protected function stockRows(): Builder
    {
        $product = ( new Product() )->getMorphClass();
        $variant = ( new ProductVariant() )->getMorphClass();

        return InventoryItem::query()
            ->where( 'inventory_items.track_inventory', true )
            ->where( static function ( Builder $owner ) use ( $product, $variant ): void {
                $owner->where( static fn ( Builder $own ) => $own->where( 'inventory_items.stockable_type', $product )->whereColumn( 'inventory_items.stockable_id', 'products.id' ) )
                    ->orWhere( static fn ( Builder $own ) => $own->where( 'inventory_items.stockable_type', $variant )->whereIn(
                        'inventory_items.stockable_id',
                        ProductVariant::query()->select( 'product_variants.id' )->whereColumn( 'product_variants.product_id', 'products.id' ),
                    ) );
            } );
    }

    /**
     * Product ids Scout matches, when the engine's Scout feature is on.
     *
     * @since 1.0.0
     *
     * @param  string  $search  Search text.
     *
     * @return array<int, int>
     */
    protected function scoutIds( string $search ): array
    {
        if ( ! (bool) config( 'artisanpack.ecommerce.features.scout', true ) ) {
            return [];
        }

        try {
            return Product::search( $search )->take( self::SCOUT_LIMIT )->keys()->map( static fn ( mixed $id ): int => (int) $id )->all();
        } catch ( Throwable $exception ) {
            report( $exception );

            return [];
        }
    }
}
