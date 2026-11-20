{{--
    Products list (#119). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Index.

    The catalog on the shared Resource table (#79): one row per product with
    its image, type, status, price and stock. New product is the page's one
    solid action, with Export catalog and Import beside it as outline
    buttons. The bulk bar adds or removes a category or tag across the
    selection (bulk-controls), beside Publish, Archive and Delete.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    $createRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'products.create';
    $showCreate  = $canCreate && \Illuminate\Support\Facades\Route::has( $createRoute );
    $showImport  = $canImport && \Illuminate\Support\Facades\Route::has( $importRoute );
@endphp
<div class="ec-stack" data-products-index>
    <x-artisanpack-page-header :title="__( 'Products' )" :description="__( 'Everything you sell, with its type, status, price and stock.' )" :level="1">
        <x-slot:actions>
            <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="download" wire:click="exportCatalog" wire:loading.attr="disabled" wire:target="exportCatalog" data-export-catalog>{{ __( 'Export catalog' ) }}</x-artisanpack-button>
            @if ( $showImport )
                <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="upload" :href="route( $importRoute )" data-import-products>{{ __( 'Import' ) }}</x-artisanpack-button>
            @endif
            @if ( $showCreate )
                <x-artisanpack-button size="sm" leading-icon="plus" :href="route( $createRoute )" data-new-product>{{ __( 'New product' ) }}</x-artisanpack-button>
            @endif
        </x-slot:actions>
    </x-artisanpack-page-header>

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'         => 'o-cube',
        'emptyTitle'        => __( 'No products yet' ),
        'emptyDescription'  => __( 'Add your first product to start selling.' ),
        'emptyActions'      => $showCreate || $showImport ? 'ecommerce-admin::livewire.products.empty-actions' : null,
        'searchHint'        => __( 'Search by name or SKU' ),
        'bulkControls'      => 'ecommerce-admin::livewire.products.bulk-controls',
        'bulkInlineActions' => $bulkInlineActions,
        'rowLabel'          => static fn ( \ArtisanPackUI\Ecommerce\Models\Product $product ): string => (string) $product->name,
        'cellContext'       => [ 'thumbnails' => $thumbnails ],
    ] )
</div>
