@if ( $context['canAdjust'] && ! \ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels::isReadOnly( $row ) )
    <x-artisanpack-button variant="outline" size="sm" icon="o-adjustments-horizontal" wire:click="startAdjust( {{ $row->getKey() }} )" :label="__( 'Adjust' )" :aria-label="__( 'Adjust stock of :item', [ 'item' => \ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels::label( $row ) ] )" />
@endif
