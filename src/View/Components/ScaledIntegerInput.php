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
use Illuminate\Support\HtmlString;
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
     * The unit the prefix or suffix shows, as read aloud after the label.
     *
     * @since 2.0.0
     *
     * @return string|null The unit, or null for none.
     */
    public function unitLabel(): ?string
    {
        return null;
    }

    /**
     * The label with the unit added for screen readers ("Price, USD"): the
     * prefix and suffix are context for the label, not part of the value.
     *
     * @since 2.0.0
     *
     * @return HtmlString|null The escaped label, or null without one.
     */
    public function accessibleLabel(): ?HtmlString
    {
        $label = $this->label ?? null;

        if ( null === $label || '' === $label ) {
            return null;
        }

        $unit = $this->unitLabel();

        if ( null === $unit || '' === $unit ) {
            return new HtmlString( e( $label ) );
        }

        return new HtmlString( e( $label ) . '<span class="apui-sr-only">' . e( __( ', :unit', [ 'unit' => $unit ] ) ) . '</span>' );
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
