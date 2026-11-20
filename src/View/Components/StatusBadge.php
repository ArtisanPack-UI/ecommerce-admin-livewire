<?php

/**
 * Status badge component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StatusPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-status-badge type="payment" :value="$order->payment_status" />`
 * `<x-artisanpack-ec-status-badge :substatus="$order->substatus" size="lg" />`
 *
 * Types: `system`, `payment`, `fulfillment`, `review`, `shipment`, and
 * sub-statuses.
 *
 * A soft design-system Badge with a leading dot (#81), so status is carried
 * by the label and the dot, never by color alone. The color follows
 * `StatusPresenter`: warning needs action, info is in progress, success is
 * done, error failed, neutral is closed or unknown. A visually hidden type
 * prefix ("Payment status:") keeps the badge unambiguous out of its column.
 *
 * A merchant-picked hex (a sub-status's color, or one the `statusBadge`
 * filter returns) renders as a neutral badge with the hex as its dot, so the
 * label's contrast holds whatever color was picked.
 *
 * Sizes: `md` (the default) in tables and headers, `sm` in dense lists, `lg`
 * in page headers.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class StatusBadge extends Component
{
    /**
     * The sizes the badge takes.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const SIZES = [ 'sm', 'md', 'lg' ];

    /**
     * The design-system colors a status may take; anything else is neutral.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const COLORS = [ 'primary', 'secondary', 'accent', 'neutral', 'success', 'warning', 'error', 'info' ];

    /**
     * The visible label.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $label;

    /**
     * The badge color: one of {@see self::COLORS}.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $color;

    /**
     * The merchant-picked `#rrggbb` color for the dot, if any.
     *
     * @since 2.0.0
     *
     * @var string|null
     */
    public ?string $hex = null;

    /**
     * @since 1.0.0
     * @since 2.0.0 Added `$size`.
     *
     * @param  string               $type       The status type, or `substatus`.
     * @param  string|null          $value      The stored status value.
     * @param  OrderSubstatus|null  $substatus  A sub-status (sets the type to `substatus`).
     * @param  string               $size       `sm`, `md` or `lg`.
     */
    public function __construct(
        public string $type = 'system',
        public ?string $value = null,
        public ?OrderSubstatus $substatus = null,
        public string $size = 'md',
    ) {
        $this->size = in_array( $this->size, self::SIZES, true ) ? $this->size : 'md';

        if ( null !== $this->substatus ) {
            $this->type  = 'substatus';
            $this->label = (string) $this->substatus->label;
            $this->color = 'neutral';
            $this->hex   = self::isHex( $this->substatus->color ) ? (string) $this->substatus->color : null;

            return;
        }

        [ 'label' => $this->label, 'color' => $color ] = StatusPresenter::present( $this->type, (string) $this->value );

        if ( self::isHex( $color ) ) {
            $this->color = 'neutral';
            $this->hex   = (string) $color;

            return;
        }

        $this->color = in_array( $color, self::COLORS, true ) ? (string) $color : 'neutral';
    }

    /**
     * Whether the component renders.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function shouldRender(): bool
    {
        return null !== $this->substatus || ( null !== $this->value && '' !== $this->value );
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
        return view( 'ecommerce-admin::components.status-badge', [
            'typeLabel' => StatusPresenter::typeLabel( $this->type ),
        ] );
    }

    /**
     * Whether a value is a `#rrggbb` color.
     *
     * @since 1.0.0
     *
     * @param  mixed  $color  The value.
     *
     * @return bool
     */
    private static function isHex( mixed $color ): bool
    {
        return is_string( $color ) && 1 === preg_match( '/^#[0-9a-fA-F]{6}$/D', $color );
    }
}
