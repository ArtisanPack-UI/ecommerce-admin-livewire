{{-- Webhooks list cell (#136): the subscription's URL in mono, left to right, truncated with the full address in its title. --}}
<bdi dir="ltr" class="ec-mono ec-webhooks-url" title="{{ $row->url }}" data-webhook-url>{{ $row->url }}</bdi>
