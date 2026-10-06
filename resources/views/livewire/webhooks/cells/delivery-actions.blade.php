<div class="flex justify-end">
    <x-artisanpack-button
        variant="ghost"
        size="sm"
        icon="o-eye"
        wire:click="openDelivery( {{ $row->getKey() }} )"
        :label="__( 'Details' )"
        :aria-label="__( 'Details of delivery :id', [ 'id' => $row->getKey() ] )"
    />
</div>
