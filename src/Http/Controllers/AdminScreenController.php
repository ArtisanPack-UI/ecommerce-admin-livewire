<?php

/**
 * Admin screen controller.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * Returns the page view for each admin screen.
 *
 * Every page is a controller action (not a closure, so `route:cache` works)
 * returning a view that extends the resolved layout and embeds one Livewire
 * component. The component authorizes the screen itself.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class AdminScreenController extends Controller
{
    /**
     * The dashboard.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function dashboard(): View
    {
        return view( 'ecommerce-admin::pages.dashboard' );
    }
}
