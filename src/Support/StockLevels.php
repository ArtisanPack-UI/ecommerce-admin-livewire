<?php

/**
 * Stock adjustments for the inventory screen.
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
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Services\InventoryService;
use ArtisanPackUI\Ecommerce\Services\ProductService;
use Illuminate\Support\Facades\DB;

/**
 * Reads and changes a stock row on behalf of the inventory screen.
 *
 * Quantity changes go through the engine's `InventoryService::adjust()`,
 * which locks the row, audits the change, and fires the stock hooks.
 * Settings (threshold, backorder) go through `ProductService`, so the
 * product's own write rules apply. A row whose product type is missing is
 * read-only (plan §16.6).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class StockLevels
{
    /**
     * Adjustment modes: add or remove units, or set the count.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const MODES = [ 'delta', 'set' ];

    /**
     * The product that owns a stock row.
     *
     * @since 1.0.0
     *
     * @param  InventoryItem  $item  Stock row.
     *
     * @return Product|null
     */
    public static function product( InventoryItem $item ): ?Product
    {
        $stockable = $item->stockable;

        return match ( true ) {
            $stockable instanceof Product        => $stockable,
            $stockable instanceof ProductVariant => $stockable->product,
            default                              => null,
        };
    }

    /**
     * A human label for a stock row: the product name, plus the variant name.
     *
     * @since 1.0.0
     *
     * @param  InventoryItem  $item  Stock row.
     *
     * @return string
     */
    public static function label( InventoryItem $item ): string
    {
        $stockable = $item->stockable;

        return $stockable instanceof Product || $stockable instanceof ProductVariant
            ? self::stockableLabel( $stockable )
            : __( 'Deleted item #:id', [ 'id' => $item->stockable_id ] );
    }

    /**
     * The SKU of a stock row's product or variant.
     *
     * @since 1.0.0
     *
     * @param  InventoryItem  $item  Stock row.
     *
     * @return string
     */
    public static function sku( InventoryItem $item ): string
    {
        $stockable = $item->stockable;

        return $stockable instanceof Product || $stockable instanceof ProductVariant ? (string) ( $stockable->sku ?? '' ) : '';
    }

    /**
     * Whether a stock row may not be changed: its owner is gone or its
     * product's type is missing.
     *
     * @since 1.0.0
     *
     * @param  InventoryItem  $item  Stock row.
     *
     * @return bool
     */
    public static function isReadOnly( InventoryItem $item ): bool
    {
        $product = self::product( $item );

        return null === $product || $product->typeIsMissing();
    }

    /**
     * The product or variant with a SKU. Product SKUs are checked first.
     *
     * @since 1.0.0
     *
     * @param  string  $sku  SKU.
     *
     * @return Product|ProductVariant|null
     */
    public static function findStockable( string $sku ): Product|ProductVariant|null
    {
        $sku = trim( $sku );

        if ( '' === $sku ) {
            return null;
        }

        return Product::query()->where( 'sku', $sku )->first()
            ?? ProductVariant::query()->with( 'product' )->where( 'sku', $sku )->first();
    }

    /**
     * The existing stock row (no warehouse) of a product or variant, without
     * creating one.
     *
     * @since 1.0.0
     *
     * @param  Product|ProductVariant  $stockable  Product or variant.
     *
     * @return InventoryItem|null
     */
    public static function existingItem( Product|ProductVariant $stockable ): ?InventoryItem
    {
        return InventoryItem::query()
            ->where( 'stockable_type', $stockable->getMorphClass() )
            ->where( 'stockable_id', $stockable->getKey() )
            ->whereNull( 'warehouse_id' )
            ->first();
    }

    /**
     * The label of a product or variant (see {@see self::label()}).
     *
     * @since 1.0.0
     *
     * @param  Product|ProductVariant  $stockable  Product or variant.
     *
     * @return string
     */
    public static function stockableLabel( Product|ProductVariant $stockable ): string
    {
        if ( $stockable instanceof ProductVariant ) {
            $name = trim( (string) ( $stockable->name ?? '' ) );

            return trim( (string) ( $stockable->product->name ?? '' ) . ( '' === $name ? '' : ' — ' . $name ) );
        }

        return (string) $stockable->name;
    }

    /**
     * Whether a product's or variant's stock may not be changed (its
     * product type is missing).
     *
     * @since 1.0.0
     *
     * @param  Product|ProductVariant  $stockable  Product or variant.
     *
     * @return bool
     */
    public static function stockableIsReadOnly( Product|ProductVariant $stockable ): bool
    {
        $product = $stockable instanceof Product ? $stockable : $stockable->product;

        return null === $product || $product->typeIsMissing();
    }

    /**
     * Changes the quantity on hand.
     *
     * With `set`, the delta is worked out from the locked row, so a sale
     * that lands at the same time is not overwritten silently: the count
     * ends at `$quantity` either way.
     *
     * @since 1.0.0
     *
     * @param  InventoryItem  $item      Stock row.
     * @param  string         $mode      `delta` or `set`.
     * @param  int            $quantity  Units to add (negative removes), or the new count.
     * @param  string         $reason    Why (required).
     *
     * @throws ProductWriteException When the row is read-only, the reason is blank, or the count would be negative.
     *
     * @return array{item: InventoryItem, delta: int}
     */
    public static function adjust( InventoryItem $item, string $mode, int $quantity, string $reason ): array
    {
        if ( self::isReadOnly( $item ) ) {
            throw ProductWriteException::field( 'quantity', 'read-only', __( 'This item\'s product type is not installed, so its stock cannot be changed here.' ) );
        }

        if ( '' === trim( $reason ) ) {
            throw ProductWriteException::field( 'reason', 'reason-required', __( 'Give a reason for the stock change.' ) );
        }

        return DB::transaction( static function () use ( $item, $mode, $quantity, $reason ): array {
            $fresh = InventoryItem::query()->lockForUpdate()->findOrFail( $item->getKey() );
            $delta = 'set' === $mode ? $quantity - (int) $fresh->quantity_on_hand : $quantity;

            if ( 'set' === $mode && $quantity < 0 ) {
                throw ProductWriteException::field( 'quantity', 'negative', __( 'The count cannot be negative.' ) );
            }

            if ( 0 === $delta ) {
                return [ 'item' => $fresh, 'delta' => 0 ];
            }

            return [ 'item' => app( InventoryService::class )->adjust( $fresh, $delta, trim( $reason ) ), 'delta' => $delta ];
        } );
    }

    /**
     * Changes a stock row's settings (`low_stock_threshold`, `allow_backorder`,
     * `track_inventory`).
     *
     * @since 1.0.0
     *
     * @param  InventoryItem         $item      Stock row.
     * @param  array<string, mixed>  $settings  Settings to change.
     *
     * @throws ProductWriteException When the row is read-only or a value is invalid.
     *
     * @return void
     */
    public static function updateSettings( InventoryItem $item, array $settings ): void
    {
        if ( self::isReadOnly( $item ) ) {
            throw ProductWriteException::field( 'settings', 'read-only', __( 'This item\'s product type is not installed, so its stock cannot be changed here.' ) );
        }

        $settings  = array_intersect_key( $settings, array_flip( ProductService::INVENTORY_SETTINGS ) );
        $stockable = $item->stockable;
        $service   = app( ProductService::class );

        if ( null !== $item->warehouse_id ) {
            // The engine's product writes only reach the row with no warehouse.
            $item->fill( $settings )->save();

            return;
        }

        if ( $stockable instanceof ProductVariant ) {
            $service->updateVariant( $stockable, [ 'inventory' => $settings ] );
        } elseif ( $stockable instanceof Product ) {
            $service->update( $stockable, [ 'inventory' => $settings ] );
        }
    }
}
