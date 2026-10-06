{{--
    Dashboard: the tracked items furthest below their low-stock threshold.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels;

    $inventoryRoute = AdminNav::ROUTE_PREFIX . 'inventory.index';
@endphp
<x-artisanpack-card shadow class="h-full">
    <h2 id="dashboard-widget-{{ $widget['key'] }}" class="text-lg font-semibold">{{ $widget['label'] }}</h2>

    @if ( $dashboard['lowStock']->isEmpty() )
        <x-artisanpack-ec-empty-state icon="o-check-circle" :title="__( 'Stock looks healthy' )" :description="__( 'No tracked item is at or below its low-stock threshold.' )" />
    @else
        <div class="overflow-x-auto">
            <table class="table table-sm" data-low-stock>
                <caption class="sr-only">{{ __( 'Items at or below their low-stock threshold, lowest first' ) }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __( 'Item' ) }}</th>
                        <th scope="col">{{ __( 'SKU' ) }}</th>
                        <th scope="col" class="text-end">{{ __( 'Available' ) }}</th>
                        <th scope="col" class="text-end">{{ __( 'Threshold' ) }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ( $dashboard['lowStock'] as $item )
                        <tr wire:key="dashboard-low-stock-{{ $item->id }}">
                            <td>
                                <span class="font-semibold">{{ StockLevels::label( $item ) }}</span>
                                @if ( (int) $item->quantity_available <= 0 )
                                    <x-artisanpack-badge :value="__( 'Out of stock' )" class="badge-sm ms-1" color="error" />
                                @endif
                            </td>
                            <td>{{ StockLevels::sku( $item ) ?: '—' }}</td>
                            <td class="text-end tabular-nums">{{ (int) $item->quantity_available }}</td>
                            <td class="text-end tabular-nums">{{ (int) $item->low_stock_threshold }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ( \Illuminate\Support\Facades\Route::has( $inventoryRoute ) )
        <x-slot:actions>
            <a href="{{ route( $inventoryRoute, [ 'filters' => [ 'stock' => 'reorder' ] ] ) }}" class="link link-hover text-sm">{{ __( 'View all low-stock items' ) }}</a>
        </x-slot:actions>
    @endif
</x-artisanpack-card>
