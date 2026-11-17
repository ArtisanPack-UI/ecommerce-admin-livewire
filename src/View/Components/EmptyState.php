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
 * `<x-artisanpack-ec-admin-empty-state icon="inbox" :title="…" :description="…">actions</x-artisanpack-ec-admin-empty-state>`
 *
 * Also registered as `x-artisanpack-ec-empty-state`, a name
 * ecommerce-storefront-livewire shares; the admin's views use
 * `ec-admin-empty-state`, which is always this component.
 *
 * What a list, panel or tab shows when it has nothing in it (#80): the
 * design-system EmptyState with an icon, a title that says what's missing,
 * one sentence on why or what happens next, and up to two actions in the
 * slot (one primary at most; a second is outline or ghost).
 *
 * `size="md"` (the default) stands in for a table; `size="sm"` sits inside a
 * card, panel or tab. The root is a `status` region, so a list that empties
 * after a search or filter is announced; the icon is decorative and the
 * title carries the meaning.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class EmptyState extends Component
{
    /**
     * The sizes the empty state takes.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const SIZES = [ 'md', 'sm' ];

    /**
     * @since 1.0.0
     * @since 2.0.0 Added `$size` and `$headingLevel`; the default icon is the design system's `inbox`.
     *
     * @param  string       $title         The headline.
     * @param  string|null  $description   The supporting text.
     * @param  string       $icon          The icon name: a design-system icon, or a Heroicons `o-` name.
     * @param  string       $size          `md` in place of a table, `sm` inside a card or panel.
     * @param  int          $headingLevel  The title's heading level (2 by default, the level under a page's h1).
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public string $icon = 'inbox',
        public string $size = 'md',
        public int $headingLevel = 2,
    ) {
        $this->size = in_array( $this->size, self::SIZES, true ) ? $this->size : 'md';
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
