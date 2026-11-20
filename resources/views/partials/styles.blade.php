{{--
    Inlines the admin's own stylesheet (resources/css/admin.css): the few
    token-only helpers the design system has no component for, such as the
    money figures and the resource table's filter and bulk-action strips.
    Inlined, so a host needs no build step for them. Once per page.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      2.0.0
--}}
@once
    <style data-ecommerce-admin-styles @if ( null !== ( $cspNonce = \Illuminate\Support\Facades\Vite::cspNonce() ) ) nonce="{{ $cspNonce }}" @endif>{!! \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminStyles::css() !!}</style>
@endonce
