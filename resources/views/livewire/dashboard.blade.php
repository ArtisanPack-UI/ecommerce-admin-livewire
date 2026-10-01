<div>
    <x-artisanpack-header :title="__( 'Dashboard' )" :level="1" separator />

    @if ( [] === $sections )
        <p>{{ __( 'There is nothing here for you yet.' ) }}</p>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ( $sections as $sectionKey => $section )
                <x-artisanpack-card :title="$section['label']" shadow wire:key="ecommerce-admin-dashboard-{{ $sectionKey }}">
                    <ul class="flex flex-col gap-2">
                        @foreach ( $section['items'] as $item )
                            <li wire:key="ecommerce-admin-dashboard-link-{{ $item['key'] }}">
                                <a href="{{ \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::url( $item ) }}" class="link link-hover inline-flex items-center gap-2">
                                    <x-artisanpack-icon :name="$item['icon']" class="w-4 h-4" aria-hidden="true" />
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-artisanpack-card>
            @endforeach
        </div>
    @endif
</div>
