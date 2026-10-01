<div class="flex justify-end gap-1">
    @if ( $context['canUpdate'] && $context['editingId'] !== (int) $row->getKey() )
        <x-artisanpack-button variant="ghost" size="sm" icon="o-pencil" wire:click="startRename( {{ $row->getKey() }} )" :aria-label="__( 'Rename :name', [ 'name' => $row->name ] )" />
    @endif
    @if ( $context['canDelete'] )
        <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="confirmDelete( {{ $row->getKey() }} )" :aria-label="__( 'Delete :name', [ 'name' => $row->name ] )" />
    @endif
</div>
