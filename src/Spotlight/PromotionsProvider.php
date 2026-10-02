<?php

/**
 * Command palette: promotions.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Spotlight;

use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Finds promotions by name or key.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class PromotionsProvider implements SpotlightProvider
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

        return Promotion::query()
            ->select( [ 'id', 'name', 'key', 'is_active' ] )
            ->where( static function ( Builder $where ) use ( $search ): void {
                ResourceQuery::orWhereContains( $where, 'name', $search );
                ResourceQuery::orWhereContains( $where, 'key', $search );
            } )
            ->orderBy( 'name' )
            ->limit( $limit )
            ->get()
            ->filter( static fn ( Promotion $promotion ): bool => Gate::forUser( $user )->allows( 'view', $promotion ) )
            ->map( static fn ( Promotion $promotion ): array => [
                'name'        => (string) $promotion->name,
                'description' => $promotion->is_active ? __( 'Promotion · Active' ) : __( 'Promotion · Inactive' ),
                'link'        => route( $route, [ 'promotion' => $promotion->getKey() ] ),
                'icon'        => 'o-megaphone',
            ] )
            ->values()
            ->all();
    }
}
