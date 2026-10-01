<?php

/**
 * Missing product type warning.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

use ArtisanPackUI\Ecommerce\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-product-type-warning :product="$product" />`
 *
 * Shows the engine's read-only banner when the product's type is provided by
 * a satellite that is not installed. Renders nothing otherwise.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class ProductTypeWarning extends Component
{
    /**
     * @since 1.0.0
     *
     * @param  Product  $product  The product.
     */
    public function __construct( public Product $product )
    {
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
        return $this->product->typeIsMissing();
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
        return view( 'ecommerce-admin::components.product-type-warning', [
            'warning' => (string) $this->product->typeWarning(),
        ] );
    }
}
