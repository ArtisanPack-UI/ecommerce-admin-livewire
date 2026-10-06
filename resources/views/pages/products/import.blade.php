{{--
    The import products page.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@extends( $ecommerceAdminLayout )

@section( 'title', __( 'Import products' ) )

@section( 'content' )
    @include( 'ecommerce-admin::partials.page-assets' )

    <livewire:artisanpack-ecommerce-admin-products-import />
@endsection
