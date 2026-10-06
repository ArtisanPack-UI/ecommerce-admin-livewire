<?php

/**
 * Empty spotlight search class.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Spotlight;

use Illuminate\Http\Request;

/**
 * Stands in for the host's spotlight search class when the host has none.
 *
 * livewire-ui-components' spotlight route resolves the class named in
 * `artisanpack.livewire-ui-components.components.spotlight.class` before it
 * applies the `ap.livewireUiComponents.spotlightCommands` filter. When the
 * host never created that class, this one is bound in its place so the
 * route still reaches the filter.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class NullSpotlight
{
    /**
     * Returns no results.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The spotlight request.
     *
     * @return array<int, mixed>
     */
    public function search( Request $request ): array
    {
        return [];
    }
}
