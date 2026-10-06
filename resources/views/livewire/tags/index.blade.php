{{--
    Tags table. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tags\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div>
    <x-artisanpack-header :title="__( 'Tags' )" :level="1" separator />

    @if ( $canCreate )
        <form wire:submit="createTag" class="mb-4 flex flex-wrap items-end gap-3" data-new-tag>
            <x-artisanpack-input id="new-tag-name" class="min-w-64" :label="__( 'New tag' )" wire:model="newTagName" />
            <x-artisanpack-button type="submit" icon="o-plus" wire:loading.attr="disabled" :label="__( 'Add tag' )" />
        </form>
    @endif

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'o-tag',
        'emptyTitle'       => __( 'No tags yet' ),
        'emptyDescription' => __( 'Tags are flat labels you can add to any product.' ),
        'bulkControls'     => 'ecommerce-admin::livewire.tags.bulk-controls',
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\ProductTag $tag ): string => (string) $tag->name,
        'cellContext'      => [ 'editingId' => $editingId, 'canUpdate' => $canUpdate, 'canDelete' => $canDelete ],
    ] )
</div>
