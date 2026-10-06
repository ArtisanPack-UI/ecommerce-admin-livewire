@if ( $row->is_featured )
    <span class="inline-flex items-center gap-1" data-featured>
        <x-artisanpack-icon name="s-star" class="h-4 w-4 text-warning" aria-hidden="true" />
        <span class="sr-only">{{ __( 'Featured' ) }}</span>
    </span>
@else
    <span class="sr-only">{{ __( 'Not featured' ) }}</span>
@endif
