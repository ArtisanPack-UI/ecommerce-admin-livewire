<?php

/**
 * Command palette provider contract.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Spotlight;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * One source of command palette results (spec §8.1).
 *
 * A provider returns at most `$limit` results for a search and runs at most
 * one query. Register more through the
 * `ap.ecommerceAdminLivewire.spotlight.providers` filter.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
interface SpotlightProvider
{
    /**
     * The engine ability (`order.viewAny`) the user needs before the
     * provider runs, or null for none.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function ability(): ?string;

    /**
     * Results for a search.
     *
     * `icon` is a Heroicon name (`o-cube`); the palette renders it.
     *
     * @since 1.0.0
     *
     * @param  string           $search  The trimmed search text (never empty).
     * @param  Authenticatable  $user    The current user.
     * @param  int              $limit   The most results to return.
     *
     * @return array<int, array{name: string, description?: string|null, link: string, icon?: string|null}>
     */
    public function search( string $search, Authenticatable $user, int $limit ): array;
}
