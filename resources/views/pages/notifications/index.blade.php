{{--
    The notifications page.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@extends( $ecommerceAdminLayout )

@section( 'title', __( 'Notifications' ) )

@section( 'content' )
    @include( 'ecommerce-admin::partials.page-assets' )

    <livewire:artisanpack-ecommerce-admin-notifications-index />
@endsection
