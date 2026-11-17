{{--
    The category and tag the products bulk actions add or remove.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="flex flex-wrap items-end gap-3">
    <x-artisanpack-select
        id="products-bulk-category"
        class="select-sm"
        :label="__( 'Category to add or remove' )"
        :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $bulkCategoryOptions )"
        :placeholder="[] === $bulkCategoryOptions ? __( 'No categories yet' ) : __( 'Choose a category' )"
        wire:model="bulkCategoryId"
    />
    <x-artisanpack-select
        id="products-bulk-tag"
        class="select-sm"
        :label="__( 'Tag to add or remove' )"
        :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $bulkTagOptions )"
        :placeholder="[] === $bulkTagOptions ? __( 'No tags yet' ) : __( 'Choose a tag' )"
        wire:model="bulkTagId"
    />
</div>
