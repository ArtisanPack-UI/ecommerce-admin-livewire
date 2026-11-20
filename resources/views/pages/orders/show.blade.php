{{--
    The order detail page.

    The component loads and authorizes the order: a user without access
    gets a 403, and a missing order is a 404 only for users who may list
    orders. The title names the order number when the controller found it.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@extends( $ecommerceAdminLayout )

@section( 'title', $screenTitle ?? __( 'Order' ) )

@section( 'content' )
    @include( 'ecommerce-admin::partials.page-assets' )

    <livewire:artisanpack-ecommerce-admin-orders-show :order="$order" />
@endsection
