{{--
    A polite live region for real-time updates. It is always in the page so
    screen readers pick up changes to its text.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-live-region>{{ $message ?? '' }}</div>
