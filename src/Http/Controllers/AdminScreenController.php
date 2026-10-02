<?php

/**
 * Admin screen controller.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

/**
 * Returns the page view for each admin screen.
 *
 * Every page is a controller action (not a closure, so `route:cache` works)
 * returning a view that extends the resolved layout and embeds one Livewire
 * component. The component authorizes the screen itself.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class AdminScreenController extends Controller
{
    /**
     * The dashboard.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function dashboard(): View
    {
        return view( 'ecommerce-admin::pages.dashboard' );
    }

    /**
     * The orders index.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function ordersIndex(): View
    {
        return view( 'ecommerce-admin::pages.orders.index' );
    }

    /**
     * An order's detail page.
     *
     * The id is passed through unresolved: the component loads and
     * authorizes the order, so the access check answers before the lookup.
     *
     * @since 1.0.0
     *
     * @param  string  $order  The order id.
     *
     * @return View
     */
    public function ordersShow( string $order ): View
    {
        return view( 'ecommerce-admin::pages.orders.show', [ 'order' => (int) $order ] );
    }

    /**
     * The products index.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function productsIndex(): View
    {
        return view( 'ecommerce-admin::pages.products.index' );
    }

    /**
     * The new-product form.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function productsCreate(): View
    {
        return view( 'ecommerce-admin::pages.products.form', [ 'product' => null ] );
    }

    /**
     * The edit-product form. The component loads and authorizes the product.
     *
     * @since 1.0.0
     *
     * @param  string  $product  The product id.
     *
     * @return View
     */
    public function productsEdit( string $product ): View
    {
        return view( 'ecommerce-admin::pages.products.form', [ 'product' => (int) $product ] );
    }

    /**
     * The reviews moderation queue.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function reviewsIndex(): View
    {
        return view( 'ecommerce-admin::pages.reviews.index' );
    }

    /**
     * The product CSV import.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function productsImport(): View
    {
        return view( 'ecommerce-admin::pages.products.import' );
    }

    /**
     * The category tree.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function categoriesIndex(): View
    {
        return view( 'ecommerce-admin::pages.categories.index' );
    }

    /**
     * The tags table.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function tagsIndex(): View
    {
        return view( 'ecommerce-admin::pages.tags.index' );
    }

    /**
     * The inventory table.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function inventoryIndex(): View
    {
        return view( 'ecommerce-admin::pages.inventory.index' );
    }

    /**
     * The digital files table.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function digitalFilesIndex(): View
    {
        return view( 'ecommerce-admin::pages.digital-files.index' );
    }

    /**
     * The license keys table.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function licenseKeysIndex(): View
    {
        return view( 'ecommerce-admin::pages.license-keys.index' );
    }

    /**
     * The customers index.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function customersIndex(): View
    {
        return view( 'ecommerce-admin::pages.customers.index' );
    }

    /**
     * A customer's detail page. The component loads and authorizes the
     * customer.
     *
     * @since 1.0.0
     *
     * @param  string  $customer  The customer id.
     *
     * @return View
     */
    public function customersShow( string $customer ): View
    {
        return view( 'ecommerce-admin::pages.customers.show', [ 'customer' => (int) $customer ] );
    }

    /**
     * The promotions index.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function promotionsIndex(): View
    {
        return view( 'ecommerce-admin::pages.promotions.index' );
    }

    /**
     * The new-promotion form.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function promotionsCreate(): View
    {
        return view( 'ecommerce-admin::pages.promotions.form', [ 'promotion' => null ] );
    }

    /**
     * The edit-promotion form. The component loads and authorizes the
     * promotion.
     *
     * @since 1.0.0
     *
     * @param  string  $promotion  The promotion id.
     *
     * @return View
     */
    public function promotionsEdit( string $promotion ): View
    {
        return view( 'ecommerce-admin::pages.promotions.form', [ 'promotion' => (int) $promotion ] );
    }
}
