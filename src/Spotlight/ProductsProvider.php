<?php

/**
 * Command palette: products.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Spotlight;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Finds products by name or SKU.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class ProductsProvider implements SpotlightProvider
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
        return 'product.viewAny';
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
        $route = AdminNav::ROUTE_PREFIX . 'products.edit';

        if ( ! Route::has( $route ) ) {
            return [];
        }

        return Product::query()
            ->select( [ 'id', 'name', 'sku', 'status' ] )
            ->where( static function ( Builder $where ) use ( $search ): void {
                ResourceQuery::orWhereContains( $where, 'name', $search );
                ResourceQuery::orWhereContains( $where, 'sku', $search );
            } )
            ->orderBy( 'name' )
            ->limit( $limit )
            ->get()
            ->filter( static fn ( Product $product ): bool => Gate::forUser( $user )->allows( 'view', $product ) )
            ->map( static fn ( Product $product ): array => [
                'name'        => (string) $product->name,
                'description' => '' === (string) $product->sku ? __( 'Product' ) : __( 'Product · SKU :sku', [ 'sku' => $product->sku ] ),
                'link'        => route( $route, [ 'product' => $product->getKey() ] ),
                'icon'        => 'o-cube',
            ] )
            ->values()
            ->all();
    }
}
