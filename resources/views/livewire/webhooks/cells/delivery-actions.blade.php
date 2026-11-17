<div class="flex justify-end">
    <x-artisanpack-button
        variant="ghost"
        size="sm"
        leading-icon="o-eye"
        wire:click="openDelivery( {{ $row->getKey() }} )"
        :aria-label="__( 'Details of delivery :id', [ 'id' => $row->getKey() ] )">{{ __( 'Details' ) }}</x-artisanpack-button>
</div>
