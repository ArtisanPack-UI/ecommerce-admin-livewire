{{--
    Products index. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php( $createRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'products.create' )
<div>
    <x-artisanpack-page-header :title="__( 'Products' )" :level="1">
        <x-slot:actions>
            <x-artisanpack-button variant="outline" leading-icon="o-document-arrow-down" wire:click="exportCatalog" wire:loading.attr="disabled">{{ __( 'Export catalog' ) }}</x-artisanpack-button>
            @if ( $canImport && \Illuminate\Support\Facades\Route::has( $importRoute ) )
                <x-artisanpack-button variant="outline" leading-icon="o-arrow-up-tray" :href="route( $importRoute )">{{ __( 'Import' ) }}</x-artisanpack-button>
            @endif
            @if ( $canCreate && \Illuminate\Support\Facades\Route::has( $createRoute ) )
                <x-artisanpack-button color="primary" leading-icon="o-plus" :href="route( $createRoute )">{{ __( 'New product' ) }}</x-artisanpack-button>
            @endif
        </x-slot:actions>
    </x-artisanpack-page-header>

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'o-cube',
        'emptyTitle'       => __( 'No products yet' ),
        'emptyDescription' => __( 'Add your first product to start selling.' ),
        'bulkControls'     => 'ecommerce-admin::livewire.products.bulk-controls',
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\Product $product ): string => (string) $product->name,
    ] )
</div>
