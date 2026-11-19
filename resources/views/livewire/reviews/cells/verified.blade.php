{{-- Reviews cell (#125): a Verified badge for a verified purchase, a muted "No" otherwise. --}}
@if ( $row->is_verified_purchase )
    <x-artisanpack-badge color="success" size="sm" leading-icon="check" data-verified>{{ __( 'Verified' ) }}</x-artisanpack-badge>
@else
    <span class="ec-muted-text">{{ __( 'No' ) }}</span>
@endif
