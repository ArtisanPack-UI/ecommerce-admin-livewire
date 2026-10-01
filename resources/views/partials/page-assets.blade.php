{{--
    Pushes the Livewire assets onto the layout stacks.

    cms-framework's admin layout ships no Livewire assets, so under it each page
    pushes them; the standalone layout includes them itself.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( $ecommerceAdminPushesAssets ?? false )
    @push( 'styles' )
        @livewireStyles
    @endpush

    @push( 'scripts' )
        @livewireScripts
    @endpush
@endif
