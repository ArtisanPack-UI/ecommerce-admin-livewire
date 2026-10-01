@if ( [] === $lines )
    <p {{ $attributes->class( [ 'text-base-content/70' ] ) }}>{{ __( 'No address' ) }}</p>
@else
    <address {{ $attributes->class( [ 'not-italic', 'leading-relaxed' ] ) }}>
        @foreach ( $lines as $line )
            <span class="block">{{ $line }}</span>
        @endforeach

        @if ( null !== $phone )
            <span class="block"><span class="sr-only">{{ __( 'Phone:' ) }}</span> {{ $phone }}</span>
        @endif
    </address>
@endif
