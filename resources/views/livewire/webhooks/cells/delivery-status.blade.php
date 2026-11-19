{{--
    A delivery's status (#137): the status badge with its icon, then the
    endpoint's HTTP code in mono when there is one. Delivered is success,
    Pending neutral, Retrying warning and Failed error.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\Webhooks;

    $status = Webhooks::status( $row );
    $icon   = match ( $status ) {
        'delivered' => 'check',
        'pending'   => 'clock',
        'retrying'  => 'refresh-cw',
        default     => 'circle-x',
    };
@endphp
<span class="ec-webhook-detail-pair" data-delivery-status="{{ $status }}">
    <x-artisanpack-badge :color="Webhooks::statusColor( $status )" :leading-icon="$icon">{{ Webhooks::statusLabel( $status ) }}</x-artisanpack-badge>
    @if ( null !== $row->response_status )
        <bdi dir="ltr" class="ec-webhook-detail-code ec-muted">{{ __( 'HTTP :status', [ 'status' => (int) $row->response_status ] ) }}</bdi>
    @endif
</span>
