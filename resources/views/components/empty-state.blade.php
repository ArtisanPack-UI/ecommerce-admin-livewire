{{--
    Empty state. See EmptyState.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div {{ $attributes->class( [ 'flex flex-col items-center gap-3 px-4 py-12 text-center' ] ) }} role="status" data-empty-state>
    <x-artisanpack-icon :name="$icon" class="w-10 h-10 opacity-50" aria-hidden="true" />
    <p class="text-lg font-semibold">{{ $title }}</p>

    @if ( null !== $description && '' !== $description )
        <p class="max-w-prose opacity-75">{{ $description }}</p>
    @endif

    @if ( $slot->isNotEmpty() )
        <div class="flex flex-wrap justify-center gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
