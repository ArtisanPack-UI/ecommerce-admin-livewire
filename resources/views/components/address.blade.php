{{--
    Address (#85). A read-only postal address: plain <address> text, one line
    per part in the country's order, the name first at weight 500 and an
    optional phone after a decorative icon. It takes the surface it sits on.
    See Address.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( $isEmpty() )
    <p {{ $attributes->class( [ 'ec-address-empty' ] ) }}>{{ __( 'No address' ) }}</p>
@else
    <address {{ $attributes->class( [ 'ec-address' ] ) }}>
        @if ( null !== $name )
            <span class="ec-address-name">{{ $name }}</span>
        @endif

        @foreach ( $lines as $line )
            <span>{{ $line }}</span>
        @endforeach

        @if ( null !== $phone )
            <span class="ec-address-phone"><x-artisanpack-icon name="phone" size="sm" aria-hidden="true" /><span class="apui-sr-only">{{ __( 'Phone:' ) }}</span> <bdi dir="ltr">{{ $phone }}</bdi></span>
        @endif
    </address>
@endif
