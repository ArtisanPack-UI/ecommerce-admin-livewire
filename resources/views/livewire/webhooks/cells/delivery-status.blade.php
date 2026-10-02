@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\Webhooks;

    $status = Webhooks::status( $row );
@endphp
<div class="flex flex-col gap-1" data-delivery-status="{{ $status }}">
    <x-artisanpack-badge :value="Webhooks::statusLabel( $status )" :class="'badge-sm ' . Webhooks::statusClass( $status )" />
    @if ( null !== $row->response_status )
        <span class="text-xs opacity-75">{{ __( 'HTTP :status', [ 'status' => (int) $row->response_status ] ) }}</span>
    @endif
</div>
