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
    :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $mergeOptions )"
    :placeholder="__( 'Choose a tag' )"
    wire:model="mergeTargetId"
/>
