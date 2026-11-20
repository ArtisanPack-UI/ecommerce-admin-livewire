{{--
    Products list (#119): the "No products yet" state's actions, New product
    first and Import beside it. Rendered by the shared resource-table partial
    with the index screen's variables.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      2.0.0
--}}
@if ( $showCreate )
    <x-artisanpack-button leading-icon="plus" :href="route( $createRoute )" data-empty-new-product>{{ __( 'New product' ) }}</x-artisanpack-button>
@endif
@if ( $showImport )
    <x-artisanpack-button variant="outline" color="neutral" leading-icon="upload" :href="route( $importRoute )" data-empty-import>{{ __( 'Import' ) }}</x-artisanpack-button>
@endif
