{{--
    Webhooks list cell (#136): All events as one primary badge with a zap;
    otherwise the first two event keys as neutral mono badges, then a "+N
    more" disclosure that reveals the rest (keyboard and pointer alike).
--}}
@php
    $webhookEvents = array_values( array_map( 'strval', (array) $row->events ) );
    $allEvents     = in_array( \ArtisanPackUI\Ecommerce\Models\WebhookSubscription::ALL_EVENTS, $webhookEvents, true );
    $shownEvents   = array_slice( $webhookEvents, 0, 2 );
    $moreEvents    = array_slice( $webhookEvents, 2 );
@endphp
@if ( $allEvents )
    <x-artisanpack-badge color="primary" size="sm" leading-icon="zap" data-webhook-all-events>{{ __( 'All events' ) }}</x-artisanpack-badge>
@else
    <div class="ec-webhooks-events">
        @foreach ( $shownEvents as $webhookEvent )
            <x-artisanpack-badge color="neutral" size="sm" data-webhook-event><bdi dir="ltr" class="ec-webhooks-event-key">{{ $webhookEvent }}</bdi></x-artisanpack-badge>
        @endforeach
        @if ( [] !== $moreEvents )
            <details class="ec-webhooks-more" data-webhook-more-events>
                <summary>{{ __( '+:count more', [ 'count' => count( $moreEvents ) ] ) }}</summary>
                <div class="ec-webhooks-events">
                    @foreach ( $moreEvents as $webhookEvent )
                        <x-artisanpack-badge color="neutral" size="sm" data-webhook-event-more><bdi dir="ltr" class="ec-webhooks-event-key">{{ $webhookEvent }}</bdi></x-artisanpack-badge>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
@endif
