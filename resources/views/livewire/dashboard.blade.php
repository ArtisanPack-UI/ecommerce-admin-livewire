{{--
    Dashboard (#116). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Dashboard.

    The admin home. Registered widgets render in a two-column grid in
    registry order: a `full` widget spans both columns, the rest sit side by
    side; below 1024px every widget is full width. Each widget is a section
    labelled by its visible or visually hidden heading. A brand-new store
    gets a welcome card instead, with the page's one primary action ("Add
    your first product"). Someone whose role sees no widgets gets a card of
    links for each section they can open. Live updates go to one polite
    region.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
@endphp
<div class="ec-stack" data-dashboard>
    <x-artisanpack-page-header :title="__( 'Dashboard' )" :description="$today" :level="1" />

    @include( 'ecommerce-admin::partials.live-region', [ 'message' => $liveAnnouncement ] )

    @if ( $firstRun )
        <div class="apui-card" data-first-run>
            <x-artisanpack-ec-admin-empty-state
                class="ec-first-run"
                icon="o-building-storefront"
                :title="__( 'Welcome to your store' )"
                :description="__( 'Your store has no products or orders yet. Add your first product to get started.' )"
            >
                @if ( null !== $createUrl )
                    <x-artisanpack-button :href="$createUrl" leading-icon="plus">{{ __( 'Add your first product' ) }}</x-artisanpack-button>
                @endif
                @if ( null !== $importUrl )
                    <x-artisanpack-button :href="$importUrl" variant="outline" color="neutral" leading-icon="upload">{{ __( 'Import products' ) }}</x-artisanpack-button>
                @endif
            </x-artisanpack-ec-admin-empty-state>
        </div>
    @endif

    @if ( [] !== $widgets )
        <div class="ec-dashboard-grid" data-dashboard-widgets>
            @foreach ( $widgets as $widget )
                <section
                    @class( [ 'ec-dashboard-widget', 'ec-dashboard-widget--full' => 'full' === $widget['width'] ] )
                    aria-labelledby="dashboard-widget-{{ $widget['key'] }}"
                    data-dashboard-widget="{{ $widget['key'] }}"
                    wire:key="dashboard-widget-{{ $widget['key'] }}"
                >
                    @if ( null !== $widget['view'] )
                        @include( $widget['view'], [ 'widget' => $widget, 'dashboard' => $dashboard ] )
                    @else
                        <h2 id="dashboard-widget-{{ $widget['key'] }}" class="apui-sr-only">{{ $widget['label'] }}</h2>
                        @livewire( $widget['component'], [], key( 'dashboard-widget-component-' . $widget['key'] ) )
                    @endif
                </section>
            @endforeach
        </div>
    @elseif ( ! $firstRun )
        @if ( [] === $sections )
            <x-artisanpack-ec-admin-empty-state
                icon="lock"
                :title="__( 'There is nothing here for you yet.' )"
                :description="__( 'Ask a store admin for access to the screens you need.' )"
            />
        @else
            <div class="ec-dashboard-links" data-dashboard-sections>
                @foreach ( $sections as $sectionKey => $section )
                    <section class="apui-card" aria-labelledby="dashboard-section-{{ $sectionKey }}" wire:key="ecommerce-admin-dashboard-{{ $sectionKey }}">
                        <div data-scope="card" data-part="header">
                            <h2 id="dashboard-section-{{ $sectionKey }}" data-scope="card" data-part="title">{{ $section['label'] ?? __( 'Store admin' ) }}</h2>
                        </div>
                        <div data-scope="card" data-part="body">
                            <ul class="ec-plain-list ec-dashboard-links__list">
                                @foreach ( $section['items'] as $item )
                                    <li wire:key="ecommerce-admin-dashboard-link-{{ $item['key'] }}">
                                        <a href="{{ AdminNav::url( $item ) }}" class="ec-dashboard-link">
                                            <x-artisanpack-icon :name="$item['icon']" aria-hidden="true" />
                                            <span>{{ $item['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    @endif
</div>
