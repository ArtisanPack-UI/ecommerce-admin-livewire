{{--
    License keys cell (#127): the key (masked to its last four characters
    without licenseKey.view) in monospace, kept left to right.
--}}
<bdi dir="ltr" class="ec-mono ec-nowrap" data-license-key="{{ $row->getKey() }}">{{ $context['keyFor']( $row ) }}</bdi>
