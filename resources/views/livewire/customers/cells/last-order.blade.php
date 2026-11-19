{{-- Customers cell (#128): the last order's date, or "Never", muted. --}}
@if ( null === $row->last_ordered_at )
    <span class="ec-cell-muted ec-nowrap" data-last-order="never">{{ __( 'Never' ) }}</span>
@else
    <time class="ec-cell-muted ec-nowrap" datetime="{{ $row->last_ordered_at->toIso8601String() }}">{{ \ArtisanPackUI\Ecommerce\Support\LocalizedDate::format( $row->last_ordered_at ) }}</time>
@endif
