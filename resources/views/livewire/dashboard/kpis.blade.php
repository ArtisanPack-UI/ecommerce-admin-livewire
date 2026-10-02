{{--
    Dashboard KPI row. Each KPI links to the screen filtered to match.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<h2 id="dashboard-widget-{{ $widget['key'] }}" class="sr-only">{{ $widget['label'] }}</h2>

<ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5" data-kpis>
    @foreach ( $dashboard['kpis'] as $kpi )
        <li wire:key="dashboard-kpi-{{ $kpi['key'] }}" data-kpi="{{ $kpi['key'] }}" class="relative">
            <x-artisanpack-stat
                :title="$kpi['label']"
                :value="$kpi['value']"
                :description="$kpi['description']"
                :icon="$kpi['icon']"
                :animate="false"
                class="h-full shadow"
            />
            @if ( null !== $kpi['url'] )
                {{-- Stretched link: the whole card is the target; its name is the KPI and its value. --}}
                <a href="{{ $kpi['url'] }}" class="absolute inset-0 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" data-kpi-link>
                    <span class="sr-only">{{ __( ':label: :value. :description', [ 'label' => $kpi['label'], 'value' => $kpi['value'], 'description' => $kpi['description'] ] ) }}</span>
                </a>
            @endif
        </li>
    @endforeach
</ul>
