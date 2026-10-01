<?php

/**
 * Dashboard component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire;

use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The admin landing screen.
 *
 * Lists the screens the user can open, grouped by section. The KPI widgets
 * replace this in the dashboard issue (spec §7.1).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Dashboard extends Component
{
    use AuthorizesEcommerce;

    /**
     * Authorizes the screen.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeAdminAccess();
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $sections = AdminNav::grouped( auth()->user() );

        unset( $sections[ AdminNav::TOP ] );

        return view( 'ecommerce-admin::livewire.dashboard', [
            'sections' => $sections,
        ] );
    }
}
