{{--
    Pushes the host's Vite build, the Livewire assets, and the toast
    container onto the layout stacks.

    cms-framework's admin layout ships none of them, so under it each page
    pushes them; the standalone layout includes them itself.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( $ecommerceAdminPushesAssets ?? false )
    @push( 'styles' )
        @include( 'ecommerce-admin::partials.vite-assets' )
        @livewireStyles
    @endpush

    @push( 'scripts' )
        <x-artisanpack-toast />
        @livewireScripts
        @include( 'ecommerce-admin::partials.rate-limit-notice' )
    @endpush
@endif
