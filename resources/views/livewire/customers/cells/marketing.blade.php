{{--
    Customers cell (#128): a success badge only when the customer accepts
    marketing; otherwise plain muted text, so the column scans fast.
--}}
@if ( $row->accepts_marketing )
    <x-artisanpack-badge color="success" leading-icon="check" data-marketing="subscribed">{{ __( 'Subscribed' ) }}</x-artisanpack-badge>
@else
    <span class="ec-cell-muted ec-nowrap" data-marketing="not-subscribed">{{ __( 'Not subscribed' ) }}</span>
@endif
