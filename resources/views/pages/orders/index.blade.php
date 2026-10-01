{{--
    The orders index page.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@extends( $ecommerceAdminLayout )

@section( 'title', __( 'Orders' ) )

@section( 'content' )
    @include( 'ecommerce-admin::partials.page-assets' )

    <livewire:artisanpack-ecommerce-admin-orders-index />
@endsection
