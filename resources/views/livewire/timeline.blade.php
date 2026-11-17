{{--
    Activity timeline. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Timeline.

    The list is the design-system Timeline: an ordered list of events, each
    with its actor, and the raw payload of an event the admin doesn't know.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
@endphp
<div>
    <x-artisanpack-card :title="__( 'Timeline' )" data-timeline>
        <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="{{ __( 'Filter the timeline' ) }}">
            @foreach ( $filters as $value => $label )
                <x-artisanpack-button
                    wire:key="timeline-filter-{{ $value }}"
                    size="sm"
                    :variant="$filter === $value ? 'solid' : 'ghost'"
                    aria-pressed="{{ $filter === $value ? 'true' : 'false' }}"
                    wire:click="$set( 'filter', {{ \Illuminate\Support\Js::from( $value ) }} )">{{ $label }}</x-artisanpack-button>
            @endforeach
        </div>

        @if ( [] === $entries )
            <x-artisanpack-ec-admin-empty-state
                size="sm"
                :heading-level="4"
                :title="__( 'Nothing here yet' )"
                :description="'notes' === $filter ? __( 'No notes have been added.' ) : __( 'Activity will appear here as it happens.' )"
                icon="o-clock"
            />
        @else
            <x-artisanpack-timeline :label="__( 'Activity, newest first' )">
                @foreach ( $entries as $entry )
                    <x-artisanpack-timeline-item
                        wire:key="timeline-entry-{{ $entry['id'] }}"
                        data-event-type="{{ $entry['type'] }}"
                        :title="$entry['description']"
                        :icon="$entry['icon']"
                        :time="null !== $entry['time'] ? $entry['time']->diffForHumans() : null"
                        :datetime="null !== $entry['time'] ? $entry['time']->toIso8601String() : null"
                    >
                        <x-slot:card>
                            <span>{{ $entry['actor'] }}</span>
                            @if ( null !== $entry['time'] )
                                <span class="apui-sr-only">({{ LocalizedDate::format( $entry['time'] ) }})</span>
                            @endif
                            @if ( ! $entry['known'] && null !== $entry['payload'] && '' !== $entry['payload'] )
                                <details>
                                    <summary>{{ __( 'Details' ) }}</summary>
                                    <pre class="ec-payload">{{ $entry['payload'] }}</pre>
                                </details>
                            @endif
                        </x-slot:card>
                    </x-artisanpack-timeline-item>
                @endforeach
            </x-artisanpack-timeline>

            @if ( $hasMore )
                <div class="mt-2">
                    <x-artisanpack-button size="sm" wire:click="loadMore" wire:loading.attr="disabled">{{ __( 'Load more' ) }}</x-artisanpack-button>
                </div>
            @endif
        @endif
    </x-artisanpack-card>
</div>
