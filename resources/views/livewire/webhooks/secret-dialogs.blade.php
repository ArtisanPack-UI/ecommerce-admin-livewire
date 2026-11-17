{{--
    The rotate-secret confirmation and the one-time secret notice, shared by
    the webhook screens. See ManagesWebhookSubscriptions.

    Expects $rotating (the subscription waiting for confirmation, or null)
    and $rotateToken.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( null !== $rotating )
    @php( $rotateTitle = __( 'Rotate the secret of ":name"?', [ 'name' => $rotating->name ] ) )
    <x-artisanpack-dialog wire:model="confirmingRotate" :title="$rotateTitle">
        <p data-rotate-secret>{{ __( 'A new signing secret replaces the current one straight away. Deliveries signed with the old secret will fail verification until the receiving endpoint is updated.' ) }}</p>

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" wire:click="cancelRotate">{{ __( 'Keep current secret' ) }}</x-artisanpack-button>
            <x-artisanpack-button
                color="warning"
                wire:click="rotateSecret( {{ \Illuminate\Support\Js::from( $rotateToken ) }} )"
                wire:loading.attr="disabled">{{ __( 'Rotate secret' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-dialog>
@endif

@if ( $showingSecret )
    <x-artisanpack-dialog wire:model.live="showingSecret" :title="__( 'Signing secret' )" role="alertdialog" hide-close>
        {{-- An alertdialog without a close button: clicking outside can't lose the one-time secret. --}}
        {{-- The secret is in this render only; wire:ignore keeps it on screen through later renders. --}}
        <div class="flex flex-col gap-3" data-revealed-secret wire:ignore x-data="{ copied: false, failed: false }">
            <x-artisanpack-alert
                color="warning"
                icon="o-exclamation-triangle"
                :title="__( 'Copy this secret now' )"
                :description="__( 'It is shown only once. Store it with the receiving endpoint to verify signatures.' )"
                role="status"
            />
            <x-artisanpack-input id="webhook-secret" :label="__( 'Secret' )" :value="$revealedSecret ?? ''" readonly class="font-mono" x-ref="secret" />
            <div>
                <x-artisanpack-button
                    variant="outline"
                    size="sm"
                    leading-icon="o-clipboard-document"
                    x-on:click="( navigator.clipboard ? navigator.clipboard.writeText( $refs.secret.value ) : Promise.reject() ).then( () => { copied = true; failed = false } ).catch( () => { failed = true; copied = false; $refs.secret.focus(); $refs.secret.select() } )">{{ __( 'Copy secret' ) }}</x-artisanpack-button>
                <span class="ms-2 text-sm" role="status" x-text="copied ? @js( __( 'Copied.' ) ) : ( failed ? @js( __( 'Copy failed. The secret is selected; press Ctrl+C.' ) ) : '' )"></span>
            </div>
        </div>

        <x-slot:footer>
            <x-artisanpack-button color="primary" wire:click="dismissSecret">{{ __( 'I have saved the secret' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-dialog>
@endif
