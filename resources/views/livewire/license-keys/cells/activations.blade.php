{{--
    License keys cell (#127): "2 of 3" over a short bar that turns warning
    once every slot is used. A key with no limit (a null limit) reads "1, no
    limit" and has no bar; a limit of zero reads "0 of 0", also without one.
    The bar repeats the text, so it is hidden from screen readers; its width
    comes from an `.ec-w-N` class (no inline styles under the CSP).
--}}
@php
    $level = \ArtisanPackUI\EcommerceAdminLivewire\Livewire\LicenseKeys\Index::activationLevel( $row );
    $full  = \ArtisanPackUI\EcommerceAdminLivewire\Livewire\LicenseKeys\Index::activationsFull( $row );
@endphp
<div class="ec-license-keys-usage" data-activations-used="{{ (int) $row->activations_count }}">
    @if ( null === $row->activations_limit )
        <span><bdi dir="ltr">{{ __( ':count, no limit', [ 'count' => (int) $row->activations_count ] ) }}</bdi></span>
    @elseif ( null === $level )
        {{-- A limit of zero allows no activations: the count, with no bar to fill. --}}
        <span data-activations-full="true"><bdi dir="ltr">{{ __( ':used of :limit', [ 'used' => (int) $row->activations_count, 'limit' => (int) $row->activations_limit ] ) }}</bdi></span>
    @else
        <span><bdi dir="ltr">{{ __( ':used of :limit', [ 'used' => (int) $row->activations_count, 'limit' => (int) $row->activations_limit ] ) }}</bdi></span>
        <x-artisanpack-progress
            :value="(int) $row->activations_count"
            :max="(int) $row->activations_limit"
            size="sm"
            :color="$full ? 'warning' : 'primary'"
            class="ec-w-{{ $level }}"
            aria-hidden="true"
            :data-activations-full="$full ? 'true' : null"
        />
    @endif
</div>
