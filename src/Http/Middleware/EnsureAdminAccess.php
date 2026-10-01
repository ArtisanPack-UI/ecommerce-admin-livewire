<?php

/**
 * Admin access middleware.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Http\Middleware;

use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a user into the admin only when at least one nav entry is visible to them.
 *
 * Registered as `ecommerce-admin.access` on the admin routes and as Livewire
 * persistent middleware, so Livewire update requests from an admin page are
 * re-checked too. Each screen still authorizes its own ability.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class EnsureAdminAccess
{
    /**
     * The route-middleware alias.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ALIAS = 'ecommerce-admin.access';

    /**
     * Handles the request.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The request.
     * @param  Closure  $next     The next handler.
     *
     * @return Response
     */
    public function handle( Request $request, Closure $next ): Response
    {
        abort_unless(
            AdminNav::canAccess( $request->user() ),
            403,
            __( 'You do not have access to the store admin.' ),
        );

        return $next( $request );
    }
}
