{{--
    Inventory cell (#124): the low-stock threshold, edited in place. The
    pencil (named for the item) swaps the value for a field with Save and
    Cancel; focus moves into the field, and back to the pencil on Save,
    Cancel or Escape.
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels;

    $itemId   = (int) $row->getKey();
    $label    = StockLevels::label( $row );
    $focusKey = 'inventory-threshold-' . $itemId;
@endphp
@if ( $context['editingThresholdId'] === $itemId )
    <form
        wire:submit="saveThreshold"
        class="ec-inventory-inline-edit"
        x-data
        x-init="$nextTick( () => $el.querySelector( 'input' )?.focus() )"
        x-on:keydown.escape.prevent.stop="$el.querySelector( '[data-threshold-cancel]' )?.click()"
        data-threshold-form="{{ $itemId }}"
    >
        <div class="ec-inventory-threshold-field">
            <x-artisanpack-input
                id="inventory-threshold-{{ $itemId }}"
                size="sm"
                type="number"
                min="0"
                step="1"
                inputmode="numeric"
                :aria-label="__( 'Low-stock threshold of :item', [ 'item' => $label ] )"
                :placeholder="__( 'None' )"
                wire:model="thresholdValue"
            />
        </div>
        <x-artisanpack-button type="submit" size="xs" wire:loading.attr="disabled" wire:target="saveThreshold" data-focus-return="{{ $focusKey }}">{{ __( 'Save' ) }}</x-artisanpack-button>
        <x-artisanpack-button variant="ghost" color="neutral" size="xs" wire:click="cancelThreshold" data-focus-return="{{ $focusKey }}" data-threshold-cancel>{{ __( 'Cancel' ) }}</x-artisanpack-button>
    </form>
@else
    <span class="ec-inventory-threshold">
        @if ( null === $row->low_stock_threshold )
            <span class="ec-muted-text">{{ __( 'None' ) }}</span>
        @else
            <bdi dir="ltr" class="ec-money">{{ $row->low_stock_threshold }}</bdi>
        @endif
        @if ( $context['canAdjust'] && ! StockLevels::isReadOnly( $row ) )
            <x-artisanpack-button
                variant="ghost"
                color="neutral"
                size="xs"
                icon-only
                leading-icon="pencil"
                wire:click="editThreshold( {{ $itemId }} )"
                :aria-label="__( 'Edit the low-stock threshold of :item', [ 'item' => $label ] )"
                data-focus-key="{{ $focusKey }}"
            />
        @endif
    </span>
@endif
