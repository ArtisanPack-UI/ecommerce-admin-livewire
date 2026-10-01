<?php

/**
 * Category picker component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

/**
 * `<x-artisanpack-ec-category-picker model="…" :options="$this->optionsForPicker( 'category', '…' )" />`
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class CategoryPicker extends Picker
{
    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'category';
    }

    /**
     * {@inheritDoc}
     */
    public function unavailableMessage(): string
    {
        return __( 'Picking categories becomes available once the store engine manages categories.' );
    }
}
