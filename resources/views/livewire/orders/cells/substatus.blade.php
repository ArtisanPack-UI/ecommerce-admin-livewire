@if ( null !== $row->substatus )
    <x-artisanpack-ec-status-badge :substatus="$row->substatus" />
@else
    <span aria-hidden="true">&mdash;</span><span class="sr-only">{{ __( 'None' ) }}</span>
@endif
