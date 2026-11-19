{{-- Webhooks list cell (#136): consecutive failures, muted at zero and in error text otherwise. --}}
@php( $webhookFailures = (int) $row->consecutive_failures )
@if ( $webhookFailures > 0 )
    <bdi dir="ltr" class="ec-webhooks-failures" data-failing data-webhook-failures>{{ $webhookFailures }}</bdi>
@else
    <bdi dir="ltr" class="ec-webhooks-failures ec-muted" data-webhook-failures>{{ $webhookFailures }}</bdi>
@endif
