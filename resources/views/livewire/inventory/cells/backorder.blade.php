@if ( $context['canAdjust'] && ! \ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels::isReadOnly( $row ) )
    <x-artisanpack-toggle
        id="inventory-backorder-{{ $row->getKey() }}"
        :checked="$row->allow_backorder"
        wire:click="toggleBackorder( {{ $row->getKey() }} )"
        :aria-label="__( 'Allow backorders of :item', [ 'item' => \ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels::label( $row ) ] )"
        data-backorder="{{ $row->allow_backorder ? '1' : '0' }}"
    />
@else
    {{ $row->allow_backorder ? __( 'Allowed' ) : __( 'Not allowed' ) }}
@endif
