{{--
    Inventory cell (#124): on hand less reserved, with an "Out of stock"
    (error) or "Low stock" (warning) badge beside it for tracked stock, so the
    state never rests on colour alone.
--}}
@php( $available = $row->availableQuantity() )
<span class="ec-inventory-available">
    <bdi dir="ltr" class="ec-money">{{ $available }}</bdi>
    @if ( $row->track_inventory && $available <= 0 )
        <x-artisanpack-badge color="error" dot data-out-of-stock>{{ __( 'Out of stock' ) }}</x-artisanpack-badge>
    @elseif ( $row->track_inventory && null !== $row->low_stock_threshold && $available <= $row->low_stock_threshold )
        <x-artisanpack-badge color="warning" dot data-low-stock>{{ __( 'Low stock' ) }}</x-artisanpack-badge>
    @endif
</span>
