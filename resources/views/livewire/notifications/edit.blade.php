{{--
    Notification template editor. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notifications\Edit.

    The form sits on the start side, Variables and the live Preview on the
    end side. The code editors stay left to right in RTL, the email preview
    always renders on white like an inbox, and SMS shows its length and a
    message bubble instead of a subject and a test send.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\UnsavedChanges;
    use Illuminate\Support\Js;

    $errorCount   = count( $previewErrors );
    $errorTitle   = trans_choice( 'The template has :count error.|The template has :count errors.', $errorCount, [ 'count' => $errorCount ] );
    $isStale      = 0 !== $errorCount;
    $smsSummary   = null;

    if ( null !== $sms ) {
        $smsSummary = strtr( e( __( ':used characters · :messages' ) ), [
            ':used'     => '<bdi dir="ltr">' . e( $sms['characters'] . ' / ' . $sms['limit'] ) . '</bdi>',
            ':messages' => e( trans_choice( ':count message|:count messages', $sms['messages'], [ 'count' => $sms['messages'] ] ) ),
        ] );
    }
@endphp
<div
    class="ec-stack ec-notif-edit"
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
    data-channel="{{ $template->channel }}"
>
    <x-artisanpack-page-header :title="$label" :level="1">
        <x-slot:description><bdi dir="ltr" class="ec-mono">{{ $template->key }}</bdi> · {{ $channelLabel }}</x-slot:description>
        <x-slot:actions>
            @if ( null !== $indexUrl )
                <x-artisanpack-button variant="ghost" color="neutral" size="sm" :href="$indexUrl" data-back-link>{{ __( 'All notifications' ) }}</x-artisanpack-button>
            @endif
        </x-slot:actions>
    </x-artisanpack-page-header>

    <div class="ec-notif-edit-controls" data-template-controls>
        <div class="ec-notif-edit-control">
            <x-artisanpack-select
                placeholder=""
                id="notification-locale"
                size="sm"
                :label="__( 'Language' )"
                :options="ControlOptions::from( $localeOptions )"
                wire:model.live="localeId"
            />
        </div>

        @if ( $canUpdate && [] !== $newLocales )
            <form wire:submit="addLocale" class="ec-notif-edit-add" data-add-locale>
                <div class="ec-notif-edit-control">
                    <x-artisanpack-select
                        id="notification-new-locale"
                        size="sm"
                        :label="__( 'Add a translation' )"
                        :options="ControlOptions::from( $newLocales )"
                        :placeholder="__( 'Choose a language' )"
                        wire:model="newLocale"
                    />
                </div>
                <x-artisanpack-button type="submit" variant="outline" color="neutral" size="sm" leading-icon="plus" wire:loading.attr="disabled" wire:target="addLocale">{{ __( 'Add' ) }}</x-artisanpack-button>
            </form>
        @endif

        <div class="ec-notif-edit-active">
            <x-artisanpack-toggle
                id="notification-active"
                :label="__( 'Send this notification' )"
                wire:model.live="isActive"
                :checked="$isActive"
                :disabled="! $canUpdate"
                data-active-toggle
            />
        </div>
    </div>

    <div class="ec-notif-edit-split">
        <div class="ec-notif-edit-col">
            <form wire:submit="save" class="apui-card" data-surface="default" data-template-form>
                <div data-scope="card" data-part="body" class="ec-notif-edit-form">
                    @if ( $saveFailed )
                        @include( 'ecommerce-admin::partials.error-summary' )
                    @endif

                    @if ( $hasSubject )
                        <x-artisanpack-input
                            id="notification-subject"
                            class="ec-mono-input"
                            dir="auto"
                            :label="__( 'Subject' )"
                            wire:model.live.debounce.500ms="subject"
                            x-on:focusin="target = 'subject'"
                            :readonly="! $canUpdate"
                        />
                    @endif

                    @if ( $canUpdate )
                        <div class="ec-notif-edit-code" x-on:focusin="target = 'body'" x-on:keyup.debounce.600ms="$wire.$commit()" x-on:paste.debounce.600ms="$wire.$commit()" data-body-editor>
                            <x-artisanpack-code-editor
                                id="notification-body"
                                :label="__( 'Body' )"
                                :hint="__( 'Twig source. Click a variable to insert it at the cursor.' )"
                                language="twig"
                                :rows="$isSms ? 4 : 14"
                                :value="$body"
                                :diagnostics="$diagnostics"
                                wire:model="body"
                            />
                        </div>
                    @else
                        <div class="ec-notif-edit-code" data-body-readonly>
                            <x-artisanpack-code-editor
                                id="notification-body"
                                :label="__( 'Body' )"
                                language="twig"
                                :rows="$isSms ? 4 : 14"
                                :value="$body"
                                readonly
                            />
                        </div>
                    @endif

                    @if ( null !== $smsSummary )
                        <p class="ec-muted ec-notif-edit-sms-count" data-sms-count>{!! $smsSummary !!}</p>
                    @endif

                    @if ( $canUpdate )
                        <div class="ec-notif-edit-code" x-on:keyup.debounce.600ms="$wire.$commit()" x-on:paste.debounce.600ms="$wire.$commit()" data-preview-data>
                            <x-artisanpack-code-editor
                                id="notification-preview-data"
                                :label="__( 'Preview data (JSON)' )"
                                :hint="__( 'Sample values the preview renders with. They are saved with the template but never sent.' )"
                                language="json"
                                :rows="8"
                                :value="$previewJson"
                                wire:model="previewJson"
                            />
                        </div>
                    @else
                        <div class="ec-notif-edit-code" data-preview-data-readonly>
                            <x-artisanpack-code-editor
                                id="notification-preview-data"
                                :label="__( 'Preview data (JSON)' )"
                                language="json"
                                :rows="8"
                                :value="$previewJson"
                                readonly
                            />
                        </div>
                    @endif

                    @if ( $canUpdate )
                        <div class="ec-notif-edit-actions">
                            @if ( $hasDefault )
                                <x-artisanpack-button variant="ghost" color="neutral" leading-icon="rotate-ccw" wire:click="confirmReset" data-reset>{{ __( 'Reset to default' ) }}</x-artisanpack-button>
                            @endif
                            <span class="ec-notif-edit-spacer" aria-hidden="true"></span>
                            @if ( $isHtml )
                                <x-artisanpack-button variant="outline" color="neutral" leading-icon="mail" wire:click="sendTest" wire:loading.attr="disabled" wire:target="sendTest" data-send-test>{{ __( 'Send test to me' ) }}</x-artisanpack-button>
                            @endif
                            <x-artisanpack-button
                                type="submit"
                                leading-icon="check"
                                wire:loading.attr="disabled"
                                wire:target="save"
                                :disabled="$isStale"
                                data-save>{{ __( 'Save template' ) }}</x-artisanpack-button>
                        </div>
                    @endif
                </div>
            </form>
        </div>

        <div class="ec-notif-edit-col" role="region" aria-label="{{ __( 'Variables and preview' ) }}">
            <section class="apui-card" data-surface="default" aria-labelledby="notification-variables-title" data-variables>
                <div data-scope="card" data-part="header">
                    <h2 data-scope="card" data-part="title" id="notification-variables-title">{{ __( 'Variables' ) }}</h2>
                </div>
                <div data-scope="card" data-part="body">
                    @if ( [] === $variables )
                        <p class="ec-muted">{{ __( 'This template declares no variables.' ) }}</p>
                    @else
                        <ul class="ec-notif-edit-vars">
                            @foreach ( $variables as $variable )
                                <li wire:key="variable-{{ $variable['path'] }}">
                                    <button
                                        type="button"
                                        class="apui-badge ec-notif-edit-var"
                                        data-variant="soft"
                                        data-color="neutral"
                                        data-size="md"
                                        x-on:click="insert( {{ Js::from( $variable['snippet'] ) }} )"
                                        @disabled( ! $canUpdate )
                                        aria-label="{{ __( 'Insert :variable', [ 'variable' => $variable['path'] ] ) }}"
                                    ><span data-scope="badge" data-part="icon" aria-hidden="true"><x-artisanpack-icon name="plus" aria-hidden="true" /></span><bdi dir="ltr" data-scope="badge" data-part="label">{{ $variable['path'] }}</bdi></button>
                                </li>
                            @endforeach
                        </ul>
                        <p class="ec-muted ec-notif-edit-vars-hint">{{ __( 'Inserts into the subject or body, wherever you were typing last.' ) }}</p>
                    @endif
                </div>
            </section>

            <section class="apui-card" data-surface="default" aria-labelledby="notification-preview-title" data-preview>
                <div data-scope="card" data-part="header">
                    <h2 data-scope="card" data-part="title" id="notification-preview-title">{{ __( 'Preview' ) }}</h2>
                </div>
                <div data-scope="card" data-part="body" class="ec-notif-edit-preview">
                    {{-- Announce the outcome, not the whole preview, on every keystroke. --}}
                    @include( 'ecommerce-admin::partials.live-region', [ 'message' => $isStale ? $errorTitle : __( 'Preview updated.' ), 'revision' => $previewRevision ] )

                    @if ( $isStale )
                        <x-artisanpack-alert
                            color="error"
                            icon="circle-alert"
                            :title="$errorTitle"
                            :description="__( 'The preview is not up to date, and the template cannot be saved until these are fixed.' )"
                            data-preview-errors
                        />
                        {{-- Every error, not only the first per field that the field shows under itself. --}}
                        <ul class="ec-notif-edit-errors" data-preview-error-list>
                            @foreach ( $previewErrors as $previewError )
                                <li dir="auto" wire:key="preview-error-{{ $loop->index }}">{{ $previewError['message'] }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="ec-muted ec-notif-edit-status" aria-hidden="true" data-preview-status><x-artisanpack-icon name="circle-check" aria-hidden="true" />{{ __( 'Preview updated.' ) }}</p>
                    @endif

                    @if ( $isHtml )
                        <div @class( [ 'ec-notif-edit-email', 'ec-notif-edit-stale' => $isStale ] ) data-preview-email>
                            @if ( $hasSubject )
                                <p class="ec-notif-edit-email-head" data-preview-subject>
                                    {{ __( 'Subject:' ) }}
                                    <strong dir="auto">{{ $previewSubject }}</strong>
                                </p>
                            @endif
                            <iframe
                                sandbox=""
                                srcdoc="{{ $previewBody }}"
                                title="{{ __( 'Email preview' ) }}"
                                class="ec-notif-edit-email-frame"
                                data-preview-body
                            ></iframe>
                        </div>
                    @elseif ( $isSms )
                        <div @class( [ 'ec-notif-edit-sms', 'ec-notif-edit-stale' => $isStale ] ) data-preview-sms>
                            <p class="ec-notif-edit-sms-bubble" dir="auto" data-preview-body>{{ $previewBody }}</p>
                            <span class="ec-muted">{{ __( 'SMS preview' ) }}</span>
                        </div>
                    @else
                        @if ( $hasSubject )
                            <p class="ec-body" data-preview-subject>
                                <span class="ec-strong">{{ __( 'Subject:' ) }}</span>
                                <span dir="auto">{{ $previewSubject }}</span>
                            </p>
                        @endif
                        <pre @class( [ 'ec-notif-edit-text', 'ec-notif-edit-stale' => $isStale ] ) dir="auto" data-preview-body>{{ $previewBody }}</pre>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <x-artisanpack-dialog
        wire:model="confirmingReset"
        role="alertdialog"
        size="sm"
        color="warning"
        icon="rotate-ccw"
        :title="__( 'Reset to the default copy?' )"
        :close-label="__( 'Close' )"
        :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
        data-reset-confirm
    >
        <p class="ec-body">{{ __( 'The subject and body go back to the copy the store shipped with. Your edits to them are lost; the preview data and language stay.' ) }}</p>

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelReset" data-autofocus>{{ __( 'Keep my copy' ) }}</x-artisanpack-button>
            <x-artisanpack-button color="error" wire:click="resetToDefault" wire:loading.attr="disabled" wire:target="resetToDefault" data-reset-button>{{ __( 'Reset' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-dialog>
</div>
