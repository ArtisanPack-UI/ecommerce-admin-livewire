<?php

/**
 * Reports screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Reports;

use ArtisanPackUI\Ecommerce\Registries\ReportRegistry;
use ArtisanPackUI\Ecommerce\Reports\Report;
use ArtisanPackUI\Ecommerce\Reports\ReportRange;
use ArtisanPackUI\Ecommerce\Reports\ReportRunner;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Csv;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ReportPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One report on the shared report frame (spec §7.7).
 *
 * The frame gives every report the same controls and output:
 *
 * - a date range with presets (today, last 7 / 30 days, this and last
 *   month, quarter, year) or custom dates, a previous-period comparison,
 *   and a day / week / month interval — date controls are hidden for
 *   point-in-time reports such as inventory;
 * - headline numbers with the change against the previous period, written
 *   out in words so they do not rely on colour;
 * - a chart, labelled for screen readers, with the table below as its data
 *   equivalent;
 * - a table of every row, and CSV export of the same table;
 * - a shareable URL: the range and options live in the query string.
 *
 * Numbers come from the engine's report queries, in the store's base
 * currency. Orders placed under an earlier base currency are converted at
 * today's rate and flagged above the table (plan §16.4).
 *
 * Ability: `report.view`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Show extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;

    /**
     * Date presets.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const PRESETS = [ 'today', 'yesterday', 'last-7-days', 'last-30-days', 'this-month', 'last-month', 'this-quarter', 'last-quarter', 'this-year', 'last-year', 'custom' ];

    /**
     * The default preset.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const DEFAULT_PRESET = 'last-30-days';

    /**
     * The report key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $report = '';

    /**
     * The date preset, or `custom`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( except: self::DEFAULT_PRESET )]
    public string $preset = self::DEFAULT_PRESET;

    /**
     * First day (`Y-m-d`), for custom ranges.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( except: '' )]
    public string $from = '';

    /**
     * Last day (`Y-m-d`), for custom ranges.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( except: '' )]
    public string $to = '';

    /**
     * Bucket size.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( except: 'day' )]
    public string $interval = 'day';

    /**
     * Whether to compare with the previous period.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Url( except: false )]
    public bool $compare = false;

    /**
     * The sales metric the chart plots.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( except: 'net' )]
    public string $metric = 'net';

    /**
     * Report sort option (top products, inventory).
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( except: '' )]
    public string $sort = '';

    /**
     * Report row limit (0 = the report's default).
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Url( except: 0 )]
    public int $limit = 0;

    /**
     * Bumped on every render, so the report's live region is read again when
     * an option changes but the range does not.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $revision = 0;

    /**
     * Authorizes the screen and resolves the report.
     *
     * @since 1.0.0
     *
     * @param  string  $report  Report key.
     *
     * @return void
     */
    public function mount( string $report ): void
    {
        $this->authorizeEcommerce( 'view', Report::class );

        abort_unless( app( ReportRegistry::class )->has( $report ), 404 );

        $this->report = $report;

        if ( ! in_array( $this->preset, self::PRESETS, true ) ) {
            $this->preset = self::DEFAULT_PRESET;
        }

        if ( 'custom' !== $this->preset ) {
            $this->applyPreset();
        }
    }

    /**
     * Re-checks the screen ability on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeEcommerce( 'view', Report::class );
    }

    /**
     * Fills the dates from the chosen preset.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedPreset(): void
    {
        if ( ! in_array( $this->preset, self::PRESETS, true ) ) {
            $this->preset = self::DEFAULT_PRESET;
        }

        if ( 'custom' !== $this->preset ) {
            $this->applyPreset();
        }
    }

    /**
     * Editing a date switches to a custom range.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedFrom(): void
    {
        $this->preset = 'custom';
    }

    /**
     * Editing a date switches to a custom range.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedTo(): void
    {
        $this->preset = 'custom';
    }

    /**
     * Downloads the table as CSV.
     *
     * @since 1.0.0
     *
     * @return StreamedResponse|null
     */
    public function export(): ?StreamedResponse
    {
        $this->authorizeEcommerce( 'view', Report::class );

        $result = $this->run();

        if ( null === $result ) {
            return null;
        }

        [ $headers, $rows ] = ReportPresenter::csv( ReportPresenter::table( $this->report, $result ), (string) $result['currency'] );

        $csv  = Csv::build( $headers, $rows );
        $name = sprintf(
            '%s-%s.csv',
            $this->report,
            null === $result['range'] ? Carbon::now()->format( 'Y-m-d' ) : $result['range']['from'] . '-to-' . $result['range']['to'],
        );

        return response()->streamDownload( static function () use ( $csv ): void {
            echo $csv;
        }, $name, [ 'Content-Type' => 'text/csv; charset=UTF-8' ] );
    }

    /**
     * Renders the report.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $this->revision++;

        $registry = app( ReportRegistry::class );
        $ranged   = $registry->get( $this->report )->ranged();
        $result   = $this->run();
        $chart    = null === $result ? null : ReportPresenter::chart( $this->report, $result, $this->metric );

        return view( 'ecommerce-admin::livewire.reports.show', [
            'reports'      => app( ReportRunner::class )->available(),
            'label'        => (string) ( $registry->meta( $this->report )['label'] ?? $this->report ),
            'ranged'       => $ranged,
            'result'       => $result,
            'table'        => null === $result ? null : ReportPresenter::table( $this->report, $result ),
            'kpis'         => null === $result ? [] : ReportPresenter::kpis( $this->report, $result ),
            'chart'        => $chart,
            'chartOptions' => null === $chart ? [] : ReportPresenter::chartOptions( $chart, (string) $result['currency'] ),
            'presets'      => self::presetLabels(),
            'metrics'      => ReportPresenter::salesMetricLabels(),
            'sorts'        => $this->sortOptions(),
            'limits'       => $this->limitOptions(),
        ] );
    }

    /**
     * Preset labels.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public static function presetLabels(): array
    {
        return [
            'today'        => __( 'Today' ),
            'yesterday'    => __( 'Yesterday' ),
            'last-7-days'  => __( 'Last 7 days' ),
            'last-30-days' => __( 'Last 30 days' ),
            'this-month'   => __( 'This month' ),
            'last-month'   => __( 'Last month' ),
            'this-quarter' => __( 'This quarter' ),
            'last-quarter' => __( 'Last quarter' ),
            'this-year'    => __( 'This year' ),
            'last-year'    => __( 'Last year' ),
            'custom'       => __( 'Custom range' ),
        ];
    }

    /**
     * The first and last day of a preset, in the store time zone.
     *
     * @since 1.0.0
     *
     * @param  string                $preset  Preset key.
     * @param  CarbonImmutable|null  $today   Today (store time zone).
     *
     * @return array{0: string, 1: string}
     */
    public static function presetRange( string $preset, ?CarbonImmutable $today = null ): array
    {
        $today ??= CarbonImmutable::now( ReportRange::timezone() );

        [ $from, $to ] = match ( $preset ) {
            'today'        => [ $today, $today ],
            'yesterday'    => [ $today->subDay(), $today->subDay() ],
            'last-7-days'  => [ $today->subDays( 6 ), $today ],
            'this-month'   => [ $today->startOfMonth(), $today ],
            'last-month'   => [ $today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth() ],
            'this-quarter' => [ $today->startOfQuarter(), $today ],
            'last-quarter' => [ $today->subQuarterNoOverflow()->startOfQuarter(), $today->subQuarterNoOverflow()->endOfQuarter() ],
            'this-year'    => [ $today->startOfYear(), $today ],
            'last-year'    => [ $today->subYearNoOverflow()->startOfYear(), $today->subYearNoOverflow()->endOfYear() ],
            default        => [ $today->subDays( ReportRange::DEFAULT_DAYS - 1 ), $today ],
        };

        return [ $from->format( 'Y-m-d' ), $to->format( 'Y-m-d' ) ];
    }

    /**
     * Runs the report, or records why it cannot.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>|null
     */
    protected function run(): ?array
    {
        $this->resetErrorBag();

        $report = app( ReportRegistry::class )->get( $this->report );
        $range  = null;

        if ( $report->ranged() ) {
            $validator = Validator::make(
                [ 'from' => $this->from, 'to' => $this->to, 'interval' => $this->interval ],
                [
                    'from'     => [ 'required', 'date_format:Y-m-d' ],
                    'to'       => [ 'required', 'date_format:Y-m-d', 'after_or_equal:from' ],
                    'interval' => [ 'required', 'in:' . implode( ',', ReportRange::INTERVALS ) ],
                ],
                [ 'to.after_or_equal' => __( 'The end date must be on or after the start date.' ) ],
                [ 'from' => __( 'start date' ), 'to' => __( 'end date' ), 'interval' => __( 'interval' ) ],
            );

            if ( $validator->fails() ) {
                $this->setErrorBag( $validator->errors() );

                return null;
            }

            try {
                $range = ReportRange::make( $this->from, $this->to, $this->interval, $this->compare );
            } catch ( InvalidArgumentException ) {
                $this->addError( 'from', __( 'Reports cover at most :days days. Choose a shorter range.', [ 'days' => ReportRange::MAX_DAYS ] ) );

                return null;
            }
        }

        $options = Validator::make(
            array_filter( [ 'sort' => '' === $this->sort ? null : $this->sort, 'limit' => 0 === $this->limit ? null : $this->limit ], static fn ( $value ): bool => null !== $value ),
            $report->optionRules(),
        );

        if ( $options->fails() ) {
            $this->sort  = '';
            $this->limit = 0;

            return app( ReportRunner::class )->run( $this->report, $range );
        }

        return app( ReportRunner::class )->run( $this->report, $range, array_intersect_key( $options->validated(), $report->optionRules() ) );
    }

    /**
     * Sets the dates from the preset.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function applyPreset(): void
    {
        [ $this->from, $this->to ] = self::presetRange( $this->preset );

        if ( in_array( $this->preset, [ 'this-year', 'last-year' ], true ) && 'day' === $this->interval ) {
            $this->interval = 'month';
        }
    }

    /**
     * Sort choices for the report, if it has any.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function sortOptions(): array
    {
        return match ( $this->report ) {
            'top-products' => [ 'net_revenue' => __( 'Net revenue' ), 'units' => __( 'Units sold' ) ],
            'inventory'    => [ 'stock_value' => __( 'Stock value' ), 'available' => __( 'Available' ), 'on_hand' => __( 'On hand' ), 'reserved' => __( 'Reserved' ), 'name' => __( 'Name' ) ],
            default        => [],
        };
    }

    /**
     * Row-limit choices for the report, if it has any.
     *
     * @since 1.0.0
     *
     * @return array<int, int>
     */
    protected function limitOptions(): array
    {
        return match ( $this->report ) {
            'top-products'           => [ 10, 25, 50, 100 ],
            'inventory', 'low-stock' => [ 100, 250, 500, 1000 ],
            default                  => [],
        };
    }
}
