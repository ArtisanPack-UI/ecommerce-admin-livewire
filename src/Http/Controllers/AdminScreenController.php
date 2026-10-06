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

use ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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
    /**
     * The command palette's results: the host's own spotlight results, then
     * the admin's (through the component library's results filter).
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The request (`search` query).
     *
     * @return array<int, mixed>
     */
    public function spotlight( Request $request ): array
    {
        $class   = config( 'artisanpack.livewire-ui-components.components.spotlight.class' );
        $results = is_string( $class ) && '' !== $class && ( class_exists( $class ) || app()->bound( $class ) )
            ? (array) app()->make( $class )->search( $request )
            : [];

        return array_values( (array) applyFilters( AdminSpotlight::COMMANDS_FILTER, $results, $request->user() ) );
    }

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

    /**
     * The shipping zones and methods screen.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function shippingIndex(): View
    {
        return view( 'ecommerce-admin::pages.shipping.index' );
    }

    /**
     * The tax classes and rates screen.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function taxIndex(): View
    {
        return view( 'ecommerce-admin::pages.tax.index' );
    }

    /**
     * The notification templates list.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function notificationsIndex(): View
    {
        return view( 'ecommerce-admin::pages.notifications.index' );
    }

    /**
     * The notification template editor. The component loads and authorizes
     * the template.
     *
     * @since 1.0.0
     *
     * @param  string  $template  The template id.
     *
     * @return View
     */
    public function notificationsEdit( string $template ): View
    {
        return view( 'ecommerce-admin::pages.notifications.edit', [ 'template' => (int) $template ] );
    }

    /**
     * The webhook subscriptions list.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function webhooksIndex(): View
    {
        return view( 'ecommerce-admin::pages.webhooks.index' );
    }

    /**
     * A webhook subscription and its deliveries log. The component loads
     * and authorizes the subscription.
     *
     * @since 1.0.0
     *
     * @param  string  $subscription  The subscription id.
     *
     * @return View
     */
    public function webhooksShow( string $subscription ): View
    {
        return view( 'ecommerce-admin::pages.webhooks.show', [ 'subscription' => (int) $subscription ] );
    }

    /**
     * The order sub-statuses screen.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function orderStatusesIndex(): View
    {
        return view( 'ecommerce-admin::pages.order-statuses.index' );
    }

    /**
     * The kanban boards list.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function kanbanBoardsIndex(): View
    {
        return view( 'ecommerce-admin::pages.kanban-boards.index' );
    }

    /**
     * A kanban board's settings: details, routing rules, columns, and
     * automations. The component loads and authorizes the board.
     *
     * @since 1.0.0
     *
     * @param  string  $board  The board id.
     *
     * @return View
     */
    public function kanbanBoardsEdit( string $board ): View
    {
        return view( 'ecommerce-admin::pages.kanban-boards.edit', [ 'board' => (int) $board ] );
    }

    /**
     * A report. The component authorizes `report.view` and 404s an unknown
     * report.
     *
     * @since 1.0.0
     *
     * @param  string  $report  The report key.
     *
     * @return View
     */
    public function reportsShow( string $report ): View
    {
        return view( 'ecommerce-admin::pages.reports.show', [ 'report' => $report ] );
    }

    /**
     * A settings tab. The component authorizes `settings.view` and 404s an
     * unknown tab.
     *
     * @since 1.0.0
     *
     * @param  string  $group  The settings group or tab key.
     *
     * @return View
     */
    public function settingsShow( string $group ): View
    {
        return view( 'ecommerce-admin::pages.settings.show', [ 'group' => $group ] );
    }
}
