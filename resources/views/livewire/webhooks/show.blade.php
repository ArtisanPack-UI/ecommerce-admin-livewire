{{--
    A webhook subscription and its deliveries log. See
    ArtisanPackUI\EcommerceAdminLivewire\Livewire\Webhooks\Show.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\Webhooks;
@endphp
<div class="flex flex-col gap-6">
    <x-artisanpack-header :title="$subscription->name" :subtitle="$subscription->url" :level="1" separator>
        <x-slot:actions>
            @if ( null !== $indexUrl )
                <x-artisanpack-button variant="ghost" icon="o-arrow-left" :link="$indexUrl" :label="__( 'All subscriptions' )" />
            @endif
            @if ( $canUpdate )
                @unless ( $subscription->is_active )
                    <x-artisanpack-button color="primary" icon="o-play" wire:click="reEnable( {{ $subscription->id }} )" :label="__( 'Re-enable' )" />
                @endunless
                <x-artisanpack-button variant="outline" icon="o-key" wire:click="confirmRotate( {{ $subscription->id }} )" :label="__( 'Rotate secret' )" />
            @endif
        </x-slot:actions>
    </x-artisanpack-header>

    @unless ( $subscription->is_active )
        <x-artisanpack-alert
            color="warning"
            icon="o-exclamation-triangle"
            :title="$disabledByEngine
                ? trans_choice( 'Disabled after :count failed delivery in a row|Disabled after :count failed deliveries in a row', (int) $subscription->consecutive_failures, [ 'count' => (int) $subscription->consecutive_failures ] )
                : __( 'This subscription is disabled' )"
            :description="__( 'No events are sent while it is disabled. Re-enable it to resume deliveries and replays.' )"
            role="status"
            data-subscription-disabled
        />
    @endunless

    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-subscription-details>
        <div>
            <dt class="text-sm opacity-75">{{ __( 'Events' ) }}</dt>
            <dd class="flex flex-wrap gap-1">
                @foreach ( (array) $subscription->events as $event )
                    <x-artisanpack-badge :value="Webhooks::eventLabel( (string) $event )" class="badge-ghost badge-sm" />
                @endforeach
            </dd>
        </div>
        <div>
            <dt class="text-sm opacity-75">{{ __( 'Last success' ) }}</dt>
            <dd>{{ null === $subscription->last_success_at ? __( 'Never' ) : Webhooks::dateTime( $subscription->last_success_at ) }}</dd>
        </div>
        <div>
            <dt class="text-sm opacity-75">{{ __( 'Last failure' ) }}</dt>
            <dd>{{ null === $subscription->last_failure_at ? __( 'Never' ) : Webhooks::dateTime( $subscription->last_failure_at ) }}</dd>
        </div>
        <div>
            <dt class="text-sm opacity-75">{{ __( 'Consecutive failures' ) }}</dt>
            <dd>{{ (int) $subscription->consecutive_failures }}</dd>
        </div>
    </dl>

    <section aria-labelledby="webhook-deliveries-heading" class="flex flex-col gap-3">
        <h2 id="webhook-deliveries-heading" class="text-lg font-semibold">{{ __( 'Deliveries' ) }}</h2>

        @include( 'ecommerce-admin::partials.resource-table', [
            'emptyIcon'        => 'o-paper-airplane',
            'emptyTitle'       => __( 'No deliveries yet' ),
            'emptyDescription' => __( 'Each event sent to this URL is logged here with the response.' ),
            'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\WebhookDelivery $delivery ): string => __( 'Delivery :id (:event)', [ 'id' => $delivery->id, 'event' => $delivery->event ] ),
        ] )
    </section>

    <x-artisanpack-drawer
        wire:model="viewingDelivery"
        :title="__( 'Delivery details' )"
        right
        separator
        with-close-button
        close-on-escape
        class="w-full max-w-2xl"
    >
        @if ( null !== $delivery )
            @php( $status = Webhooks::status( $delivery ) )
            <div class="flex flex-col gap-4" data-delivery="{{ $delivery->id }}">
                <dl class="grid grid-cols-2 gap-3">
                    <div>
                        <dt class="text-sm opacity-75">{{ __( 'Event' ) }}</dt>
                        <dd class="font-mono">{{ $delivery->event }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm opacity-75">{{ __( 'Status' ) }}</dt>
                        <dd><x-artisanpack-badge :value="Webhooks::statusLabel( $status )" :class="'badge-sm ' . Webhooks::statusClass( $status )" /></dd>
                    </div>
                    <div>
                        <dt class="text-sm opacity-75">{{ __( 'Attempts' ) }}</dt>
                        <dd>{{ (int) $delivery->attempts }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm opacity-75">{{ 'retrying' === $status || 'pending' === $status ? __( 'Next retry' ) : __( 'Delivered at' ) }}</dt>
                        <dd>{{ 'delivered' === $status ? Webhooks::dateTime( $delivery->delivered_at ) : ( Webhooks::dateTime( $delivery->next_retry_at ) ?: '—' ) }}</dd>
                    </div>
                </dl>

                <section aria-labelledby="delivery-payload-heading">
                    <h3 id="delivery-payload-heading" class="mb-1 font-semibold">{{ __( 'Payload' ) }}</h3>
                    <pre class="max-h-96 overflow-auto rounded-box bg-base-200 p-3 text-xs" data-delivery-payload>{{ json_encode( (array) $delivery->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) }}</pre>
                </section>

                <section aria-labelledby="delivery-response-heading">
                    <h3 id="delivery-response-heading" class="mb-1 font-semibold">{{ __( 'Response' ) }}</h3>
                    @if ( null === $delivery->response_status && ( null === $delivery->response_body || '' === $delivery->response_body ) )
                        <p class="opacity-75">{{ __( 'No response recorded yet.' ) }}</p>
                    @else
                        <p class="mb-1 text-sm" data-delivery-response-status>{{ null === $delivery->response_status ? __( 'No status code' ) : __( 'HTTP :status', [ 'status' => (int) $delivery->response_status ] ) }}</p>
                        <pre class="max-h-64 overflow-auto rounded-box bg-base-200 p-3 text-xs" data-delivery-response>{{ (string) $delivery->response_body }}</pre>
                    @endif
                </section>

                @if ( $canUpdate )
                    <div class="flex justify-end gap-2">
                        <x-artisanpack-button variant="ghost" wire:click="closeDelivery" :label="__( 'Close' )" />
                        <x-artisanpack-button
                            color="primary"
                            icon="o-arrow-path"
                            wire:click="replay( {{ \Illuminate\Support\Js::from( $replayToken ) }} )"
                            wire:loading.attr="disabled"
                            :label="__( 'Replay delivery' )"
                        />
                    </div>
                @endif
            </div>
        @endif
    </x-artisanpack-drawer>

    @include( 'ecommerce-admin::livewire.webhooks.secret-dialogs' )
</div>
