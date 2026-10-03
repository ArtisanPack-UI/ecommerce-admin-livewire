{{--
    Pushes the host's Vite build, the Livewire assets, the toast container,
    and the command palette onto the layout stacks, and shows the palette's
    Search button above the page.

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

    {{-- cms-framework's layout has no top bar, so the palette's button sits above the page. --}}
    <div class="mb-2 flex justify-end" data-cms-spotlight-bar>
        @include( 'ecommerce-admin::partials.spotlight-button' )
    </div>

    @push( 'scripts' )
        @include( 'ecommerce-admin::partials.spotlight' )
        <x-artisanpack-toast />
        @livewireScripts
        @include( 'ecommerce-admin::partials.accessibility' )
        @include( 'ecommerce-admin::partials.rate-limit-notice' )
    @endpush
@endif
