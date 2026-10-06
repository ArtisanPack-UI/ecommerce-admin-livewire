<?php

/**
 * Navigation badge counts.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\ProductReview;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\InventoryQuery;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the count shown on a navigation entry's badge.
 *
 * Each core badge is one count query, cached for {@see self::TTL} seconds. A
 * satellite answers its own badge keys through the
 * `ap.ecommerceAdminLivewire.nav.badge` filter.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class NavBadges
{
    /**
     * Orders paid for and not fully shipped.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ORDERS_AWAITING_FULFILLMENT = 'orders-awaiting-fulfillment';

    /**
     * Reviews waiting for moderation.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PENDING_REVIEWS = 'pending-reviews';

    /**
     * Tracked stock at or below its low-stock threshold.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const LOW_STOCK = 'low-stock';

    /**
     * How long a count is cached, in seconds.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const TTL = 60;

    /**
     * The cache key prefix.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CACHE_PREFIX = 'artisanpack.ecommerce-admin-livewire.nav.badge.';

    /**
     * The count for a badge key, or null when there is nothing to show.
     *
     * @since 1.0.0
     *
     * @param  string  $badge  The badge key.
     *
     * @return int|null
     */
    public static function count( string $badge ): ?int
    {
        $count = match ( $badge ) {
            self::ORDERS_AWAITING_FULFILLMENT => self::remember( $badge, static fn (): int => Order::query()
                ->where( 'system_status', 'processing' )
                ->whereIn( 'fulfillment_status', [ 'unfulfilled', 'partial' ] )
                ->count() ),
            self::PENDING_REVIEWS => self::remember( $badge, static fn (): int => ProductReview::query()
                ->where( 'status', ProductReview::STATUS_PENDING )
                ->count() ),
            // The same rows as the inventory "low" filter: tracked, something
            // still available, and at or below the threshold.
            self::LOW_STOCK => self::remember( $badge, static function (): int {
                $query     = InventoryItem::query();
                $available = InventoryQuery::availableSql( $query );

                return $query
                    ->where( $query->qualifyColumn( 'track_inventory' ), true )
                    ->whereNotNull( $query->qualifyColumn( 'low_stock_threshold' ) )
                    ->whereRaw( $available . ' > 0' )
                    ->whereRaw( $available . ' <= ' . $query->getQuery()->getGrammar()->wrap( $query->qualifyColumn( 'low_stock_threshold' ) ) )
                    ->count();
            } ),
            default => applyFilters( 'ap.ecommerceAdminLivewire.nav.badge', null, $badge ),
        };

        return is_numeric( $count ) && (int) $count > 0 ? (int) $count : null;
    }

    /**
     * The accessible description of a badge, e.g. "7 low-stock items".
     *
     * @since 1.0.0
     *
     * @param  string  $badge  The badge key.
     * @param  int     $count  The count.
     *
     * @return string
     */
    public static function describe( string $badge, int $count ): string
    {
        return match ( $badge ) {
            self::ORDERS_AWAITING_FULFILLMENT => trans_choice( ':count order awaiting fulfillment|:count orders awaiting fulfillment', $count, [ 'count' => $count ] ),
            self::PENDING_REVIEWS             => trans_choice( ':count review awaiting moderation|:count reviews awaiting moderation', $count, [ 'count' => $count ] ),
            self::LOW_STOCK                   => trans_choice( ':count low-stock item|:count low-stock items', $count, [ 'count' => $count ] ),
            default                           => (string) applyFilters(
                'ap.ecommerceAdminLivewire.nav.badgeLabel',
                trans_choice( ':count item|:count items', $count, [ 'count' => $count ] ),
                $badge,
                $count,
            ),
        };
    }

    /**
     * Clears the cached counts.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function flush(): void
    {
        foreach ( [ self::ORDERS_AWAITING_FULFILLMENT, self::PENDING_REVIEWS, self::LOW_STOCK ] as $badge ) {
            Cache::forget( self::CACHE_PREFIX . $badge );
        }
    }

    /**
     * Caches one count query.
     *
     * @since 1.0.0
     *
     * @param  string    $badge  The badge key.
     * @param  callable  $query  Returns the count.
     *
     * @return int
     */
    private static function remember( string $badge, callable $query ): int
    {
        return (int) Cache::remember( self::CACHE_PREFIX . $badge, self::TTL, $query );
    }
}
