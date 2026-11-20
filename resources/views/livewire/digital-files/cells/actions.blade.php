{{-- Digital files cell (#126): Edit and Delete, icon-only and named for the file. --}}
<div class="ec-row-actions">
    @if ( $context['canUpdate'] )
        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="pencil" wire:click="edit( {{ $row->getKey() }} )" :aria-label="__( 'Edit :label', [ 'label' => $row->label ] )" data-edit-file="{{ $row->getKey() }}" />
    @endif
    @if ( $context['canDelete'] )
        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="confirmDelete( {{ $row->getKey() }} )" :aria-label="__( 'Delete :label', [ 'label' => $row->label ] )" data-delete-file="{{ $row->getKey() }}" />
    @endif
</div>
