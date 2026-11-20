{{--
    Webhook secret dialogs (#91), shared by the webhook screens. See
    ManagesWebhookSubscriptions.

    Rotate confirms a change that breaks deliveries until the receiver is
    updated: a warning-toned Dialog that opens with focus on the safe
    choice, "Keep current secret". Reveal shows a new secret once: an
    alertdialog with no close button that ignores Escape and the backdrop,
    and closes only when the person says they have saved it. Focus starts
    on Copy secret; the secret is read-only, monospace and left to right,
    and the copy result is announced. If the clipboard is blocked, the
    secret is selected and the message says how to copy it.

    Expects $rotating (the subscription waiting for confirmation, or null)
    and $rotateToken.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( null !== $rotating )
    {{-- Built outside the tag: a nested double quote in a component attribute stops Blade compiling it. --}}
    @php( $rotateTitle = __( 'Rotate the secret of ":name"?', [ 'name' => $rotating->name ] ) )
    <x-artisanpack-dialog
        wire:model="confirmingRotate"
        color="warning"
        icon="rotate-cw"
        :title="$rotateTitle"
        :description="__( 'A new signing secret replaces the current one straight away. Deliveries signed with the old secret will fail verification until the receiving endpoint is updated.' )"
        :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
        data-rotate-secret
    >
        <x-slot:footer>
            <x-artisanpack-button variant="outline" color="neutral" wire:click="cancelRotate" data-autofocus>{{ __( 'Keep current secret' ) }}</x-artisanpack-button>
            <x-artisanpack-button
                color="warning"
                leading-icon="rotate-cw"
                wire:click="rotateSecret( {{ \Illuminate\Support\Js::from( $rotateToken ) }} )"
                wire:loading.attr="disabled">{{ __( 'Rotate secret' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-dialog>
@endif

@if ( $showingSecret )
    <x-artisanpack-dialog
        wire:model.live="showingSecret"
        role="alertdialog"
        hide-close
        color="primary"
        icon="key"
        :title="__( 'Signing secret' )"
        :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
    >
        {{-- The secret is in this render only; wire:ignore keeps it on screen through later renders. --}}
        {{-- Escape is stopped before the dialog sees it, so it can't lose the one-time secret. --}}
        <div class="ec-secret-reveal" data-revealed-secret wire:ignore x-data x-on:keydown.escape.window.capture.stop.prevent="">
            <x-artisanpack-alert
                color="warning"
                icon="triangle-alert"
                :title="__( 'Copy this secret now' )"
                :description="__( 'It is shown only once. Store it with the receiving endpoint to verify signatures.' )"
            />
            <div class="ec-secret-field">
                <span class="ec-field-label" id="webhook-secret-label">{{ __( 'Secret' ) }}</span>
                <x-artisanpack-clipboard
                    variant="field"
                    size="md"
                    class="ec-secret"
                    role="group"
                    aria-labelledby="webhook-secret-label"
                    :value="$revealedSecret ?? ''"
                    :label="__( 'Copy secret' )"
                    :copied-label="__( 'Copied' )"
                    :copied-message="__( 'Copied.' )"
                    :error-message="__( 'Copy failed. The secret is selected; press Ctrl+C.' )"
                />
            </div>
        </div>

        <x-slot:footer>
            <x-artisanpack-button wire:click="dismissSecret">{{ __( 'I have saved the secret' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-dialog>
@endif
