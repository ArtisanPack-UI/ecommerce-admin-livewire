{{--
    Digital files. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\DigitalFiles\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\DigitalFiles\Index;

    $sourceOptions = [
        [ 'id' => 'media', 'name' => __( 'Media library' ) ],
        [ 'id' => 'path', 'name' => __( 'Path on a disk' ) ],
    ];
@endphp
<div>
    <x-artisanpack-header :title="__( 'Digital files' )" :level="1" separator>
        @if ( $canCreate )
            <x-slot:actions>
                <x-artisanpack-button color="primary" icon="o-plus" wire:click="create" :label="__( 'New file' )" />
            </x-slot:actions>
        @endif
    </x-artisanpack-header>

    @if ( $keptCount > 0 )
        <x-artisanpack-alert
            color="warning"
            icon="o-archive-box"
            :title="trans_choice( ':count file has buyers and was kept.|:count files have buyers and were kept.', $keptCount, [ 'count' => $keptCount ] )"
            :description="__( 'Archive files instead of deleting them: existing buyers keep their downloads, and the file is no longer offered.' )"
            role="status"
            class="mb-4"
            data-kept-files
        >
            <x-slot:actions>
                @if ( $canUpdate )
                    <x-artisanpack-button size="sm" color="primary" wire:click="archiveKept" wire:loading.attr="disabled" :label="__( 'Archive instead' )" />
                @endif
                <x-artisanpack-button size="sm" variant="ghost" wire:click="dismissKept" :label="__( 'Dismiss' )" />
            </x-slot:actions>
        </x-artisanpack-alert>
    @endif

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'o-arrow-down-tray',
        'emptyTitle'       => __( 'No digital files yet' ),
        'emptyDescription' => __( 'Files attached to digital products appear here.' ),
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\DigitalFile $file ): string => (string) $file->label,
        'cellContext'      => [ 'canUpdate' => $canUpdate, 'canDelete' => $canDelete ],
    ] )

    <x-artisanpack-drawer
        wire:model="editing"
        :title="null === $fileId ? __( 'New digital file' ) : __( 'Edit digital file' )"
        right
        separator
        with-close-button
        close-on-escape
        class="w-full max-w-xl"
    >
        @if ( $editing )
            <form wire:submit="save" class="flex flex-col gap-4" data-digital-file-form>
                @include( 'ecommerce-admin::partials.error-summary' )
                <x-artisanpack-ec-product-picker
                    id="digital-file-product"
                    model="form.product_id"
                    single
                    live
                    :label="__( 'Product' )"
                    :options="$this->optionsForPicker( 'product', 'form.product_id' )"
                />
                @error( 'form.product_id' )
                    <span class="text-sm text-error" role="alert">{{ $message }}</span>
                @enderror

                @if ( [] !== $variantOptions )
                    <x-artisanpack-select
                        id="digital-file-variant"
                        :label="__( 'Variant' )"
                        :options="$variantOptions"
                        :placeholder="__( 'All variants (the product itself)' )"
                        placeholder-value=""
                        wire:model="form.product_variant_id"
                    />
                @endif

                <x-artisanpack-input id="digital-file-label" :label="__( 'Label' )" wire:model="form.label" required />
                <x-artisanpack-input id="digital-file-version" :label="__( 'Version' )" wire:model.live.debounce.400ms="form.version" />

                @if ( $versionBumped )
                    <x-artisanpack-alert
                        color="warning"
                        icon="o-exclamation-triangle"
                        :title="__( 'Customers will be notified' )"
                        :description="__( 'Saving a new version emails everyone who bought this file to tell them an update is available.' )"
                        role="status"
                        data-version-warning
                    />
                @endif

                <x-artisanpack-toggle id="digital-file-streaming" :label="__( 'Streaming only' )" :hint="__( 'Can be played but never downloaded.' )" wire:model="form.is_streaming_only" />

                <x-artisanpack-toggle id="digital-file-archived" :label="__( 'Archived' )" :hint="__( 'Existing buyers keep their downloads; the file is no longer offered.' )" wire:model="form.is_archived" />

                <x-artisanpack-radio id="digital-file-source" :label="__( 'File' )" :hint="__( 'A media-library file must be stored on a private disk (:disks), or buyers could download it without a link.', [ 'disks' => implode( ', ', \ArtisanPackUI\EcommerceAdminLivewire\Support\DigitalDisks::allowed() ) ] )" :options="$sourceOptions" wire:model.live="form.source" inline />

                @if ( 'media' === $form['source'] )
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm" data-media-file>
                            {{ empty( $form['media_id'] ) ? __( 'No file chosen yet.' ) : __( 'Media library file #:id', [ 'id' => $form['media_id'] ] ) }}
                        </span>
                        @if ( $mediaLibrary )
                            <x-artisanpack-button variant="outline" size="sm" icon="o-paper-clip" x-on:click="Livewire.dispatch( 'open-media-modal', { context: {{ \Illuminate\Support\Js::from( Index::MEDIA_CONTEXT ) }} } )" :label="__( 'Choose file' )" />
                        @else
                            <x-artisanpack-input id="digital-file-media" type="number" min="1" :label="__( 'Media id' )" wire:model="form.media_id" />
                        @endif
                    </div>
                    @error( 'form.media_id' )
                        <span class="text-sm text-error" role="alert">{{ $message }}</span>
                    @enderror
                @else
                    <x-artisanpack-select id="digital-file-disk" :label="__( 'Disk' )" :options="$diskOptions" wire:model="form.disk" />
                    <x-artisanpack-input id="digital-file-path" :label="__( 'Path on the disk' )" :placeholder="__( 'downloads/guide.pdf' )" wire:model="form.path" />
                @endif

                <div class="flex justify-end gap-2">
                    <x-artisanpack-button variant="ghost" wire:click="$set( 'editing', false )" :label="__( 'Cancel' )" />
                    <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" :label="$versionBumped ? __( 'Save and notify customers' ) : __( 'Save file' )" />
                </div>
            </form>
        @endif
    </x-artisanpack-drawer>

    @if ( $mediaLibrary )
        <livewire:dynamic-component :is="\ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia::modal()" :multi-select="false" key="digital-files-media-modal" />
    @endif
</div>
