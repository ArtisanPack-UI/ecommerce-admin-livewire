<?php

/**
 * Command palette: orders.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Spotlight;

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Finds orders by number (a leading `#` is ignored) or email.
 *
 * When the search is exactly an order number, the order is followed by its
 * contextual actions — "Refund order #…" and "Add note to #…" — for users
 * allowed to take them.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class OrdersProvider implements SpotlightProvider
{
    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function ability(): ?string
    {
        return 'order.viewAny';
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     *
     * @param  string           $search  The search text.
     * @param  Authenticatable  $user    The current user.
     * @param  int              $limit   The most results to return.
     *
     * @return array<int, array{name: string, description?: string|null, link: string, icon?: string|null}>
     */
    public function search( string $search, Authenticatable $user, int $limit ): array
    {
        $route = AdminNav::ROUTE_PREFIX . 'orders.show';

        if ( ! Route::has( $route ) ) {
            return [];
        }

        $number = ltrim( $search, '#' );
        $orders = Order::query()
            ->select( [ 'id', 'order_number', 'email', 'system_status', 'total_amount', 'total_currency', 'placed_at' ] )
            ->where( static function ( Builder $where ) use ( $search, $number ): void {
                ResourceQuery::orWhereContains( $where, 'order_number', '' === $number ? $search : $number );
                ResourceQuery::orWhereContains( $where, 'email', $search );
            } )
            ->orderByRaw( 'CASE WHEN order_number = ? THEN 0 ELSE 1 END', [ $number ] )
            ->orderByDesc( 'id' )
            ->limit( $limit )
            ->get();

        $results = [];

        foreach ( $orders as $order ) {
            if ( ! Gate::forUser( $user )->allows( 'view', $order ) ) {
                continue;
            }

            $url       = route( $route, [ 'order' => $order->getKey() ] );
            $results[] = [
                'name'        => __( 'Order #:number', [ 'number' => $order->order_number ] ),
                'description' => implode( ' · ', array_filter( [
                    (string) $order->email,
                    MoneyFormatter::format( (int) $order->total_amount, (string) $order->total_currency ),
                ] ) ),
                'link'        => $url,
                'icon'        => 'o-shopping-bag',
            ];

            if ( 0 !== strcasecmp( (string) $order->order_number, $number ) ) {
                continue;
            }

            if ( Gate::forUser( $user )->allows( 'refund', $order ) ) {
                $results[] = [
                    'name'        => __( 'Refund order #:number', [ 'number' => $order->order_number ] ),
                    'description' => __( 'Open the refund dialog' ),
                    'link'        => route( $route, [ 'order' => $order->getKey(), 'action' => 'refund' ] ),
                    'icon'        => 'o-receipt-refund',
                ];
            }

            if ( Gate::forUser( $user )->allows( 'update', $order ) ) {
                $results[] = [
                    'name'        => __( 'Add note to #:number', [ 'number' => $order->order_number ] ),
                    'description' => __( 'Jump to the order\'s notes' ),
                    'link'        => $url . '#order-notes',
                    'icon'        => 'o-chat-bubble-left-ellipsis',
                ];
            }
        }

        return $results;
    }
}
