@php( $available = $row->availableQuantity() )
<span class="tabular-nums">{{ $available }}</span>
@if ( $row->track_inventory && $available <= 0 )
    <x-artisanpack-badge :value="__( 'Out of stock' )" class="badge-sm" color="error" />
@elseif ( $row->track_inventory && null !== $row->low_stock_threshold && $available <= $row->low_stock_threshold )
    <x-artisanpack-badge :value="__( 'Low stock' )" class="badge-sm" color="warning" />
@endif
