@if ( (int) $row->stock_tracked === 0 )
    <span class="opacity-75">{{ __( 'Not tracked' ) }}</span>
@else
    <span class="tabular-nums">{{ (int) $row->stock_available }}</span>
    @if ( (int) $row->stock_available <= 0 )
        <x-artisanpack-badge :value="__( 'Out of stock' )" class="badge-sm" color="error" />
    @endif
@endif
