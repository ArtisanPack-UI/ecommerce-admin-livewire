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
        :options="$bulkCategoryOptions"
        :placeholder="[] === $bulkCategoryOptions ? __( 'No categories yet' ) : __( 'Choose a category' )"
        placeholder-value=""
        wire:model="bulkCategoryId"
    />
    <x-artisanpack-select
        id="products-bulk-tag"
        class="select-sm"
        :label="__( 'Tag to add or remove' )"
        :options="$bulkTagOptions"
        :placeholder="[] === $bulkTagOptions ? __( 'No tags yet' ) : __( 'Choose a tag' )"
        placeholder-value=""
        wire:model="bulkTagId"
    />
</div>
