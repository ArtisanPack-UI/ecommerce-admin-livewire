<span class="whitespace-nowrap" aria-hidden="true">{{ str_repeat( '★', max( 0, min( 5, (int) $row->rating ) ) ) }}{{ str_repeat( '☆', 5 - max( 0, min( 5, (int) $row->rating ) ) ) }}</span>
<span class="sr-only">{{ __( ':rating out of 5', [ 'rating' => (int) $row->rating ] ) }}</span>
