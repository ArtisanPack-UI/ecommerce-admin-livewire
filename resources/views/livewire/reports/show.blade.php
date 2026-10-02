{{--
    A report. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Reports\Show.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ReportPresenter;

    $currency = (string) ( $result['currency'] ?? config( 'artisanpack.ecommerce.base_currency', 'USD' ) );
    $display  = static function ( mixed $value, string $type ) use ( $currency ): string {
        if ( null === $value || '' === $value ) {
            return '—';
        }

        return match ( $type ) {
            'money' => MoneyFormatter::format( (int) $value, $currency ),
            'int'   => \Illuminate\Support\Number::format( (int) $value, locale: app()->getLocale() ),
            default => ReportPresenter::plain( $value, $type, $currency ),
        };
    };
@endphp
<div class="flex flex-col gap-6" data-report="{{ $report }}">
    <x-artisanpack-header :title="$label" :subtitle="__( 'Amounts are in :currency, the store\'s base currency.', [ 'currency' => $currency ] )" :level="1" separator>
        <x-slot:actions>
            <x-artisanpack-button
                icon="o-arrow-down-tray"
                variant="outline"
                wire:click="export"
                wire:loading.attr="disabled"
                :disabled="null === $result"
                :label="__( 'Export CSV' )"
                data-export
            />
        </x-slot:actions>
    </x-artisanpack-header>

    <nav aria-label="{{ __( 'Reports' ) }}">
        <x-artisanpack-menu class="menu-horizontal flex-wrap gap-1 rounded-box bg-base-200 p-1">
            @foreach ( $reports as $available )
                @continue( 'summary' === $available['key'] )
                <x-artisanpack-menu-item
                    wire:key="report-tab-{{ $available['key'] }}"
                    :title="$available['label']"
                    :link="route( 'artisanpack.ecommerce.admin.reports.show', [ 'report' => $available['key'] ] )"
                    :active="$available['key'] === $report"
                    :aria-current="$available['key'] === $report ? 'page' : null"
                    role="link"
                    no-wire-navigate
                />
            @endforeach
        </x-artisanpack-menu>
    </nav>

    <form class="flex flex-wrap items-end gap-3" wire:submit.prevent aria-label="{{ __( 'Report options' ) }}" data-report-controls>
        @if ( $ranged )
            <x-artisanpack-select
                id="report-preset"
                :label="__( 'Date range' )"
                :options="collect( $presets )->map( fn ( $presetLabel, $key ) => [ 'id' => $key, 'name' => $presetLabel ] )->values()->all()"
                wire:model.live="preset"
            />
            <x-artisanpack-input id="report-from" type="date" :label="__( 'From' )" wire:model.live.blur="from" />
            <x-artisanpack-input id="report-to" type="date" :label="__( 'To' )" wire:model.live.blur="to" />
            <x-artisanpack-select
                id="report-interval"
                :label="__( 'Interval' )"
                :options="[ [ 'id' => 'day', 'name' => __( 'Day' ) ], [ 'id' => 'week', 'name' => __( 'Week' ) ], [ 'id' => 'month', 'name' => __( 'Month' ) ] ]"
                wire:model.live="interval"
            />
            <div class="pb-2">
                <x-artisanpack-toggle id="report-compare" :label="__( 'Compare with the previous period' )" wire:model.live="compare" />
            </div>
        @endif

        @if ( 'sales' === $report )
            <x-artisanpack-select
                id="report-metric"
                :label="__( 'Chart' )"
                :options="collect( $metrics )->map( fn ( $metricLabel, $key ) => [ 'id' => $key, 'name' => $metricLabel ] )->values()->all()"
                wire:model.live="metric"
            />
        @endif

        @if ( [] !== $sorts )
            <x-artisanpack-select
                id="report-sort"
                :label="__( 'Sort by' )"
                :options="collect( $sorts )->map( fn ( $sortLabel, $key ) => [ 'id' => $key, 'name' => $sortLabel ] )->values()->all()"
                :placeholder="__( 'Default' )"
                placeholder-value=""
                wire:model.live="sort"
            />
        @endif

        @if ( [] !== $limits )
            <x-artisanpack-select
                id="report-limit"
                :label="__( 'Rows' )"
                :options="collect( $limits )->map( fn ( $count ) => [ 'id' => $count, 'name' => (string) $count ] )->all()"
                :placeholder="__( 'Default' )"
                placeholder-value="0"
                wire:model.live="limit"
            />
        @endif
    </form>

    @if ( null === $result )
        <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" role="alert" data-report-invalid>
            @foreach ( $errors->all() as $message )
                <p>{{ $message }}</p>
            @endforeach
        </x-artisanpack-alert>
    @else
        @include( 'ecommerce-admin::partials.live-region', [ 'message' => null === $result['range'] ? __( ':report updated.', [ 'report' => $label ] ) : __( ':report updated: :from to :to.', [ 'report' => $label, 'from' => $result['range']['from'], 'to' => $result['range']['to'] ] ), 'revision' => $revision ] )

        <div wire:loading.class="opacity-50" class="flex flex-col gap-6" aria-busy="false" wire:loading.attr="aria-busy">
            @if ( ( $result['notices']['converted_orders'] ?? 0 ) > 0 )
                <x-artisanpack-alert color="info" icon="o-information-circle" role="status" data-converted-orders="{{ $result['notices']['converted_orders'] }}">
                    {{ trans_choice(
                        ':count order was placed under a different base currency. It is converted to :currency at today\'s exchange rate, so its amount can change.|:count orders were placed under a different base currency. They are converted to :currency at today\'s exchange rate, so their amounts can change.',
                        (int) $result['notices']['converted_orders'],
                        [ 'count' => (int) $result['notices']['converted_orders'], 'currency' => $currency ],
                    ) }}
                </x-artisanpack-alert>
            @endif

            @if ( ( $result['notices']['unconverted_orders'] ?? 0 ) > 0 )
                <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" role="status" data-unconverted-orders="{{ $result['notices']['unconverted_orders'] }}">
                    {{ trans_choice(
                        ':count order is left out because there is no exchange rate from its base currency to :currency.|:count orders are left out because there is no exchange rate from their base currency to :currency.',
                        (int) $result['notices']['unconverted_orders'],
                        [ 'count' => (int) $result['notices']['unconverted_orders'], 'currency' => $currency ],
                    ) }}
                </x-artisanpack-alert>
            @endif

            @if ( [] !== $kpis )
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-kpis>
                    @foreach ( $kpis as $kpi )
                        @php
                            $description = null;

                            if ( null !== $kpi['previous'] ) {
                                $previous    = $display( $kpi['previous'], $kpi['type'] );
                                $description = match ( true ) {
                                    null === $kpi['change'] => __( 'Previous period: :value', [ 'value' => $previous ] ),
                                    $kpi['change'] > 0      => __( 'Up :change% from :value', [ 'change' => abs( $kpi['change'] ), 'value' => $previous ] ),
                                    $kpi['change'] < 0      => __( 'Down :change% from :value', [ 'change' => abs( $kpi['change'] ), 'value' => $previous ] ),
                                    default                 => __( 'Same as the previous period (:value)', [ 'value' => $previous ] ),
                                };
                            }
                        @endphp
                        <x-artisanpack-stat
                            wire:key="kpi-{{ $kpi['key'] }}"
                            :title="$kpi['label']"
                            :value="$display( $kpi['value'], $kpi['type'] )"
                            :description="$description"
                            data-kpi="{{ $kpi['key'] }}"
                        />
                    @endforeach
                </div>
            @endif

            @if ( null !== $chart && [] !== $chart['categories'] )
                <figure class="flex flex-col gap-2" data-chart>
                    <div role="img" aria-label="{{ $chart['summary'] }}">
                        <x-artisanpack-chart
                            :id="'report-chart-' . $report"
                            :type="$chart['type']"
                            :series="$chart['series']"
                            :options="$chartOptions"
                            height="320"
                            wire:key="report-chart-{{ $report }}-{{ md5( json_encode( [ $chart['series'], $chart['categories'] ] ) ) }}"
                        />
                    </div>
                    <figcaption class="text-sm opacity-75">{{ $chart['summary'] }}</figcaption>
                </figure>
            @endif

            @if ( null !== $table )
                <div class="overflow-x-auto" data-report-table>
                    <table class="table table-sm">
                        <caption class="sr-only">{{ $label }}</caption>
                        <thead>
                            <tr>
                                @foreach ( $table['columns'] as $column )
                                    <th scope="col" @class( [ 'text-end' => in_array( $column['type'], [ 'int', 'money', 'percent', 'rate' ], true ) ] )>{{ $column['label'] }}</th>
                                @endforeach
                                @if ( 'low-stock' === $report )
                                    <th scope="col"><span class="sr-only">{{ __( 'Actions' ) }}</span></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ( $table['rows'] as $index => $row )
                                <tr wire:key="report-row-{{ $index }}" @class( [ 'opacity-80' => $row['indent'] ?? false ] ) data-report-row>
                                    @foreach ( $table['columns'] as $columnIndex => $column )
                                        @php $text = $display( $row[ $column['key'] ] ?? null, $column['type'] ); @endphp
                                        @if ( 0 === $columnIndex || ( 1 === $columnIndex && 'top-products' === $report ) )
                                            @if ( 1 === $columnIndex && ( $row['indent'] ?? false ) )
                                                <th scope="row" class="ps-8 font-normal">
                                                    <span class="sr-only">{{ __( 'Variant:' ) }}</span>
                                                    {{ $text }}
                                                </th>
                                            @elseif ( 'top-products' === $report && 0 === $columnIndex )
                                                <td>{{ $text === '—' ? '' : $text }}</td>
                                            @else
                                                <th scope="row" class="font-medium">{{ $text }}</th>
                                            @endif
                                        @else
                                            <td @class( [ 'text-end tabular-nums' => in_array( $column['type'], [ 'int', 'money', 'percent', 'rate' ], true ) ] )>{{ $text }}</td>
                                        @endif
                                    @endforeach
                                    @if ( 'low-stock' === $report )
                                        <td>
                                            @if ( null !== ( $row['action'] ?? null ) )
                                                <a href="{{ $row['action']['url'] }}" class="link" aria-label="{{ $row['action']['aria'] }}">{{ $row['action']['label'] }}</a>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count( $table['columns'] ) + ( 'low-stock' === $report ? 1 : 0 ) }}" data-report-empty>
                                        {{ $ranged ? __( 'Nothing to report for this period.' ) : __( 'Nothing to report.' ) }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ( null !== $table['totals'] && [] !== $table['rows'] )
                            <tfoot>
                                <tr data-report-totals>
                                    @foreach ( $table['columns'] as $columnIndex => $column )
                                        @php $text = $display( $table['totals'][ $column['key'] ] ?? null, $column['type'] ); @endphp
                                        @if ( 0 === $columnIndex )
                                            <th scope="row">@if ( '—' === $text )<span class="sr-only">{{ __( 'Totals' ) }}</span>@else{{ $text }}@endif</th>
                                        @else
                                            <td @class( [ 'text-end tabular-nums' => in_array( $column['type'], [ 'int', 'money', 'percent', 'rate' ], true ) ] )>{{ '—' === $text ? '' : $text }}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
