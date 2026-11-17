{{--
    Promotion state badge (#92), used by the promotions table and the
    promotion form header. Expects $row (a Promotion).

    The Status badge recipe (#81), a soft design-system Badge with a hidden
    "Promotion state:" prefix, plus a decorative icon per state so the four
    read apart in grayscale. Unknown states fall back to neutral with a
    readable label. The promotion's window sits in its own column, not here.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    $promotionState = \ArtisanPackUI\EcommerceAdminLivewire\Queries\PromotionsQuery::state( $row );
    [ $stateColor, $stateIcon ] = match ( $promotionState ) {
        'active'    => [ 'success', 'check' ],
        'scheduled' => [ 'info', 'clock' ],
        'expired'   => [ 'neutral', 'calendar' ],
        'disabled'  => [ 'warning', 'minus' ],
        default     => [ 'neutral', null ],
    };
@endphp
<x-artisanpack-badge variant="soft" :color="$stateColor" :leading-icon="$stateIcon" data-promotion-state="{{ $promotionState }}">
    <span class="apui-sr-only">{{ __( 'Promotion state:' ) }}</span>{{ \ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Index::stateLabel( $promotionState ) }}
</x-artisanpack-badge>
