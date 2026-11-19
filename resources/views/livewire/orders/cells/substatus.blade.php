{{-- Orders list cell: the sub-status badge, or a dash read as "None". --}}
@if ( null !== $row->substatus )
    <x-artisanpack-ec-status-badge :substatus="$row->substatus" />
@else
    <span class="ec-muted-text" aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
@endif
