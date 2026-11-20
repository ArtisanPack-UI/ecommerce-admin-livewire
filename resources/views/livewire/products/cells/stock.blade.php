{{--
    Products list cell (#119): units available (summed over variants), an
    "Out of stock" badge at zero (oversold stock keeps its negative count
    beside it), or "Not tracked".
--}}
@if ( (int) $row->stock_tracked === 0 )
    <span class="ec-muted-text">{{ __( 'Not tracked' ) }}</span>
@elseif ( (int) $row->stock_available > 0 )
    <bdi dir="ltr">{{ (int) $row->stock_available }}</bdi>
@else
    @if ( (int) $row->stock_available < 0 )
        <bdi dir="ltr">{{ (int) $row->stock_available }}</bdi>
    @endif
    <x-artisanpack-badge color="error" dot data-out-of-stock>{{ __( 'Out of stock' ) }}</x-artisanpack-badge>
@endif
