<?php

/**
 * Product picker component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

/**
 * `<x-artisanpack-ec-product-picker model="…" :options="$this->optionsForPicker( 'product', '…' )" />`
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class ProductPicker extends Picker
{
    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'product';
    }
}
