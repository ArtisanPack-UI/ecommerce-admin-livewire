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
                $query->orderBy( static::column( Customer::class, 'last_name' ), $direction )->orderBy( static::column( Customer::class, 'first_name' ), $direction );
            },
            'email'      => static::column( Customer::class, 'email' ),
            'orders'     => static::column( Customer::class, 'orders_count' ),
            'spent'      => static::column( Customer::class, 'total_spent_amount' ),
            'last_order' => static::column( Customer::class, 'last_ordered_at' ),
            'created'    => static::column( Customer::class, 'created_at' ),
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
        return Customer::query()->select( static::column( Customer::class, '*' ) );
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
            static::orWhereContains( $where, static::column( Customer::class, 'email' ), $search );
            static::orWhereContains( $where, static::column( Customer::class, 'phone' ), $search );

            $where->orWhere( static function ( Builder $name ) use ( $words ): void {
                foreach ( $words as $word ) {
                    $name->where( static function ( Builder $part ) use ( $word ): void {
                        static::orWhereContains( $part, static::column( Customer::class, 'first_name' ), $word );
                        static::orWhereContains( $part, static::column( Customer::class, 'last_name' ), $word );
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
                ? $query->whereNotNull( static::column( Customer::class, 'user_id' ) )
                : $query->whereNull( static::column( Customer::class, 'user_id' ) ),
            'accepts_marketing' => static fn ( Builder $query, mixed $value ) => $query->where( static::column( Customer::class, 'accepts_marketing' ), '1' === (string) $value ),
            'orders'            => static function ( Builder $query, mixed $value ): void {
                $range = (array) $value;

                if ( is_numeric( $range['min'] ?? null ) ) {
                    $query->where( static::column( Customer::class, 'orders_count' ), '>=', (int) ceil( (float) $range['min'] ) );
                }

                if ( is_numeric( $range['max'] ?? null ) ) {
                    $query->where( static::column( Customer::class, 'orders_count' ), '<=', (int) floor( (float) $range['max'] ) );
                }
            },
            'spent'             => static function ( Builder $query, mixed $value ): void {
                $range   = (array) $value;
                $subunit = MinorUnits::subunit( StoreCurrencies::base() );
                $min     = self::minorBound( $range['min'] ?? null, $subunit, true );
                $max     = self::minorBound( $range['max'] ?? null, $subunit, false );

                if ( null !== $min ) {
                    $query->where( static::column( Customer::class, 'total_spent_amount' ), '>=', $min );
                }

                if ( null !== $max ) {
                    $query->where( static::column( Customer::class, 'total_spent_amount' ), '<=', $max );
                }
            },
            'last_order'        => static function ( Builder $query, mixed $value ): void {
                $range = (array) $value;
                $from  = static::dayStart( $range['from'] ?? null );
                $to    = static::dayEnd( $range['to'] ?? null );

                if ( null !== $from ) {
                    $query->where( static::column( Customer::class, 'last_ordered_at' ), '>=', $from );
                }

                if ( null !== $to ) {
                    $query->where( static::column( Customer::class, 'last_ordered_at' ), '<=', $to );
                }
            },
        ];
    }

    /**
     * A spend bound in the store currency's minor units.
     *
     * The table sends plain decimals (`.` separator, up to 4 places), which
     * may be finer than the currency allows (`10.125` USD, `10.5` JPY), so
     * the bound is rounded inward: a minimum up, a maximum down. Anything
     * else is ignored rather than thrown.
     *
     * @since 1.0.0
     *
     * @param  mixed  $major    The bound in major units.
     * @param  int    $subunit  Decimal places of the currency.
     * @param  bool   $roundUp  Round up (a minimum) instead of down (a maximum).
     *
     * @return int|null
     */
    private static function minorBound( mixed $major, int $subunit, bool $roundUp ): ?int
    {
        $major = is_int( $major ) || is_string( $major ) ? trim( (string) $major ) : '';

        if ( 1 !== preg_match( '/^\d{1,15}(\.\d{1,4})?$/D', $major ) ) {
            return null;
        }

        $scaled = bcmul( $major, bcpow( '10', (string) $subunit, 0 ), 4 );
        $whole  = bcadd( $scaled, '0', 0 );

        if ( $roundUp && 0 !== bccomp( $scaled, $whole, 4 ) ) {
            $whole = bcadd( $whole, '1', 0 );
        }

        return (int) $whole;
    }
}
