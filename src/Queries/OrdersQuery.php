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
            'number'             => 'orders.order_number',
            'placed'             => 'orders.placed_at',
            'customer'           => 'orders.email',
            'system_status'      => 'orders.system_status',
            'payment_status'     => 'orders.payment_status',
            'fulfillment_status' => 'orders.fulfillment_status',
            'total'              => 'orders.total_amount',
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
            ->select( 'orders.*' )
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
            static::orWhereContains( $where, 'orders.order_number', '' === $number ? $search : $number );
            static::orWhereContains( $where, 'orders.email', $search );

            $where->orWhereHas( 'customer', static function ( Builder $customer ) use ( $words ): void {
                foreach ( $words as $word ) {
                    $customer->where( static function ( Builder $name ) use ( $word ): void {
                        static::orWhereContains( $name, 'customers.first_name', $word );
                        static::orWhereContains( $name, 'customers.last_name', $word );
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
            'system_status'      => static fn ( Builder $query, mixed $value ) => $query->where( 'orders.system_status', (string) $value ),
            'substatus'          => static fn ( Builder $query, mixed $value ) => $query->where( 'orders.substatus_id', (int) $value ),
            'payment_status'     => static fn ( Builder $query, mixed $value ) => $query->where( 'orders.payment_status', (string) $value ),
            'fulfillment_status' => static fn ( Builder $query, mixed $value ) => $query->where( 'orders.fulfillment_status', (string) $value ),
            'currency'           => static fn ( Builder $query, mixed $value ) => $query->where( 'orders.currency', strtoupper( (string) $value ) ),
            'board'              => static fn ( Builder $query, mixed $value ) => $query->whereHas(
                'boardAssignments',
                static fn ( Builder $assignment ) => $assignment->where( 'board_id', (int) $value )->whereNull( 'removed_at' ),
            ),
            'placed'             => static function ( Builder $query, mixed $value ): void {
                $range = (array) $value;
                $from  = static::date( $range['from'] ?? null );
                $to    = static::date( $range['to'] ?? null );

                if ( null !== $from ) {
                    $query->where( 'orders.placed_at', '>=', $from->startOfDay() );
                }

                if ( null !== $to ) {
                    $query->where( 'orders.placed_at', '<=', $to->endOfDay() );
                }
            },
        ];
    }
}
