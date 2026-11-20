{{--
    Webhooks list cell (#136): Active (success) or Disabled (warning), each a
    soft badge with a dot and a hidden "Subscription state:" prefix. Why a
    subscription was disabled is on its detail page; the failures column
    shows the run of failures here.
--}}
@if ( $row->is_active )
    <x-artisanpack-badge color="success" dot data-webhook-state="active"><span class="apui-sr-only">{{ __( 'Subscription state:' ) }}</span>{{ __( 'Active' ) }}</x-artisanpack-badge>
@else
    <x-artisanpack-badge color="warning" dot data-webhook-state="disabled"><span class="apui-sr-only">{{ __( 'Subscription state:' ) }}</span>{{ __( 'Disabled' ) }}</x-artisanpack-badge>
@endif
