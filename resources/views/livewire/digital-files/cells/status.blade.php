{{--
    Digital files cell (#126): one status badge. Archived (neutral, archive
    icon) wins over Streaming only (info, eye icon); otherwise Offered
    (success dot). Each carries its word, so colour is never the only cue.
--}}
@if ( null !== $row->archived_at )
    <x-artisanpack-badge color="neutral" leading-icon="archive" data-file-status="archived">{{ __( 'Archived' ) }}</x-artisanpack-badge>
@elseif ( $row->is_streaming_only )
    <x-artisanpack-badge color="info" leading-icon="eye" data-file-status="streaming">{{ __( 'Streaming only' ) }}</x-artisanpack-badge>
@else
    <x-artisanpack-badge color="success" dot data-file-status="offered">{{ __( 'Offered' ) }}</x-artisanpack-badge>
@endif
