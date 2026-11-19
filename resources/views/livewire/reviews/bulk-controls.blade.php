{{--
    Reviews (#125): the bulk bar's own controls. The reason field feeds the
    Reject bulk action for every selected review (it is optional and private),
    and Approve sits beside it as the bar's soft success button; the bar
    leaves Approve out of its own row ($bulkInlineActions). A user who may not
    moderate sees neither.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( isset( $tableBulkActions['reject'] ) )
    <label class="ec-bulk-inline-label" for="reviews-bulk-reject-reason">{{ __( 'Reason (for Reject)' ) }}</label>
    <div class="ec-reviews-bulk-reason">
        <x-artisanpack-input
            id="reviews-bulk-reject-reason"
            size="sm"
            :aria-label="__( 'Reason (for Reject)' )"
            :placeholder="__( 'Optional' )"
            maxlength="500"
            wire:model="rejectReason"
        />
    </div>
@endif
@if ( isset( $tableBulkActions['approve'] ) )
    <x-artisanpack-button
        variant="soft"
        color="success"
        size="sm"
        leading-icon="check"
        wire:click="runBulkAction( {{ \Illuminate\Support\Js::from( 'approve' ) }} )"
        wire:loading.attr="disabled"
        data-focus-key="bulk-approve"
        wire:key="bulk-action-approve"
    >{{ $tableBulkActions['approve']['label'] }}</x-artisanpack-button>
@endif
