{{--
    Pushes the host's Vite build, the admin's inline styles, the Livewire
    assets, the toaster, and the command palette onto the layout stacks, and
    shows the palette's Search button above the page.

    cms-framework's admin layout ships none of them, so under it each page
    pushes them; the standalone layout includes them itself.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( $ecommerceAdminPushesAssets ?? false )
    @push( 'styles' )
        @include( 'ecommerce-admin::partials.vite-assets' )
        @include( 'ecommerce-admin::partials.styles' )
        @livewireStyles
    @endpush

    {{-- cms-framework's layout has no top bar, so the palette's button sits above the page. --}}
    <div class="ec-spotlight-bar" data-cms-spotlight-bar>
        @include( 'ecommerce-admin::partials.spotlight-button' )
    </div>

    @push( 'scripts' )
        <x-artisanpack-toaster />
        @livewireScripts
        @include( 'ecommerce-admin::partials.accessibility' )
        @include( 'ecommerce-admin::partials.rate-limit-notice' )
    @endpush
@endif
