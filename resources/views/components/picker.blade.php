{{--
    Pickers (#84). A design-system Combobox that searches a registered picker
    source as you type: single pickers show the choice as the value with a
    clear button, multi pickers show removable chips. With no source
    registered it is an info Alert instead of a dead field. See Picker.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( ! $available() )
    <x-artisanpack-alert
        color="info"
        :title="$label"
        :description="$unavailableMessage()"
        data-picker-unavailable="{{ $type() }}"
    />
@elseif ( $live )
    <x-artisanpack-combobox
        :id="$id ?? $model"
        :label="$label"
        :hint="$hint"
        :options="$comboboxOptions()"
        :multiple="! $single"
        :clearable="$single"
        :leading-icon="$single ? 'search' : null"
        remote
        :placeholder="$placeholderText()"
        :empty-label="__( 'No results found.' )"
        :loading-label="__( 'Searching…' )"
        x-on:search-change.debounce.300ms="{{ $searchHandler() }}"
        data-picker="{{ $type() }}"
        wire:model.live="{{ $model }}"
        {{ $attributes->whereDoesntStartWith( 'wire:model' ) }}
    />
@else
    <x-artisanpack-combobox
        :id="$id ?? $model"
        :label="$label"
        :hint="$hint"
        :options="$comboboxOptions()"
        :multiple="! $single"
        :clearable="$single"
        :leading-icon="$single ? 'search' : null"
        remote
        :placeholder="$placeholderText()"
        :empty-label="__( 'No results found.' )"
        :loading-label="__( 'Searching…' )"
        x-on:search-change.debounce.300ms="{{ $searchHandler() }}"
        data-picker="{{ $type() }}"
        wire:model="{{ $model }}"
        {{ $attributes->whereDoesntStartWith( 'wire:model' ) }}
    />
@endif
