<?php

/**
 * Orders index query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Queries;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Search, filters, and sorting for the orders index (spec §7.3).
 *
 * Eager loads the customer and sub-status and sums item quantities in the
 * same query, so a page of orders costs a fixed number of queries.
 *
 * Search matches the order number (a leading `#` is ignored), the email,
 * and the customer's name (every word must match the first or last name).
 *
 * The `awaiting` filter (`1` / `0`) matches the engine's "awaiting
 * fulfillment" definition: a `processing` order that is unfulfilled or
 * partly fulfilled.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class OrdersQuery extends ResourceQuery
{
    /**
     * The sortable columns.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public function sorts(): array
    {
        return [
            'number'             => static::column( Order::class, 'order_number' ),
            'placed'             => static::column( Order::class, 'placed_at' ),
            'customer'           => static::column( Order::class, 'email' ),
            'system_status'      => static::column( Order::class, 'system_status' ),
            'payment_status'     => static::column( Order::class, 'payment_status' ),
            'fulfillment_status' => static::column( Order::class, 'fulfillment_status' ),
            'total'              => static::column( Order::class, 'total_amount' ),
            'items'              => 'items_sum_quantity',
        ];
    }

    /**
     * Newest first.
     *
     * @since 1.0.0
     *
     * @return array{0: string, 1: string}
     */
    public function defaultSort(): array
    {
        return [ 'placed', 'desc' ];
    }

    /**
     * Orders with their customer, sub-status, and item quantity.
     *
     * @since 1.0.0
     *
     * @return Builder
     */
    protected function baseQuery(): Builder
    {
        return Order::query()
            ->select( static::column( Order::class, '*' ) )
            ->with( [ 'customer', 'substatus' ] )
            ->withSum( 'items', 'quantity' );
    }

    /**
     * Matches the number, email, or customer name.
     *
     * @since 1.0.0
     *
     * @param  Builder  $query   The query.
     * @param  string   $search  The term.
     *
     * @return void
     */
    protected function applySearch( Builder $query, string $search ): void
    {
        $number = ltrim( $search, '#' );
        $words  = array_values( array_filter( preg_split( '/\s+/u', $search ) ?: [] ) );

        $query->where( static function ( Builder $where ) use ( $number, $search, $words ): void {
            static::orWhereContains( $where, static::column( Order::class, 'order_number' ), '' === $number ? $search : $number );
            static::orWhereContains( $where, static::column( Order::class, 'email' ), $search );

            $where->orWhereHas( 'customer', static function ( Builder $customer ) use ( $words ): void {
                foreach ( $words as $word ) {
                    $customer->where( static function ( Builder $name ) use ( $word ): void {
                        static::orWhereContains( $name, static::column( Customer::class, 'first_name' ), $word );
                        static::orWhereContains( $name, static::column( Customer::class, 'last_name' ), $word );
                    } );
                }
            } );
        } );
    }

    /**
     * The orders filters.
     *
     * @since 1.0.0
     *
     * @return array<string, Closure(Builder, mixed): void>
     */
    protected function filters(): array
    {
        return [
            'system_status'      => static fn ( Builder $query, mixed $value ) => $query->where( static::column( Order::class, 'system_status' ), (string) $value ),
            'substatus'          => static fn ( Builder $query, mixed $value ) => $query->where( static::column( Order::class, 'substatus_id' ), (int) $value ),
            'payment_status'     => static fn ( Builder $query, mixed $value ) => $query->where( static::column( Order::class, 'payment_status' ), (string) $value ),
            'fulfillment_status' => static fn ( Builder $query, mixed $value ) => $query->where( static::column( Order::class, 'fulfillment_status' ), (string) $value ),
            'currency'           => static fn ( Builder $query, mixed $value ) => $query->where( static::column( Order::class, 'currency' ), strtoupper( (string) $value ) ),
            'awaiting'           => static fn ( Builder $query, mixed $value ) => '1' === (string) $value
                ? $query->where( static::column( Order::class, 'system_status' ), 'processing' )->whereIn( static::column( Order::class, 'fulfillment_status' ), [ 'unfulfilled', 'partial' ] )
                : $query->where( static fn ( Builder $not ) => $not->where( static::column( Order::class, 'system_status' ), '!=', 'processing' )->orWhereNotIn( static::column( Order::class, 'fulfillment_status' ), [ 'unfulfilled', 'partial' ] ) ),
            'board'              => static fn ( Builder $query, mixed $value ) => $query->whereHas(
                'boardAssignments',
                static fn ( Builder $assignment ) => $assignment->where( 'board_id', (int) $value )->whereNull( 'removed_at' ),
            ),
            'placed'             => static function ( Builder $query, mixed $value ): void {
                $range = (array) $value;
                $from  = static::date( $range['from'] ?? null );
                $to    = static::date( $range['to'] ?? null );

                if ( null !== $from ) {
                    $query->where( static::column( Order::class, 'placed_at' ), '>=', $from->startOfDay() );
                }

                if ( null !== $to ) {
                    $query->where( static::column( Order::class, 'placed_at' ), '<=', $to->endOfDay() );
                }
            },
        ];
    }
}
