{{--
    Tags cell (#123): the slug, left-to-right monospace. Empty while the row
    is being renamed (the slug field sits in the name cell).
--}}
@if ( $context['editingId'] !== (int) $row->getKey() )
    <bdi dir="ltr" class="ec-mono">{{ $row->slug }}</bdi>
@endif
