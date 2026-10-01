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
 * `<x-artisanpack-ec-status-badge :substatus="$order->substatus" />`
 *
 * Types: `system`, `payment`, `fulfillment`, `review`, `shipment`, and
 * sub-statuses. The badge always shows the status as text, preceded by a
 * visually hidden type label, so status is never conveyed by colour alone.
 * A sub-status colour is a hex value; livewire-ui-components picks an
 * accessible text colour for it.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class StatusBadge extends Component
{
    /**
     * The visible label.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $label;

    /**
     * The badge colour: a daisyUI variant or a hex value.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $color;

    /**
     * @since 1.0.0
     *
     * @param  string               $type       The status type, or `substatus`.
     * @param  string|null          $value      The stored status value.
     * @param  OrderSubstatus|null  $substatus  A sub-status (sets the type to `substatus`).
     */
    public function __construct(
        public string $type = 'system',
        public ?string $value = null,
        public ?OrderSubstatus $substatus = null,
    ) {
        if ( null !== $this->substatus ) {
            $this->type  = 'substatus';
            $this->label = (string) $this->substatus->label;
            $this->color = self::isHex( $this->substatus->color ) ? (string) $this->substatus->color : 'neutral';

            return;
        }

        [ 'label' => $this->label, 'color' => $this->color ] = StatusPresenter::present( $this->type, (string) $this->value );
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
     * Whether a value is a `#rrggbb` colour.
     *
     * @since 1.0.0
     *
     * @param  mixed  $color  The value.
     *
     * @return bool
     */
    private static function isHex( mixed $color ): bool
    {
        return is_string( $color ) && 1 === preg_match( '/^#[0-9a-fA-F]{6}$/', $color );
    }
}
