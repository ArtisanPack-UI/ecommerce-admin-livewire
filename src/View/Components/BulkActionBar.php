<?php

/**
 * Bulk-action bar component.
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
 * `<x-artisanpack-ec-bulk-action-bar :count="…" :actions="…" … />`
 *
 * The bar shown while table rows are selected: the selection count, the
 * "select all matching" prompt, the bulk action buttons, and "clear". Extra
 * controls a screen needs (such as a target sub-status) go in the slot.
 *
 * livewire-ui-components has no bulk-action bar yet (spec §10, U4,
 * livewire-ui-components#114), so this composes one. When the library ships
 * one, this is the one file to swap.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class BulkActionBar extends Component
{
    /**
     * @since 1.0.0
     *
     * @param  int                                $count        How many rows are selected.
     * @param  array<string, array<string, mixed>>  $actions      The bulk actions the user may run.
     * @param  int                                $total        How many rows match the search and filters.
     * @param  int                                $pageCount    How many rows are on this page.
     * @param  bool                               $allMatching  Whether every matching row is selected.
     */
    public function __construct(
        public int $count,
        public array $actions,
        public int $total = 0,
        public int $pageCount = 0,
        public bool $allMatching = false,
    ) {
    }

    /**
     * Whether to offer selecting every matching row.
     *
     * Offered once the whole page is selected and more rows match.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function offersSelectAll(): bool
    {
        return ! $this->allMatching && $this->pageCount > 0 && $this->count >= $this->pageCount && $this->total > $this->count;
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
        return view( 'ecommerce-admin::components.bulk-action-bar' );
    }
}
