<div data-ecommerce-admin-navigation>
    <x-artisanpack-menu :label="__( 'Store admin navigation' )">
        @foreach ( $sections as $sectionKey => $section )
            @if ( null !== $section['label'] )
                <x-artisanpack-menu-separator :title="$section['label']" wire:key="ecommerce-admin-nav-section-{{ $sectionKey }}" />
            @endif

            @foreach ( $section['items'] as $item )
                <x-artisanpack-menu-item
                    wire:key="ecommerce-admin-nav-{{ $item['key'] }}"
                    :title="$item['label']"
                    :icon="$item['icon']"
                    :href="\ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::url( $item )"
                    :current="$item['active']"
                    :badge="null === $item['badgeCount'] ? null : (string) $item['badgeCount']"
                    :aria-label="null === $item['badgeLabel'] ? null : __( ':label (:badge)', [ 'label' => $item['label'], 'badge' => $item['badgeLabel'] ] )"
                />
            @endforeach
        @endforeach
    </x-artisanpack-menu>
</div>
