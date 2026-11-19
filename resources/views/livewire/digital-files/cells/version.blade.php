{{-- Digital files cell (#126): the version, left to right in monospace. --}}
@if ( filled( $row->version ) )
    <bdi dir="ltr" class="ec-mono">{{ $row->version }}</bdi>
@else
    <span class="ec-cell-muted" aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'No version' ) }}</span>
@endif
