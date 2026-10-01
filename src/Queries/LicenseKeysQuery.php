<?php

/**
 * License keys table query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Queries;

use ArtisanPackUI\Ecommerce\Models\LicenseKey;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Search, filters, and sorts for the license keys table (spec §7.2).
 *
 * Search matches the order number, the order email, and the customer's
 * name; it matches the key itself only when the query is built with
 * `$searchKeys` (users who may see keys in full), so a masked list cannot
 * be used to confirm a guessed key.
 *
 * The `status` filter is `active`, `revoked`, or `expired`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class LicenseKeysQuery extends ResourceQuery
{
    /**
     * @since 1.0.0
     *
     * @param  bool  $searchKeys  Whether the search may match the key.
     */
    public function __construct( private readonly bool $searchKeys = false )
    {
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, Closure|string>
     */
    public function sorts(): array
    {
        return [
            'issued'      => 'license_keys.created_at',
            'expires'     => 'license_keys.expires_at',
            'activations' => 'license_keys.activations_count',
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array{0: string, 1: string}
     */
    public function defaultSort(): array
    {
        return [ 'issued', 'desc' ];
    }

    /**
     * @since 1.0.0
     *
     * @return Builder<LicenseKey>
     */
    protected function baseQuery(): Builder
    {
        return LicenseKey::query()->with( [
            'orderItem:id,order_id,product_id,product_snapshot',
            'orderItem.order:id,order_number,email,customer_id',
            'orderItem.order.customer:id,first_name,last_name,email',
        ] );
    }

    /**
     * @since 1.0.0
     *
     * @param  Builder<LicenseKey>  $query   The query.
     * @param  string               $search  Search text.
     *
     * @return void
     */
    protected function applySearch( Builder $query, string $search ): void
    {
        $searchKeys = $this->searchKeys;
        $number     = ltrim( $search, '#' );

        $query->where( static function ( Builder $where ) use ( $search, $number, $searchKeys ): void {
            if ( $searchKeys ) {
                static::orWhereContains( $where, 'license_keys.key', $search );
            }

            $where->orWhereHas( 'orderItem.order', static function ( Builder $order ) use ( $search, $number ): void {
                $order->where( static function ( Builder $match ) use ( $search, $number ): void {
                    static::orWhereContains( $match, 'orders.order_number', '' === $number ? $search : $number );
                    static::orWhereContains( $match, 'orders.email', $search );

                    $match->orWhereHas( 'customer', static function ( Builder $customer ) use ( $search ): void {
                        $customer->where( static function ( Builder $name ) use ( $search ): void {
                            static::orWhereContains( $name, 'customers.first_name', $search );
                            static::orWhereContains( $name, 'customers.last_name', $search );
                            static::orWhereContains( $name, 'customers.email', $search );
                        } );
                    } );
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
            'status' => static function ( Builder $query, mixed $value ): void {
                $now = Carbon::now();

                match ( (string) $value ) {
                    'revoked' => $query->where( 'license_keys.is_revoked', true ),
                    'expired' => $query->where( 'license_keys.is_revoked', false )->whereNotNull( 'license_keys.expires_at' )->where( 'license_keys.expires_at', '<=', $now ),
                    'active'  => $query->where( 'license_keys.is_revoked', false )->where( static fn ( Builder $live ) => $live->whereNull( 'license_keys.expires_at' )->orWhere( 'license_keys.expires_at', '>', $now ) ),
                    default   => null,
                };
            },
        ];
    }
}
