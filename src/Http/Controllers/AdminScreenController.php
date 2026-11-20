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

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Index as CustomersIndex;
use ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight;
use Illuminate\Contracts\Auth\Authenticatable;
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
     * The command palette's results, as groups: the host's own spotlight
     * results (through the component library's results filter) in an
     * unlabeled group, then one group per admin provider. The actions are
     * left out when the palette lists them itself (see
     * AdminSpotlight::actionGroup()), so they don't show twice.
     *
     * @since 1.0.0
     * @since 2.0.0 Returns groups of results instead of one flat list.
     *
     * @param  Request  $request  The request (`search` query).
     *
     * @return array<int, array{id: string, label: string|null, items: array<int, mixed>}>
     */
    public function spotlight( Request $request ): array
    {
        $class   = config( 'artisanpack.livewire-ui-components.components.spotlight.class' );
        $user    = $request->user();
        $search  = $request->query( 'search' );
        $results = is_string( $class ) && '' !== $class && ( class_exists( $class ) || app()->bound( $class ) )
            ? (array) app()->make( $class )->search( $request )
            : [];
        $results = array_values( (array) applyFilters( AdminSpotlight::COMMANDS_FILTER, $results, $user ) );
        $groups  = [] === $results ? [] : [ [ 'id' => 'results', 'label' => null, 'items' => $results ] ];

        if ( ! AdminSpotlight::enabled() || ! $user instanceof Authenticatable || ! is_string( $search ) ) {
            return $groups;
        }

        $except = null === AdminSpotlight::actionsProvider( $user ) ? [] : [ 'actions' ];

        return [ ...$groups, ...AdminSpotlight::groups( $search, $user, $except ) ];
    }

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
     * The id is passed to the component, which loads and authorizes the
     * order. The page title names the order number (WCAG 2.4.2); it is only
     * sent when the component lets the page render.
     *
     * @since 1.0.0
     * @since 2.0.0 Passes a `screenTitle` naming the order.
     *
     * @param  string  $order  The order id.
     *
     * @return View
     */
    public function ordersShow( string $order ): View
    {
        $number = Order::query()->whereKey( (int) $order )->value( 'order_number' );

        return view( 'ecommerce-admin::pages.orders.show', [
            'order'       => (int) $order,
            'screenTitle' => null === $number || '' === $number ? __( 'Order' ) : __( 'Order #:number', [ 'number' => $number ] ),
        ] );
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
     * customer; the page title names them (WCAG 2.4.2).
     *
     * @since 1.0.0
     * @since 2.0.0 Passes a `screenTitle` naming the customer.
     *
     * @param  string  $customer  The customer id.
     *
     * @return View
     */
    public function customersShow( string $customer ): View
    {
        $model = Customer::query()->find( (int) $customer, [ 'id', 'first_name', 'last_name', 'email' ] );
        $name  = null === $model ? null : ( CustomersIndex::customerName( $model ) ?? (string) $model->email );

        return view( 'ecommerce-admin::pages.customers.show', [
            'customer'    => (int) $customer,
            'screenTitle' => null === $name || '' === $name ? __( 'Customer' ) : $name,
        ] );
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
     * promotion; the page title names it (WCAG 2.4.2).
     *
     * @since 1.0.0
     * @since 2.0.0 Passes a `screenTitle` naming the promotion.
     *
     * @param  string  $promotion  The promotion id.
     *
     * @return View
     */
    public function promotionsEdit( string $promotion ): View
    {
        $name = Promotion::query()->whereKey( (int) $promotion )->value( 'name' );

        return view( 'ecommerce-admin::pages.promotions.form', [
            'promotion'   => (int) $promotion,
            'screenTitle' => null === $name || '' === $name ? __( 'Edit promotion' ) : __( 'Edit :name', [ 'name' => $name ] ),
        ] );
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
