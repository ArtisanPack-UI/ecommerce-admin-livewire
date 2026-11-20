{{--
    Orders list cell: the order number as the row's link, in mono and kept
    left to right; orders shown from the new-orders banner get a New badge.
--}}
@php
    $showRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'orders.show';
    $highlightAfter = $context['highlightAfterId'] ?? null;
    $highlightUpTo  = $context['highlightUpToId'] ?? null;
@endphp
@if ( \Illuminate\Support\Facades\Route::has( $showRoute ) )
    <a href="{{ route( $showRoute, [ 'order' => $row->getKey() ] ) }}" class="apui-link" data-standalone><bdi dir="ltr" class="ec-mono">#{{ $row->order_number }}</bdi></a>
@else
    <bdi dir="ltr" class="ec-mono">#{{ $row->order_number }}</bdi>
@endif
@if ( null !== $highlightAfter && null !== $highlightUpTo && (int) $row->getKey() > (int) $highlightAfter && (int) $row->getKey() <= (int) $highlightUpTo )
    <x-artisanpack-badge color="info" size="sm" class="ec-badge-after" data-new-order>{{ __( 'New' ) }}</x-artisanpack-badge>
@endif
