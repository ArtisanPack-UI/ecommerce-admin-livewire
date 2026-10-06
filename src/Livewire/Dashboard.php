<?php

/**
 * Dashboard component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire;

use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductReview;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Reports\LowStockReport;
use ArtisanPackUI\Ecommerce\Reports\ReportRange;
use ArtisanPackUI\Ecommerce\Reports\SalesReport;
use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\ListensForAdminBroadcasts;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminBroadcasts;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\DashboardWidgets;
use ArtisanPackUI\EcommerceAdminLivewire\Support\MinorUnits;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StoreCurrencies;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

/**
 * The admin landing screen (spec §7.1).
 *
 * Shows what needs attention, built from the engine's dashboard summary
 * report so the numbers match the reports screen:
 *
 * - a KPI row — sales today and over the last 30 days (base currency),
 *   orders awaiting fulfillment, low-stock items, and reviews awaiting
 *   moderation — each linking to the screen filtered to match;
 * - a 30-day sales sparkline;
 * - the five lowest-stock items and the ten most recent orders.
 *
 * Every widget and KPI is hidden without the ability of the screen it
 * links to (`report.view`, `order.viewAny`, `inventory.viewAny`,
 * `review.viewAny`), and only the data for visible widgets is queried. A
 * store with no products and no orders gets a first-run state in place of
 * the core widgets. When nothing on the dashboard is visible, it lists the
 * screens the user can open.
 *
 * Other packages add widgets through the
 * `ap.ecommerceAdminLivewire.dashboard.widgets` filter (see
 * {@see DashboardWidgets}). With real-time updates on, the dashboard
 * refreshes when the engine broadcasts an order, stock, or review change.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Dashboard extends Component
{
    use AuthorizesEcommerce;
    use ListensForAdminBroadcasts;

    /**
     * How many recent orders to list.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const RECENT_ORDERS = 10;

    /**
     * How many low-stock items to list.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const LOW_STOCK_ITEMS = 5;

    /**
     * Ability per KPI.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public const KPI_ABILITIES = [
        'sales-today'          => 'report.view',
        'sales-30-days'        => 'report.view',
        'awaiting-fulfillment' => 'order.viewAny',
        'low-stock'            => 'inventory.viewAny',
        'pending-reviews'      => 'review.viewAny',
    ];

    /**
     * This request's sales report.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>|null
     */
    protected ?array $salesReportResult = null;

    /**
     * Authorizes the screen.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeAdminAccess();
    }

    /**
     * Re-checks access on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeAdminAccess();
    }

    /**
     * Refreshes after an order broadcast.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function orderBroadcast(): void
    {
        // Only re-render when something on screen shows orders.
        if ( ! $this->showsOrders() ) {
            $this->skipRender();

            return;
        }

        $this->liveAnnouncement = __( 'Orders changed. The dashboard has been updated.' );
    }

    /**
     * Refreshes after a stock broadcast.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function stockBroadcast(): void
    {
        $this->liveAnnouncement = __( 'Stock changed. The dashboard has been updated.' );
    }

    /**
     * Refreshes after a review broadcast.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function reviewBroadcast(): void
    {
        $this->liveAnnouncement = __( 'A review was submitted. The dashboard has been updated.' );
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
        $user    = auth()->user();
        $kpiKeys = $this->kpiKeys();
        $widgets = $this->widgetsFor( $kpiKeys );

        $firstRun = [] !== $widgets && $this->storeIsEmpty();

        if ( $firstRun ) {
            $core    = array_column( DashboardWidgets::core(), 'key' );
            $widgets = array_values( array_filter( $widgets, static fn ( array $widget ): bool => ! in_array( $widget['key'], $core, true ) ) );
        }

        $keys     = array_column( $widgets, 'key' );
        $sections = [];

        if ( [] === $widgets && ! $firstRun ) {
            $sections = AdminNav::grouped( $user );

            unset( $sections[ AdminNav::TOP ] );
        }

        $canCreate = $firstRun && Authorization::allows( $user, 'product.create' );

        return view( 'ecommerce-admin::livewire.dashboard', [
            'widgets'   => $widgets,
            'firstRun'  => $firstRun,
            'sections'  => $sections,
            'createUrl' => $canCreate ? self::url( 'products.create' ) : null,
            'importUrl' => $canCreate ? self::url( 'products.import' ) : null,
            'dashboard' => [
                'kpis'         => in_array( DashboardWidgets::KPIS, $keys, true ) ? $this->kpis( $kpiKeys ) : [],
                'sales'        => in_array( DashboardWidgets::SALES, $keys, true ) ? $this->sales() : null,
                'lowStock'     => in_array( DashboardWidgets::LOW_STOCK, $keys, true ) ? $this->lowStock() : null,
                'recentOrders' => in_array( DashboardWidgets::RECENT_ORDERS, $keys, true ) ? $this->recentOrders() : null,
            ],
        ] );
    }

    /**
     * The broadcasts the dashboard refreshes on.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function adminBroadcasts(): array
    {
        return [
            AdminBroadcasts::ORDER_STATUS_CHANGED => 'orderBroadcast',
            AdminBroadcasts::PAYMENT_SUCCEEDED    => 'orderBroadcast',
            AdminBroadcasts::STOCK_CHANGED        => 'stockBroadcast',
            AdminBroadcasts::REVIEW_SUBMITTED     => 'reviewBroadcast',
        ];
    }

    /**
     * The requested KPIs, from the engine's summary report.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $keys  The KPI keys the user can see.
     *
     * @return array<int, array{key: string, label: string, value: string, description: string, url: string|null, icon: string}>
     */
    protected function kpis( array $keys ): array
    {
        // The engine's SummaryReport runs the same 30-day SalesReport the
        // sparkline needs; run it once (cached) and add the three counts.
        $month    = $this->salesReport();
        $today    = $month['series'][ array_key_last( (array) $month['series'] ) ] ?? [ 'total' => 0, 'orders' => 0 ];
        $currency = (string) $month['currency'];
        $totals   = [
            'sales_today'          => (int) $today['total'],
            'orders_today'         => (int) $today['orders'],
            'sales_30_days'        => (int) $month['totals']['total'],
            'orders_30_days'       => (int) $month['totals']['orders'],
            'awaiting_fulfillment' => in_array( 'awaiting-fulfillment', $keys, true ) ? Order::query()->where( 'system_status', 'processing' )->whereIn( 'fulfillment_status', [ 'unfulfilled', 'partial' ] )->count() : 0,
            'low_stock'            => in_array( 'low-stock', $keys, true ) ? LowStockReport::query()->count() : 0,
            'pending_reviews'      => in_array( 'pending-reviews', $keys, true ) ? ProductReview::query()->where( 'status', ProductReview::STATUS_PENDING )->count() : 0,
        ];
        $kpis     = [
            'sales-today' => [
                'label'       => __( 'Sales today' ),
                'value'       => MoneyFormatter::format( $totals['sales_today'], $currency ),
                'description' => trans_choice( ':count order|:count orders', $totals['orders_today'], [ 'count' => $totals['orders_today'] ] ),
                'url'         => self::url( 'reports.show', [ 'report' => 'sales', 'preset' => 'today' ] ),
                'icon'        => 'o-banknotes',
            ],
            'sales-30-days' => [
                'label'       => __( 'Sales, last 30 days' ),
                'value'       => MoneyFormatter::format( $totals['sales_30_days'], $currency ),
                'description' => trans_choice( ':count order|:count orders', $totals['orders_30_days'], [ 'count' => $totals['orders_30_days'] ] ),
                'url'         => self::url( 'reports.show', [ 'report' => 'sales' ] ),
                'icon'        => 'o-chart-bar',
            ],
            'awaiting-fulfillment' => [
                'label'       => __( 'Awaiting fulfillment' ),
                'value'       => (string) $totals['awaiting_fulfillment'],
                'description' => trans_choice( ':count order to ship|:count orders to ship', $totals['awaiting_fulfillment'], [ 'count' => $totals['awaiting_fulfillment'] ] ),
                'url'         => self::url( 'orders.index', [ 'filters' => [ 'awaiting' => '1' ] ] ),
                'icon'        => 'o-truck',
            ],
            'low-stock' => [
                'label'       => __( 'Low stock' ),
                'value'       => (string) $totals['low_stock'],
                'description' => trans_choice( ':count item at or below its threshold|:count items at or below their threshold', $totals['low_stock'], [ 'count' => $totals['low_stock'] ] ),
                'url'         => self::url( 'inventory.index', [ 'filters' => [ 'stock' => 'reorder' ] ] ),
                'icon'        => 'o-archive-box',
            ],
            'pending-reviews' => [
                'label'       => __( 'Reviews to moderate' ),
                'value'       => (string) $totals['pending_reviews'],
                'description' => trans_choice( ':count review awaiting moderation|:count reviews awaiting moderation', $totals['pending_reviews'], [ 'count' => $totals['pending_reviews'] ] ),
                'url'         => self::url( 'reviews.index', [ 'filters' => [ 'status' => ProductReview::STATUS_PENDING ] ] ),
                'icon'        => 'o-star',
            ],
        ];

        return array_map( static fn ( string $key ): array => [ 'key' => $key, ...$kpis[ $key ] ], $keys );
    }

    /**
     * Daily sales totals for the last 30 days, for the sparkline.
     *
     * @since 1.0.0
     *
     * @return array{values: array<int, float>, total: string, summary: string, url: string|null}
     */
    protected function sales(): array
    {
        $result   = $this->salesReport();
        $currency = (string) $result['currency'];
        $series   = array_values( (array) $result['series'] );
        $best     = null;

        foreach ( $series as $day ) {
            if ( (int) $day['total'] > 0 && ( null === $best || (int) $day['total'] > (int) $best['total'] ) ) {
                $best = $day;
            }
        }

        $total = MoneyFormatter::format( (int) $result['totals']['total'], $currency );

        return [
            'values'  => array_map( static fn ( array $day ): float => (float) MinorUnits::toMajor( (int) $day['total'], $currency ), $series ),
            'total'   => $total,
            'summary' => null === $best
                ? __( 'No sales in the last 30 days.' )
                : __( 'Sales over the last 30 days: :total in total. Best day: :day, with :amount.', [
                    'total'  => $total,
                    'day'    => LocalizedDate::format( (string) $best['start'] ),
                    'amount' => MoneyFormatter::format( (int) $best['total'], $currency ),
                ] ),
            'url'     => self::url( 'reports.show', [ 'report' => 'sales' ] ),
        ];
    }

    /**
     * The last 30 days of sales, run once per request and cached for a
     * minute per store time zone and currency, so broadcasts and the KPI row
     * and sparkline don't each re-run it.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function salesReport(): array
    {
        if ( null !== $this->salesReportResult ) {
            return $this->salesReportResult;
        }

        $key = 'ecommerce-admin.dashboard.sales.' . ReportRange::timezone() . '.' . StoreCurrencies::base();

        return $this->salesReportResult = (array) Cache::remember( $key, 60, static fn (): array => app( SalesReport::class )->run( ReportRange::lastDays( 30 ) ) );
    }

    /**
     * The KPI keys the user may see.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function kpiKeys(): array
    {
        $user = auth()->user();

        return array_keys( array_filter( self::KPI_ABILITIES, static fn ( string $ability ): bool => Authorization::allows( $user, $ability ) ) );
    }

    /**
     * The widgets the user sees; the KPI row has no ability of its own and
     * shows when any KPI does.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $kpiKeys  The KPI keys the user may see.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function widgetsFor( array $kpiKeys ): array
    {
        return array_values( array_filter(
            DashboardWidgets::visible( auth()->user() ),
            static fn ( array $widget ): bool => DashboardWidgets::KPIS !== $widget['key'] || [] !== $kpiKeys,
        ) );
    }

    /**
     * Whether a widget on screen shows orders: the sales chart, recent
     * orders, or an order-based KPI.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function showsOrders(): bool
    {
        $kpiKeys = $this->kpiKeys();
        $keys    = array_column( $this->widgetsFor( $kpiKeys ), 'key' );

        return [] !== array_intersect( $keys, [ DashboardWidgets::SALES, DashboardWidgets::RECENT_ORDERS ] )
            || ( in_array( DashboardWidgets::KPIS, $keys, true ) && [] !== array_intersect( $kpiKeys, [ 'sales-today', 'sales-30-days', 'awaiting-fulfillment' ] ) );
    }

    /**
     * The tracked items furthest below their threshold.
     *
     * @since 1.0.0
     *
     * @return Collection<int, InventoryItem>
     */
    protected function lowStock(): Collection
    {
        return LowStockReport::query()
            ->select( ( new InventoryItem() )->qualifyColumn( '*' ) )
            ->selectRaw( '(quantity_on_hand - quantity_reserved) as quantity_available' )
            ->with( [
                'stockable' => static fn ( MorphTo $morph ) => $morph->morphWith( [
                    ProductVariant::class => [ 'product:id,name,type,sku' ],
                ] ),
            ] )
            ->orderByRaw( '(quantity_on_hand - quantity_reserved) asc' )
            ->orderBy( 'id' )
            ->limit( self::LOW_STOCK_ITEMS )
            ->get();
    }

    /**
     * The most recently placed orders.
     *
     * @since 1.0.0
     *
     * @return Collection<int, Order>
     */
    protected function recentOrders(): Collection
    {
        return Order::query()
            ->with( 'customer' )
            ->whereNotNull( 'placed_at' )
            ->orderByDesc( 'placed_at' )
            ->orderByDesc( 'id' )
            ->limit( self::RECENT_ORDERS )
            ->get();
    }

    /**
     * Whether the store has neither products nor orders yet.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function storeIsEmpty(): bool
    {
        return ! Product::query()->exists() && ! Order::query()->exists();
    }

    /**
     * An admin URL, or null when the route is not registered.
     *
     * @since 1.0.0
     *
     * @param  string                $route       Route name, without the admin prefix.
     * @param  array<string, mixed>  $parameters  Route parameters and query.
     *
     * @return string|null
     */
    private static function url( string $route, array $parameters = [] ): ?string
    {
        $name = AdminNav::ROUTE_PREFIX . $route;

        return Route::has( $name ) ? route( $name, $parameters ) : null;
    }
}
