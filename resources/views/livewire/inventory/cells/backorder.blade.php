{{--
    Inventory cell (#124): the backorder switch. Its visible label is the
    state ("Allowed" / "Not allowed"); the item it is for follows in
    visually hidden text, so the accessible name starts with what is seen
    (WCAG 2.5.3). It is disabled for read-only stock and for users who may
    not adjust stock. The key carries a revision the screen bumps when a
    change fails, so the switch is redrawn with the saved state.
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels;

    $editable = $context['canAdjust'] && ! StockLevels::isReadOnly( $row );
    $revision = (int) ( $context['backorderRevision'] ?? 0 );
    $label    = $row->allow_backorder ? __( 'Allowed' ) : __( 'Not allowed' );
    $item     = __( 'backorders of :item', [ 'item' => StockLevels::label( $row ) ] );
@endphp
@if ( $editable )
    <x-artisanpack-toggle
        id="inventory-backorder-{{ $row->getKey() }}"
        wire:key="inventory-backorder-{{ $row->getKey() }}-{{ $row->allow_backorder ? '1' : '0' }}-{{ $revision }}"
        size="sm"
        :checked="(bool) $row->allow_backorder"
        wire:click="toggleBackorder( {{ $row->getKey() }} )"
        data-backorder="{{ $row->allow_backorder ? '1' : '0' }}"
    >
        <x-slot:label>{{ $label }}<span class="apui-sr-only">, {{ $item }}</span></x-slot:label>
    </x-artisanpack-toggle>
@else
    <x-artisanpack-toggle
        id="inventory-backorder-{{ $row->getKey() }}"
        size="sm"
        disabled
        :checked="(bool) $row->allow_backorder"
        data-backorder="{{ $row->allow_backorder ? '1' : '0' }}"
    >
        <x-slot:label>{{ $label }}<span class="apui-sr-only">, {{ $item }}</span></x-slot:label>
    </x-artisanpack-toggle>
@endif
