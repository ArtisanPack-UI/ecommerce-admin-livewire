<?php

/**
 * Customers table query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Queries;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\EcommerceAdminLivewire\Support\MinorUnits;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StoreCurrencies;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Search, filters, and sorts for the customers table (spec §7.4).
 *
 * Order count, total spent, and last order come from the counters the
 * engine keeps on `customers`, so the table needs no join on `orders`.
 * The spend range is entered in the store currency's major units.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class CustomersQuery extends ResourceQuery
{
    /**
     * @since 1.0.0
     *
     * @return array<string, Closure|string>
     */
    public function sorts(): array
    {
        return [
            'name'       => static function ( Builder $query, string $direction ): void {
                $query->orderBy( 'customers.last_name', $direction )->orderBy( 'customers.first_name', $direction );
            },
            'email'      => 'customers.email',
            'orders'     => 'customers.orders_count',
            'spent'      => 'customers.total_spent_amount',
            'last_order' => 'customers.last_ordered_at',
            'created'    => 'customers.created_at',
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array{0: string, 1: string}
     */
    public function defaultSort(): array
    {
        return [ 'created', 'desc' ];
    }

    /**
     * @since 1.0.0
     *
     * @return Builder<Customer>
     */
    protected function baseQuery(): Builder
    {
        return Customer::query()->select( 'customers.*' );
    }

    /**
     * Matches the email, the phone, or every word of the name.
     *
     * @since 1.0.0
     *
     * @param  Builder<Customer>  $query   The query.
     * @param  string             $search  Search text.
     *
     * @return void
     */
    protected function applySearch( Builder $query, string $search ): void
    {
        $words = array_values( array_filter( preg_split( '/\s+/u', $search ) ?: [] ) );

        $query->where( static function ( Builder $where ) use ( $search, $words ): void {
            static::orWhereContains( $where, 'customers.email', $search );
            static::orWhereContains( $where, 'customers.phone', $search );

            $where->orWhere( static function ( Builder $name ) use ( $words ): void {
                foreach ( $words as $word ) {
                    $name->where( static function ( Builder $part ) use ( $word ): void {
                        static::orWhereContains( $part, 'customers.first_name', $word );
                        static::orWhereContains( $part, 'customers.last_name', $word );
                    } );
                }
            } );
        } );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, Closure(Builder, mixed): void>
     */
    protected function filters(): array
    {
        return [
            'has_account'       => static fn ( Builder $query, mixed $value ) => '1' === (string) $value
                ? $query->whereNotNull( 'customers.user_id' )
                : $query->whereNull( 'customers.user_id' ),
            'accepts_marketing' => static fn ( Builder $query, mixed $value ) => $query->where( 'customers.accepts_marketing', '1' === (string) $value ),
            'orders'            => static function ( Builder $query, mixed $value ): void {
                $range = (array) $value;

                if ( is_numeric( $range['min'] ?? null ) ) {
                    $query->where( 'customers.orders_count', '>=', (int) ceil( (float) $range['min'] ) );
                }

                if ( is_numeric( $range['max'] ?? null ) ) {
                    $query->where( 'customers.orders_count', '<=', (int) floor( (float) $range['max'] ) );
                }
            },
            'spent'             => static function ( Builder $query, mixed $value ): void {
                $range    = (array) $value;
                $currency = StoreCurrencies::base();
                $min      = is_numeric( $range['min'] ?? null ) ? MinorUnits::toMinor( (string) $range['min'], $currency ) : null;
                $max      = is_numeric( $range['max'] ?? null ) ? MinorUnits::toMinor( (string) $range['max'], $currency ) : null;

                if ( null !== $min ) {
                    $query->where( 'customers.total_spent_amount', '>=', $min );
                }

                if ( null !== $max ) {
                    $query->where( 'customers.total_spent_amount', '<=', $max );
                }
            },
            'last_order'        => static function ( Builder $query, mixed $value ): void {
                $range = (array) $value;
                $from  = static::date( $range['from'] ?? null );
                $to    = static::date( $range['to'] ?? null );

                if ( null !== $from ) {
                    $query->where( 'customers.last_ordered_at', '>=', $from->startOfDay() );
                }

                if ( null !== $to ) {
                    $query->where( 'customers.last_ordered_at', '<=', $to->endOfDay() );
                }
            },
        ];
    }
}
