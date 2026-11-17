@if ( $context['canAdjust'] && ! \ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels::isReadOnly( $row ) )
    <x-artisanpack-button variant="outline" size="sm" leading-icon="o-adjustments-horizontal" wire:click="startAdjust( {{ $row->getKey() }} )" :aria-label="__( 'Adjust stock of :item', [ 'item' => \ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels::label( $row ) ] )">{{ __( 'Adjust' ) }}</x-artisanpack-button>
@endif
