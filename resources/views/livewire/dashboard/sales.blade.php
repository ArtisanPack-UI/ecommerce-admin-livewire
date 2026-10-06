{{--
    Dashboard 30-day sales sparkline. The chart is decorative for screen
    readers; its summary sentence carries the same information.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php( $sales = $dashboard['sales'] )
<x-artisanpack-card shadow class="h-full">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="dashboard-widget-{{ $widget['key'] }}" class="text-lg font-semibold">{{ $widget['label'] }}</h2>
        <span class="text-2xl font-black tabular-nums" data-sales-total>{{ $sales['total'] }}</span>
    </div>

    <figure class="mt-4 flex flex-col gap-2" data-sales-sparkline>
        {{-- `inert` keeps the chart's own focusable parts out of the tab order too. --}}
        <div aria-hidden="true" inert>
            <x-artisanpack-sparkline
                id="dashboard-sales-sparkline"
                :data="$sales['values']"
                type="area"
                height="64"
                class="w-full"
                wire:key="dashboard-sales-sparkline-{{ md5( json_encode( $sales['values'] ) ) }}"
            />
        </div>
        <figcaption class="text-sm opacity-75">{{ $sales['summary'] }}</figcaption>
    </figure>

    @if ( null !== $sales['url'] )
        <x-slot:actions>
            <a href="{{ $sales['url'] }}" class="link link-hover text-sm">{{ __( 'View the sales report' ) }}</a>
        </x-slot:actions>
    @endif
</x-artisanpack-card>
