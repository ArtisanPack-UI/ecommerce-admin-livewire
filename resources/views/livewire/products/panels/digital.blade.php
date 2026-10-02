{{--
    Digital product panel. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\DigitalPanel.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\DigitalPanel;

    $files = (array) ( $state['files'] ?? [] );
@endphp
<div class="flex flex-col gap-6" data-digital-panel>
    <section aria-labelledby="digital-files-heading">
        <h3 id="digital-files-heading" class="mb-2 text-lg font-semibold">{{ __( 'Files' ) }}</h3>

        @if ( [] === $files )
            <p class="mb-3 text-sm opacity-75">{{ __( 'Attach the files buyers can download or stream.' ) }}</p>
        @endif

        <ul class="flex list-none flex-col gap-3">
            @foreach ( $files as $i => $file )
                @php( $fileLabel = $file['label'] ?: __( 'File :number', [ 'number' => $i + 1 ] ) )
                <li wire:key="digital-file-{{ $file['id'] ?? 'new-' . $i }}" class="rounded-box border border-base-300 p-3" data-digital-file="{{ $i }}">
                    <div class="grid gap-3 md:grid-cols-3">
                        <x-artisanpack-input id="digital-{{ $i }}-label" :label="__( 'Label of file :number', [ 'number' => $i + 1 ] )" wire:model.blur="state.files.{{ $i }}.label" :disabled="$readOnly" />
                        <x-artisanpack-input id="digital-{{ $i }}-version" :label="__( 'Version of :file', [ 'file' => $fileLabel ] )" :hint="__( 'Changing it on a saved file emails past buyers.' )" wire:model.blur="state.files.{{ $i }}.version" :disabled="$readOnly" />
                        <div class="flex items-end">
                            <x-artisanpack-toggle id="digital-{{ $i }}-streaming" :label="__( 'Streaming only' )" :hint="__( 'Can be played but never downloaded.' )" wire:model="state.files.{{ $i }}.is_streaming_only" :disabled="$readOnly" />
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-end gap-3">
                        @if ( 'media' === ( $file['source'] ?? 'path' ) )
                            <span class="text-sm" data-media-file>
                                {{ null === $file['media_id'] ? __( 'No file chosen yet.' ) : __( 'Media library file #:id', [ 'id' => $file['media_id'] ] ) }}
                            </span>
                            @error( 'state.files.' . $i . '.media_id' )
                                <span class="text-sm text-error" role="alert">{{ $message }}</span>
                            @enderror
                            @if ( $mediaLibrary && ! $readOnly )
                                <x-artisanpack-button variant="outline" size="sm" icon="o-paper-clip" x-on:click="Livewire.dispatch( 'open-media-modal', { context: @js( DigitalPanel::MEDIA_CONTEXT . $i ) } )" :label="__( 'Choose file' )" :aria-label="__( 'Choose file for :file', [ 'file' => $fileLabel ] )" />
                            @endif
                        @else
                            <x-artisanpack-select id="digital-{{ $i }}-disk" :label="__( 'Disk' )" :options="$diskOptions" wire:model="state.files.{{ $i }}.disk" :disabled="$readOnly" />
                            <x-artisanpack-input id="digital-{{ $i }}-path" class="min-w-72" :label="__( 'Path of :file on the disk', [ 'file' => $fileLabel ] )" :placeholder="__( 'downloads/guide.pdf' )" wire:model.blur="state.files.{{ $i }}.path" :disabled="$readOnly" />
                        @endif

                        @unless ( $readOnly )
                            <x-artisanpack-button class="ms-auto" variant="ghost" size="sm" icon="o-trash" wire:click="removeFile( {{ $i }} )" wire:confirm="{{ __( 'Remove this file? Buyers lose access to it when you save.' ) }}" :aria-label="__( 'Remove :file', [ 'file' => $fileLabel ] )" />
                        @endunless
                    </div>
                </li>
            @endforeach
        </ul>

        @unless ( $readOnly )
            <x-artisanpack-button class="mt-3" variant="outline" size="sm" icon="o-plus" wire:click="addFile" :label="__( 'Add file' )" />
        @endunless
    </section>

    <section aria-labelledby="digital-access-heading">
        <h3 id="digital-access-heading" class="mb-2 text-lg font-semibold">{{ __( 'Download access' ) }}</h3>
        <div class="grid gap-4 md:grid-cols-2">
            <x-artisanpack-input
                id="digital-download-limit"
                type="number"
                min="0"
                step="1"
                :label="__( 'Downloads per purchase' )"
                :hint="__( 'Empty uses the store default (:count). 0 means unlimited.', [ 'count' => $defaultLimit ] )"
                wire:model="state.download_limit"
                :disabled="$readOnly"
            />
            <x-artisanpack-input
                id="digital-download-expiry"
                type="number"
                min="0"
                step="1"
                :label="__( 'Days until links expire' )"
                :hint="__( 'Empty uses the store default (:count). 0 means never.', [ 'count' => $defaultExpiry ] )"
                wire:model="state.download_expiry_days"
                :disabled="$readOnly"
            />
        </div>
    </section>

    <section aria-labelledby="digital-licensing-heading">
        <h3 id="digital-licensing-heading" class="mb-2 text-lg font-semibold">{{ __( 'License keys' ) }}</h3>
        <x-artisanpack-toggle id="digital-licensing" :label="__( 'Issue a license key with each purchase' )" wire:model.live="state.licensing_enabled" :disabled="$readOnly" />

        @if ( true === ( $state['licensing_enabled'] ?? false ) )
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <x-artisanpack-input id="digital-activations" type="number" min="1" step="1" :label="__( 'Activations per key' )" :hint="__( 'Multiplied by the quantity bought. Empty uses the store default.' )" wire:model="state.activations_limit" :disabled="$readOnly" />
                <x-artisanpack-input id="digital-license-expiry" type="number" min="1" step="1" :label="__( 'Days until keys expire' )" :hint="__( 'Empty uses the store default.' )" wire:model="state.license_expires_in_days" :disabled="$readOnly" />
            </div>
        @endif
    </section>
</div>
