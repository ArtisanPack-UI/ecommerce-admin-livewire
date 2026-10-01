<nav aria-label="{{ __( 'Store admin' ) }}">
    <x-artisanpack-menu>
        @foreach ( $sections as $sectionKey => $section )
            @if ( null !== $section['label'] )
                <x-artisanpack-menu-separator :title="$section['label']" wire:key="ecommerce-admin-nav-section-{{ $sectionKey }}" />
            @endif

            @foreach ( $section['items'] as $item )
                <x-artisanpack-menu-item
                    wire:key="ecommerce-admin-nav-{{ $item['key'] }}"
                    :title="$item['label']"
                    :icon="$item['icon']"
                    :link="\ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::url( $item )"
                    :active="$item['active']"
                    :badge="null === $item['badgeCount'] ? null : (string) $item['badgeCount']"
                    badge-classes="badge-primary"
                    :aria-current="$item['active'] ? 'page' : null"
                    :aria-label="null === $item['badgeLabel'] ? null : __( ':label (:badge)', [ 'label' => $item['label'], 'badge' => $item['badgeLabel'] ] )"
                />
            @endforeach
        @endforeach
    </x-artisanpack-menu>
</nav>
