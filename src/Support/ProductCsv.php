<?php

/**
 * Product CSV format.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Exceptions\ProductWriteException;
use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Models\TaxClass;
use ArtisanPackUI\Ecommerce\Registries\ProductTypeRegistry;
use ArtisanPackUI\Ecommerce\Services\ProductService;
use ArtisanPackUI\Ecommerce\Services\ProductTagService;
use Generator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The product CSV format shared by export and import (spec §7.2,
 * `docs/product-csv.md`).
 *
 * - One row per product. A product with variants is followed by one row per
 *   variant; a variant row repeats the product's `type`, `name`, `slug`, and
 *   `sku` (to find the product) and fills the `variant_*`, price, and stock
 *   columns.
 * - Prices are a pair of columns per currency, `price_{CUR}` and
 *   `compare_at_price_{CUR}`, in major units (`12.50`).
 * - `categories` holds category slugs and `tags` tag names, separated by `|`.
 *
 * On import, rows match on SKU, then slug (variants on `variant_sku`). A
 * blank cell on an existing product or variant leaves that value alone. Each
 * row is written through the engine's `ProductService`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class ProductCsv
{
    /**
     * Separator for list cells (`categories`, `tags`).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const LIST_SEPARATOR = '|';

    /**
     * Product columns that map straight onto `ProductService` fields.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const PRODUCT_COLUMNS = [ 'type', 'name', 'slug', 'sku', 'status', 'short_description', 'description', 'barcode', 'weight', 'weight_unit', 'length', 'width', 'height', 'dim_unit', 'is_taxable', 'tax_class_key', 'is_featured', 'position' ];

    /**
     * The largest catalog position (the column is an unsigned int).
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_POSITION = 4294967295;

    /**
     * Stock columns.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const STOCK_COLUMNS = [ 'track_inventory', 'quantity_on_hand', 'allow_backorder', 'low_stock_threshold' ];

    /**
     * Columns read as yes/no.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const BOOLEAN_COLUMNS = [ 'is_taxable', 'track_inventory', 'allow_backorder', 'is_featured' ];

    /**
     * Columns read as decimal numbers.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const DECIMAL_COLUMNS = [ 'weight', 'length', 'width', 'height' ];

    /**
     * Every column, keyed by name, with its label.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        $columns = [
            'type'                => __( 'Type' ),
            'name'                => __( 'Name' ),
            'slug'                => __( 'Slug' ),
            'sku'                 => __( 'SKU' ),
            'status'              => __( 'Status' ),
            'short_description'   => __( 'Short description' ),
            'description'         => __( 'Description' ),
            'barcode'             => __( 'Barcode' ),
            'categories'          => __( 'Categories' ),
            'tags'                => __( 'Tags' ),
            'weight'              => __( 'Weight' ),
            'weight_unit'         => __( 'Weight unit' ),
            'length'              => __( 'Length' ),
            'width'               => __( 'Width' ),
            'height'              => __( 'Height' ),
            'dim_unit'            => __( 'Dimension unit' ),
            'is_taxable'          => __( 'Taxable' ),
            'tax_class_key'       => __( 'Tax class' ),
            'is_featured'         => __( 'Featured' ),
            'position'            => __( 'Catalog position' ),
            'track_inventory'     => __( 'Track inventory' ),
            'quantity_on_hand'    => __( 'Quantity on hand' ),
            'allow_backorder'     => __( 'Allow backorders' ),
            'low_stock_threshold' => __( 'Low-stock threshold' ),
            'variant_sku'         => __( 'Variant SKU' ),
            'variant_name'        => __( 'Variant name' ),
        ];

        foreach ( self::currencies() as $currency ) {
            $columns[ 'price_' . $currency ]            = __( 'Price (:currency)', [ 'currency' => $currency ] );
            $columns[ 'compare_at_price_' . $currency ] = __( 'Compare-at price (:currency)', [ 'currency' => $currency ] );
        }

        return $columns;
    }

    /**
     * The store's enabled currencies.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public static function currencies(): array
    {
        return StoreCurrencies::enabled();
    }

    /**
     * Suggests a column for each CSV header: an exact or loosely matching
     * column name, or `''` to ignore the header.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $headers  CSV headers.
     *
     * @return array<string, string>
     */
    public static function guessMapping( array $headers ): array
    {
        $columns = array_keys( self::columns() );
        $loose   = [];

        foreach ( $columns as $column ) {
            $loose[ self::looseKey( $column ) ] = $column;
        }

        $aliases = [
            'title'          => 'name',
            'productname'    => 'name',
            'price'          => 'price_' . StoreCurrencies::base(),
            'compareatprice' => 'compare_at_price_' . StoreCurrencies::base(),
            'stock'          => 'quantity_on_hand',
            'quantity'       => 'quantity_on_hand',
            'category'       => 'categories',
            'tag'            => 'tags',
        ];

        $mapping = [];
        $used    = [];

        foreach ( $headers as $header ) {
            $key    = self::looseKey( $header );
            $column = $loose[ $key ] ?? $aliases[ $key ] ?? '';

            if ( '' !== $column && isset( $used[ $column ] ) ) {
                $column = '';
            }

            $mapping[ $header ] = $column;

            if ( '' !== $column ) {
                $used[ $column ] = true;
            }
        }

        return $mapping;
    }

    /**
     * The export rows for a product query, header first.
     *
     * @since 1.0.0
     *
     * @param  Builder<Product>  $products  Products to export.
     *
     * @return Generator<int, array<int, string>>
     */
    public static function exportRows( Builder $products ): Generator
    {
        $columns = array_keys( self::columns() );

        yield $columns;

        $query = ( clone $products )->with( [
            'categories:id,slug',
            'tags:id,name',
            'prices',
            'variants' => static fn ( $variant ) => $variant->orderBy( 'position' )->orderBy( 'id' ),
            'variants.prices',
        ] );

        foreach ( $query->lazy( 200 )->chunk( 200 ) as $chunk ) {
            $stock = self::stockMap( $chunk );

            foreach ( $chunk as $product ) {
                $values = [];

                foreach ( self::PRODUCT_COLUMNS as $column ) {
                    $values[ $column ] = self::exportValue( $product->getAttribute( $column ) );
                }

                $values['categories'] = $product->categories->pluck( 'slug' )->implode( self::LIST_SEPARATOR );
                $values['tags']       = $product->tags->pluck( 'name' )->implode( self::LIST_SEPARATOR );
                $values += self::stockValues( $product, $stock ) + self::priceValues( $product->prices );

                yield self::ordered( $columns, $values );

                foreach ( $product->variants as $variant ) {
                    $row = [
                        'type'         => (string) $product->type,
                        'name'         => (string) $product->name,
                        'slug'         => (string) $product->slug,
                        'sku'          => (string) ( $product->sku ?? '' ),
                        'variant_sku'  => (string) ( $variant->sku ?? '' ),
                        'variant_name' => (string) ( $variant->name ?? '' ),
                    ];

                    yield self::ordered( $columns, $row + self::stockValues( $variant, $stock ) + self::priceValues( $variant->prices ) );
                }
            }
        }
    }

    /**
     * Checks one mapped row without writing anything.
     *
     * `$context` carries what earlier rows of the same file would create, so
     * a variant row can follow the row that creates its product, and a SKU
     * used twice in the file is caught.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $row      Values keyed by column.
     * @param  array<string, mixed>   $context  Running state across rows.
     *
     * @return array{action: string, error: string|null, label: string}
     */
    public static function check( array $row, array &$context ): array
    {
        $context += [ 'products' => [], 'skus' => [] ];

        try {
            $action = self::resolve( $row, $context );

            self::validateValues( $row, $action );

            foreach ( [ self::value( $row, 'sku' ), self::value( $row, 'variant_sku' ) ] as $index => $sku ) {
                if ( '' === $sku || ( 0 === $index && self::isVariantRow( $row ) ) ) {
                    continue;
                }

                $key = mb_strtolower( $sku );

                if ( isset( $context['skus'][ $key ] ) ) {
                    throw new InvalidArgumentException( __( 'SKU ":sku" is used by an earlier row of this file.', [ 'sku' => $sku ] ) );
                }

                $context['skus'][ $key ] = true;
            }

            if ( 'create' === $action['action'] ) {
                foreach ( [ self::value( $row, 'sku' ), Str::slug( self::value( $row, 'slug' ) ?: self::value( $row, 'name' ) ) ] as $key ) {
                    if ( '' !== $key ) {
                        $context['products'][ mb_strtolower( $key ) ] = true;
                    }
                }
            }

            return [ 'action' => $action['action'], 'error' => null, 'label' => self::label( $row ) ];
        } catch ( InvalidArgumentException|ProductWriteException $exception ) {
            return [ 'action' => 'error', 'error' => $exception->getMessage(), 'label' => self::label( $row ) ];
        }
    }

    /**
     * Writes one mapped row through `ProductService`. The caller wraps it in
     * a transaction.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $row   Values keyed by column.
     * @param  Authenticatable|null   $user  The user the import runs as; each row checks their ability.
     *
     * @throws InvalidArgumentException|ProductWriteException When the row is refused.
     *
     * @return string The action taken: `create`, `update`, `create-variant`, or `update-variant`.
     */
    public static function apply( array $row, ?Authenticatable $user ): string
    {
        $context = [];
        $action  = self::resolve( $row, $context );

        self::validateValues( $row, $action );

        $ability = 'create' === $action['action'] ? 'product.create' : 'product.update';

        if ( ! Authorization::allows( $user, $ability, $action['product'] ) ) {
            throw new InvalidArgumentException( 'create' === $action['action'] ? __( 'You may not create products.' ) : __( 'You may not edit this product.' ) );
        }

        $service = app( ProductService::class );

        switch ( $action['action'] ) {
            case 'create':
                $product = $service->create( self::productData( $row, true ) + [ 'prices' => self::priceRows( $row ) ] );
                break;

            case 'update':
                $product = $service->update( $action['product'], self::productData( $row, false ) );
                self::writePrices( $product, $row );
                self::writeQuantity( $product, $row );
                break;

            case 'create-variant':
                $variant = $service->createVariant( $action['product'], self::variantData( $row, true ) + [ 'prices' => self::priceRows( $row ) ] );
                break;

            default:
                $variant = $service->updateVariant( $action['variant'], self::variantData( $row, false ) );
                self::writePrices( $variant, $row );
                self::writeQuantity( $variant, $row );
        }

        return $action['action'];
    }

    /**
     * A label for a row in reports: the variant SKU, the SKU, the slug, or the name.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $row  Values keyed by column.
     *
     * @return string
     */
    public static function label( array $row ): string
    {
        foreach ( [ 'variant_sku', 'sku', 'slug', 'name' ] as $column ) {
            if ( '' !== self::value( $row, $column ) ) {
                return self::value( $row, $column );
            }
        }

        return '';
    }

    /**
     * Finds what a row writes to.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $row      Values keyed by column.
     * @param  array<string, mixed>   $context  Running state (dry runs).
     *
     * @throws InvalidArgumentException When the row cannot be placed.
     *
     * @return array{action: string, product: Product|null, variant: ProductVariant|null}
     */
    private static function resolve( array $row, array $context ): array
    {
        $product = self::matchProduct( $row );

        if ( self::isVariantRow( $row ) ) {
            $variantSku = self::value( $row, 'variant_sku' );

            if ( '' === $variantSku ) {
                throw new InvalidArgumentException( __( 'A variant row needs a variant SKU.' ) );
            }

            $variant = ProductVariant::query()->with( 'product' )->where( 'sku', $variantSku )->first();

            if ( null !== $variant ) {
                self::assertEditable( $variant->product );

                return [ 'action' => 'update-variant', 'product' => $variant->product, 'variant' => $variant ];
            }

            if ( Product::query()->where( 'sku', $variantSku )->exists() ) {
                throw new InvalidArgumentException( __( 'Another product or variant already uses this SKU.' ) );
            }

            if ( null === $product ) {
                $pending = array_filter( [ mb_strtolower( self::value( $row, 'sku' ) ), mb_strtolower( Str::slug( self::value( $row, 'slug' ) ) ) ] );

                if ( [] !== array_intersect_key( (array) ( $context['products'] ?? [] ), array_flip( $pending ) ) ) {
                    return [ 'action' => 'create-variant', 'product' => null, 'variant' => null ];
                }

                throw new InvalidArgumentException( __( 'No product matches this row\'s SKU or slug, so the variant has nowhere to go.' ) );
            }

            self::assertEditable( $product );

            return [ 'action' => 'create-variant', 'product' => $product, 'variant' => null ];
        }

        if ( null !== $product ) {
            self::assertEditable( $product );

            return [ 'action' => 'update', 'product' => $product, 'variant' => null ];
        }

        // In a dry run, a product an earlier row creates is updated by this
        // one, just as it will be when the rows are applied in order.
        $pending = array_filter( [ mb_strtolower( self::value( $row, 'sku' ) ), mb_strtolower( Str::slug( self::value( $row, 'slug' ) ) ) ] );

        if ( [] !== array_intersect_key( (array) ( $context['products'] ?? [] ), array_flip( $pending ) ) ) {
            return [ 'action' => 'update', 'product' => null, 'variant' => null ];
        }

        return [ 'action' => 'create', 'product' => null, 'variant' => null ];
    }

    /**
     * Checks a row's values the way the engine will.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>                                                             $row     Values keyed by column.
     * @param  array{action: string, product: Product|null, variant: ProductVariant|null}        $action  Resolved action.
     *
     * @throws InvalidArgumentException When a value is invalid.
     *
     * @return void
     */
    private static function validateValues( array $row, array $action ): void
    {
        $creating = 'create' === $action['action'];
        $type     = self::value( $row, 'type' );

        if ( $creating && '' === self::value( $row, 'name' ) ) {
            throw new InvalidArgumentException( __( 'A new product needs a name.' ) );
        }

        if ( '' !== $type && ! app( ProductTypeRegistry::class )->has( $type ) && ( $creating || $type !== $action['product']?->type ) ) {
            throw new InvalidArgumentException( __( 'Product type ":type" is not registered.', [ 'type' => $type ] ) );
        }

        $status = self::value( $row, 'status' );

        if ( '' !== $status && ! in_array( $status, ProductService::STATUSES, true ) ) {
            throw new InvalidArgumentException( __( 'Status must be draft, active, or archived.' ) );
        }

        foreach ( self::BOOLEAN_COLUMNS as $column ) {
            if ( '' !== self::value( $row, $column ) && null === self::boolean( self::value( $row, $column ) ) ) {
                throw new InvalidArgumentException( __( 'Column ":column" must be yes or no.', [ 'column' => $column ] ) );
            }
        }

        foreach ( self::DECIMAL_COLUMNS as $column ) {
            $value = self::value( $row, $column );

            if ( '' !== $value && ( ! is_numeric( $value ) || (float) $value < 0 ) ) {
                throw new InvalidArgumentException( __( 'Column ":column" must be a number.', [ 'column' => $column ] ) );
            }
        }

        foreach ( [ 'quantity_on_hand', 'low_stock_threshold' ] as $column ) {
            $value = self::value( $row, $column );

            if ( '' !== $value && 1 !== preg_match( '/^\d{1,9}$/', $value ) ) {
                throw new InvalidArgumentException( __( 'Column ":column" must be a whole number of 0 or more.', [ 'column' => $column ] ) );
            }
        }

        $position = self::value( $row, 'position' );

        if ( '' !== $position && ( 1 !== preg_match( '/^\d{1,10}$/', $position ) || (int) $position > self::MAX_POSITION ) ) {
            throw new InvalidArgumentException( __( 'Column ":column" must be a whole number from 0 to :max.', [ 'column' => 'position', 'max' => self::MAX_POSITION ] ) );
        }

        $taxClass = self::value( $row, 'tax_class_key' );

        if ( '' !== $taxClass && ! TaxClass::query()->where( 'key', $taxClass )->exists() ) {
            throw new InvalidArgumentException( __( 'Tax class ":class" does not exist.', [ 'class' => $taxClass ] ) );
        }

        $missing = array_diff( self::listValue( $row, 'categories' ), ProductCategory::query()->whereIn( 'slug', self::listValue( $row, 'categories' ) )->pluck( 'slug' )->all() );

        if ( [] !== $missing ) {
            throw new InvalidArgumentException( __( 'No category has the slug ":slug".', [ 'slug' => reset( $missing ) ] ) );
        }

        self::priceRows( $row );

        if ( ! self::isVariantRow( $row ) ) {
            self::assertUnique( $row, $action['product'] );
        }
    }

    /**
     * Refuses a SKU or slug another product (or variant) already uses.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $row      Values keyed by column.
     * @param  Product|null           $product  The matched product, when updating.
     *
     * @throws InvalidArgumentException When taken.
     *
     * @return void
     */
    private static function assertUnique( array $row, ?Product $product ): void
    {
        $sku  = self::value( $row, 'sku' );
        $slug = Str::slug( self::value( $row, 'slug' ) );
        $id   = $product?->id;

        if ( '' !== $sku && (
            Product::query()->where( 'sku', $sku )->when( null !== $id, static fn ( Builder $query ) => $query->whereKeyNot( $id ) )->exists()
            || ProductVariant::query()->where( 'sku', $sku )->exists()
        ) ) {
            throw new InvalidArgumentException( __( 'Another product or variant already uses the SKU ":sku".', [ 'sku' => $sku ] ) );
        }

        if ( '' !== $slug && Product::query()->where( 'slug', $slug )->when( null !== $id, static fn ( Builder $query ) => $query->whereKeyNot( $id ) )->exists() ) {
            throw new InvalidArgumentException( __( 'Another product already uses the slug ":slug".', [ 'slug' => $slug ] ) );
        }
    }

    /**
     * The product a row names: by SKU, then by slug.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $row  Values keyed by column.
     *
     * @return Product|null
     */
    private static function matchProduct( array $row ): ?Product
    {
        $sku  = self::value( $row, 'sku' );
        $slug = self::value( $row, 'slug' );

        return ( '' === $sku ? null : Product::query()->where( 'sku', $sku )->first() )
            ?? ( '' === $slug ? null : Product::query()->where( 'slug', Str::slug( $slug ) )->first() );
    }

    /**
     * Refuses products whose type is missing (read-only, plan §16.6).
     *
     * @since 1.0.0
     *
     * @param  Product|null  $product  Product.
     *
     * @throws InvalidArgumentException When read-only.
     *
     * @return void
     */
    private static function assertEditable( ?Product $product ): void
    {
        if ( null !== $product && $product->typeIsMissing() ) {
            throw new InvalidArgumentException( __( 'This product\'s type is not installed, so it is read-only.' ) );
        }
    }

    /**
     * `ProductService` data for a product row. On update, blank cells are left out.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $row       Values keyed by column.
     * @param  bool                   $creating  Whether the product is new.
     *
     * @return array<string, mixed>
     */
    private static function productData( array $row, bool $creating ): array
    {
        $data = [];

        foreach ( self::PRODUCT_COLUMNS as $column ) {
            $value = self::value( $row, $column );

            if ( '' !== $value ) {
                $data[ $column ] = match ( true ) {
                    in_array( $column, self::BOOLEAN_COLUMNS, true ) => self::boolean( $value ),
                    'position' === $column                           => (int) $value,
                    default                                          => $value,
                };
            }
        }

        if ( $creating ) {
            $data['type'] ??= 'simple';
            $data['status'] ??= 'draft';
        }

        if ( array_key_exists( 'categories', $row ) && ( $creating || '' !== self::value( $row, 'categories' ) ) ) {
            $data['category_ids'] = ProductCategory::query()->whereIn( 'slug', self::listValue( $row, 'categories' ) )->pluck( 'id' )->all();
        }

        if ( array_key_exists( 'tags', $row ) && ( $creating || '' !== self::value( $row, 'tags' ) ) ) {
            $tags            = app( ProductTagService::class );
            $data['tag_ids'] = array_map( static fn ( string $name ): int => (int) $tags->findOrCreate( $name )->id, self::listValue( $row, 'tags' ) );
        }

        $inventory = self::inventorySettings( $row );

        if ( $creating && '' !== self::value( $row, 'quantity_on_hand' ) ) {
            $inventory['quantity_on_hand'] = (int) self::value( $row, 'quantity_on_hand' );
        }

        if ( [] !== $inventory ) {
            $data['inventory'] = $inventory;
        }

        return $data;
    }

    /**
     * `ProductService` data for a variant row.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $row       Values keyed by column.
     * @param  bool                   $creating  Whether the variant is new.
     *
     * @return array<string, mixed>
     */
    private static function variantData( array $row, bool $creating ): array
    {
        $data = [ 'sku' => self::value( $row, 'variant_sku' ) ];

        if ( '' !== self::value( $row, 'variant_name' ) ) {
            $data['name'] = self::value( $row, 'variant_name' );
        }

        $inventory = self::inventorySettings( $row );

        if ( $creating && '' !== self::value( $row, 'quantity_on_hand' ) ) {
            $inventory['quantity_on_hand'] = (int) self::value( $row, 'quantity_on_hand' );
        }

        if ( [] !== $inventory ) {
            $data['inventory'] = $inventory;
        }

        return $data;
    }

    /**
     * The non-blank stock settings of a row.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $row  Values keyed by column.
     *
     * @return array<string, mixed>
     */
    private static function inventorySettings( array $row ): array
    {
        $settings = [];

        foreach ( [ 'track_inventory', 'allow_backorder' ] as $column ) {
            if ( '' !== self::value( $row, $column ) ) {
                $settings[ $column ] = self::boolean( self::value( $row, $column ) );
            }
        }

        if ( '' !== self::value( $row, 'low_stock_threshold' ) ) {
            $settings['low_stock_threshold'] = (int) self::value( $row, 'low_stock_threshold' );
        }

        return $settings;
    }

    /**
     * The price rows of a row, in minor units.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $row  Values keyed by column.
     *
     * @throws InvalidArgumentException When an amount is invalid, or a compare-at price has no price.
     *
     * @return array<int, array{currency: string, price_amount: int, compare_at_amount: int|null}>
     */
    private static function priceRows( array $row ): array
    {
        $rows = [];

        foreach ( self::currencies() as $currency ) {
            $price   = self::amount( $row, 'price_' . $currency, $currency );
            $compare = self::amount( $row, 'compare_at_price_' . $currency, $currency );

            if ( null === $price && null !== $compare ) {
                throw new InvalidArgumentException( __( 'A compare-at price in :currency needs a price in :currency too.', [ 'currency' => $currency ] ) );
            }

            if ( null !== $price ) {
                $rows[] = [ 'currency' => $currency, 'price_amount' => $price, 'compare_at_amount' => $compare ];
            }
        }

        return $rows;
    }

    /**
     * Upserts the row's unscheduled price in each currency it fills.
     *
     * @since 1.0.0
     *
     * @param  Product|ProductVariant  $priceable  Owner.
     * @param  array<string, string>   $row        Values keyed by column.
     *
     * @return void
     */
    private static function writePrices( Product|ProductVariant $priceable, array $row ): void
    {
        $service = app( ProductService::class );

        foreach ( self::priceRows( $row ) as $price ) {
            $service->upsertPrice( $priceable, $price + [ 'starts_at' => null, 'ends_at' => null ] );
        }
    }

    /**
     * Brings the stock count to the row's quantity, through an audited
     * adjustment.
     *
     * @since 1.0.0
     *
     * @param  Product|ProductVariant  $stockable  Owner.
     * @param  array<string, string>   $row        Values keyed by column.
     *
     * @return void
     */
    private static function writeQuantity( Product|ProductVariant $stockable, array $row ): void
    {
        if ( '' === self::value( $row, 'quantity_on_hand' ) ) {
            return;
        }

        // Locks the stock row, so a sale landing meanwhile is not lost.
        StockLevels::adjust( app( ProductService::class )->inventoryItemFor( $stockable ), 'set', (int) self::value( $row, 'quantity_on_hand' ), __( 'CSV import' ) );
    }

    /**
     * An amount column in minor units, or null when blank.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $row       Values keyed by column.
     * @param  string                 $column    Column.
     * @param  string                 $currency  Currency.
     *
     * @throws InvalidArgumentException When the amount is invalid.
     *
     * @return int|null
     */
    private static function amount( array $row, string $column, string $currency ): ?int
    {
        $value = self::value( $row, $column );

        if ( '' === $value ) {
            return null;
        }

        try {
            $minor = MinorUnits::toMinor( $value, $currency );
        } catch ( InvalidArgumentException ) {
            $minor = null;
        }

        if ( null === $minor || $minor < 0 ) {
            throw new InvalidArgumentException( __( 'Column ":column" must be an amount such as 12.50.', [ 'column' => $column ] ) );
        }

        return $minor;
    }

    /**
     * The stock rows (no warehouse) of a batch of products and their
     * variants, keyed `{morph}:{id}`.
     *
     * @since 1.0.0
     *
     * @param  iterable<int, Product>  $products  Products with `variants` loaded.
     *
     * @return array<string, InventoryItem>
     */
    private static function stockMap( iterable $products ): array
    {
        $productIds = [];
        $variantIds = [];

        foreach ( $products as $product ) {
            $productIds[] = $product->getKey();

            foreach ( $product->variants as $variant ) {
                $variantIds[] = $variant->getKey();
            }
        }

        $productType = ( new Product() )->getMorphClass();
        $variantType = ( new ProductVariant() )->getMorphClass();
        $map         = [];

        $items = InventoryItem::query()
            ->where( 'warehouse_id', InventoryItem::DEFAULT_WAREHOUSE )
            ->where( static function ( Builder $owner ) use ( $productType, $productIds, $variantType, $variantIds ): void {
                $owner->where( static fn ( Builder $own ) => $own->where( 'stockable_type', $productType )->whereIn( 'stockable_id', $productIds ) )
                    ->orWhere( static fn ( Builder $own ) => $own->where( 'stockable_type', $variantType )->whereIn( 'stockable_id', [] === $variantIds ? [ 0 ] : $variantIds ) );
            } )
            ->get();

        foreach ( $items as $item ) {
            $map[ $item->stockable_type . ':' . $item->stockable_id ] = $item;
        }

        return $map;
    }

    /**
     * The stock columns of a product or variant.
     *
     * @since 1.0.0
     *
     * @param  Product|ProductVariant         $stockable  Owner.
     * @param  array<string, InventoryItem>   $stock      Stock rows from {@see self::stockMap()}.
     *
     * @return array<string, string>
     */
    private static function stockValues( Model $stockable, array $stock ): array
    {
        $item = $stock[ $stockable->getMorphClass() . ':' . $stockable->getKey() ] ?? null;

        if ( null === $item ) {
            return [];
        }

        return [
            'track_inventory'     => $item->track_inventory ? '1' : '0',
            'quantity_on_hand'    => (string) $item->quantity_on_hand,
            'allow_backorder'     => $item->allow_backorder ? '1' : '0',
            'low_stock_threshold' => null === $item->low_stock_threshold ? '' : (string) $item->low_stock_threshold,
        ];
    }

    /**
     * The price columns from unscheduled price rows.
     *
     * @since 1.0.0
     *
     * @param  iterable<int, ProductPrice>  $prices  Price rows.
     *
     * @return array<string, string>
     */
    private static function priceValues( iterable $prices ): array
    {
        $values = [];

        foreach ( $prices as $price ) {
            if ( null !== $price->starts_at || null !== $price->ends_at ) {
                continue;
            }

            $currency = strtoupper( (string) $price->currency );

            $values[ 'price_' . $currency ]            = MinorUnits::toMajor( (int) $price->price_amount, $currency );
            $values[ 'compare_at_price_' . $currency ] = null === $price->compare_at_amount ? '' : MinorUnits::toMajor( (int) $price->compare_at_amount, $currency );
        }

        return $values;
    }

    /**
     * Values in column order, blanks for missing ones.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>     $columns  Columns.
     * @param  array<string, string>  $values   Values keyed by column.
     *
     * @return array<int, string>
     */
    private static function ordered( array $columns, array $values ): array
    {
        return array_map( static fn ( string $column ): string => (string) ( $values[ $column ] ?? '' ), $columns );
    }

    /**
     * A model attribute as CSV text.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  Attribute.
     *
     * @return string
     */
    private static function exportValue( mixed $value ): string
    {
        return match ( true ) {
            is_bool( $value )   => $value ? '1' : '0',
            is_scalar( $value ) => (string) $value,
            default             => '',
        };
    }

    /**
     * Whether a row describes a variant.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $row  Values keyed by column.
     *
     * @return bool
     */
    private static function isVariantRow( array $row ): bool
    {
        return '' !== self::value( $row, 'variant_sku' ) || '' !== self::value( $row, 'variant_name' );
    }

    /**
     * A trimmed cell.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $row     Values keyed by column.
     * @param  string                $column  Column.
     *
     * @return string
     */
    private static function value( array $row, string $column ): string
    {
        return trim( (string) ( $row[ $column ] ?? '' ) );
    }

    /**
     * A list cell's items.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $row     Values keyed by column.
     * @param  string                 $column  Column.
     *
     * @return array<int, string>
     */
    private static function listValue( array $row, string $column ): array
    {
        return array_values( array_unique( array_filter( array_map( 'trim', explode( self::LIST_SEPARATOR, self::value( $row, $column ) ) ), static fn ( string $item ): bool => '' !== $item ) ) );
    }

    /**
     * A yes/no cell, or null when unreadable.
     *
     * @since 1.0.0
     *
     * @param  string  $value  Cell.
     *
     * @return bool|null
     */
    private static function boolean( string $value ): ?bool
    {
        return match ( strtolower( trim( $value ) ) ) {
            '1', 'true', 'yes', 'y' => true,
            '0', 'false', 'no', 'n' => false,
            default                 => null,
        };
    }

    /**
     * A header reduced to letters and digits, for loose matching.
     *
     * @since 1.0.0
     *
     * @param  string  $header  Header.
     *
     * @return string
     */
    private static function looseKey( string $header): string
    {
        return (string) preg_replace( '/[^a-z0-9]/', '', strtolower( $header ) );
    }
}
