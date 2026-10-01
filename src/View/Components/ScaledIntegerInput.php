<?php

/**
 * Base for inputs bound to scaled integers.
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
 * An input that shows a decimal and binds an integer scaled by 10^scale.
 *
 * The visible field holds the decimal text; the `wire:model` property holds
 * the integer. Alpine converts between them by moving digits in strings (the
 * same algorithm as {@see \ArtisanPackUI\EcommerceAdminLivewire\Support\MinorUnits}),
 * so no value passes through a float. Input that cannot be converted (e.g.
 * too many decimals) marks the field invalid and clears the bound value, so
 * the server's validation rejects the submit rather than saving the
 * previous amount.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
abstract class ScaledIntegerInput extends Component
{
    /**
     * The number of decimals the bound integer carries.
     *
     * @since 1.0.0
     *
     * @return int
     */
    abstract public function scale(): int;

    /**
     * Whether the display drops trailing fractional zeros.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function trimsZeros(): bool
    {
        return false;
    }

    /**
     * The message shown when the entry cannot be converted.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function invalidMessage(): string
    {
        return 0 === $this->scale()
            ? __( 'Enter a whole number.' )
            : trans_choice( 'Enter a number with up to :count decimal place.|Enter a number with up to :count decimal places.', $this->scale(), [ 'count' => $this->scale() ] );
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
        return view( 'ecommerce-admin::components.scaled-integer-input' );
    }
}
