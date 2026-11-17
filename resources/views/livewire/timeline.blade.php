{{--
    Activity timeline (#89). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Timeline.

    A Card whose header holds the filter: toggle buttons (aria-pressed) in
    a labelled group, styled as segmented tabs, since the list below stays
    the same region. The list is the design-system Timeline, newest first:
    a decorative marker (toned only for events that need attention or
    complete money movement), the event and a relative time with the full
    date hidden after it, the actor, and the raw payload of an event the
    admin doesn't know. "Load more" moves focus to the first new entry.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPack\LivewireUiComponents\Support\IconRegistry;
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
@endphp
<div>
    <x-artisanpack-card :title="__( 'Timeline' )" class="ec-timeline" data-timeline>
        <x-slot:actions>
            <div class="apui-tabs" data-size="sm">
                <div data-scope="tabs" data-part="list" role="group" aria-label="{{ __( 'Filter the timeline' ) }}">
                    @foreach ( $filters as $value => $label )
                        <button
                            type="button"
                            wire:key="timeline-filter-{{ $value }}"
                            data-scope="tabs"
                            data-part="trigger"
                            aria-pressed="{{ $filter === $value ? 'true' : 'false' }}"
                            wire:click="$set( 'filter', {{ \Illuminate\Support\Js::from( $value ) }} )"
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </x-slot:actions>

        <div class="ec-timeline-loading" wire:loading.grid wire:target="filter" aria-busy="true">
            <p class="apui-sr-only" role="status">{{ __( 'Loading activity…' ) }}</p>
            @for ( $skeleton = 0; $skeleton < 4; $skeleton++ )
                <div class="ec-timeline-skeleton" aria-hidden="true">
                    <x-artisanpack-skeleton shape="circle" class="ec-skel-marker" />
                    <div class="ec-timeline-skeleton-lines">
                        <x-artisanpack-skeleton shape="text" class="ec-skel-line" />
                        <x-artisanpack-skeleton shape="text" class="ec-skel-line-short" />
                    </div>
                </div>
            @endfor
        </div>

        <div wire:loading.remove wire:target="filter">
            @if ( [] === $entries )
                <x-artisanpack-ec-admin-empty-state
                    size="sm"
                    :heading-level="4"
                    :title="__( 'Nothing here yet' )"
                    :description="'notes' === $filter ? __( 'No notes have been added.' ) : __( 'Activity will appear here as it happens.' )"
                    icon="clock"
                />
            @else
                <x-artisanpack-timeline :label="__( 'Activity, newest first' )">
                    @foreach ( $entries as $entry )
                        <li
                            wire:key="timeline-entry-{{ $entry['id'] }}"
                            data-scope="timeline"
                            data-part="item"
                            data-event-type="{{ $entry['type'] }}"
                            data-timeline-entry
                            tabindex="-1"
                        >
                            <span data-scope="timeline" data-part="marker"@if ( null !== $entry['tone'] ) data-color="{{ $entry['tone'] }}"@endif aria-hidden="true">{!! IconRegistry::render( $entry['icon'] ) !!}</span>
                            <div data-scope="timeline" data-part="head">
                                <span class="ec-timeline-event">{{ $entry['description'] }}</span>
                                @if ( null !== $entry['time'] )
                                    <time data-scope="timeline" data-part="time" datetime="{{ $entry['time']->toIso8601String() }}">{{ $entry['time']->diffForHumans() }}<span class="apui-sr-only"> ({{ LocalizedDate::format( $entry['time'] ) }})</span></time>
                                @endif
                            </div>
                            <div data-scope="timeline" data-part="body">{{ $entry['actor'] }}</div>
                            @if ( ! $entry['known'] && null !== $entry['payload'] && '' !== $entry['payload'] )
                                <details class="ec-details">
                                    <summary>{{ __( 'Details' ) }}</summary>
                                    <pre class="ec-payload" dir="ltr">{{ $entry['payload'] }}</pre>
                                </details>
                            @endif
                        </li>
                    @endforeach
                </x-artisanpack-timeline>

                @if ( $hasMore )
                    <div class="ec-timeline-more">
                        <x-artisanpack-button
                            variant="outline"
                            color="neutral"
                            size="sm"
                            wire:loading.attr="disabled"
                            wire:target="loadMore"
                            data-load-more
                            x-data
                            x-on:click="const shown = {{ count( $entries ) }}; const list = $el.closest( '[data-timeline]' ); $wire.loadMore().then( () => $nextTick( () => list?.querySelectorAll( '[data-timeline-entry]' )[ shown ]?.focus() ) )"
                        >{{ __( 'Load more' ) }}</x-artisanpack-button>
                    </div>
                @endif
            @endif
        </div>
    </x-artisanpack-card>
</div>
