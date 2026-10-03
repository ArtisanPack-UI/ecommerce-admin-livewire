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
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Finds coupons by code and opens the promotion they belong to. Coupons
 * have no view abilities of their own — the admin only shows them inside
 * their promotion — so the provider needs `promotion.viewAny`, and a coupon
 * is listed only when the user may view its promotion (eager-loaded for
 * that check).
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
        return 'promotion.viewAny';
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

        if ( ! Route::has( $route ) ) {
            return [];
        }

        return Coupon::query()
            ->select( [ 'id', 'promotion_id', 'code' ] )
            ->with( 'promotion' )
            ->where( static fn ( Builder $where ) => ResourceQuery::orWhereContains( $where, 'code', $search ) )
            ->orderBy( 'code' )
            ->limit( $limit )
            ->get()
            ->filter( static fn ( Coupon $coupon ): bool => null !== $coupon->promotion && Gate::forUser( $user )->allows( 'view', $coupon->promotion ) )
            ->map( static fn ( Coupon $coupon ): array => [
                'name'        => (string) $coupon->code,
                'description' => __( 'Coupon' ),
                'link'        => route( $route, [ 'promotion' => $coupon->promotion_id, 'tab' => 'coupons' ] ),
                'icon'        => 'o-ticket',
            ] )
            ->values()
            ->all();
    }
}
