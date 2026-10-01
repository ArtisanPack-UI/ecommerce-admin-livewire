@php( $image = \ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia::featured( $row ) )
@if ( null === $image )
    <div class="flex size-10 items-center justify-center rounded bg-base-200" aria-hidden="true">
        <x-artisanpack-icon name="o-photo" class="size-5 opacity-50" />
    </div>
@else
    <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" class="size-10 rounded object-cover" loading="lazy" />
@endif
