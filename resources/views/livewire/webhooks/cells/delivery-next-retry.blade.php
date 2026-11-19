{{--
    When a pending or retrying delivery is tried next (#137), relative to now
    with the full date in its title; a dash for delivered and failed ones.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      2.0.0
--}}
@php( $status = \ArtisanPackUI\EcommerceAdminLivewire\Support\Webhooks::status( $row ) )
@if ( in_array( $status, [ 'pending', 'retrying' ], true ) && null !== $row->next_retry_at )
    <time class="ec-muted" datetime="{{ $row->next_retry_at->toIso8601String() }}" title="{{ \ArtisanPackUI\EcommerceAdminLivewire\Support\Webhooks::dateTime( $row->next_retry_at ) }}" data-delivery-next-retry>{{ $row->next_retry_at->isPast() ? __( 'now' ) : $row->next_retry_at->diffForHumans() }}</time>
@else
    <span class="ec-muted" aria-hidden="true">—</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
@endif
