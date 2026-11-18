{{--
    Digital product panel (#105). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\DigitalPanel.

    Settings for downloadable products: the files buyers get, how often and
    how long they can download them, and whether each purchase issues a
    license key. Each file is a raised row with its label, version and two
    switches (streaming only, archived) above its source: a media-library
    chip with its name and size, or a disk and a path. Store defaults are
    named in the hints, and the license fields appear only while "Issue a
    license key" is on.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\DigitalPanel;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use Illuminate\Support\Js;

    $files = (array) ( $state['files'] ?? [] );
@endphp
<div data-digital-panel>
    <x-artisanpack-card :classes="[ 'body' => 'ec-stack-lg' ]">
        <section aria-labelledby="digital-files-heading" class="ec-stack-sm">
            <x-artisanpack-heading :level="3" :size="5" id="digital-files-heading">{{ __( 'Files' ) }}</x-artisanpack-heading>

            @if ( [] === $files )
                <p class="ec-muted">{{ __( 'Attach the files buyers can download or stream.' ) }}</p>
            @endif

            @error( 'state.files' )
                <p class="ec-field-error" role="alert"><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
            @enderror

            @if ( [] !== $files )
                <ul class="ec-list">
                    @foreach ( $files as $i => $file )
                        @php
                            $fileLabel = filled( $file['label'] ?? null ) ? (string) $file['label'] : __( 'File :number', [ 'number' => $i + 1 ] );
                            $mediaId   = is_numeric( $file['media_id'] ?? null ) ? (int) $file['media_id'] : null;
                            $media     = null === $mediaId ? null : ( $mediaDetails[ $mediaId ] ?? null );
                        @endphp
                        <li wire:key="digital-file-{{ $file['id'] ?? 'new-' . $i }}" class="ec-item ec-file" data-digital-file="{{ $i }}">
                            <div class="ec-field-grid ec-file-fields">
                                <x-artisanpack-input id="digital-{{ $i }}-label" :label="__( 'Label of file :number', [ 'number' => $i + 1 ] )" wire:model.live.blur="state.files.{{ $i }}.label" :disabled="$readOnly" />
                                <x-artisanpack-input id="digital-{{ $i }}-version" dir="ltr" :label="__( 'Version of :file', [ 'file' => $fileLabel ] )" :hint="__( 'Changing it on a saved file emails past buyers.' )" wire:model.live.blur="state.files.{{ $i }}.version" :disabled="$readOnly" />
                                <div class="ec-stack-sm ec-file-switches">
                                    <x-artisanpack-toggle id="digital-{{ $i }}-streaming" size="sm" :label="__( 'Streaming only' )" :description="__( 'Can be played but never downloaded.' )" wire:model="state.files.{{ $i }}.is_streaming_only" :disabled="$readOnly" />
                                    <x-artisanpack-toggle id="digital-{{ $i }}-archived" size="sm" :label="__( 'Archived' )" :description="__( 'Existing buyers keep their downloads; the file is no longer offered.' )" wire:model="state.files.{{ $i }}.is_archived" :disabled="$readOnly" />
                                </div>
                            </div>

                            @if ( 'media' === ( $file['source'] ?? 'path' ) )
                                <div class="ec-file-chip" data-media-file>
                                    <x-artisanpack-icon name="file-text" variant="tile" size="sm" />
                                    <div class="ec-file-chip-text">
                                        <span class="ec-file-chip-title">{{ null === $mediaId ? __( 'No file chosen yet.' ) : __( 'Media library file #:id', [ 'id' => $mediaId ] ) }}</span>
                                        @if ( null !== $media && '' !== $media['name'] )
                                            <bdi dir="ltr" class="ec-file-chip-meta">{{ null === $media['size'] ? $media['name'] : $media['name'] . ' · ' . $media['size'] }}</bdi>
                                        @endif
                                        @error( 'state.files.' . $i . '.media_id' )
                                            <span class="ec-field-error" role="alert"><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</span>
                                        @enderror
                                    </div>
                                    @if ( $mediaLibrary && ! $readOnly )
                                        <x-artisanpack-button
                                            variant="outline"
                                            color="neutral"
                                            size="xs"
                                            leading-icon="refresh-cw"
                                            x-on:click="Livewire.dispatch( 'open-media-modal', { context: {{ Js::from( DigitalPanel::MEDIA_CONTEXT . $i ) }} } )"
                                            :aria-label="null === $mediaId ? __( 'Choose file for :file', [ 'file' => $fileLabel ] ) : __( 'Replace file for :file', [ 'file' => $fileLabel ] )"
                                        >{{ null === $mediaId ? __( 'Choose file' ) : __( 'Replace file' ) }}</x-artisanpack-button>
                                    @endif
                                    @unless ( $readOnly )
                                        <x-artisanpack-button variant="ghost" color="neutral" size="xs" leading-icon="trash" wire:click="removeFile( {{ $i }} )" wire:loading.attr="disabled" wire:confirm="{{ __( 'Remove this file when you save? A file customers already bought can\'t be removed; archive it instead.' ) }}" :aria-label="__( 'Remove :file', [ 'file' => $fileLabel ] )" />
                                    @endunless
                                </div>
                            @else
                                <div class="ec-file-source">
                                    <x-artisanpack-select placeholder="" id="digital-{{ $i }}-disk" class="ec-file-disk" :label="__( 'Disk' )" :options="ControlOptions::from( $diskOptions )" wire:model="state.files.{{ $i }}.disk" :disabled="$readOnly" />
                                    <x-artisanpack-input id="digital-{{ $i }}-path" class="ec-file-path ec-mono-input" dir="ltr" :label="__( 'Path of :file on the disk', [ 'file' => $fileLabel ] )" :placeholder="__( 'downloads/guide.pdf' )" wire:model.live.blur="state.files.{{ $i }}.path" :disabled="$readOnly" />
                                    @unless ( $readOnly )
                                        <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="trash" class="ec-file-remove" wire:click="removeFile( {{ $i }} )" wire:loading.attr="disabled" wire:confirm="{{ __( 'Remove this file when you save? A file customers already bought can\'t be removed; archive it instead.' ) }}" :aria-label="__( 'Remove :file', [ 'file' => $fileLabel ] )" />
                                    @endunless
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @unless ( $readOnly )
                <div>
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="plus" wire:click="addFile">{{ __( 'Add file' ) }}</x-artisanpack-button>
                </div>
            @endunless
        </section>

        <x-artisanpack-divider />

        <section aria-labelledby="digital-access-heading" class="ec-stack-sm">
            <x-artisanpack-heading :level="3" :size="5" id="digital-access-heading">{{ __( 'Download access' ) }}</x-artisanpack-heading>
            <div class="ec-field-grid">
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

        <x-artisanpack-divider />

        <section aria-labelledby="digital-licensing-heading" class="ec-stack-sm">
            <x-artisanpack-heading :level="3" :size="5" id="digital-licensing-heading">{{ __( 'License keys' ) }}</x-artisanpack-heading>
            <x-artisanpack-toggle id="digital-licensing" :label="__( 'Issue a license key with each purchase' )" wire:model.live="state.licensing_enabled" :disabled="$readOnly" />

            @if ( true === ( $state['licensing_enabled'] ?? false ) )
                <div class="ec-field-grid" data-licensing-fields>
                    <x-artisanpack-input id="digital-activations" type="number" min="1" step="1" :label="__( 'Activations per key' )" :hint="__( 'Multiplied by the quantity bought. Empty uses the store default.' )" wire:model="state.activations_limit" :disabled="$readOnly" />
                    <x-artisanpack-input id="digital-license-expiry" type="number" min="1" step="1" :label="__( 'Days until keys expire' )" :hint="__( 'Empty uses the store default.' )" wire:model="state.license_expires_in_days" :disabled="$readOnly" />
                </div>
            @endif
        </section>
    </x-artisanpack-card>
</div>
