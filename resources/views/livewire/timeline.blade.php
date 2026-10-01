{{--
    Activity timeline. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Timeline.

    The list is composed here from x-artisanpack-timeline-item inside a
    semantic <ol> until livewire-ui-components ships a timeline wrapper
    (livewire-ui-components#112); swapping it in only touches this file.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
@endphp
<div>
    <x-artisanpack-card :title="__( 'Timeline' )" shadow data-timeline>
        <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="{{ __( 'Filter the timeline' ) }}">
            @foreach ( $filters as $value => $label )
                <x-artisanpack-button
                    wire:key="timeline-filter-{{ $value }}"
                    size="sm"
                    :variant="$filter === $value ? 'primary' : 'ghost'"
                    :label="$label"
                    aria-pressed="{{ $filter === $value ? 'true' : 'false' }}"
                    wire:click="$set( 'filter', '{{ $value }}' )"
                />
            @endforeach
        </div>

        @if ( [] === $entries )
            <x-artisanpack-ec-empty-state
                :title="__( 'Nothing here yet' )"
                :description="'notes' === $filter ? __( 'No notes have been added.' ) : __( 'Activity will appear here as it happens.' )"
                icon="o-clock"
            />
        @else
            <ol class="list-none" aria-label="{{ __( 'Activity, newest first' ) }}">
                @foreach ( $entries as $entry )
                    <li wire:key="timeline-entry-{{ $entry['id'] }}" data-event-type="{{ $entry['type'] }}">
                        <x-artisanpack-timeline-item
                            :title="$entry['description']"
                            :icon="$entry['icon']"
                            :first="$loop->first"
                            :last="$loop->last"
                        >
                        </x-artisanpack-timeline-item>
                        <div class="-mt-2 ps-8 pb-3 text-sm opacity-75">
                            <span>{{ $entry['actor'] }}</span>
                            @if ( null !== $entry['time'] )
                                &middot;
                                <time datetime="{{ $entry['time']->toIso8601String() }}" title="{{ LocalizedDate::format( $entry['time'] ) }}">
                                    {{ $entry['time']->diffForHumans() }}
                                    <span class="sr-only">({{ LocalizedDate::format( $entry['time'] ) }})</span>
                                </time>
                            @endif
                        </div>
                        @if ( ! $entry['known'] && null !== $entry['payload'] && '' !== $entry['payload'] )
                            <details class="ps-8 pb-3 text-sm">
                                <summary class="cursor-pointer">{{ __( 'Details' ) }}</summary>
                                <pre class="mt-2 overflow-x-auto whitespace-pre-wrap break-all">{{ $entry['payload'] }}</pre>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ol>

            @if ( $hasMore )
                <div class="mt-2">
                    <x-artisanpack-button size="sm" wire:click="loadMore" wire:loading.attr="disabled" :label="__( 'Load more' )" />
                </div>
            @endif
        @endif
    </x-artisanpack-card>
</div>
