@if ( null === $row->last_ordered_at )
    <span class="opacity-75">{{ __( 'Never' ) }}</span>
@else
    <time datetime="{{ $row->last_ordered_at->toIso8601String() }}">{{ \ArtisanPackUI\Ecommerce\Support\LocalizedDate::format( $row->last_ordered_at ) }}</time>
@endif
