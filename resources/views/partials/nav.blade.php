{{--
    The admin navigation, rendered from AdminNav for the signed-in user.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
@endphp
<nav aria-label="{{ __( 'Store admin' ) }}">
    <x-artisanpack-menu>
        @foreach ( AdminNav::grouped( auth()->user() ) as $sectionKey => $section )
            @if ( null !== $section['label'] )
                <x-artisanpack-menu-separator :title="$section['label']" wire:key="ecommerce-admin-nav-section-{{ $sectionKey }}" />
            @endif

            @foreach ( $section['items'] as $item )
                @php( $isActive = AdminNav::isActive( $item ) )
                <x-artisanpack-menu-item
                    wire:key="ecommerce-admin-nav-{{ $item['key'] }}"
                    :title="$item['label']"
                    :icon="$item['icon']"
                    :link="AdminNav::url( $item )"
                    :active="$isActive"
                    :aria-current="$isActive ? 'page' : null"
                />
            @endforeach
        @endforeach
    </x-artisanpack-menu>
</nav>
