{{--
    The order detail page.

    The component loads and authorizes the order, so a user without access
    gets a 403 before the order id is looked up.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@extends( $ecommerceAdminLayout )

@section( 'title', __( 'Order' ) )

@section( 'content' )
    @include( 'ecommerce-admin::partials.page-assets' )

    <livewire:artisanpack-ecommerce-admin-orders-show :order="$order" />
@endsection
