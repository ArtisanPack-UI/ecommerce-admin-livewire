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
    <x-artisanpack-modal wire:model="confirmingRotate" :title="$rotateTitle" separator>
        <p data-rotate-secret>{{ __( 'A new signing secret replaces the current one straight away. Deliveries signed with the old secret will fail verification until the receiving endpoint is updated.' ) }}</p>

        <x-slot:actions>
            <x-artisanpack-button variant="ghost" wire:click="cancelRotate" :label="__( 'Keep current secret' )" />
            <x-artisanpack-button
                color="warning"
                wire:click="rotateSecret( {{ \Illuminate\Support\Js::from( $rotateToken ) }} )"
                wire:loading.attr="disabled"
                :label="__( 'Rotate secret' )"
            />
        </x-slot:actions>
    </x-artisanpack-modal>
@endif

@if ( null !== $revealedSecret )
    <x-artisanpack-modal wire:model.live="showingSecret" :title="__( 'Signing secret' )" separator persistent>
        <div class="flex flex-col gap-3" data-revealed-secret x-data="{ copied: false }">
            <x-artisanpack-alert
                color="warning"
                icon="o-exclamation-triangle"
                :title="__( 'Copy this secret now' )"
                :description="__( 'It is shown only once. Store it with the receiving endpoint to verify signatures.' )"
                role="status"
            />
            <x-artisanpack-input id="webhook-secret" :label="__( 'Secret' )" :value="$revealedSecret" readonly class="font-mono" x-ref="secret" />
            <div>
                <x-artisanpack-button
                    variant="outline"
                    size="sm"
                    icon="o-clipboard-document"
                    x-on:click="navigator.clipboard?.writeText( $refs.secret.value ).then( () => copied = true )"
                    :label="__( 'Copy secret' )"
                />
                <span x-show="copied" x-cloak class="ms-2 text-sm" role="status">{{ __( 'Copied.' ) }}</span>
            </div>
        </div>

        <x-slot:actions>
            <x-artisanpack-button color="primary" wire:click="dismissSecret" :label="__( 'I have saved the secret' )" />
        </x-slot:actions>
    </x-artisanpack-modal>
@endif
