@if ( $row->is_verified_purchase )
    <x-artisanpack-badge :value="__( 'Verified' )" color="success" class="badge-sm" data-verified />
@else
    <span class="opacity-75">{{ __( 'No' ) }}</span>
@endif
