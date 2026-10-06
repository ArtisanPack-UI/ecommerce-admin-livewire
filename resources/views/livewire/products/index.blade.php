{{--
    Products index. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php( $createRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'products.create' )
<div>
    <x-artisanpack-header :title="__( 'Products' )" :level="1" separator>
        <x-slot:actions>
            <x-artisanpack-button variant="outline" icon="o-document-arrow-down" wire:click="exportCatalog" wire:loading.attr="disabled" :label="__( 'Export catalog' )" />
            @if ( $canImport && \Illuminate\Support\Facades\Route::has( $importRoute ) )
                <x-artisanpack-button variant="outline" icon="o-arrow-up-tray" :link="route( $importRoute )" :label="__( 'Import' )" />
            @endif
            @if ( $canCreate && \Illuminate\Support\Facades\Route::has( $createRoute ) )
                <x-artisanpack-button color="primary" icon="o-plus" :link="route( $createRoute )" :label="__( 'New product' )" />
            @endif
        </x-slot:actions>
    </x-artisanpack-header>

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'o-cube',
        'emptyTitle'       => __( 'No products yet' ),
        'emptyDescription' => __( 'Add your first product to start selling.' ),
        'bulkControls'     => 'ecommerce-admin::livewire.products.bulk-controls',
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\Product $product ): string => (string) $product->name,
    ] )
</div>
