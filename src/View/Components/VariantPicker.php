<?php

/**
 * Variant picker component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

/**
 * `<x-artisanpack-ec-variant-picker model="…" :options="$this->optionsForPicker( 'variant', '…' )" />`
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class VariantPicker extends Picker
{
    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'variant';
    }
}
