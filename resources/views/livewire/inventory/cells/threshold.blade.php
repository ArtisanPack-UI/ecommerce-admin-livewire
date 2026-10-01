@if ( $context['editingThresholdId'] === (int) $row->getKey() )
    <form wire:submit="saveThreshold" class="flex flex-wrap items-end gap-2" data-threshold-form="{{ $row->getKey() }}">
        <x-artisanpack-input id="inventory-threshold-{{ $row->getKey() }}" class="input-sm w-24" type="number" min="0" step="1" :label="__( 'Threshold' )" wire:model="thresholdValue" />
        <x-artisanpack-button type="submit" size="sm" color="primary" :label="__( 'Save' )" />
        <x-artisanpack-button size="sm" variant="ghost" wire:click="cancelThreshold" :label="__( 'Cancel' )" />
    </form>
@else
    <span class="tabular-nums">{{ $row->low_stock_threshold ?? __( 'None' ) }}</span>
    @if ( $context['canAdjust'] && ! \ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels::isReadOnly( $row ) )
        <x-artisanpack-button variant="ghost" size="xs" icon="o-pencil" wire:click="editThreshold( {{ $row->getKey() }} )" :aria-label="__( 'Edit the low-stock threshold of :item', [ 'item' => \ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels::label( $row ) ] )" />
    @endif
@endif
