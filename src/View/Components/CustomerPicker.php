<?php

/**
 * Customer picker component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

/**
 * `<x-artisanpack-ec-customer-picker model="…" :options="$this->optionsForPicker( 'customer', '…' )" />`
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class CustomerPicker extends Picker
{
    /**
     * {@inheritDoc}
     */
    public function type(): string
    {
        return 'customer';
    }
}
