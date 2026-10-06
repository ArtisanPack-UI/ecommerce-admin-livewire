{{--
    A polite live region for real-time updates. It is always in the page so
    screen readers pick up changes to its text. Pass `revision` (a counter
    the component bumps on every update) to have an unchanged message read
    again: odd revisions end in an invisible zero-width space.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-live-region>{{ ( $message ?? '' ) . ( 1 === ( (int) ( $revision ?? 0 ) ) % 2 ? "\u{200B}" : '' ) }}</div>
