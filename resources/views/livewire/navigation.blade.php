{{--
    The sidebar navigation (#93): a design-system Menu built from the AdminNav
    registry. Sections come in position order with their label as a menu
    title; a section with no permitted items is never passed in, so its
    label is dropped too. A count is part of the link's name ("Orders (12
    orders awaiting fulfillment)"); the visual badge is primary for orders to
    ship, warning for low stock and neutral otherwise.

    In the AppShell's collapsed rail the design system hides the labels,
    titles and badges visually; admin.css turns the section titles into
    separators, the badges into dots and data-tooltip into a tooltip on hover
    and focus.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div data-ecommerce-admin-navigation>
    <x-artisanpack-menu :label="__( 'Store admin' )" class="ec-admin-nav" :classes="[ 'item' => 'ec-admin-nav__item', 'badge' => 'ec-admin-nav__badge' ]">
        @foreach ( $sections as $sectionKey => $section )
            @if ( ! $loop->first )
                <x-artisanpack-menu-separator class="ec-admin-nav__rail-separator" wire:key="ecommerce-admin-nav-separator-{{ $sectionKey }}" />
            @endif

            @if ( null !== $section['label'] )
                <x-artisanpack-menu-title :label="$section['label']" wire:key="ecommerce-admin-nav-section-{{ $sectionKey }}" />
            @endif

            @foreach ( $section['items'] as $item )
                @php
                    $accessibleName = null === $item['badgeLabel'] ? $item['label'] : __( ':label (:badge)', [ 'label' => $item['label'], 'badge' => $item['badgeLabel'] ] );
                @endphp
                <x-artisanpack-menu-item
                    wire:key="ecommerce-admin-nav-{{ $item['key'] }}"
                    :label="$item['label']"
                    :icon="$item['icon']"
                    :href="\ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::url( $item )"
                    :current="$item['active']"
                    :badge="null === $item['badgeCount'] ? null : (string) $item['badgeCount']"
                    :aria-label="$accessibleName"
                    :data-tooltip="$accessibleName"
                    :data-badge-tone="null === $item['badgeCount'] ? null : $item['badgeTone']"
                />
            @endforeach
        @endforeach
    </x-artisanpack-menu>
</div>
