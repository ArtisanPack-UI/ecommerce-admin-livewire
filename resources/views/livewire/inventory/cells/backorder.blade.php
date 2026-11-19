{{--
    Inventory cell (#124): the backorder switch, named for its item ("Allow
    backorders of Canvas tote — Natural") with the state beside it. It is
    disabled for read-only stock and for users who may not adjust stock.
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels;

    $editable = $context['canAdjust'] && ! StockLevels::isReadOnly( $row );
@endphp
@if ( $editable )
    <x-artisanpack-toggle
        id="inventory-backorder-{{ $row->getKey() }}"
        size="sm"
        :checked="(bool) $row->allow_backorder"
        :label="$row->allow_backorder ? __( 'Allowed' ) : __( 'Not allowed' )"
        :aria-label="__( 'Allow backorders of :item', [ 'item' => StockLevels::label( $row ) ] )"
        wire:click="toggleBackorder( {{ $row->getKey() }} )"
        data-backorder="{{ $row->allow_backorder ? '1' : '0' }}"
    />
@else
    <x-artisanpack-toggle
        id="inventory-backorder-{{ $row->getKey() }}"
        size="sm"
        disabled
        :checked="(bool) $row->allow_backorder"
        :label="$row->allow_backorder ? __( 'Allowed' ) : __( 'Not allowed' )"
        :aria-label="__( 'Allow backorders of :item', [ 'item' => StockLevels::label( $row ) ] )"
        data-backorder="{{ $row->allow_backorder ? '1' : '0' }}"
    />
@endif
