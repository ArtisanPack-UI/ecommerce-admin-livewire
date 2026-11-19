{{--
    Opens a delivery's details (#137).

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<x-artisanpack-button
    variant="ghost"
    color="neutral"
    size="sm"
    wire:click="openDelivery( {{ $row->getKey() }} )"
    data-focus-key="webhook-delivery-{{ $row->getKey() }}"
    :aria-label="__( 'Details of delivery :id', [ 'id' => $row->getKey() ] )">{{ __( 'Details' ) }}</x-artisanpack-button>
