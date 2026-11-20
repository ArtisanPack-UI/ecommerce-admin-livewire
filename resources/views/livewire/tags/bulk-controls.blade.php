{{--
    Tags (#123): the tag the selection merges into, followed by its Merge
    button. Merge is left out of the bar's own buttons ($bulkInlineActions)
    so it sits beside the select; it checks the select before asking for the
    inline confirmation, and changing the select drops a pending one. Shown
    only when the user may run it.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( isset( $tableBulkActions['merge'] ) )
    <div class="ec-tags-merge" role="group" aria-label="{{ __( 'Merge the selected tags' ) }}">
        {{-- Visible, and part of the select's name ("Tag to merge into"). --}}
        <span class="ec-bulk-inline-label" aria-hidden="true">{{ __( 'Merge into' ) }}</span>
        <div class="ec-tags-merge-select">
            <x-artisanpack-select
                id="tags-merge-target"
                size="sm"
                :aria-label="__( 'Tag to merge into' )"
                :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $mergeOptions )"
                :placeholder="__( 'Choose a tag' )"
                wire:model.live="mergeTargetId"
            />
        </div>
        <x-artisanpack-button
            variant="outline"
            color="neutral"
            size="sm"
            leading-icon="zap"
            wire:click="runBulkAction( 'merge' )"
            wire:loading.attr="disabled"
            data-focus-key="bulk-merge"
            wire:key="bulk-action-merge"
        >{{ __( 'Merge' ) }}</x-artisanpack-button>
    </div>
@endif
