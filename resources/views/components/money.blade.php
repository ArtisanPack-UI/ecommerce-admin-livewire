@if ( null === $formatted() )
    <span {{ $attributes->class( [ 'tabular-nums' ] ) }}><span aria-hidden="true">&mdash;</span><span class="sr-only">{{ __( 'No amount' ) }}</span></span>
@else
    <span {{ $attributes->class( [ 'tabular-nums' ] ) }} data-currency="{{ $currency }}">{{ $formatted() }}</span>
@endif
