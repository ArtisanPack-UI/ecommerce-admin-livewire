<?php

/**
 * Tag picker component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

/**
 * `<x-artisanpack-ec-tag-picker model="…" :options="$this->optionsForPicker( 'tag', '…' )" />`
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class TagPicker extends Picker
{
    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'tag';
    }

    /**
     * {@inheritDoc}
     */
    public function unavailableMessage(): string
    {
        return __( 'Picking tags becomes available once the store engine manages tags.' );
    }
}
