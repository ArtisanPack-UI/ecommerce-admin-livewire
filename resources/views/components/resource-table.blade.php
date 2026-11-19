{{--
    The grid of an index screen (#79): the scroll part of a design-system
    Table. See ResourceTable for why this renders the Table markup itself.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    $pageKeys  = $pageKeys();
    $pageState = $pageSelectionState();
@endphp
<div {{ $attributes->merge( [ 'data-scope' => 'table', 'data-part' => 'scroll' ] ) }} data-resource-table>
    <table data-scope="table" data-part="table">
        <caption class="apui-sr-only">{{ $caption }}</caption>

        <thead>
            <tr>
                @if ( $selectable )
                    {{-- The page's keys are read from data-keys on every change: Alpine
                         state from the first render would go stale after paging,
                         sorting, or filtering. The scope sits on the cell so the
                         checkbox keeps its own Alpine handlers; change bubbles up. --}}
                    <th
                        data-scope="table"
                        data-part="select"
                        scope="col"
                        wire:key="select-page-{{ md5( implode( ',', $pageKeys ) . '|' . $pageState ) }}"
                        data-keys="{{ json_encode( $pageKeys ) }}"
                        x-data="{ keys() { return JSON.parse( $root.dataset.keys || '[]' ) } }"
                        x-on:change="$wire.set( 'selected', $event.target.checked
                            ? [ ...new Set( [ ...$wire.selected.map( String ), ...keys() ] ) ]
                            : $wire.selected.map( String ).filter( ( key ) => ! keys().includes( key ) ) )"
                    >
                        <x-artisanpack-checkbox
                            :aria-label="__( 'Select every row on this page' )"
                            :checked="'checked' === $pageState"
                            :indeterminate="'indeterminate' === $pageState"
                            :disabled="[] === $pageKeys"
                            data-select-page
                        />
                    </th>
                @endif

                @foreach ( $columns as $column )
                    <th
                        data-scope="table"
                        data-part="head-cell"
                        scope="col"
                        @class( [ $column['class'] ] )
                        @if ( $column['numeric'] ?? false ) data-numeric @endif
                        @if ( $column['sortable'] ) aria-sort="{{ $ariaSort( $column ) }}" @endif
                    >
                        @if ( $column['sortable'] )
                            {{-- A hidden label keeps the sort button; only the text is visually hidden. --}}
                            <button
                                type="button"
                                data-scope="table"
                                data-part="sort"
                                wire:click="sort( {{ \Illuminate\Support\Js::from( $column['key'] ) }} )"
                            >@if ( $column['labelHidden'] ?? false )<span class="apui-sr-only">{{ $column['label'] }}</span>@else{{ $column['label'] }}@endif<x-artisanpack-icon :name="$sortIcon( $column )" aria-hidden="true" /></button>
                        @elseif ( $column['labelHidden'] ?? false )
                            <span class="apui-sr-only">{{ $column['label'] }}</span>
                        @else
                            {{ $column['label'] }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @foreach ( $rows as $row )
                <tr
                    data-scope="table"
                    data-part="row"
                    wire:key="resource-row-{{ $row->getKey() }}"
                    @if ( $selectable ) aria-selected="{{ $isSelected( $row ) ? 'true' : 'false' }}" @endif
                >
                    @if ( $selectable )
                        <td data-scope="table" data-part="select">
                            <x-artisanpack-checkbox
                                :aria-label="$checkboxLabel( $row )"
                                value="{{ $row->getKey() }}"
                                wire:model.live="selected"
                            />
                        </td>
                    @endif

                    @foreach ( $columns as $column )
                        <td
                            data-scope="table"
                            data-part="cell"
                            @class( [ $column['class'] ] )
                            @if ( $column['numeric'] ?? false ) data-numeric @endif
                        >
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
