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
 * `<x-artisanpack-ec-admin-money :amount="$order->total_amount" :currency="$order->currency" />`
 *
 * Also registered as `x-artisanpack-ec-money`, a name ecommerce-storefront-
 * livewire shares; the admin's views use `ec-admin-money`, which is always
 * this component.
 *
 * Formats an integer minor-unit amount through the engine's `MoneyFormatter`
 * in the app locale (or `locale`), so the admin and storefront agree.
 *
 * Built on design-system text roles and tokens (#82): the figure inherits
 * size and weight from where it sits, uses tabular figures and never wraps.
 * It is isolated left-to-right (`<bdi dir="ltr">`), so its sign and symbol
 * don't reorder in a right-to-left page. A negative amount uses a true minus
 * and the error text role; a missing amount shows an em dash that reads as
 * "No amount". `compare-at` adds a struck-through earlier price that reads as
 * "Was …", and `show-code` adds the currency code for views that mix
 * currencies.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Money extends Component
{
    /**
     * The true minus sign (U+2212).
     *
     * @since 2.0.0
     *
     * @var string
     */
    public const MINUS = "\u{2212}";

    /**
     * @since 1.0.0
     * @since 2.0.0 Added `$compareAt` and `$showCode`.
     *
     * @param  int|null     $amount     The amount in minor units; null renders a dash.
     * @param  string|null  $currency   The ISO 4217 code; defaults to the store's base currency.
     * @param  string|null  $locale     The display locale; defaults to the app locale.
     * @param  int|null     $compareAt  An earlier price in minor units, shown struck through after the amount.
     * @param  bool         $showCode   Whether to show the currency code, for views that mix currencies.
     */
    public function __construct(
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $locale = null,
        public ?int $compareAt = null,
        public bool $showCode = false,
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
        return null === $this->amount ? null : $this->format( $this->amount );
    }

    /**
     * The formatted compare-at price, when there is one that differs from
     * the amount.
     *
     * @since 2.0.0
     *
     * @return string|null
     */
    public function formattedCompareAt(): ?string
    {
        if ( null === $this->compareAt || null === $this->amount || $this->compareAt === $this->amount ) {
            return null;
        }

        return $this->format( $this->compareAt );
    }

    /**
     * The text tone: `negative` below zero, else none.
     *
     * @since 2.0.0
     *
     * @return string|null
     */
    public function tone(): ?string
    {
        return null !== $this->amount && $this->amount < 0 ? 'negative' : null;
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

    /**
     * Formats an amount, with a true minus sign when it is negative.
     *
     * @since 2.0.0
     *
     * @param  int  $amount  The amount in minor units.
     *
     * @return string
     */
    private function format( int $amount ): string
    {
        $formatted = MoneyFormatter::format( $amount, (string) $this->currency, $this->locale );

        return $amount < 0 ? str_replace( '-', self::MINUS, $formatted ) : $formatted;
    }
}
