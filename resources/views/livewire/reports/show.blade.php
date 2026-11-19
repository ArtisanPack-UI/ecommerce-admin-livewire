{{--
    Report (#141). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Reports\Show.

    One page for every report: a link tab per report, then the options
    (range, interval, chart metric, sort and rows, the comparison toggle),
    the key figures, a chart and a table with totals. Export gives the same
    rows as CSV.

    - KPI changes pair an arrow with the words; the arrow is green when the
      news is good (refunds going down is good) and red when it's bad.
    - The chart over time is drawn from the design system's chart geometry
      in a Card with an h2 title (x-artisanpack-chart titles are h3). One
      series in chart colour 1; the previous period is dashed in the axis
      colour. Ranked reports are a bar list instead. Every chart is named and
      captioned by its text summary, and the plot stays left to right in RTL.
    - Discounts and refunds are muted in the table, with a minus sign.
    - An invalid range replaces the results with the reason and disables
      Export; an empty range shows the empty state with a longer range.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ReportPresenter;
    use Illuminate\Support\Number;

    $numeric = [ 'int', 'money', 'percent', 'rate' ];
    $display = static function ( mixed $value, string $type ) use ( $currency ): string {
        if ( null === $value || '' === $value ) {
            return '—';
        }

        return match ( $type ) {
            'money' => MoneyFormatter::format( (int) $value, $currency ),
            'int'   => Number::format( (int) $value, locale: app()->getLocale() ),
            default => ReportPresenter::plain( $value, $type, $currency ),
        };
    };
    $cell = static function ( mixed $value, array $column ) use ( $display ): string {
        $text = $display( $value, $column['type'] );

        return ( $column['deduction'] ?? false ) && is_numeric( $value ) && $value > 0 ? '−' . $text : $text;
    };
    $invalid   = null === $result;
    $messages  = $errors->all();
    $dateError = $errors->hasAny( [ 'from', 'to' ] );
    $compared  = null !== $plot && count( $chart['series'] ) > 1;
@endphp
<div class="ec-stack ec-report" data-report="{{ $report }}">
    <x-artisanpack-page-header :title="__( 'Reports' )" :description="__( 'Amounts are in :currency, the store\'s base currency.', [ 'currency' => $currency ] )" :level="1">
        <x-slot:actions>
            <x-artisanpack-button
                variant="outline"
                color="neutral"
                size="sm"
                leading-icon="download"
                wire:click="export"
                wire:loading.attr="disabled"
                :disabled="$invalid"
                data-export
            >{{ __( 'Export CSV' ) }}</x-artisanpack-button>
        </x-slot:actions>
    </x-artisanpack-page-header>

    <nav class="apui-tabs ec-link-tabs" data-variant="underline" aria-label="{{ __( 'Reports' ) }}" data-report-tabs>
        <div data-scope="tabs" data-part="list">
            @foreach ( $reports as $available )
                @continue( 'summary' === $available['key'] )
                <a
                    wire:key="report-tab-{{ $available['key'] }}"
                    data-scope="tabs"
                    data-part="trigger"
                    href="{{ route( 'artisanpack.ecommerce.admin.reports.show', [ 'report' => $available['key'] ] ) }}"
                    @if ( $available['key'] === $report ) aria-current="page" @endif
                >{{ $available['label'] }}</a>
            @endforeach
        </div>
    </nav>

    <form class="ec-report-controls" wire:submit.prevent aria-label="{{ __( 'Report options' ) }}" data-report-controls>
        @if ( $ranged )
            <div class="ec-report-control" data-width="md">
                <x-artisanpack-select
                    placeholder=""
                    id="report-preset"
                    size="sm"
                    :label="__( 'Date range' )"
                    :options="ControlOptions::from( collect( $presets )->map( fn ( $presetLabel, $key ) => [ 'id' => $key, 'name' => $presetLabel ] )->values()->all() )"
                    wire:model.live="preset"
                />
            </div>
            @if ( 'custom' === $preset || $dateError )
                <div class="ec-report-control" data-width="date">
                    <x-artisanpack-input id="report-from" type="date" size="sm" :label="__( 'From' )" error-field="report-range" :invalid="$dateError" :aria-describedby="$dateError ? 'report-invalid' : null" wire:model.live.blur="from" />
                </div>
                <div class="ec-report-control" data-width="date">
                    <x-artisanpack-input id="report-to" type="date" size="sm" :label="__( 'To' )" error-field="report-range" :invalid="$dateError" :aria-describedby="$dateError ? 'report-invalid' : null" wire:model.live.blur="to" />
                </div>
            @endif
            <div class="ec-report-control" data-width="sm">
                <x-artisanpack-select
                    placeholder=""
                    id="report-interval"
                    size="sm"
                    :label="__( 'Interval' )"
                    :options="ControlOptions::from( [ [ 'id' => 'day', 'name' => __( 'Day' ) ], [ 'id' => 'week', 'name' => __( 'Week' ) ], [ 'id' => 'month', 'name' => __( 'Month' ) ] ] )"
                    wire:model.live="interval"
                />
            </div>
        @endif

        @if ( 'sales' === $report )
            <div class="ec-report-control" data-width="md">
                <x-artisanpack-select
                    placeholder=""
                    id="report-metric"
                    size="sm"
                    :label="__( 'Chart' )"
                    :options="ControlOptions::from( collect( $metrics )->map( fn ( $metricLabel, $key ) => [ 'id' => $key, 'name' => $metricLabel ] )->values()->all() )"
                    wire:model.live="metric"
                />
            </div>
        @endif

        @if ( [] !== $sorts )
            <div class="ec-report-control" data-width="md">
                <x-artisanpack-select
                    id="report-sort"
                    size="sm"
                    :label="__( 'Sort by' )"
                    :options="ControlOptions::from( collect( $sorts )->map( fn ( $sortLabel, $key ) => [ 'id' => $key, 'name' => $sortLabel ] )->values()->all() )"
                    :placeholder="__( 'Default' )"
                    wire:model.live="sort"
                />
            </div>
        @endif

        @if ( [] !== $limits )
            <div class="ec-report-control" data-width="xs">
                <x-artisanpack-select
                    placeholder=""
                    id="report-limit"
                    size="sm"
                    :label="__( 'Rows' )"
                    :options="ControlOptions::from( [ [ 'id' => 0, 'name' => __( 'Default' ) ], ...collect( $limits )->map( fn ( $count ) => [ 'id' => $count, 'name' => Number::format( $count, locale: app()->getLocale() ) ] )->all() ] )"
                    wire:model.live="limit"
                />
            </div>
        @endif

        @if ( $ranged )
            <div class="ec-report-toggle">
                <x-artisanpack-toggle id="report-compare" size="sm" :label="__( 'Compare with the previous period' )" wire:model.live="compare" />
            </div>
        @endif
    </form>

    @if ( $invalid )
        <x-artisanpack-alert
            id="report-invalid"
            color="warning"
            icon="triangle-alert"
            role="alert"
            :title="$messages[0] ?? __( 'This report could not be run.' )"
            :description="count( $messages ) > 1 ? implode( ' ', array_slice( $messages, 1 ) ) : null"
            data-report-invalid
        />
    @else
        @include( 'ecommerce-admin::partials.live-region', [ 'message' => null === $result['range'] ? __( ':report updated.', [ 'report' => $label ] ) : __( ':report updated: :from to :to.', [ 'report' => $label, 'from' => $result['range']['from'], 'to' => $result['range']['to'] ] ), 'revision' => $revision ] )

        <div class="ec-stack ec-report-results" wire:loading.class="ec-report-results--busy" aria-busy="false" wire:loading.attr="aria-busy">
            @if ( ( $result['notices']['converted_orders'] ?? 0 ) > 0 )
                <x-artisanpack-alert
                    color="info"
                    icon="info"
                    role="status"
                    :title="trans_choice(
                        ':count order was placed under a different base currency. It is converted to :currency at today\'s exchange rate, so its amount can change.|:count orders were placed under a different base currency. They are converted to :currency at today\'s exchange rate, so their amounts can change.',
                        (int) $result['notices']['converted_orders'],
                        [ 'count' => (int) $result['notices']['converted_orders'], 'currency' => $currency ],
                    )"
                    data-converted-orders="{{ $result['notices']['converted_orders'] }}"
                />
            @endif

            @if ( ( $result['notices']['unconverted_orders'] ?? 0 ) > 0 )
                <x-artisanpack-alert
                    color="warning"
                    icon="triangle-alert"
                    role="status"
                    :title="trans_choice(
                        ':count order is left out because there is no exchange rate from its base currency to :currency.|:count orders are left out because there is no exchange rate from their base currency to :currency.',
                        (int) $result['notices']['unconverted_orders'],
                        [ 'count' => (int) $result['notices']['unconverted_orders'], 'currency' => $currency ],
                    )"
                    data-unconverted-orders="{{ $result['notices']['unconverted_orders'] }}"
                />
            @endif

            @if ( $empty )
                <div class="apui-card" data-report-empty>
                    <x-artisanpack-ec-admin-empty-state
                        icon="chart-column"
                        :heading-level="2"
                        :title="$ranged ? __( 'Nothing to report for this period.' ) : __( 'Nothing to report.' )"
                        :description="$ranged && $longer ? __( 'Try a longer date range.' ) : null"
                    >
                        @if ( $ranged && $longer )
                            <x-artisanpack-button variant="outline" color="neutral" wire:click="$set( 'preset', 'last-90-days' )" data-report-longer>{{ __( 'Last 90 days' ) }}</x-artisanpack-button>
                        @endif
                    </x-artisanpack-ec-admin-empty-state>
                </div>
            @else
                @if ( [] !== $kpis )
                    <ul class="ec-kpis" data-kpis>
                        @foreach ( $kpis as $kpi )
                            @php
                                $words = null;
                                $icon  = null;

                                if ( null !== $kpi['previous'] ) {
                                    $previous = $display( $kpi['previous'], $kpi['type'] );
                                    $change   = Number::format( abs( (float) $kpi['change'] ), maxPrecision: 1, locale: app()->getLocale() );
                                    $words    = match ( true ) {
                                        null === $kpi['change'] => __( 'Previous period: :value', [ 'value' => $previous ] ),
                                        $kpi['change'] > 0      => __( 'Up :change% from :value', [ 'change' => $change, 'value' => $previous ] ),
                                        $kpi['change'] < 0      => __( 'Down :change% from :value', [ 'change' => $change, 'value' => $previous ] ),
                                        default                 => __( 'Same as the previous period (:value)', [ 'value' => $previous ] ),
                                    };
                                    $icon     = match ( true ) {
                                        null === $kpi['change'] => null,
                                        $kpi['change'] > 0      => 'arrow-up-right',
                                        $kpi['change'] < 0      => 'arrow-down-right',
                                        default                 => 'minus',
                                    };
                                }
                            @endphp
                            <li class="apui-stat" data-size="md" data-color="neutral" wire:key="kpi-{{ $kpi['key'] }}" data-kpi="{{ $kpi['key'] }}">
                                <div data-scope="stat" data-part="top"><span data-scope="stat" data-part="label">{{ $kpi['label'] }}</span></div>
                                <span data-scope="stat" data-part="value"><bdi dir="ltr">{{ $display( $kpi['value'], $kpi['type'] ) }}</bdi></span>
                                @if ( null !== $words )
                                    <span data-scope="stat" data-part="foot">
                                        <span data-scope="stat" data-part="description" class="ec-report-change" data-kpi-change>
                                            @if ( null !== $icon )
                                                <span class="ec-report-trend" data-tone="{{ $kpi['tone'] }}" aria-hidden="true"><x-artisanpack-icon :name="$icon" size="sm" /></span>
                                            @endif
                                            <bdi>{{ $words }}</bdi>
                                        </span>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ( null !== $plot )
                    <section class="apui-card apui-chart ec-report-chart" data-type="area" x-data="apuiChart" aria-labelledby="report-chart-title" data-chart wire:key="report-chart-{{ $report }}-{{ md5( json_encode( [ $chart['series'], $chart['categories'] ] ) ) }}">
                        <div data-scope="chart" data-part="header">
                            <div>
                                <h2 id="report-chart-title" data-scope="chart" data-part="title">{{ $chart['title'] }}</h2>
                            </div>
                            @if ( $compared )
                                <ul data-scope="chart" data-part="legend" data-chart-legend>
                                    <li><span data-scope="chart" data-part="swatch" data-series="1" aria-hidden="true"></span>{{ __( 'This period' ) }}</li>
                                    <li><span data-scope="chart" data-part="swatch" data-series="2" aria-hidden="true"></span>{{ __( 'Previous period' ) }}</li>
                                </ul>
                            @endif
                        </div>
                        <figure class="ec-report-figure">
                            <div data-scope="chart" data-part="body" dir="ltr">
                                <svg data-scope="chart" data-part="plot" viewBox="0 0 {{ $plot['width'] }} {{ $plot['height'] }}" width="{{ $plot['width'] }}" height="{{ $plot['height'] }}" role="group" aria-label="{{ $chart['summary'] }}">
                                    <g aria-hidden="true">
                                        @foreach ( $plot['ticks'] as $tick )
                                            <line data-scope="chart" data-part="grid" x1="{{ $plot['left'] }}" x2="{{ $plot['right'] }}" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}" />
                                            <text data-scope="chart" data-part="axis" x="{{ $plot['tickX'] }}" y="{{ $tick['textY'] }}" text-anchor="end">{{ $tick['label'] }}</text>
                                        @endforeach
                                        @foreach ( $plot['categories'] as $category )
                                            <text data-scope="chart" data-part="axis" x="{{ $category['x'] }}" y="{{ $plot['labelY'] }}" text-anchor="middle">{{ $category['label'] }}</text>
                                        @endforeach
                                    </g>
                                    <g aria-hidden="true">
                                        @foreach ( $plot['areas'] as $area )
                                            <path data-scope="chart" data-part="area" data-series="{{ $area['series'] }}" d="{{ $area['d'] }}" />
                                        @endforeach
                                        @foreach ( $plot['lines'] as $line )
                                            <path data-scope="chart" data-part="line" data-series="{{ $line['series'] }}" d="{{ $line['d'] }}" />
                                        @endforeach
                                    </g>
                                    <g aria-hidden="true">
                                        @foreach ( $plot['points'] as $point )
                                            <g data-scope="chart" data-part="point" data-index="{{ $point['index'] }}" visibility="hidden">
                                                <line data-scope="chart" data-part="crosshair" x1="{{ $point['x'] }}" x2="{{ $point['x'] }}" y1="{{ $point['top'] }}" y2="{{ $point['bottom'] }}" />
                                                @foreach ( $point['dots'] as $dot )
                                                    <circle data-scope="chart" data-part="dot" data-series="{{ $dot['series'] }}" cx="{{ $dot['cx'] }}" cy="{{ $dot['cy'] }}" r="5" />
                                                @endforeach
                                            </g>
                                        @endforeach
                                    </g>
                                    @foreach ( $plot['marks'] as $mark )
                                        <rect data-scope="chart" data-part="mark" data-series="{{ $mark['series'] }}" data-index="{{ $mark['index'] }}" x="{{ $mark['x'] }}" y="{{ $mark['y'] }}" width="{{ $mark['width'] }}" height="{{ $mark['height'] }}" tabindex="0" role="img" aria-label="{{ $mark['label'] }}" data-tooltip-title="{{ $mark['title'] }}" data-tooltip-body="{{ $mark['body'] }}" data-anchor-x="{{ $mark['anchorX'] }}" data-anchor-y="{{ $mark['anchorY'] }}" />
                                    @endforeach
                                </svg>
                                <div data-scope="chart" data-part="tooltip" aria-hidden="true" hidden></div>
                            </div>
                            <figcaption class="ec-muted" data-chart-summary>{{ $chart['summary'] }}</figcaption>
                        </figure>
                    </section>
                @elseif ( [] !== $bars )
                    <section class="apui-card" aria-labelledby="report-chart-title" data-chart>
                        <div data-scope="card" data-part="header">
                            <div>
                                <h2 id="report-chart-title" data-scope="card" data-part="title">{{ $chart['title'] }}</h2>
                            </div>
                        </div>
                        <div data-scope="card" data-part="body">
                            <figure class="ec-report-figure">
                                <ul class="ec-report-bars" aria-label="{{ $chart['summary'] }}">
                                    @foreach ( $bars as $index => $bar )
                                        <li class="ec-report-bar" wire:key="report-bar-{{ $index }}">
                                            <span class="ec-report-bar__name">{{ $bar['name'] }}</span>
                                            <span class="ec-report-bar__track" dir="ltr" aria-hidden="true"><span class="ec-report-bar__fill" style="inline-size: {{ $bar['percent'] }}%"></span></span>
                                            <bdi dir="ltr" class="ec-report-bar__value">{{ $bar['value'] }}</bdi>
                                        </li>
                                    @endforeach
                                </ul>
                                <figcaption class="ec-muted" data-chart-summary>{{ $chart['summary'] }}</figcaption>
                            </figure>
                        </div>
                    </section>
                @endif

                @if ( null !== $table )
                    <section class="apui-card ec-report-table" aria-labelledby="report-table-title" data-report-table>
                        <div data-scope="card" data-part="header">
                            <div>
                                <h2 id="report-table-title" data-scope="card" data-part="title">{{ $label }}</h2>
                            </div>
                        </div>
                        <div data-scope="card" data-part="body" class="ec-report-table__body">
                            <div class="apui-table ec-table">
                                <div data-scope="table" data-part="scroll">
                                    <table data-scope="table" data-part="table">
                                        <caption class="apui-sr-only">{{ $label }}</caption>
                                        <thead data-scope="table" data-part="head">
                                            <tr data-scope="table" data-part="row">
                                                @foreach ( $table['columns'] as $column )
                                                    <th data-scope="table" data-part="head-cell" scope="col" @if ( in_array( $column['type'], $numeric, true ) ) data-numeric @endif>{{ $column['label'] }}</th>
                                                @endforeach
                                                @if ( 'low-stock' === $report )
                                                    <th data-scope="table" data-part="head-cell" scope="col"><span class="apui-sr-only">{{ __( 'Actions' ) }}</span></th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ( $table['rows'] as $index => $row )
                                                <tr data-scope="table" data-part="row" wire:key="report-row-{{ $index }}" data-report-row @if ( $row['indent'] ?? false ) data-indent @endif>
                                                    @foreach ( $table['columns'] as $columnIndex => $column )
                                                        @php $text = $cell( $row[ $column['key'] ] ?? null, $column ); @endphp
                                                        @if ( 0 === $columnIndex && 'top-products' !== $report )
                                                            <th data-scope="table" data-part="cell" scope="row">{{ $text }}</th>
                                                        @elseif ( 1 === $columnIndex && 'top-products' === $report )
                                                            <th data-scope="table" data-part="cell" scope="row">
                                                                @if ( $row['indent'] ?? false )
                                                                    <span data-scope="table" data-part="secondary"><span class="apui-sr-only">{{ __( 'Variant:' ) }} </span>{{ $text }}</span>
                                                                @else
                                                                    <span data-scope="table" data-part="primary">{{ $text }}</span>
                                                                @endif
                                                            </th>
                                                        @elseif ( 0 === $columnIndex )
                                                            <td data-scope="table" data-part="cell" class="ec-cell-muted"><bdi dir="ltr">{{ '—' === $text ? '' : $text }}</bdi></td>
                                                        @elseif ( in_array( $column['type'], $numeric, true ) )
                                                            <td data-scope="table" data-part="cell" data-numeric><bdi dir="ltr" class="ec-money" @if ( $column['deduction'] ?? false ) data-tone="muted" @endif>{{ $text }}</bdi></td>
                                                        @elseif ( 'sku' === $column['key'] )
                                                            <td data-scope="table" data-part="cell"><bdi dir="ltr" class="ec-mono">{{ $text }}</bdi></td>
                                                        @else
                                                            <td data-scope="table" data-part="cell">{{ $text }}</td>
                                                        @endif
                                                    @endforeach
                                                    @if ( 'low-stock' === $report )
                                                        <td data-scope="table" data-part="cell">
                                                            @if ( null !== ( $row['action'] ?? null ) )
                                                                <x-artisanpack-link :href="$row['action']['url']" :navigate="false" standalone :aria-label="$row['action']['aria']">{{ $row['action']['label'] }}</x-artisanpack-link>
                                                            @endif
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        @if ( null !== $table['totals'] )
                                            <tfoot>
                                                <tr data-scope="table" data-part="row" data-report-totals>
                                                    @foreach ( $table['columns'] as $columnIndex => $column )
                                                        @php $text = $cell( $table['totals'][ $column['key'] ] ?? null, $column ); @endphp
                                                        @if ( 0 === $columnIndex )
                                                            <th data-scope="table" data-part="cell" scope="row">@if ( '—' === $text ){{ __( 'Totals' ) }}@else{{ $text }}@endif</th>
                                                        @elseif ( '—' === $text )
                                                            <td data-scope="table" data-part="cell"></td>
                                                        @else
                                                            <td data-scope="table" data-part="cell" @if ( in_array( $column['type'], $numeric, true ) ) data-numeric @endif><bdi dir="ltr" class="ec-money">{{ $text }}</bdi></td>
                                                        @endif
                                                    @endforeach
                                                    @if ( 'low-stock' === $report )
                                                        <td data-scope="table" data-part="cell"></td>
                                                    @endif
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>
                @endif
            @endif
        </div>
    @endif
</div>
