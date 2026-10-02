<?php

/**
 * Command palette: actions and navigation.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Spotlight;

use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

/**
 * "New product", "New promotion", and a "Go to …" entry for every
 * navigation entry the user can open. Runs no query.
 *
 * An entry matches when every word of the search appears in its label or
 * keywords, ignoring case.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class ActionsProvider implements SpotlightProvider
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
        return null;
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
        $words   = array_values( array_filter( preg_split( '/\s+/u', mb_strtolower( $search ) ) ?: [] ) );
        $results = [];

        foreach ( $this->actions( $user ) as $action ) {
            $haystack = mb_strtolower( $action['name'] . ' ' . $action['keywords'] );

            foreach ( $words as $word ) {
                if ( ! str_contains( $haystack, $word ) ) {
                    continue 2;
                }
            }

            unset( $action['keywords'] );

            $results[] = $action;

            if ( count( $results ) >= $limit ) {
                break;
            }
        }

        return $results;
    }

    /**
     * Every action the user may run.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The current user.
     *
     * @return array<int, array{name: string, description: string, link: string, icon: string, keywords: string}>
     */
    protected function actions( Authenticatable $user ): array
    {
        $actions = [];
        $create  = [
            [ 'products.create', 'product.create', __( 'New product' ), __( 'Create a product' ), 'add create' ],
            [ 'promotions.create', 'promotion.create', __( 'New promotion' ), __( 'Create a promotion or coupon' ), 'add create discount coupon' ],
        ];

        foreach ( $create as [ $route, $ability, $name, $description, $keywords ] ) {
            if ( Route::has( AdminNav::ROUTE_PREFIX . $route ) && Authorization::allows( $user, $ability ) ) {
                $actions[] = [
                    'name'        => $name,
                    'description' => $description,
                    'link'        => route( AdminNav::ROUTE_PREFIX . $route ),
                    'icon'        => 'o-plus',
                    'keywords'    => $keywords,
                ];
            }
        }

        foreach ( AdminNav::visibleItems( $user ) as $item ) {
            $actions[] = [
                'name'        => __( 'Go to :screen', [ 'screen' => $item['label'] ] ),
                'description' => __( 'Open the :screen screen', [ 'screen' => $item['label'] ] ),
                'link'        => AdminNav::url( $item ),
                'icon'        => '' === $item['icon'] ? 'o-arrow-right' : $item['icon'],
                'keywords'    => 'open',
            ];
        }

        return $actions;
    }
}
