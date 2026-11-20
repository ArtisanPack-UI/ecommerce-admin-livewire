{{--
    Tags cell (#123): Rename and Delete, named for the tag. Hidden while the
    row is being renamed; Save and Cancel take their place in the name cell.
--}}
@if ( $context['editingId'] !== (int) $row->getKey() )
    <div class="ec-row-actions ec-tags-actions">
        @if ( $context['canUpdate'] )
            <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="pencil" wire:click="startRename( {{ $row->getKey() }} )" :aria-label="__( 'Rename :name', [ 'name' => $row->name ] )" data-focus-key="rename-{{ $row->getKey() }}" />
        @endif
        @if ( $context['canDelete'] )
            <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="confirmDelete( {{ $row->getKey() }} )" :aria-label="__( 'Delete :name', [ 'name' => $row->name ] )" />
        @endif
    </div>
@endif
