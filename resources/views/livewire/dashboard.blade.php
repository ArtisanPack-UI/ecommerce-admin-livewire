{{--
    The admin dashboard. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Dashboard.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-6">
    <x-artisanpack-header :title="__( 'Dashboard' )" :level="1" separator />

    @include( 'ecommerce-admin::partials.live-region', [ 'message' => $liveAnnouncement ] )

    @if ( $firstRun )
        <x-artisanpack-card shadow data-first-run>
            <x-artisanpack-ec-empty-state
                icon="o-building-storefront"
                :title="__( 'Welcome to your store' )"
                :description="__( 'Your store has no products or orders yet. Add your first product to get started.' )"
            >
                @if ( null !== $createUrl )
                    <x-artisanpack-button :link="$createUrl" color="primary" icon="o-plus" :label="__( 'Add your first product' )" />
                @endif
                @if ( null !== $importUrl )
                    <x-artisanpack-button :link="$importUrl" icon="o-arrow-up-tray" :label="__( 'Import products' )" />
                @endif
            </x-artisanpack-ec-empty-state>
        </x-artisanpack-card>
    @endif

    @if ( [] !== $widgets )
        <div class="grid gap-6 lg:grid-cols-2" data-dashboard-widgets>
            @foreach ( $widgets as $widget )
                <section
                    @class( [ 'min-w-0', 'lg:col-span-2' => 'full' === $widget['width'] ] )
                    aria-labelledby="dashboard-widget-{{ $widget['key'] }}"
                    data-dashboard-widget="{{ $widget['key'] }}"
                    wire:key="dashboard-widget-{{ $widget['key'] }}"
                >
                    @if ( null !== $widget['view'] )
                        @include( $widget['view'], [ 'widget' => $widget, 'dashboard' => $dashboard ] )
                    @else
                        <h2 id="dashboard-widget-{{ $widget['key'] }}" class="sr-only">{{ $widget['label'] }}</h2>
                        @livewire( $widget['component'], [], key( 'dashboard-widget-component-' . $widget['key'] ) )
                    @endif
                </section>
            @endforeach
        </div>
    @elseif ( ! $firstRun )
        @if ( [] === $sections )
            <p>{{ __( 'There is nothing here for you yet.' ) }}</p>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-dashboard-sections>
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
    @endif
</div>
