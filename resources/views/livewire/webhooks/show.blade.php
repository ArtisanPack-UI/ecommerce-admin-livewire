{{--
    Webhook subscription detail (#137). See
    ArtisanPackUI\EcommerceAdminLivewire\Livewire\Webhooks\Show.

    The page header carries the name and the URL (left-to-right mono); the
    Active or Disabled badge follows the h1 (the header's meta slot would put
    it before the title). A disabled subscription gets a warning alert that
    says how many deliveries failed in a row when failures switched it off,
    or plainly that it is disabled when staff did, and Re-enable becomes the
    primary action. An active subscription with deliveries the engine parked
    while it was off offers "Send missed deliveries".

    A card lists the events and the subscription's health; the failures in a
    row turn red when they aren't zero. The deliveries log follows. Each
    delivery opens in a drawer with its payload and response in left-to-right
    mono and Replay as the primary action, which is blocked while the
    subscription is disabled. Rotating the secret uses the shared secret
    dialogs (#91).

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Models\WebhookDelivery;
    use ArtisanPackUI\Ecommerce\Models\WebhookSubscription;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\Webhooks;

    $failures   = (int) $subscription->consecutive_failures;
    $isActive   = (bool) $subscription->is_active;
    $allEvents  = in_array( WebhookSubscription::ALL_EVENTS, (array) $subscription->events, true );
    $dateOrNever = static fn ( ?\DateTimeInterface $date ): string => null === $date ? '' : Webhooks::dateTime( $date );
@endphp
<div class="ec-stack-lg ec-webhook-detail" data-webhook-detail>
    <x-artisanpack-page-header :title="$subscription->name" :level="1">
        <x-slot:description>
            <bdi dir="ltr" class="ec-mono ec-break" data-subscription-url>{{ $subscription->url }}</bdi>
        </x-slot:description>
        <x-slot:actions>
            @if ( null !== $indexUrl )
                <x-artisanpack-button variant="ghost" color="neutral" size="sm" :href="$indexUrl">{{ __( 'All subscriptions' ) }}</x-artisanpack-button>
            @endif
            @if ( $canUpdate )
                @unless ( $isActive )
                    <x-artisanpack-button size="sm" leading-icon="refresh-cw" wire:click="reEnable( {{ $subscription->id }} )" wire:loading.attr="disabled" wire:target="reEnable" data-re-enable>{{ __( 'Re-enable' ) }}</x-artisanpack-button>
                @endunless
                @if ( $isActive && $parkedCount > 0 )
                    <x-artisanpack-button size="sm" variant="outline" color="neutral" leading-icon="redo-2" wire:click="sendMissedDeliveries( {{ $subscription->id }} )" wire:loading.attr="disabled" wire:target="sendMissedDeliveries" data-send-missed-deliveries="{{ $parkedCount }}">{{ __( 'Send missed deliveries' ) }}</x-artisanpack-button>
                @endif
                @if ( null !== $editUrl )
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="pencil" :href="$editUrl" data-edit-subscription>{{ __( 'Edit' ) }}</x-artisanpack-button>
                @endif
                <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="key" wire:click="confirmRotate( {{ $subscription->id }} )" data-focus-key="webhook-rotate-{{ $subscription->id }}" data-rotate-secret>{{ __( 'Rotate secret' ) }}</x-artisanpack-button>
            @endif
        </x-slot:actions>
    </x-artisanpack-page-header>

    <div class="ec-badge-row" data-subscription-state="{{ $isActive ? 'active' : 'disabled' }}">
        <x-artisanpack-badge :color="$isActive ? 'success' : 'warning'" dot>{{ $isActive ? __( 'Active' ) : __( 'Disabled' ) }}</x-artisanpack-badge>
    </div>

    @unless ( $isActive )
        <x-artisanpack-alert
            color="warning"
            icon="triangle-alert"
            :title="$disabledByEngine
                ? trans_choice( 'Disabled after :count failed delivery in a row|Disabled after :count failed deliveries in a row', $failures, [ 'count' => $failures ] )
                : __( 'This subscription is disabled' )"
            :description="__( 'No events are sent while it is disabled. Re-enable it to resume deliveries and replays.' )"
            data-subscription-disabled="{{ $disabledByEngine ? 'failures' : 'staff' }}"
        />
    @endunless

    <x-artisanpack-card>
        <h2 class="apui-sr-only">{{ __( 'Subscription details' ) }}</h2>
        <dl class="ec-webhook-detail-facts" data-subscription-details>
            <div>
                <dt>{{ __( 'Events' ) }}</dt>
                <dd>
                    <ul class="ec-webhook-detail-chips" role="list">
                        @if ( $allEvents )
                            <li><x-artisanpack-badge color="primary" size="sm" leading-icon="zap">{{ __( 'All events' ) }}</x-artisanpack-badge></li>
                        @else
                            @foreach ( (array) $subscription->events as $event )
                                <li wire:key="subscription-event-{{ $event }}"><x-artisanpack-badge color="neutral" size="sm"><bdi dir="ltr" class="ec-webhook-detail-code">{{ Webhooks::eventLabel( (string) $event ) }}</bdi></x-artisanpack-badge></li>
                            @endforeach
                        @endif
                    </ul>
                </dd>
            </div>
            <div>
                <dt>{{ __( 'Last success' ) }}</dt>
                <dd data-subscription-last-success>
                    @if ( null === $subscription->last_success_at )
                        <span class="ec-muted">{{ __( 'Never' ) }}</span>
                    @else
                        <time datetime="{{ $subscription->last_success_at->toIso8601String() }}">{{ $dateOrNever( $subscription->last_success_at ) }}</time>
                    @endif
                </dd>
            </div>
            <div>
                <dt>{{ __( 'Last failure' ) }}</dt>
                <dd data-subscription-last-failure>
                    @if ( null === $subscription->last_failure_at )
                        <span class="ec-muted">{{ __( 'Never' ) }}</span>
                    @else
                        <time datetime="{{ $subscription->last_failure_at->toIso8601String() }}">{{ $dateOrNever( $subscription->last_failure_at ) }}</time>
                    @endif
                </dd>
            </div>
            <div>
                <dt>{{ __( 'Consecutive failures' ) }}</dt>
                <dd><bdi dir="ltr" @class( [ 'ec-webhook-detail-failures' => $failures > 0 ] ) data-subscription-failures="{{ $failures }}">{{ $failures }}</bdi></dd>
            </div>
        </dl>
    </x-artisanpack-card>

    <section aria-labelledby="webhook-deliveries-heading" class="ec-stack-sm" data-webhook-deliveries>
        <x-artisanpack-heading :level="2" :size="5" id="webhook-deliveries-heading">{{ __( 'Deliveries' ) }}</x-artisanpack-heading>

        @include( 'ecommerce-admin::partials.resource-table', [
            'emptyIcon'        => 'inbox',
            'emptyTitle'       => __( 'No deliveries yet' ),
            'emptyDescription' => __( 'Each event sent to this URL is logged here with the response.' ),
            'rowLabel'         => static fn ( WebhookDelivery $delivery ): string => __( 'Delivery :id (:event)', [ 'id' => $delivery->id, 'event' => $delivery->event ] ),
        ] )
    </section>

    <x-artisanpack-drawer
        wire:model="viewingDelivery"
        :title="null === $delivery ? __( 'Delivery details' ) : __( 'Delivery :id (:event)', [ 'id' => $delivery->id, 'event' => $delivery->event ] )"
        side="end"
        size="lg"
        :close-label="__( 'Close' )"
    >
        @if ( null !== $delivery )
            @php( $status = Webhooks::status( $delivery ) )
            <div class="ec-stack-lg" data-delivery="{{ $delivery->id }}">
                <dl class="ec-dl">
                    <div>
                        <dt>{{ __( 'Event' ) }}</dt>
                        <dd><x-artisanpack-badge color="neutral" size="sm"><bdi dir="ltr" class="ec-webhook-detail-code">{{ $delivery->event }}</bdi></x-artisanpack-badge></dd>
                    </div>
                    <div>
                        <dt>{{ __( 'Status' ) }}</dt>
                        <dd>@include( 'ecommerce-admin::livewire.webhooks.cells.delivery-status', [ 'row' => $delivery ] )</dd>
                    </div>
                    <div>
                        <dt>{{ __( 'Attempts' ) }}</dt>
                        <dd><bdi dir="ltr">{{ (int) $delivery->attempts }}</bdi></dd>
                    </div>
                    <div>
                        <dt>{{ __( 'Next retry' ) }}</dt>
                        <dd>
                            @if ( in_array( $status, [ 'pending', 'retrying' ], true ) && null !== $delivery->next_retry_at )
                                <time datetime="{{ $delivery->next_retry_at->toIso8601String() }}">{{ __( ':date (:relative)', [ 'date' => Webhooks::dateTime( $delivery->next_retry_at ), 'relative' => $delivery->next_retry_at->isPast() ? __( 'now' ) : $delivery->next_retry_at->diffForHumans() ] ) }}</time>
                            @else
                                <span class="ec-muted">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>{{ __( 'Delivered at' ) }}</dt>
                        <dd>
                            @if ( null === $delivery->delivered_at )
                                <span class="ec-muted">{{ __( 'Never' ) }}</span>
                            @else
                                <time datetime="{{ $delivery->delivered_at->toIso8601String() }}">{{ Webhooks::dateTime( $delivery->delivered_at ) }}</time>
                            @endif
                        </dd>
                    </div>
                </dl>

                <section aria-labelledby="delivery-payload-heading" class="ec-stack-sm">
                    <x-artisanpack-heading :level="3" :size="6" id="delivery-payload-heading">{{ __( 'Payload' ) }}</x-artisanpack-heading>
                    <pre class="ec-webhook-detail-payload" dir="ltr" tabindex="0" aria-labelledby="delivery-payload-heading" data-delivery-payload>{{ json_encode( (array) $delivery->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) }}</pre>
                </section>

                <section aria-labelledby="delivery-response-heading" class="ec-stack-sm">
                    <x-artisanpack-heading :level="3" :size="6" id="delivery-response-heading">{{ __( 'Response' ) }}</x-artisanpack-heading>
                    @if ( null === $delivery->response_status && ( null === $delivery->response_body || '' === $delivery->response_body ) )
                        <p class="ec-muted">{{ __( 'No response recorded yet.' ) }}</p>
                    @else
                        <p class="ec-muted" data-delivery-response-status><bdi dir="ltr" class="ec-mono">{{ null === $delivery->response_status ? __( 'No status code' ) : __( 'HTTP :status', [ 'status' => (int) $delivery->response_status ] ) }}</bdi></p>
                        @if ( null !== $delivery->response_body && '' !== $delivery->response_body )
                            <pre class="ec-webhook-detail-payload" dir="ltr" tabindex="0" aria-labelledby="delivery-response-heading" data-delivery-response>{{ (string) $delivery->response_body }}</pre>
                        @endif
                    @endif
                </section>

                @if ( $canUpdate && ! $isActive )
                    <p class="ec-muted" id="delivery-replay-blocked" data-replay-blocked>{{ __( 'Re-enable this subscription to replay its deliveries.' ) }}</p>
                @endif
            </div>
        @endif

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" wire:click="closeDelivery">{{ __( 'Close' ) }}</x-artisanpack-button>
            @if ( $canUpdate && null !== $delivery )
                <x-artisanpack-button
                    leading-icon="refresh-cw"
                    :disabled="! $isActive"
                    :aria-describedby="$isActive ? null : 'delivery-replay-blocked'"
                    wire:click="replay( {{ \Illuminate\Support\Js::from( $replayToken ) }} )"
                    wire:loading.attr="disabled"
                    wire:target="replay"
                    data-replay-delivery
                >{{ __( 'Replay delivery' ) }}</x-artisanpack-button>
            @endif
        </x-slot:footer>
    </x-artisanpack-drawer>

    @include( 'ecommerce-admin::livewire.webhooks.secret-dialogs' )
</div>
