<?php

/**
 * Real-time listener trait.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns;

use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminBroadcasts;

/**
 * Adds `echo-private:ecommerce.admin` listeners to a component when real-time
 * updates are on for the current user (see {@see AdminBroadcasts::enabled()}).
 *
 * The component lists the broadcasts it handles in `adminBroadcasts()` and
 * puts its screen-reader message in `$liveAnnouncement`, which the
 * `ecommerce-admin::partials.live-region` partial renders in a polite live
 * region.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
trait ListensForAdminBroadcasts
{
    /**
     * The last real-time update, read out politely.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $liveAnnouncement = '';

    /**
     * Broadcast name => handler method.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    abstract protected function adminBroadcasts(): array;

    /**
     * The component's listeners, plus the broadcast listeners when enabled.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $listeners = $this->listeners;

        if ( ! AdminBroadcasts::enabled( auth()->user() ) ) {
            return $listeners;
        }

        foreach ( $this->adminBroadcasts() as $event => $method ) {
            $listeners[ AdminBroadcasts::listener( $event ) ] = $method;
        }

        return $listeners;
    }
}
