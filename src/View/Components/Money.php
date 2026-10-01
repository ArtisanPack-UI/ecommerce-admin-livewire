<?php

/**
 * Money display component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-money :amount="$order->total_amount" :currency="$order->currency" />`
 *
 * Formats an integer minor-unit amount through the engine's `MoneyFormatter`
 * in the app locale (or `locale`), so the admin and storefront agree.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Money extends Component
{
    /**
     * @since 1.0.0
     *
     * @param  int|null     $amount    The amount in minor units; null renders a dash.
     * @param  string|null  $currency  The ISO 4217 code; defaults to the store's base currency.
     * @param  string|null  $locale    The display locale; defaults to the app locale.
     */
    public function __construct(
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $locale = null,
    ) {
        $this->currency ??= (string) config( 'artisanpack.ecommerce.base_currency', 'USD' );
    }

    /**
     * The formatted amount.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function formatted(): ?string
    {
        return null === $this->amount ? null : MoneyFormatter::format( $this->amount, (string) $this->currency, $this->locale );
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
        return view( 'ecommerce-admin::components.money' );
    }
}
