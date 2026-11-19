{{-- Customers cell (#128): "Has an account" (with a person icon) or "Guest". --}}
@if ( null === $row->user_id )
    <x-artisanpack-badge color="neutral" data-account="guest">{{ __( 'Guest' ) }}</x-artisanpack-badge>
@else
    <x-artisanpack-badge color="neutral" leading-icon="user" data-account="registered">{{ __( 'Has an account' ) }}</x-artisanpack-badge>
@endif
