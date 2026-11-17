{{--
    Notification template editor. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notifications\Edit.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\UnsavedChanges;
@endphp
<div
    {!! UnsavedChanges::attributes() !!}
    x-data="{
        {!! UnsavedChanges::members() !!}
        target: 'body',
        insert( text ) {
            {{-- The CodeEditor's textarea carries the editor's id. --}}
            const field = document.getElementById( 'subject' === this.target ? 'notification-subject' : 'notification-body' );

            if ( ! field ) {
                $wire.set( 'body', ( $wire.body || '' ) + text );

                return;
            }

            const start = field.selectionStart ?? field.value.length;
            const end   = field.selectionEnd ?? field.value.length;

            field.value = field.value.slice( 0, start ) + text + field.value.slice( end );
            field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
            field.focus();
            field.setSelectionRange( start + text.length, start + text.length );
            $wire.$commit();
        },
    }"
    data-notification-editor="{{ $template->id }}"
>
    <x-artisanpack-page-header :title="$label" :description="$template->key . ' · ' . $channelLabel" :level="1">
        <x-slot:actions>
            @if ( null !== $indexUrl )
                <x-artisanpack-button variant="ghost" leading-icon="o-arrow-left" :href="$indexUrl">{{ __( 'All notifications' ) }}</x-artisanpack-button>
            @endif
        </x-slot:actions>
    </x-artisanpack-page-header>

    <div class="mb-4 flex flex-wrap items-end gap-3" data-template-controls>
        <x-artisanpack-select
            placeholder=""
            id="notification-locale"
            :label="__( 'Language' )"
            :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $localeOptions )"
            wire:model.live="localeId"
        />

        @if ( $canUpdate && [] !== $newLocales )
            <form wire:submit="addLocale" class="flex flex-wrap items-end gap-2" data-add-locale>
                <x-artisanpack-select
                    id="notification-new-locale"
                    :label="__( 'Add a translation' )"
                    :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $newLocales )"
                    :placeholder="__( 'Choose a language' )"
                    wire:model="newLocale"
                />
                <x-artisanpack-button type="submit" variant="outline" leading-icon="o-plus" wire:loading.attr="disabled">{{ __( 'Add' ) }}</x-artisanpack-button>
            </form>
        @endif

        <x-artisanpack-toggle
            id="notification-active"
            :label="__( 'Send this notification' )"
            wire:model.live="isActive"
            :disabled="! $canUpdate"
            data-active-toggle
        />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="save" class="flex flex-col gap-4 lg:col-span-2" data-template-form>
            @include( 'ecommerce-admin::partials.error-summary' )
            @if ( $hasSubject )
                <x-artisanpack-input
                    id="notification-subject"
                    :label="__( 'Subject' )"
                    wire:model.live.debounce.500ms="subject"
                    x-on:focusin="target = 'subject'"
                    :readonly="! $canUpdate"
                />
            @endif

            @if ( $canUpdate )
                <div x-on:focusin="target = 'body'" x-on:keyup.debounce.600ms="$wire.$commit()" x-on:paste.debounce.600ms="$wire.$commit()" data-body-editor>
                    <x-artisanpack-code-editor
                        id="notification-body"
                        :label="__( 'Body' )"
                        :hint="__( 'Twig source. Click a variable to insert it at the cursor.' )"
                        language="twig"
                        :rows="20"
                        wire:model="body"
                    />
                </div>

                <div x-on:keyup.debounce.600ms="$wire.$commit()" x-on:paste.debounce.600ms="$wire.$commit()" data-preview-data>
                    <x-artisanpack-code-editor
                        id="notification-preview-data"
                        :label="__( 'Preview data (JSON)' )"
                        :hint="__( 'Sample values the preview renders with. They are saved with the template but never sent.' )"
                        language="json"
                        :rows="10"
                        wire:model="previewJson"
                    />
                </div>
            @else
                <div class="fieldset" data-body-readonly>
                    <span class="fieldset-legend">{{ __( 'Body' ) }}</span>
                    <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-box border border-base-300 p-3 font-mono text-sm">{{ $body }}</pre>
                </div>
                <div class="fieldset" data-preview-data-readonly>
                    <span class="fieldset-legend">{{ __( 'Preview data (JSON)' ) }}</span>
                    <pre class="max-h-48 overflow-auto whitespace-pre-wrap rounded-box border border-base-300 p-3 font-mono text-sm">{{ $previewJson }}</pre>
                </div>
            @endif

            @if ( $canUpdate )
                <div class="flex flex-wrap justify-end gap-2">
                    @if ( $hasDefault )
                        <x-artisanpack-button variant="ghost" leading-icon="o-arrow-uturn-left" wire:click="confirmReset">{{ __( 'Reset to default' ) }}</x-artisanpack-button>
                    @endif
                    @if ( $isHtml )
                        <x-artisanpack-button variant="outline" leading-icon="o-paper-airplane" wire:click="sendTest" wire:loading.attr="disabled">{{ __( 'Send test to me' ) }}</x-artisanpack-button>
                    @endif
                    <x-artisanpack-button
                        type="submit"
                        color="primary"
                        wire:loading.attr="disabled"
                        :disabled="[] !== $previewErrors">{{ __( 'Save template' ) }}</x-artisanpack-button>
                </div>
            @endif
        </form>

        <aside class="flex flex-col gap-4" aria-label="{{ __( 'Variables and preview' ) }}">
            <section aria-labelledby="notification-variables-title" data-variables>
                <h2 id="notification-variables-title" class="mb-2 font-semibold">{{ __( 'Variables' ) }}</h2>
                @if ( [] === $variables )
                    <p class="text-sm opacity-75">{{ __( 'This template declares no variables.' ) }}</p>
                @else
                    <p class="mb-2 text-sm opacity-75">{{ __( 'Inserts into the subject or body, wherever you were typing last.' ) }}</p>
                    <ul class="flex max-h-64 list-none flex-wrap gap-1 overflow-y-auto">
                        @foreach ( $variables as $variable )
                            <li wire:key="variable-{{ $variable['path'] }}">
                                <x-artisanpack-button
                                    variant="ghost"
                                    size="xs"
                                    class="font-mono"
                                    x-on:click="insert( {{ \Illuminate\Support\Js::from( $variable['snippet'] ) }} )"
                                    :disabled="! $canUpdate"
                                    :aria-label="__( 'Insert :variable', [ 'variable' => $variable['path'] ] )">{{ $variable['path'] }}</x-artisanpack-button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section aria-labelledby="notification-preview-title" data-preview>
                <h2 id="notification-preview-title" class="mb-2 font-semibold">{{ __( 'Preview' ) }}</h2>

                {{-- Announce the outcome, not the whole preview, on every keystroke. --}}
                @include( 'ecommerce-admin::partials.live-region', [ 'message' => [] === $previewErrors ? __( 'Preview updated.' ) : trans_choice( 'The template has :count error.|The template has :count errors.', count( $previewErrors ), [ 'count' => count( $previewErrors ) ] ), 'revision' => $previewRevision ] )

                @if ( [] !== $previewErrors )
                    <x-artisanpack-alert
                        color="error"
                        icon="o-exclamation-triangle"
                        :title="trans_choice( 'The template has :count error.|The template has :count errors.', count( $previewErrors ), [ 'count' => count( $previewErrors ) ] )"
                        :description="__( 'The preview is not up to date, and the template cannot be saved until these are fixed.' )"
                        data-preview-errors
                    />
                    <ul class="mt-2 list-disc ps-5 text-sm text-error">
                        @foreach ( $previewErrors as $error )
                            <li>{{ $error['message'] }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ( $hasSubject )
                    <p class="mb-2 text-sm" data-preview-subject>
                        <span class="font-semibold">{{ __( 'Subject:' ) }}</span>
                        {{ $previewSubject }}
                    </p>
                @endif

                @if ( $isHtml )
                    <iframe
                        sandbox=""
                        srcdoc="{{ $previewBody }}"
                        title="{{ __( 'Email preview' ) }}"
                        class="h-96 w-full rounded-box border border-base-300 bg-white"
                        data-preview-body
                    ></iframe>
                @else
                    <pre class="whitespace-pre-wrap rounded-box border border-base-300 p-3 text-sm" data-preview-body>{{ $previewBody }}</pre>
                @endif
            </section>
        </aside>
    </div>

    <x-artisanpack-dialog wire:model="confirmingReset" :title="__( 'Reset to the default copy?' )">
        <p>{{ __( 'The subject and body go back to the copy the store shipped with. Your edits to them are lost; the preview data and language stay.' ) }}</p>

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" wire:click="cancelReset">{{ __( 'Keep my copy' ) }}</x-artisanpack-button>
            <x-artisanpack-button color="error" wire:click="resetToDefault" wire:loading.attr="disabled">{{ __( 'Reset' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-dialog>
</div>
