{{--
    Empty state (#80). See EmptyState.

    The design-system EmptyState as a status region; actions passed in the
    slot render in its actions part.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<x-artisanpack-empty-state
    {{ $attributes->class( [ 'ec-empty-state' ] ) }}
    role="status"
    data-empty-state
    :icon="$icon"
    :title="$title"
    :description="null !== $description && '' !== $description ? $description : null"
    :size="$size"
    :heading-level="$headingLevel"
>
    @if ( $slot->isNotEmpty() )
        <x-slot:actions>{{ $slot }}</x-slot:actions>
    @endif
</x-artisanpack-empty-state>
