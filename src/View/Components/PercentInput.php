<?php

/**
 * Percent input component.
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
 * `<x-artisanpack-ec-percent-input wire:model="rateUbps" :label="__( 'Rate' )" />`
 *
 * Shows a percent and binds `rate_ubps` (units of 10^-9). 8.375 binds
 * 83750000, the same value the engine's `TaxRateMath::fromPercent( '8.375' )`
 * returns.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class PercentInput extends ScaledIntegerInput
{
    /**
     * @since 1.0.0
     *
     * @param  string|null  $id      A distinct id when the same model appears twice.
     * @param  string|null  $label   The label.
     * @param  string|null  $hint    The hint.
     * @param  string|null  $prefix  Text before the field.
     * @param  string|null  $suffix  Text after the field; defaults to `%`.
     */
    public function __construct(
        public ?string $id = null,
        public ?string $label = null,
        public ?string $hint = null,
        public ?string $prefix = null,
        public ?string $suffix = '%',
    ) {
    }

    /**
     * `rate_ubps` per percent is 10^7.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function scale(): int
    {
        return MinorUnits::PERCENT_SCALE;
    }

    /**
     * Percentages display without padding, e.g. "8.375".
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function trimsZeros(): bool
    {
        return true;
    }
}
