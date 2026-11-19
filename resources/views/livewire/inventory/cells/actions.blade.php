{{-- Inventory cell (#124): Adjust, named for its item; read-only stock has none. --}}
@if ( $context['canAdjust'] && ! \ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels::isReadOnly( $row ) )
    <x-artisanpack-button
        variant="outline"
        color="neutral"
        size="xs"
        wire:click="startAdjust( {{ $row->getKey() }} )"
        :aria-label="__( 'Adjust stock of :item', [ 'item' => \ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels::label( $row ) ] )"
        data-focus-key="inventory-adjust-{{ $row->getKey() }}"
    >{{ __( 'Adjust' ) }}</x-artisanpack-button>
@endif
