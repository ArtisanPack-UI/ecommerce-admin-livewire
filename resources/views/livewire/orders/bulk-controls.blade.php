{{--
    The target sub-status for the orders "Change sub-status" bulk action.
    Only sub-statuses that fit every selected order are offered; with none,
    the bar says why and the action is left out.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( [] === $bulkSubstatusOptions )
    <p class="ec-bulkbar__note" data-bulk-substatus-mixed>{{ __( 'The selected orders have different statuses, so no sub-status fits all of them.' ) }}</p>
@else
    <div class="ec-bulkbar__field">
        <x-artisanpack-select
            id="orders-bulk-substatus"
            size="sm"
            :aria-label="__( 'Move to sub-status' )"
            :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $bulkSubstatusOptions )"
            :placeholder="__( 'Move to sub-status' )"
            wire:model="bulkSubstatusId"
        />
    </div>
@endif
