{{--
    The table body of an index screen. See ResourceTable for why this is
    composed rather than built on x-artisanpack-table.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div {{ $attributes->class( [ 'overflow-x-auto' ] ) }} data-resource-table>
    <table class="table table-sm">
        <caption class="sr-only">{{ $caption }}</caption>

        <thead>
            <tr>
                @if ( $selectable )
                    <th scope="col" class="w-1">
                        {{-- The page's keys are read from data-keys on every use: Alpine
                             state from the first render would go stale after paging,
                             sorting, or filtering. --}}
                        <input
                            type="checkbox"
                            class="checkbox checkbox-sm"
                            aria-label="{{ __( 'Select every row on this page' ) }}"
                            wire:key="select-page-{{ md5( implode( ',', $pageKeys() ) ) }}"
                            data-keys="{{ json_encode( $pageKeys() ) }}"
                            data-select-page
                            x-data="{ keys() { return JSON.parse( this.$el.dataset.keys || '[]' ) } }"
                            x-bind:checked="keys().length > 0 && keys().every( ( key ) => $wire.selected.map( String ).includes( key ) )"
                            x-on:change="$wire.set( 'selected', $event.target.checked
                                ? [ ...new Set( [ ...$wire.selected.map( String ), ...keys() ] ) ]
                                : $wire.selected.map( String ).filter( ( key ) => ! keys().includes( key ) ) )"
                            @disabled( 0 === count( $pageKeys() ) )
                        />
                    </th>
                @endif

                @foreach ( $columns as $column )
                    <th
                        scope="col"
                        @class( [ $column['class'] ] )
                        @if ( $column['sortable'] ) aria-sort="{{ $ariaSort( $column ) }}" @endif
                    >
                        @if ( $column['sortable'] )
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 font-semibold hover:underline focus-visible:underline"
                                wire:click="sort( {{ \Illuminate\Support\Js::from( $column['key'] ) }} )"
                            >
                                {{ $column['label'] }}
                                <x-artisanpack-icon :name="$sortIcon( $column )" class="w-3 h-3" aria-hidden="true" />
                            </button>
                        @else
                            {{ $column['label'] }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach ( $rows as $row )
                <tr wire:key="resource-row-{{ $row->getKey() }}">
                    @if ( $selectable )
                        <td>
                            <input
                                type="checkbox"
                                class="checkbox checkbox-sm"
                                value="{{ $row->getKey() }}"
                                wire:model.live="selected"
                                aria-label="{{ $checkboxLabel( $row ) }}"
                            />
                        </td>
                    @endif

                    @foreach ( $columns as $column )
                        <td @class( [ $column['class'] ] )>
                            @if ( null !== $column['view'] )
                                @include( $column['view'], [ 'row' => $row, 'column' => $column, 'context' => $context ] )
                            @else
                                {{ $cellText( $row, $column ) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
