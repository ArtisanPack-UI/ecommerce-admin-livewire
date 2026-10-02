@php
    $promotionState = \ArtisanPackUI\EcommerceAdminLivewire\Queries\PromotionsQuery::state( $row );
@endphp
<x-artisanpack-badge
    :value="\ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Index::stateLabel( $promotionState )"
    class="badge-sm"
    :color="match ( $promotionState ) { 'active' => 'success', 'scheduled' => 'info', 'expired' => 'neutral', default => 'warning' }"
/>
