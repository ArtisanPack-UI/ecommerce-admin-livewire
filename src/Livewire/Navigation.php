<?php

/**
 * Navigation component.
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
use ArtisanPackUI\EcommerceAdminLivewire\Support\NavBadges;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The standalone layout's sidebar navigation.
 *
 * Renders the entries the user may see, grouped by section, with badge
 * counts. Screens dispatch `ecommerce-admin-nav-refresh` after a change
 * that moves a count (e.g. approving a review) to re-render it.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Navigation extends Component
{
    use AuthorizesEcommerce;

    /**
     * The key of the entry for the current page.
     *
     * Resolved on mount, because update requests go to Livewire's endpoint
     * rather than the page's route.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $activeKey = null;

    /**
     * Authorizes the component and records the active entry.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeAdminAccess();

        foreach ( AdminNav::items() as $item ) {
            if ( AdminNav::isActive( $item ) ) {
                $this->activeKey = $item['key'];

                break;
            }
        }
    }

    /**
     * Clears the cached badge counts and re-renders.
     *
     * @since 1.0.0
     *
     * @return void
     */
    #[On( 'ecommerce-admin-nav-refresh' )]
    public function refreshBadges(): void
    {
        $this->authorizeAdminAccess();

        NavBadges::flush();
        AdminNav::flush();
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

        foreach ( $sections as &$section ) {
            foreach ( $section['items'] as &$item ) {
                // A registry entry carries its count; a core entry names a badge.
                $count = $item['badgeCount'] ?? ( null === $item['badge'] ? null : NavBadges::count( $item['badge'] ) );
                $count = null !== $count && $count > 0 ? $count : null;

                $item['badgeCount'] = $count;
                $item['badgeLabel'] = null === $count ? null : NavBadges::describe( $item['badge'] ?? $item['key'], $count );
                $item['active']     = $item['key'] === $this->activeKey;
            }
        }

        unset( $section, $item );

        return view( 'ecommerce-admin::livewire.navigation', [
            'sections' => $sections,
        ] );
    }
}
