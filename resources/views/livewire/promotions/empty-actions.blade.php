{{--
    Promotions list (#130): the "No promotions yet" state's action. Rendered
    by the shared resource-table partial with the index screen's variables.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      2.0.0
--}}
<x-artisanpack-button leading-icon="plus" :href="$createUrl" data-empty-new-promotion>{{ __( 'New promotion' ) }}</x-artisanpack-button>
