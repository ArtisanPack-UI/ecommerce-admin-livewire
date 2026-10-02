<?php

/**
 * Command palette: customers.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Spotlight;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Finds customers by email, or by name (every word must match the first or
 * last name).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class CustomersProvider implements SpotlightProvider
{
    /**
     * Name words matched; later words are ignored.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_WORDS = 5;

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function ability(): ?string
    {
        return 'customer.viewAny';
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
        $route = AdminNav::ROUTE_PREFIX . 'customers.show';

        if ( ! Route::has( $route ) ) {
            return [];
        }

        $words = array_slice( array_values( array_filter( preg_split( '/\s+/u', $search ) ?: [] ) ), 0, self::MAX_WORDS );

        return Customer::query()
            ->select( [ 'id', 'first_name', 'last_name', 'email' ] )
            ->where( static function ( Builder $where ) use ( $search, $words ): void {
                ResourceQuery::orWhereContains( $where, 'email', $search );

                $where->orWhere( static function ( Builder $name ) use ( $words ): void {
                    foreach ( $words as $word ) {
                        $name->where( static function ( Builder $part ) use ( $word ): void {
                            ResourceQuery::orWhereContains( $part, 'first_name', $word );
                            ResourceQuery::orWhereContains( $part, 'last_name', $word );
                        } );
                    }
                } );
            } )
            ->orderBy( 'last_name' )
            ->orderBy( 'first_name' )
            ->limit( $limit )
            ->get()
            ->filter( static fn ( Customer $customer ): bool => Gate::forUser( $user )->allows( 'view', $customer ) )
            ->map( static function ( Customer $customer ) use ( $route ): array {
                $name = trim( (string) $customer->first_name . ' ' . (string) $customer->last_name );

                return [
                    'name'        => '' === $name ? (string) $customer->email : $name,
                    'description' => '' === $name ? __( 'Customer' ) : __( 'Customer · :email', [ 'email' => $customer->email ] ),
                    'link'        => route( $route, [ 'customer' => $customer->getKey() ] ),
                    'icon'        => 'o-user',
                ];
            } )
            ->values()
            ->all();
    }
}
