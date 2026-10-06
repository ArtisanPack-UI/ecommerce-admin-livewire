{{--
    The tag the tags merge bulk action merges into.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<x-artisanpack-select
    id="tags-merge-target"
    class="select-sm"
    :label="__( 'Tag to merge into' )"
    :options="$mergeOptions"
    :placeholder="__( 'Choose a tag' )"
    placeholder-value=""
    wire:model="mergeTargetId"
/>
