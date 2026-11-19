{{--
    Tags cell (#123): the tag's name, or, while it is being renamed, the
    name and slug fields with Save and Cancel. Focus moves into the name
    field when the row opens, and back to the row's pencil on Save, Cancel
    or Escape.
--}}
@if ( $context['editingId'] === (int) $row->getKey() )
    <form
        wire:submit="saveRename"
        class="ec-tags-rename"
        data-rename-tag="{{ $row->getKey() }}"
        x-data
        x-init="$nextTick( () => $el.querySelector( '#tag-{{ $row->getKey() }}-name' )?.focus() )"
        x-on:keydown.escape.prevent.stop="$el.querySelector( '[data-rename-cancel]' )?.click()"
    >
        <div class="ec-tags-rename-field">
            <x-artisanpack-input id="tag-{{ $row->getKey() }}-name" size="sm" :aria-label="__( 'Name' )" maxlength="120" wire:model="editingName" />
        </div>
        <div class="ec-tags-rename-field">
            <x-artisanpack-input id="tag-{{ $row->getKey() }}-slug" size="sm" class="ec-mono-input" dir="ltr" :aria-label="__( 'Slug' )" maxlength="120" wire:model="editingSlug" />
        </div>
        <x-artisanpack-button type="submit" size="sm" data-focus-return="rename-{{ $row->getKey() }}" wire:loading.attr="disabled" wire:target="saveRename">{{ __( 'Save' ) }}</x-artisanpack-button>
        <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="cancelRename" data-rename-cancel data-focus-return="rename-{{ $row->getKey() }}">{{ __( 'Cancel' ) }}</x-artisanpack-button>
    </form>
@else
    <span class="ec-tags-name">{{ $row->name }}</span>
@endif
