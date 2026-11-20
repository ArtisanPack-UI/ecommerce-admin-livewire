{{--
    Dashboard: sales over the last 30 days (#96). A design-system Card with the
    total, and a Sparkline of daily sales with the best day marked and the
    first and last dates under it. The chart is a picture of the sentence
    under it, so it is hidden from assistive technology and inert; the
    figcaption carries the data. The plot stays left to right in RTL pages.

    The Card's markup is written out because x-artisanpack-card titles are
    always h3, and dashboard widgets are h2 sections (see the dashboard view).
    The chart is drawn here, not with x-artisanpack-sparkline, because that
    marks the latest value, not the best day, and has no label.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    $sales = $dashboard['sales'];
    $chart = $sales['chart'];
@endphp
<div class="apui-card" data-surface="default">
    <div data-scope="card" data-part="header">
        <div>
            <h2 data-scope="card" data-part="title" id="dashboard-widget-{{ $widget['key'] }}">{{ $widget['label'] }}</h2>
            <bdi dir="ltr" class="ec-card-figure" data-sales-total>{{ $sales['total'] }}</bdi>
        </div>
        @if ( null !== $sales['url'] )
            <div data-scope="card" data-part="actions">
                <x-artisanpack-link :href="$sales['url']" :navigate="false" trailing-icon="chevron-right" class="ec-card-link">{{ __( 'View the sales report' ) }}</x-artisanpack-link>
            </div>
        @endif
    </div>

    <div data-scope="card" data-part="body">
        <figure class="ec-sales-figure" data-sales-sparkline>
            {{-- `inert` keeps anything inside the chart out of the tab order too. --}}
            <div class="ec-sales-chart" dir="ltr" aria-hidden="true" inert>
                <svg
                    class="apui-sparkline"
                    data-variant="area"
                    viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}"
                    focusable="false"
                    wire:key="dashboard-sales-sparkline-{{ md5( $chart['line'] ) }}"
                >
                    <line data-scope="sparkline" data-part="baseline" x1="0" x2="{{ $chart['width'] }}" y1="{{ $chart['baseline'] }}" y2="{{ $chart['baseline'] }}" />
                    <path data-scope="sparkline" data-part="area" d="{{ $chart['area'] }}" />
                    <path data-scope="sparkline" data-part="line" d="{{ $chart['line'] }}" />
                    @if ( null !== $chart['best'] )
                        <circle data-scope="sparkline" data-part="dot" cx="{{ $chart['best']['x'] }}" cy="{{ $chart['best']['y'] }}" r="5" data-sales-best />
                        <text class="ec-sales-best" x="{{ $chart['best']['labelX'] }}" y="{{ $chart['best']['labelY'] }}" text-anchor="{{ $chart['best']['anchor'] }}">{{ $chart['best']['label'] }}</text>
                    @endif
                </svg>
                <div class="ec-sales-axis">
                    <span>{{ $sales['axis']['start'] }}</span>
                    <span>{{ $sales['axis']['end'] }}</span>
                </div>
            </div>
            <figcaption class="ec-sales-caption">{{ $sales['summary'] }}</figcaption>
        </figure>
    </div>
</div>
