<?php

/**
 * Money input component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

use ArtisanPackUI\EcommerceAdminLivewire\Support\MinorUnits;

/**
 * `<x-artisanpack-ec-money-input wire:model="priceAmount" currency="JPY" :label="__( 'Price' )" />`
 *
 * Shows major units and binds integer minor units using the currency's
 * subunit (JPY 0, USD 2, KWD 3). Validate the bound property as an integer
 * on the server.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class MoneyInput extends ScaledIntegerInput
{
    /**
     * @since 1.0.0
     *
     * @param  string|null  $currency  The ISO 4217 code; defaults to the store's base currency.
     * @param  string|null  $id        A distinct id when the same model appears twice.
     * @param  string|null  $label     The label.
     * @param  string|null  $hint      The hint.
     * @param  string|null  $prefix    Text before the field; defaults to the currency code.
     * @param  string|null  $suffix    Text after the field.
     */
    public function __construct(
        public ?string $currency = null,
        public ?string $id = null,
        public ?string $label = null,
        public ?string $hint = null,
        public ?string $prefix = null,
        public ?string $suffix = null,
    ) {
        $this->currency = strtoupper( $this->currency ?? (string) config( 'artisanpack.ecommerce.base_currency', 'USD' ) );
        $this->prefix ??= $this->currency;
    }

    /**
     * The currency's subunit.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function scale(): int
    {
        return MinorUnits::subunit( (string) $this->currency );
    }
}
