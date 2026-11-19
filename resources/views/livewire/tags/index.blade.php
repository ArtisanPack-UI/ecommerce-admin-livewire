{{--
    Tags (#123). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tags\Index.

    A flat list of product tags on the shared Resource table (#79), with a
    quick-add card above it whose Add tag is the page's one solid action.
    Tags are renamed in their row (cells/name), and duplicates are cleaned up
    by selecting them and merging into one (bulk-controls), which is
    confirmed inline.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="ec-stack" data-tags-index>
    <x-artisanpack-page-header :title="__( 'Tags' )" :description="__( 'Flat labels you can add to any product, for filters, collections and promotions.' )" :level="1" />

    @if ( $canCreate )
        <x-artisanpack-card data-tags-add-card>
            <form wire:submit="createTag" class="ec-tags-add" data-new-tag>
                <div class="ec-tags-add-field">
                    <x-artisanpack-input id="new-tag-name" :label="__( 'New tag' )" :placeholder="__( 'e.g. holiday-2026' )" maxlength="120" wire:model="newTagName" />
                </div>
                <x-artisanpack-button type="submit" leading-icon="plus" wire:loading.attr="disabled" wire:target="createTag">{{ __( 'Add tag' ) }}</x-artisanpack-button>
            </form>
        </x-artisanpack-card>
    @endif

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'         => 'tag',
        'emptyTitle'        => __( 'No tags yet' ),
        'emptyDescription'  => __( 'Tags are flat labels you can add to any product.' ),
        'searchHint'        => __( 'Search tags' ),
        'bulkControls'      => 'ecommerce-admin::livewire.tags.bulk-controls',
        'bulkInlineActions' => [ 'merge' ],
        'rowLabel'          => static fn ( \ArtisanPackUI\Ecommerce\Models\ProductTag $tag ): string => (string) $tag->name,
        'cellContext'       => [ 'editingId' => $editingId, 'canUpdate' => $canUpdate, 'canDelete' => $canDelete, 'productsUrl' => $productsUrl ],
    ] )
</div>
