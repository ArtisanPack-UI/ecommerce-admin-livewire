{{--
    Webhooks list (#136): the "No webhook subscriptions yet" state's action.
    Rendered by the shared resource-table partial with the index screen's
    variables.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      2.0.0
--}}
<x-artisanpack-button leading-icon="plus" wire:click="create" data-empty-new-webhook>{{ __( 'New subscription' ) }}</x-artisanpack-button>
