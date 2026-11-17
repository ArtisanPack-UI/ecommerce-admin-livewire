@if ( $context['editingId'] === (int) $row->getKey() )
    <form wire:submit="saveRename" class="flex flex-wrap items-end gap-2" data-rename-tag="{{ $row->getKey() }}">
        <x-artisanpack-input id="tag-{{ $row->getKey() }}-name" class="input-sm" :label="__( 'Name' )" wire:model="editingName" />
        <x-artisanpack-input id="tag-{{ $row->getKey() }}-slug" class="input-sm" :label="__( 'Slug' )" wire:model="editingSlug" />
        <x-artisanpack-button type="submit" size="sm" color="primary">{{ __( 'Save' ) }}</x-artisanpack-button>
        <x-artisanpack-button size="sm" variant="ghost" wire:click="cancelRename">{{ __( 'Cancel' ) }}</x-artisanpack-button>
    </form>
@else
    <span class="font-semibold">{{ $row->name }}</span>
@endif
