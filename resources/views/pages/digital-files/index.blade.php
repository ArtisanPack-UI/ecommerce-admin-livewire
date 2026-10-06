{{--
    The digital files page.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@extends( $ecommerceAdminLayout )

@section( 'title', __( 'Digital files' ) )

@section( 'content' )
    @include( 'ecommerce-admin::partials.page-assets' )

    <livewire:artisanpack-ecommerce-admin-digital-files-index />
@endsection
