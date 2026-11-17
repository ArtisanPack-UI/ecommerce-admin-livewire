{{--
    Money (#82). See Money.

    Design-system text roles and tokens only (ec-money in resources/css/admin.css):
    tabular figures, no wrapping, the error text role for a negative amount and
    the muted role for a missing one. Each figure is isolated left-to-right.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( null === $formatted() )
    <span {{ $attributes->class( [ 'ec-money' ] ) }} data-tone="muted" data-money><span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'No amount' ) }}</span></span>
@else
    <span {{ $attributes->class( [ 'ec-money-group' ] ) }} data-money data-currency="{{ $currency }}"><bdi dir="ltr" class="ec-money" @if ( null !== $tone() ) data-tone="{{ $tone() }}" @endif>{{ $formatted() }}@if ( $showCode )<span class="ec-money-code">{{ $currency }}</span>@endif</bdi>@if ( null !== $formattedCompareAt() )<span class="apui-sr-only">{{ __( 'Was' ) }}</span><bdi dir="ltr" class="ec-money ec-money-was">{{ $formattedCompareAt() }}</bdi>@endif</span>
@endif
