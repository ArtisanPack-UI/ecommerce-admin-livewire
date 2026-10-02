<?php

/**
 * Command palette: coupons.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Spotlight;

use ArtisanPackUI\Ecommerce\Models\Coupon;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

/**
 * Finds coupons by code and opens the promotion they belong to, for users
 * who can also view promotions.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class CouponsProvider implements SpotlightProvider
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
        return 'coupon.viewAny';
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
        $route = AdminNav::ROUTE_PREFIX . 'promotions.edit';

        // The result opens the coupon's promotion, so the user must be able to see promotions too.
        if ( ! Route::has( $route ) || ! Authorization::allows( $user, 'promotion.view' ) ) {
            return [];
        }

        $query = Coupon::query()->select( [ 'id', 'promotion_id', 'code' ] );

        $query->where( static fn ( $where ) => ResourceQuery::orWhereContains( $where, 'code', $search ) );

        return $query
            ->orderBy( 'code' )
            ->limit( $limit )
            ->get()
            ->map( static fn ( Coupon $coupon ): array => [
                'name'        => (string) $coupon->code,
                'description' => __( 'Coupon' ),
                'link'        => route( $route, [ 'promotion' => $coupon->promotion_id, 'tab' => 'coupons' ] ),
                'icon'        => 'o-ticket',
            ] )
            ->all();
    }
}
