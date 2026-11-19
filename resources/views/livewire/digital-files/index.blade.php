{{--
    Digital files (#126). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\DigitalFiles\Index.

    Every downloadable file across the catalog on the shared Resource table
    (#79): its product, label, version, where it's stored and one status
    badge (Offered, Streaming only or Archived). New file is the page's one
    solid action. Files are created and edited in a drawer, with Save in its
    footer; saving a new version of a file reads "Save and notify customers",
    beside a warning that says the same. A delete keeps files customers
    bought and offers "Archive instead" for them in a warning above the table.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\DigitalFiles\Index;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\DigitalDisks;
    use Illuminate\Support\Js;

    $sourceOptions = [
        [ 'id' => 'media', 'name' => __( 'Media library' ) ],
        [ 'id' => 'path', 'name' => __( 'Path on a disk' ) ],
    ];
    $formMediaId   = is_numeric( $form['media_id'] ?? null ) ? (int) $form['media_id'] : null;
    $formMediaName = null === $formMediaId ? null : ( $mediaNames[ $formMediaId ] ?? null );
@endphp
<div class="ec-stack" data-digital-files-index>
    <x-artisanpack-page-header :title="__( 'Digital files' )" :description="__( 'Every file attached to a digital product, with its version and where it’s stored.' )" :level="1">
        @if ( $canCreate )
            <x-slot:actions>
                <x-artisanpack-button size="sm" leading-icon="plus" wire:click="create" data-new-file>{{ __( 'New file' ) }}</x-artisanpack-button>
            </x-slot:actions>
        @endif
    </x-artisanpack-page-header>

    @if ( $keptCount > 0 )
        <x-artisanpack-alert
            color="warning"
            icon="triangle-alert"
            :title="trans_choice( ':count file has buyers and was kept.|:count files have buyers and were kept.', $keptCount, [ 'count' => $keptCount ] )"
            :description="__( 'Archive files instead of deleting them: existing buyers keep their downloads, and the file is no longer offered.' )"
            data-kept-files
        >
            <x-slot:actions>
                @if ( $canUpdate )
                    <x-artisanpack-button size="sm" color="warning" leading-icon="archive" wire:click="archiveKept" wire:loading.attr="disabled" data-archive-kept>{{ __( 'Archive instead' ) }}</x-artisanpack-button>
                @endif
                <x-artisanpack-button size="sm" variant="ghost" color="neutral" wire:click="dismissKept" data-dismiss-kept>{{ __( 'Dismiss' ) }}</x-artisanpack-button>
            </x-slot:actions>
        </x-artisanpack-alert>
    @endif

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'file-down',
        'emptyTitle'       => __( 'No digital files yet' ),
        'emptyDescription' => __( 'Files attached to digital products appear here.' ),
        'searchHint'       => __( 'Search by label or product' ),
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\DigitalFile $file ): string => (string) $file->label,
        'cellContext'      => [ 'canUpdate' => $canUpdate, 'canDelete' => $canDelete, 'canViewProducts' => $canViewProducts, 'mediaNames' => $mediaNames ],
    ] )

    <x-artisanpack-drawer
        wire:model="editing"
        :title="null === $fileId ? __( 'New digital file' ) : __( 'Edit digital file' )"
        side="end"
        size="md"
    >
        @if ( $editing )
            <form id="digital-file-form" wire:submit="save" class="ec-form-stack ec-stack-lg" data-digital-file-form>
                @include( 'ecommerce-admin::partials.error-summary' )

                <div class="ec-stack-sm">
                    <x-artisanpack-ec-product-picker
                        id="digital-file-product"
                        model="form.product_id"
                        single
                        live
                        :label="__( 'Product' )"
                        :options="$this->optionsForPicker( 'product', 'form.product_id' )"
                    />
                    @error( 'form.product_id' )
                        <p class="ec-field-error" role="alert"><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
                    @enderror
                </div>

                @if ( [] !== $variantOptions )
                    <x-artisanpack-select
                        id="digital-file-variant"
                        :label="__( 'Variant' )"
                        :options="ControlOptions::from( $variantOptions )"
                        :placeholder="__( 'All variants (the product itself)' )"
                        wire:model="form.product_variant_id"
                    />
                @endif

                <div class="ec-field-grid">
                    <x-artisanpack-input id="digital-file-label" :label="__( 'Label' )" wire:model="form.label" required />
                    <x-artisanpack-input id="digital-file-version" dir="ltr" :label="__( 'Version' )" wire:model.live.debounce.400ms="form.version" />
                </div>

                @if ( $versionBumped )
                    <x-artisanpack-alert
                        color="warning"
                        icon="mail"
                        :title="__( 'Customers will be notified' )"
                        :description="__( 'Saving a new version emails everyone who bought this file to tell them an update is available.' )"
                        data-version-warning
                    />
                @endif

                <x-artisanpack-toggle id="digital-file-streaming" :label="__( 'Streaming only' )" :description="__( 'Can be played but never downloaded.' )" wire:model="form.is_streaming_only" />

                <x-artisanpack-toggle id="digital-file-archived" :label="__( 'Archived' )" :description="__( 'Existing buyers keep their downloads; the file is no longer offered.' )" wire:model="form.is_archived" />

                <x-artisanpack-radio-group id="digital-file-source" :label="__( 'File' )" :options="ControlOptions::from( $sourceOptions )" wire:model.live="form.source" orientation="horizontal" />

                @if ( 'media' === $form['source'] )
                    <div class="ec-stack-sm">
                        <div class="ec-file-chip" data-media-file>
                            <x-artisanpack-icon name="file-down" variant="tile" size="sm" />
                            <div class="ec-file-chip-text">
                                <span class="ec-file-chip-title">
                                    @if ( null === $formMediaId )
                                        {{ __( 'No file chosen yet.' ) }}
                                    @else
                                        {{ __( 'Media library file #:id', [ 'id' => $formMediaId ] ) }}
                                    @endif
                                </span>
                                @if ( null !== $formMediaName )
                                    <bdi dir="ltr" class="ec-file-chip-meta">{{ $formMediaName }}</bdi>
                                @endif
                            </div>
                            @if ( $mediaLibrary )
                                <x-artisanpack-button
                                    variant="outline"
                                    color="neutral"
                                    size="xs"
                                    aria-describedby="digital-file-media-hint"
                                    x-on:click="Livewire.dispatch( 'open-media-modal', { context: {{ Js::from( Index::MEDIA_CONTEXT ) }} } )"
                                >{{ null === $formMediaId ? __( 'Choose file' ) : __( 'Replace file' ) }}</x-artisanpack-button>
                            @endif
                        </div>
                        @unless ( $mediaLibrary )
                            <x-artisanpack-input id="digital-file-media" type="number" min="1" :label="__( 'Media id' )" aria-describedby="digital-file-media-hint" wire:model="form.media_id" />
                        @endunless
                        @error( 'form.media_id' )
                            <p class="ec-field-error" role="alert"><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
                        @enderror
                        <p id="digital-file-media-hint" class="ec-muted">{{ __( 'A media-library file must be stored on a private disk (:disks), or buyers could download it without a link.', [ 'disks' => implode( ', ', DigitalDisks::allowed() ) ] ) }}</p>
                    </div>
                @else
                    <div class="ec-field-grid">
                        <x-artisanpack-select placeholder="" id="digital-file-disk" :label="__( 'Disk' )" :options="ControlOptions::from( $diskOptions )" wire:model="form.disk" />
                        <x-artisanpack-input id="digital-file-path" class="ec-mono-input" dir="ltr" :label="__( 'Path on the disk' )" :placeholder="__( 'downloads/guide.pdf' )" wire:model="form.path" />
                    </div>
                @endif
            </form>
        @endif

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'editing', false )">{{ __( 'Cancel' ) }}</x-artisanpack-button>
            <x-artisanpack-button type="submit" form="digital-file-form" :leading-icon="$versionBumped ? 'mail' : null" wire:loading.attr="disabled" data-save-file>{{ $versionBumped ? __( 'Save and notify customers' ) : __( 'Save file' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-drawer>

    @if ( $mediaLibrary )
        <livewire:dynamic-component :is="\ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia::modal()" :multi-select="false" key="digital-files-media-modal" />
    @endif
</div>
