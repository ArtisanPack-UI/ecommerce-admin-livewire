<div class="inline-flex items-center" data-status-type="{{ $type }}">
    <span class="sr-only">{{ $typeLabel }}</span>
    <x-artisanpack-badge :value="$label" :color="$color" {{ $attributes->class( [ 'badge-sm', 'whitespace-nowrap' ] ) }} />
</div>
