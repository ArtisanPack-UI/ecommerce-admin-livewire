<div class="flex justify-end gap-1">
    @if ( $context['canUpdate'] )
        <x-artisanpack-button variant="ghost" size="sm" leading-icon="o-pencil" wire:click="edit( {{ $row->getKey() }} )" :aria-label="__( 'Edit :label', [ 'label' => $row->label ] )" />
    @endif
    @if ( $context['canDelete'] )
        <x-artisanpack-button variant="ghost" size="sm" leading-icon="o-trash" wire:click="confirmDelete( {{ $row->getKey() }} )" :aria-label="__( 'Delete :label', [ 'label' => $row->label ] )" />
    @endif
</div>
