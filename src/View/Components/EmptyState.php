<?php

/**
 * Empty-state component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-empty-state icon="o-shopping-bag" :title="…" :description="…">actions</x-artisanpack-ec-empty-state>`
 *
 * livewire-ui-components has no empty-state component yet (spec §10, U1,
 * livewire-ui-components#111), so this composes one from a card and an
 * icon. When the library ships one, this is the one file to swap.
 *
 * The block is a `status` region so a screen reader announces it when a
 * search empties the table.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class EmptyState extends Component
{
    /**
     * @since 1.0.0
     *
     * @param  string       $title        The headline.
     * @param  string|null  $description  The supporting text.
     * @param  string       $icon         The icon name.
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public string $icon = 'o-inbox',
    ) {
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
        return view( 'ecommerce-admin::components.empty-state' );
    }
}
