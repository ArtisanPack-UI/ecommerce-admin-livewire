<?php

/**
 * Report presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Support\TaxRateMath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Turns an engine report result into the table, KPIs, and chart the
 * reports screen shows, and the CSV it exports (spec §7.7).
 *
 * Every report gets the same table model — columns with a type (`text`,
 * `int`, `money`, `percent`, `rate`) and rows keyed by column — so the
 * screen, the data table that stands in for the chart, and the CSV stay in
 * step. Reports this package does not know (a satellite's) fall back to a
 * table of their `rows` or `series` with the keys as headings.
 *
 * Column and table filters: `ap.ecommerceAdminLivewire.reports.{report}.table`
 * receives `( array $table, array $result )` and returns the table.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class ReportPresenter
{
    /**
     * Sales metrics a chart can plot.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const SALES_METRICS = [ 'net', 'gross', 'total', 'orders', 'average_order_value', 'refunds', 'tax', 'shipping', 'discounts' ];

    /**
     * Labels for the sales metrics.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public static function salesMetricLabels(): array
    {
        return [
            'gross'               => __( 'Gross sales' ),
            'discounts'           => __( 'Discounts' ),
            'refunds'             => __( 'Refunds' ),
            'net'                 => __( 'Net sales' ),
            'tax'                 => __( 'Tax' ),
            'shipping'            => __( 'Shipping' ),
            'total'               => __( 'Total sales' ),
            'orders'              => __( 'Orders' ),
            'average_order_value' => __( 'Average order value' ),
        ];
    }

    /**
     * The table: columns, rows, and a totals row (or null).
     *
     * @since 1.0.0
     *
     * @param  string                $report  Report key.
     * @param  array<string, mixed>  $result  Engine result.
     *
     * @return array{columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<string, mixed>>, totals: array<string, mixed>|null}
     */
    public static function table( string $report, array $result ): array
    {
        $table = match ( $report ) {
            'sales'               => self::salesTable( $result ),
            'top-products'        => self::topProductsTable( $result ),
            'revenue-by-category' => self::categoryTable( $result ),
            'tax'                 => self::taxTable( $result ),
            'inventory'           => self::inventoryTable( $result ),
            'low-stock'           => self::lowStockTable( $result ),
            default               => self::genericTable( $result ),
        };

        return (array) applyFilters( 'ap.ecommerceAdminLivewire.reports.' . $report . '.table', $table, $result );
    }

    /**
     * Headline numbers with the previous period's value and the change, for
     * the sales report; the report's totals for the others.
     *
     * @since 1.0.0
     *
     * @param  string                $report  Report key.
     * @param  array<string, mixed>  $result  Engine result.
     *
     * @return array<int, array{key: string, label: string, type: string, value: float|int, previous: float|int|null, change: float|null}>
     */
    public static function kpis( string $report, array $result ): array
    {
        $totals   = (array) ( $result['totals'] ?? [] );
        $previous = isset( $result['previous']['totals'] ) ? (array) $result['previous']['totals'] : null;

        $wanted = match ( $report ) {
            'sales'               => [ 'net' => 'money', 'orders' => 'int', 'average_order_value' => 'money', 'refunds' => 'money' ],
            'top-products'        => [ 'net_revenue' => 'money', 'units' => 'int', 'products' => 'int' ],
            'revenue-by-category' => [ 'net_revenue' => 'money', 'units' => 'int', 'categories' => 'int' ],
            'tax'                 => [ 'amount' => 'money', 'orders' => 'int' ],
            'inventory'           => [ 'stock_value' => 'money', 'on_hand' => 'int', 'reserved' => 'int', 'available' => 'int' ],
            'low-stock'           => [ 'items' => 'int' ],
            default               => array_map( static fn (): string => 'int', array_filter( $totals, 'is_int' ) ),
        };

        $labels = [
            ...self::salesMetricLabels(),
            'net_revenue' => __( 'Net revenue' ),
            'units'       => __( 'Units sold' ),
            'products'    => __( 'Products sold' ),
            'categories'  => __( 'Categories' ),
            'amount'      => __( 'Tax collected' ),
            'stock_value' => __( 'Stock value at cost' ),
            'on_hand'     => __( 'On hand' ),
            'reserved'    => __( 'Reserved' ),
            'available'   => __( 'Available' ),
            'items'       => __( 'Items at or below threshold' ),
        ];

        $kpis = [];

        foreach ( $wanted as $key => $type ) {
            if ( ! array_key_exists( $key, $totals ) ) {
                continue;
            }

            $value    = $totals[ $key ];
            $before   = null === $previous ? null : ( $previous[ $key ] ?? null );
            $kpis[]   = [
                'key'      => (string) $key,
                'label'    => $labels[ $key ] ?? (string) $key,
                'type'     => $type,
                'value'    => $value,
                'previous' => $before,
                'change'   => is_numeric( $before ) && 0 != $before ? round( ( $value - $before ) / abs( $before ) * 100, 1 ) : null,
            ];
        }

        return $kpis;
    }

    /**
     * The chart: ApexCharts type, categories, series (money as major units),
     * and a one-sentence summary for screen readers.
     *
     * @since 1.0.0
     *
     * @param  string                $report  Report key.
     * @param  array<string, mixed>  $result  Engine result.
     * @param  string                $metric  Sales metric to plot.
     *
     * @return array{type: string, categories: array<int, string>, series: array<int, array{name: string, data: array<int, float|int>}>, money: bool, summary: string}|null
     */
    public static function chart( string $report, array $result, string $metric = 'net' ): ?array
    {
        $currency = (string) ( $result['currency'] ?? 'USD' );
        $major    = static fn ( int $minor ): float => (float) MinorUnits::toMajor( $minor, $currency );

        switch ( $report ) {
            case 'sales':
                $metric   = in_array( $metric, self::SALES_METRICS, true ) ? $metric : 'net';
                $money    = 'orders' !== $metric;
                $label    = self::salesMetricLabels()[ $metric ];
                $series   = (array) ( $result['series'] ?? [] );
                $value    = static fn ( array $bucket ): int|float => $money ? $major( (int) $bucket[ $metric ] ) : (int) $bucket[ $metric ];
                $chart    = [
                    'type'       => 'area',
                    'categories' => array_map( static fn ( array $bucket ): string => self::periodLabel( (string) $bucket['period'], (string) ( $result['range']['interval'] ?? 'day' ) ), $series ),
                    'series'     => [ [ 'name' => __( ':metric, this period', [ 'metric' => $label ] ), 'data' => array_map( $value, $series ) ] ],
                    'money'      => $money,
                    'summary'    => __( ':metric by :interval from :from to :to. The table below lists every value.', [
                        'metric'   => $label,
                        'interval' => self::intervalLabel( (string) ( $result['range']['interval'] ?? 'day' ) ),
                        'from'     => (string) ( $result['range']['from'] ?? '' ),
                        'to'       => (string) ( $result['range']['to'] ?? '' ),
                    ] ),
                ];

                if ( isset( $result['previous']['series'] ) ) {
                    $chart['series'][] = [
                        'name' => __( ':metric, previous period', [ 'metric' => $label ] ),
                        'data' => array_map( $value, array_slice( (array) $result['previous']['series'], 0, count( $series ) ) ),
                    ];
                }

                return $chart;

            case 'top-products':
            case 'revenue-by-category':
                $rows = array_slice( (array) ( $result['rows'] ?? [] ), 0, 10 );

                return [
                    'type'       => 'bar',
                    'categories' => array_map( static fn ( array $row ): string => (string) $row['name'], $rows ),
                    'series'     => [ [ 'name' => __( 'Net revenue' ), 'data' => array_map( static fn ( array $row ): float => $major( (int) $row['net_revenue'] ), $rows ) ] ],
                    'money'      => true,
                    'summary'    => __( 'Net revenue for the top :count rows. The table below lists every value.', [ 'count' => count( $rows ) ] ),
                ];

            case 'tax':
                $rows = (array) ( $result['rows'] ?? [] );

                return [
                    'type'       => 'bar',
                    'categories' => array_map( static fn ( array $row ): string => trim( ( '' !== $row['jurisdiction'] ? $row['jurisdiction'] . ' · ' : '' ) . $row['label'] ), $rows ),
                    'series'     => [ [ 'name' => __( 'Tax collected' ), 'data' => array_map( static fn ( array $row ): float => $major( (int) $row['amount'] ), $rows ) ] ],
                    'money'      => true,
                    'summary'    => __( 'Tax collected by jurisdiction and rate. The table below lists every value.' ),
                ];

            case 'inventory':
                $rows = array_slice( array_values( array_filter( (array) ( $result['rows'] ?? [] ), static fn ( array $row ): bool => null !== $row['stock_value'] ) ), 0, 10 );

                return [] === $rows ? null : [
                    'type'       => 'bar',
                    'categories' => array_map( static fn ( array $row ): string => (string) $row['name'], $rows ),
                    'series'     => [ [ 'name' => __( 'Stock value at cost' ), 'data' => array_map( static fn ( array $row ): float => $major( (int) $row['stock_value'] ), $rows ) ] ],
                    'money'      => true,
                    'summary'    => __( 'Stock value at cost for the :count most valuable items. The table below lists every value.', [ 'count' => count( $rows ) ] ),
                ];

            case 'low-stock':
                $rows = array_slice( (array) ( $result['rows'] ?? [] ), 0, 10 );

                return [] === $rows ? null : [
                    'type'       => 'bar',
                    'categories' => array_map( static fn ( array $row ): string => (string) $row['name'], $rows ),
                    'series'     => [ [ 'name' => __( 'Units below threshold' ), 'data' => array_map( static fn ( array $row ): int => (int) $row['shortfall'], $rows ) ] ],
                    'money'      => false,
                    'summary'    => __( 'How far each item is below its low-stock threshold. The table below lists every value.' ),
                ];
        }

        return null;
    }

    /**
     * ApexCharts options for a chart. The previous period is dashed and both
     * series are named in the legend, so the chart does not rely on colour.
     *
     * @since 1.0.0
     *
     * @param  array{type: string, categories: array<int, string>, series: array<int, array<string, mixed>>, money: bool, summary: string}  $chart     Chart from {@see self::chart()}.
     * @param  string                                                                                                                     $currency  Base currency.
     *
     * @return array<string, mixed>
     */
    public static function chartOptions( array $chart, string $currency ): array
    {
        $dashed = count( $chart['series'] ) > 1;

        return [
            'chart'       => [ 'toolbar' => [ 'show' => false ], 'zoom' => [ 'enabled' => false ] ],
            // ApexCharts writes the tooltip title with innerHTML; the table
            // below the chart carries the names, so the title is hidden.
            'tooltip'     => [ 'x' => [ 'show' => false ] ],
            'xaxis'       => [ 'categories' => array_map( self::chartText( ... ), $chart['categories'] ) ],
            'dataLabels'  => [ 'enabled' => false ],
            'legend'      => [ 'show' => true, 'position' => 'bottom' ],
            'stroke'      => [ 'curve' => 'straight', 'width' => 2, 'dashArray' => $dashed ? [ 0, 6 ] : [ 0 ] ],
            'markers'     => [ 'size' => $dashed ? [ 3, 0 ] : 3 ],
            'yaxis'       => [ 'title' => [ 'text' => $chart['money'] ? $currency : __( 'Count' ) ] ],
            'plotOptions' => [ 'bar' => [ 'horizontal' => 'bar' === $chart['type'] ] ],
        ];
    }

    /**
     * Store data (product, category, rate names) as plain chart text.
     * ApexCharts renders labels as HTML, so tags are stripped and `<` / `>`
     * removed before anything reaches it.
     *
     * @since 1.0.0
     *
     * @param  string  $text  Raw text.
     *
     * @return string
     */
    public static function chartText( string $text ): string
    {
        return trim( str_replace( [ '<', '>' ], '', strip_tags( $text ) ) );
    }

    /**
     * The table as CSV rows: headers, then rows, then the totals row. Money
     * is written in major units.
     *
     * @since 1.0.0
     *
     * @param  array{columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<string, mixed>>, totals: array<string, mixed>|null}  $table     Table from {@see self::table()}.
     * @param  string                                                                                                                                              $currency  Base currency.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
     */
    public static function csv( array $table, string $currency ): array
    {
        $headers = array_map( static fn ( array $column ): string => 'money' === $column['type'] ? $column['label'] . ' (' . $currency . ')' : $column['label'], $table['columns'] );
        $lines   = [];

        foreach ( [ ...$table['rows'], ...( null === $table['totals'] ? [] : [ $table['totals'] ] ) ] as $row ) {
            $lines[] = array_map( static fn ( array $column ): string => self::plain( $row[ $column['key'] ] ?? null, $column['type'], $currency ), $table['columns'] );
        }

        return [ $headers, $lines ];
    }

    /**
     * A cell as plain text (CSV, and screen text for non-money cells).
     *
     * @since 1.0.0
     *
     * @param  mixed   $value     The value.
     * @param  string  $type      Column type.
     * @param  string  $currency  Base currency.
     *
     * @return string
     */
    public static function plain( mixed $value, string $type, string $currency ): string
    {
        if ( null === $value || '' === $value ) {
            return '';
        }

        return match ( $type ) {
            'money'   => MinorUnits::toMajor( (int) $value, $currency ),
            'percent' => rtrim( rtrim( number_format( (float) $value * 100, 2, '.', '' ), '0' ), '.' ) . '%',
            'rate'    => TaxRateMath::toPercent( (int) $value ) . '%',
            'int'     => (string) (int) $value,
            default   => (string) $value,
        };
    }

    /**
     * A period key as a short label in the app locale.
     *
     * @since 1.0.0
     *
     * @param  string  $period    `Y-m-d` or `Y-m`.
     * @param  string  $interval  `day`, `week`, or `month`.
     *
     * @return string
     */
    public static function periodLabel( string $period, string $interval ): string
    {
        try {
            $date = 'month' === $interval ? Carbon::createFromFormat( '!Y-m', $period ) : Carbon::createFromFormat( '!Y-m-d', $period );
        } catch ( Throwable ) {
            return $period;
        }

        if ( false === $date ) {
            return $period;
        }

        $date->locale( app()->getLocale() );

        return match ( $interval ) {
            'month' => $date->isoFormat( 'MMM YYYY' ),
            'week'  => __( 'Week of :date', [ 'date' => $date->isoFormat( 'll' ) ] ),
            default => $date->isoFormat( 'll' ),
        };
    }

    /**
     * An interval as a word.
     *
     * @since 1.0.0
     *
     * @param  string  $interval  `day`, `week`, or `month`.
     *
     * @return string
     */
    public static function intervalLabel( string $interval ): string
    {
        return match ( $interval ) {
            'week'  => __( 'week' ),
            'month' => __( 'month' ),
            default => __( 'day' ),
        };
    }

    /**
     * Sales: one row per bucket, totals in the footer.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $result  Result.
     *
     * @return array{columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<string, mixed>>, totals: array<string, mixed>|null}
     */
    private static function salesTable( array $result ): array
    {
        $labels   = self::salesMetricLabels();
        $interval = (string) ( $result['range']['interval'] ?? 'day' );
        $columns  = [ [ 'key' => 'period', 'label' => __( 'Period' ), 'type' => 'text' ] ];

        foreach ( [ 'orders', 'gross', 'discounts', 'refunds', 'net', 'tax', 'shipping', 'total', 'average_order_value' ] as $metric ) {
            $columns[] = [ 'key' => $metric, 'label' => $labels[ $metric ], 'type' => 'orders' === $metric ? 'int' : 'money' ];
        }

        return [
            'columns' => $columns,
            'rows'    => array_map( static fn ( array $bucket ): array => [ ...$bucket, 'period' => self::periodLabel( (string) $bucket['period'], $interval ) ], (array) ( $result['series'] ?? [] ) ),
            'totals'  => [ ...(array) ( $result['totals'] ?? [] ), 'period' => __( 'Total' ) ],
        ];
    }

    /**
     * Top products, each followed by its variants.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $result  Result.
     *
     * @return array{columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<string, mixed>>, totals: array<string, mixed>|null}
     */
    private static function topProductsTable( array $result ): array
    {
        $rows = [];

        foreach ( (array) ( $result['rows'] ?? [] ) as $index => $product ) {
            $rows[] = [ 'rank' => $index + 1, 'name' => $product['name'], 'sku' => $product['sku'], 'units' => $product['units'], 'net_revenue' => $product['net_revenue'], 'orders' => $product['orders'] ];

            foreach ( (array) ( $product['variants'] ?? [] ) as $variant ) {
                $rows[] = [ 'rank' => '', 'name' => $variant['name'], 'sku' => $variant['sku'], 'units' => $variant['units'], 'net_revenue' => $variant['net_revenue'], 'orders' => null, 'indent' => true ];
            }
        }

        return [
            'columns' => [
                [ 'key' => 'rank', 'label' => __( 'Rank' ), 'type' => 'text' ],
                [ 'key' => 'name', 'label' => __( 'Product' ), 'type' => 'text' ],
                [ 'key' => 'sku', 'label' => __( 'SKU' ), 'type' => 'text' ],
                [ 'key' => 'units', 'label' => __( 'Units sold' ), 'type' => 'int' ],
                [ 'key' => 'net_revenue', 'label' => __( 'Net revenue' ), 'type' => 'money' ],
                [ 'key' => 'orders', 'label' => __( 'Orders' ), 'type' => 'int' ],
            ],
            'rows'    => $rows,
            'totals'  => [ 'rank' => '', 'name' => __( 'All products' ), 'sku' => '', ...array_intersect_key( (array) ( $result['totals'] ?? [] ), array_flip( [ 'units', 'net_revenue', 'orders' ] ) ) ],
        ];
    }

    /**
     * Revenue by category.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $result  Result.
     *
     * @return array{columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<string, mixed>>, totals: array<string, mixed>|null}
     */
    private static function categoryTable( array $result ): array
    {
        return [
            'columns' => [
                [ 'key' => 'name', 'label' => __( 'Category' ), 'type' => 'text' ],
                [ 'key' => 'units', 'label' => __( 'Units sold' ), 'type' => 'int' ],
                [ 'key' => 'net_revenue', 'label' => __( 'Net revenue' ), 'type' => 'money' ],
                [ 'key' => 'share', 'label' => __( 'Share of revenue' ), 'type' => 'percent' ],
                [ 'key' => 'orders', 'label' => __( 'Orders' ), 'type' => 'int' ],
            ],
            'rows'    => array_values( (array) ( $result['rows'] ?? [] ) ),
            'totals'  => [ 'name' => __( 'All sales' ), 'share' => null, ...array_intersect_key( (array) ( $result['totals'] ?? [] ), array_flip( [ 'units', 'net_revenue', 'orders' ] ) ) ],
        ];
    }

    /**
     * Tax by jurisdiction and rate.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $result  Result.
     *
     * @return array{columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<string, mixed>>, totals: array<string, mixed>|null}
     */
    private static function taxTable( array $result ): array
    {
        return [
            'columns' => [
                [ 'key' => 'jurisdiction', 'label' => __( 'Jurisdiction' ), 'type' => 'text' ],
                [ 'key' => 'label', 'label' => __( 'Rate label' ), 'type' => 'text' ],
                [ 'key' => 'rate_ubps', 'label' => __( 'Rate' ), 'type' => 'rate' ],
                [ 'key' => 'orders', 'label' => __( 'Orders' ), 'type' => 'int' ],
                [ 'key' => 'amount', 'label' => __( 'Tax collected' ), 'type' => 'money' ],
            ],
            'rows'    => array_map( static fn ( array $row ): array => [ ...$row, 'jurisdiction' => '' === $row['jurisdiction'] ? __( 'Unknown' ) : $row['jurisdiction'] ], (array) ( $result['rows'] ?? [] ) ),
            'totals'  => [ 'jurisdiction' => __( 'All jurisdictions' ), 'label' => '', 'rate_ubps' => null, ...(array) ( $result['totals'] ?? [] ) ],
        ];
    }

    /**
     * Inventory levels.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $result  Result.
     *
     * @return array{columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<string, mixed>>, totals: array<string, mixed>|null}
     */
    private static function inventoryTable( array $result ): array
    {
        return [
            'columns' => [
                [ 'key' => 'name', 'label' => __( 'Item' ), 'type' => 'text' ],
                [ 'key' => 'sku', 'label' => __( 'SKU' ), 'type' => 'text' ],
                [ 'key' => 'on_hand', 'label' => __( 'On hand' ), 'type' => 'int' ],
                [ 'key' => 'reserved', 'label' => __( 'Reserved' ), 'type' => 'int' ],
                [ 'key' => 'available', 'label' => __( 'Available' ), 'type' => 'int' ],
                [ 'key' => 'unit_cost', 'label' => __( 'Unit cost' ), 'type' => 'money' ],
                [ 'key' => 'stock_value', 'label' => __( 'Stock value' ), 'type' => 'money' ],
            ],
            'rows'    => array_values( (array) ( $result['rows'] ?? [] ) ),
            'totals'  => [ 'name' => __( 'All tracked items' ), 'sku' => '', 'unit_cost' => null, ...array_intersect_key( (array) ( $result['totals'] ?? [] ), array_flip( [ 'on_hand', 'reserved', 'available', 'stock_value' ] ) ) ],
        ];
    }

    /**
     * Low stock, each row linking to the inventory screen to adjust it.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $result  Result.
     *
     * @return array{columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<string, mixed>>, totals: array<string, mixed>|null}
     */
    private static function lowStockTable( array $result ): array
    {
        $route = 'artisanpack.ecommerce.admin.inventory.index';

        return [
            'columns' => [
                [ 'key' => 'name', 'label' => __( 'Item' ), 'type' => 'text' ],
                [ 'key' => 'sku', 'label' => __( 'SKU' ), 'type' => 'text' ],
                [ 'key' => 'available', 'label' => __( 'Available' ), 'type' => 'int' ],
                [ 'key' => 'low_stock_threshold', 'label' => __( 'Threshold' ), 'type' => 'int' ],
                [ 'key' => 'shortfall', 'label' => __( 'Below threshold by' ), 'type' => 'int' ],
                [ 'key' => 'reserved', 'label' => __( 'Reserved' ), 'type' => 'int' ],
            ],
            'rows'    => array_map( static fn ( array $row ): array => [
                ...$row,
                'action' => Route::has( $route ) ? [
                    'label' => __( 'Adjust stock' ),
                    'aria'  => __( 'Adjust stock for :item', [ 'item' => $row['name'] ] ),
                    'url'   => route( $route, [ 'q' => (string) ( $row['sku'] ?? $row['name'] ) ] ),
                ] : null,
            ], (array) ( $result['rows'] ?? [] ) ),
            'totals'  => null,
        ];
    }

    /**
     * A satellite's report: its rows (or series) with their keys as headings.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $result  Result.
     *
     * @return array{columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<string, mixed>>, totals: array<string, mixed>|null}
     */
    private static function genericTable( array $result ): array
    {
        $rows  = array_values( array_filter( (array) ( $result['rows'] ?? $result['series'] ?? [] ), 'is_array' ) );
        $first = $rows[0] ?? [];
        $keys  = array_keys( array_filter( $first, static fn ( $value ): bool => is_scalar( $value ) || null === $value ) );

        return [
            'columns' => array_map( static fn ( string $key ): array => [ 'key' => $key, 'label' => ucfirst( str_replace( '_', ' ', $key ) ), 'type' => 'text' ], array_map( 'strval', $keys ) ),
            'rows'    => $rows,
            'totals'  => null,
        ];
    }
}
