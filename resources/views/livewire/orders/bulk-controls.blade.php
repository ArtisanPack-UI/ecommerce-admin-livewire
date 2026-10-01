{{--
    The target sub-status for the orders "change sub-status" bulk action.
    Only sub-statuses that fit every selected order are offered.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( [] === $bulkSubstatusOptions )
    <p class="text-sm opacity-75">{{ __( 'The selected orders have different statuses, so no sub-status fits all of them.' ) }}</p>
@else
    <x-artisanpack-select
        id="orders-bulk-substatus"
        class="select-sm"
        :label="__( 'Move to sub-status' )"
        :options="$bulkSubstatusOptions"
        :placeholder="__( 'Choose a sub-status' )"
        placeholder-value=""
        wire:model="bulkSubstatusId"
    />
@endif
