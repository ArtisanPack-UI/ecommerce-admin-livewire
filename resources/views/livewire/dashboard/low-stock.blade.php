{{--
    Dashboard: the tracked items furthest below their low-stock threshold,
    lowest first (#97). A design-system Card with a compact Table flush
    inside it. Out-of-stock rows carry an error Badge, and a short Progress
    bar shows how much of the threshold is left; the bar is decorative, the
    Available and Threshold columns carry the numbers. Item names open the
    inventory screen searched to that SKU.

    The Card's markup is written out because x-artisanpack-card titles are
    always h3, and dashboard widgets are h2 sections (see the dashboard view).

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels;

    $inventoryRoute = AdminNav::ROUTE_PREFIX . 'inventory.index';
    $canLink        = \Illuminate\Support\Facades\Route::has( $inventoryRoute );
@endphp
<div class="apui-card" data-surface="default">
    <div data-scope="card" data-part="header">
        <h2 data-scope="card" data-part="title" id="dashboard-widget-{{ $widget['key'] }}">{{ $widget['label'] }}</h2>
        @if ( $canLink )
            <div data-scope="card" data-part="actions">
                <x-artisanpack-link :href="route( $inventoryRoute, [ 'filters' => [ 'stock' => 'reorder' ] ] )" :navigate="false" trailing-icon="chevron-right" class="ec-card-link">{{ __( 'View all low-stock items' ) }}</x-artisanpack-link>
            </div>
        @endif
    </div>

    <div data-scope="card" data-part="body" @class( [ 'ec-card-body-flush' => $dashboard['lowStock']->isNotEmpty() ] )>
        @if ( $dashboard['lowStock']->isEmpty() )
            <x-artisanpack-ec-admin-empty-state size="sm" :heading-level="3" icon="circle-check" :title="__( 'Stock looks healthy' )" :description="__( 'No tracked item is at or below its low-stock threshold.' )" />
        @else
            <div data-scope="table" data-part="scroll" data-density="compact">
                <table data-scope="table" data-part="table" data-low-stock>
                    <caption class="apui-sr-only">{{ __( 'Items at or below their low-stock threshold, lowest first' ) }}</caption>
                    <thead>
                        <tr>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Item' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'SKU' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Available' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Threshold' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col" class="ec-low-stock-level"><span class="apui-sr-only">{{ __( 'Level' ) }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ( $dashboard['lowStock'] as $item )
                            @php
                                $available  = (int) $item->quantity_available;
                                $threshold  = (int) $item->low_stock_threshold;
                                $sku        = StockLevels::sku( $item );
                                $outOfStock = $available <= 0;
                                $level      = $threshold > 0 ? max( 0, min( 100, (int) round( $available / $threshold * 10 ) * 10 ) ) : 0;
                            @endphp
                            <tr data-scope="table" data-part="row" wire:key="dashboard-low-stock-{{ $item->id }}">
                                <td data-scope="table" data-part="cell">
                                    @if ( $canLink && '' !== $sku )
                                        <x-artisanpack-link :href="route( $inventoryRoute, [ 'q' => $sku ] )" :navigate="false" tone="neutral" standalone>{{ StockLevels::label( $item ) }}</x-artisanpack-link>
                                    @else
                                        {{ StockLevels::label( $item ) }}
                                    @endif
                                    @if ( $outOfStock )
                                        <x-artisanpack-badge size="sm" color="error" class="ec-low-stock-badge" data-out-of-stock>{{ __( 'Out of stock' ) }}</x-artisanpack-badge>
                                    @endif
                                </td>
                                <td data-scope="table" data-part="cell"><bdi dir="ltr">{{ '' !== $sku ? $sku : '—' }}</bdi></td>
                                <td data-scope="table" data-part="cell" data-numeric>{{ $available }}</td>
                                <td data-scope="table" data-part="cell" data-numeric>{{ $threshold }}</td>
                                <td data-scope="table" data-part="cell" aria-hidden="true">
                                    <x-artisanpack-progress :value="$level" size="sm" :color="$outOfStock || $level < 30 ? 'error' : 'warning'" class="ec-w-{{ $level }}" data-stock-level="{{ $level }}" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
