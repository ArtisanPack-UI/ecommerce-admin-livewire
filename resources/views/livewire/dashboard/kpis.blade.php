{{--
    Dashboard key figures (#95): a design-system Stat per KPI, each linking to
    the screen filtered to match. Only "Awaiting fulfillment" is emphasized,
    and only while there are orders to ship. The whole Stat is one link
    (stretched over it), named with the KPI, its value and its description;
    the icons are decorative.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<h2 id="dashboard-widget-{{ $widget['key'] }}" class="apui-sr-only">{{ $widget['label'] }}</h2>

<ul class="ec-kpis" data-kpis>
    @foreach ( $dashboard['kpis'] as $kpi )
        <li wire:key="dashboard-kpi-{{ $kpi['key'] }}" data-kpi="{{ $kpi['key'] }}" class="ec-kpi">
            <x-artisanpack-stat
                :label="$kpi['label']"
                :description="$kpi['description']"
                :icon="$kpi['icon']"
                :emphasis="$kpi['emphasis']"
                :color="$kpi['emphasis'] ? 'accent' : 'neutral'"
                :aria-hidden="null !== $kpi['url'] ? 'true' : null"
            ><bdi dir="ltr">{{ $kpi['value'] }}</bdi></x-artisanpack-stat>
            @if ( null !== $kpi['url'] )
                <a href="{{ $kpi['url'] }}" class="ec-kpi-link" data-kpi-link>
                    <span class="apui-sr-only">{{ __( ':label: :value. :description', [ 'label' => $kpi['label'], 'value' => $kpi['value'], 'description' => $kpi['description'] ] ) }}</span>
                </a>
            @endif
        </li>
    @endforeach
</ul>
