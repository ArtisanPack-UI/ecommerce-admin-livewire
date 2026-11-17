{{--
    Status badge (#81). See StatusBadge.

    A soft design-system Badge: a leading dot (the status color, or the
    merchant's hex as an SVG fill on a neutral badge, which a nonce-only
    style CSP leaves alone), a visually hidden type prefix, and the
    label. Labels never truncate; badges wrap as whole units.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@if ( null !== $hex )
    <x-artisanpack-badge variant="soft" color="neutral" :size="$size" data-status-type="{{ $type }}" {{ $attributes }}>
        <x-slot:icon><svg class="ec-hex-dot" width="8" height="8" viewBox="0 0 8 8" aria-hidden="true" focusable="false"><circle cx="4" cy="4" r="3.5" fill="{{ $hex }}" /></svg></x-slot:icon>
        <span class="apui-sr-only">{{ $typeLabel }}</span>{{ $label }}
    </x-artisanpack-badge>
@else
    <x-artisanpack-badge variant="soft" :color="$color" :size="$size" dot data-status-type="{{ $type }}" {{ $attributes }}>
        <span class="apui-sr-only">{{ $typeLabel }}</span>{{ $label }}
    </x-artisanpack-badge>
@endif
