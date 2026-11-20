{{--
    Webhook subscriptions (#136). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Webhooks\Index.

    Every subscription on the shared Resource table (#79) in seven columns:
    the name with its URL below in mono, its events (two badges then "+N
    more", or one primary All events badge), Active or Disabled, the last
    success and failure, and the run of failures (red when not zero).
    Disabled rows get a visible Re-enable button; the other actions are
    icon buttons named for the subscription. Create and edit happen in a
    drawer with an events checkbox list; a new subscription's signing
    secret is shown once in the shared secret dialogs (#91). Delete is an
    alertdialog whose safe choice keeps the subscription.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="ec-stack" data-webhooks-index>
    <x-artisanpack-page-header :title="__( 'Webhooks' )" :description="__( 'Send store events to other systems as signed HTTPS requests.' )" :level="1">
        @if ( $canCreate )
            <x-slot:actions>
                <x-artisanpack-button size="sm" leading-icon="plus" wire:click="create" data-new-webhook>{{ __( 'New subscription' ) }}</x-artisanpack-button>
            </x-slot:actions>
        @endif
    </x-artisanpack-page-header>

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'zap',
        'emptyTitle'       => __( 'No webhook subscriptions yet' ),
        'emptyDescription' => __( 'Subscribe a URL to store events, such as a fulfillment partner that needs every refunded order.' ),
        'emptyActions'     => $canCreate ? 'ecommerce-admin::livewire.webhooks.empty-actions' : null,
        'searchHint'       => __( 'Search subscriptions' ),
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\WebhookSubscription $subscription ): string => (string) $subscription->name,
        'cellContext'      => [ 'canUpdate' => $canUpdate, 'canDelete' => $canDelete ],
    ] )

    {{-- Live, so closing with the X, Escape or the backdrop reaches the server and clears the ?edit= link. --}}
    <x-artisanpack-drawer
        wire:model.live="editing"
        :title="null === $subscriptionId ? __( 'New subscription' ) : __( 'Edit subscription' )"
        side="end"
        size="md"
        :close-label="__( 'Close' )"
    >
        @if ( $editing )
            <form id="webhook-form" wire:submit="save" class="ec-form-stack ec-stack-lg" data-webhook-form>
                @include( 'ecommerce-admin::partials.error-summary' )

                <x-artisanpack-input id="webhook-name" :label="__( 'Name' )" :placeholder="__( 'Fulfillment partner' )" wire:model="form.name" maxlength="255" required />
                <x-artisanpack-input
                    id="webhook-url"
                    type="url"
                    class="ec-mono-input"
                    dir="ltr"
                    :label="__( 'URL' )"
                    :hint="__( 'Must use HTTPS and point to a public address.' )"
                    placeholder="https://"
                    wire:model="form.url"
                    required
                />
                <div class="ec-stack-sm">
                    <x-artisanpack-checkbox-group
                        id="webhook-events"
                        class="ec-webhooks-event-list"
                        :label="__( 'Events' )"
                        :hint="__( 'Choose All events to receive every event, including ones added later.' )"
                        :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $eventOptions )"
                        error-field="form.events"
                        wire:model="form.events"
                    />
                    @error( 'form.events.*' )
                        <p class="ec-field-error" role="alert"><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
                    @enderror
                </div>
                <x-artisanpack-toggle id="webhook-active" :label="__( 'Active' )" :description="__( 'Turning a subscription back on resets its failure count.' )" wire:model="form.is_active" />

                @if ( null === $subscriptionId )
                    <p class="ec-muted ec-webhooks-secret-note" data-secret-note><x-artisanpack-icon name="key" aria-hidden="true" />{{ __( 'A signing secret is generated when you save. It is shown once.' ) }}</p>
                @endif
            </form>
        @endif

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'editing', false )">{{ __( 'Cancel' ) }}</x-artisanpack-button>
            <x-artisanpack-button type="submit" form="webhook-form" wire:loading.attr="disabled" data-save-webhook>{{ __( 'Save subscription' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-drawer>

    @if ( null !== $deleting )
        {{-- Built outside the tag: a nested double quote in a component attribute stops Blade compiling it. --}}
        @php( $deleteTitle = __( 'Delete ":name"?', [ 'name' => $deleting->name ] ) )
        <x-artisanpack-dialog
            wire:model="confirmingDelete"
            role="alertdialog"
            size="sm"
            color="error"
            icon="trash"
            :title="$deleteTitle"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
            data-delete-confirm
        >
            <p class="ec-body" data-delete-webhook>{{ __( 'The subscription and its deliveries log are deleted. Events stop being sent to this URL.' ) }}</p>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelDelete" data-autofocus>{{ __( 'Keep subscription' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    color="error"
                    wire:click="delete( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )"
                    wire:loading.attr="disabled">{{ __( 'Delete subscription' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif

    @include( 'ecommerce-admin::livewire.webhooks.secret-dialogs' )
</div>
