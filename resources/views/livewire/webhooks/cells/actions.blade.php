<div class="flex justify-end gap-1">
    @if ( $context['canUpdate'] )
        @unless ( $row->is_active )
            <x-artisanpack-button variant="ghost" size="sm" icon="o-play" wire:click="reEnable( {{ $row->getKey() }} )" :aria-label="__( 'Re-enable :name', [ 'name' => $row->name ] )" />
        @endunless
        <x-artisanpack-button variant="ghost" size="sm" icon="o-key" wire:click="confirmRotate( {{ $row->getKey() }} )" :aria-label="__( 'Rotate the secret of :name', [ 'name' => $row->name ] )" />
        <x-artisanpack-button variant="ghost" size="sm" icon="o-pencil" wire:click="edit( {{ $row->getKey() }} )" :aria-label="__( 'Edit :name', [ 'name' => $row->name ] )" />
    @endif
    @if ( $context['canDelete'] )
        <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="confirmDelete( {{ $row->getKey() }} )" :aria-label="__( 'Delete :name', [ 'name' => $row->name ] )" />
    @endif
</div>
