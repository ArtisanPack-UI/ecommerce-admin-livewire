{{--
    The table section of an index screen that uses WithResourceTable (#79):
    one design-system Table holding the toolbar (search, filters, rows per
    page, CSV export), the filter drawer, the bulk-action bar, the inline
    confirmation for destructive bulk actions, the grid, the pager, and the
    empty states.

    Expects the variables from WithResourceTable::resourceTableData(), plus:

    - $emptyIcon, $emptyTitle, $emptyDescription — the "nothing yet" state.
    - $searchHint (optional) — what the search matches, as its placeholder.
    - $bulkControls (optional) — a view rendered inside the bulk-action bar.
    - $rowLabel (optional) — a Closure naming a row for its checkbox.
    - $cellContext (optional) — screen state passed to cell views as $context.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    $tableFilterCount ??= 0;
    $tablePerPageOptions = array_map(
        static fn ( int $value ): array => [ 'value' => (string) $value, 'label' => __( ':count per page', [ 'count' => $value ] ) ],
        $tablePerPageValues,
    );
@endphp
<div class="ec-table-section" data-resource-table-section x-data="{ filtersOpen: @js( $tableFilterCount > 0 ) }">
    {{-- Tells screen-reader users how many rows a search or filter left. --}}
    @include( 'ecommerce-admin::partials.live-region', [ 'message' => isset( $tableRows ) ? trans_choice( ':count result|:count results', $tableRows->total(), [ 'count' => $tableRows->total() ] ) : '' ] )

    <div class="apui-table ec-table" data-surface="default" wire:loading.attr="aria-busy">
        <div data-scope="table" data-part="toolbar">
            <div class="ec-table-toolbar">
                <div class="ec-table-search">
                    <x-artisanpack-input
                        id="resource-table-search"
                        size="sm"
                        type="search"
                        leading-icon="search"
                        :aria-label="__( 'Search' )"
                        :placeholder="$searchHint ?? __( 'Search' )"
                        :aria-describedby="isset( $searchHint ) ? 'resource-table-search-hint' : null"
                        wire:model.live.debounce.300ms="search"
                    />
                    {{-- The placeholder disappears on typing, so the hint is also read out. --}}
                    @isset( $searchHint )
                        <span id="resource-table-search-hint" class="apui-sr-only">{{ $searchHint }}</span>
                    @endisset
                </div>

                @if ( [] !== $tableFilters )
                    <x-artisanpack-button
                        variant="outline"
                        color="neutral"
                        size="sm"
                        leading-icon="o-adjustments-horizontal"
                        aria-controls="resource-table-filters"
                        aria-expanded="{{ $tableFilterCount > 0 ? 'true' : 'false' }}"
                        x-bind:aria-expanded="filtersOpen ? 'true' : 'false'"
                        x-on:click="filtersOpen = ! filtersOpen"
                        data-filters-toggle
                    >
                        {{ __( 'Filters' ) }}
                        @if ( $tableFilterCount > 0 )
                            <span class="ec-filter-count" aria-hidden="true">{{ $tableFilterCount }}</span>
                            <span class="apui-sr-only">{{ trans_choice( '(:count filter on)|(:count filters on)', $tableFilterCount, [ 'count' => $tableFilterCount ] ) }}</span>
                        @endif
                    </x-artisanpack-button>
                @endif

                <div class="ec-table-toolbar-end">
                    @if ( $tableFiltersActive )
                        <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="x" wire:click="resetFilters">{{ __( 'Clear filters' ) }}</x-artisanpack-button>
                    @endif

                    <x-artisanpack-select
                        placeholder=""
                        id="resource-table-per-page"
                        size="sm"
                        :aria-label="__( 'Rows per page' )"
                        :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $tablePerPageOptions )"
                        wire:model.live="perPage"
                    />

                    <x-artisanpack-button
                        variant="outline"
                        color="neutral"
                        size="sm"
                        leading-icon="download"
                        wire:click="exportCsv"
                        wire:loading.attr="disabled"
                        wire:target="exportCsv"
                    >{{ __( 'Export CSV' ) }}</x-artisanpack-button>
                </div>
            </div>
        </div>

        @if ( [] !== $tableFilters )
            <div
                id="resource-table-filters"
                class="ec-filters"
                role="group"
                aria-label="{{ __( 'Filters' ) }}"
                @if ( 0 === $tableFilterCount ) hidden @endif
                x-bind:hidden="! filtersOpen"
            >
                @foreach ( $tableFilters as $filter )
                    <div wire:key="resource-filter-{{ $filter['key'] }}">
                        @switch ( $filter['type'] )
                            @case( 'text' )
                                <x-artisanpack-input
                                    id="resource-filter-{{ $filter['key'] }}"
                                    size="sm"
                                    :label="$filter['label']"
                                    wire:model.live.debounce.300ms="filters.{{ $filter['key'] }}"
                                />
                                @break

                            @case( 'multiselect' )
                                <x-artisanpack-combobox
                                    multiple
                                    id="resource-filter-{{ $filter['key'] }}"
                                    size="sm"
                                    :label="$filter['label']"
                                    :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $filter['options'] )"
                                    :empty-label="__( 'No results found.' )"
                                    wire:model.live="filters.{{ $filter['key'] }}"
                                />
                                @break

                            @case( 'date-range' )
                                <fieldset class="ec-range">
                                    <legend>{{ $filter['label'] }}</legend>
                                    <div class="ec-range-fields">
                                        <x-artisanpack-date-picker
                                            id="resource-filter-{{ $filter['key'] }}-from"
                                            size="sm"
                                            :aria-label="__( 'From' )"
                                            :placeholder="__( 'From' )"
                                            wire:model.live="filters.{{ $filter['key'] }}.from"
                                        />
                                        <span aria-hidden="true">&ndash;</span>
                                        <x-artisanpack-date-picker
                                            id="resource-filter-{{ $filter['key'] }}-to"
                                            size="sm"
                                            :aria-label="__( 'To' )"
                                            :placeholder="__( 'To' )"
                                            wire:model.live="filters.{{ $filter['key'] }}.to"
                                        />
                                    </div>
                                </fieldset>
                                @break

                            @case( 'number-range' )
                                <fieldset class="ec-range">
                                    <legend>{{ $filter['label'] }}</legend>
                                    <div class="ec-range-fields">
                                        <x-artisanpack-input
                                            id="resource-filter-{{ $filter['key'] }}-min"
                                            size="sm"
                                            type="number"
                                            min="0"
                                            step="any"
                                            inputmode="decimal"
                                            :aria-label="__( 'Min' )"
                                            :placeholder="__( 'Min' )"
                                            wire:model.live.debounce.500ms="filters.{{ $filter['key'] }}.min"
                                        />
                                        <span aria-hidden="true">&ndash;</span>
                                        <x-artisanpack-input
                                            id="resource-filter-{{ $filter['key'] }}-max"
                                            size="sm"
                                            type="number"
                                            min="0"
                                            step="any"
                                            inputmode="decimal"
                                            :aria-label="__( 'Max' )"
                                            :placeholder="__( 'Max' )"
                                            wire:model.live.debounce.500ms="filters.{{ $filter['key'] }}.max"
                                        />
                                    </div>
                                </fieldset>
                                @break

                            @default
                                <x-artisanpack-select
                                    id="resource-filter-{{ $filter['key'] }}"
                                    size="sm"
                                    :label="$filter['label']"
                                    :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $filter['options'] )"
                                    :placeholder="__( 'Any' )"
                                    wire:model.live="filters.{{ $filter['key'] }}"
                                />
                        @endswitch
                    </div>
                @endforeach

                @if ( $tableFilterCount > 0 )
                    <div class="ec-filters-actions">
                        <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="x" wire:click="resetFilters">{{ __( 'Clear filters' ) }}</x-artisanpack-button>
                    </div>
                @endif
            </div>
        @endif

        <div aria-live="polite">
            @if ( $tableSelectable && $tableSelectionCount > 0 )
                <x-artisanpack-ec-bulk-action-bar
                    :count="$tableSelectionCount"
                    :actions="$tableBulkActions"
                    :total="$tableRows->total()"
                    :page-count="$tableRows->count()"
                    :all-matching="$tableAllMatching"
                >
                    @isset( $bulkControls )
                        @include( $bulkControls )
                    @endisset
                </x-artisanpack-ec-bulk-action-bar>
            @endif
        </div>

        @if ( null !== $tableConfirming )
            <div
                class="ec-confirm"
                role="alertdialog"
                aria-modal="false"
                aria-labelledby="resource-table-confirm-title"
                aria-describedby="resource-table-confirm-text"
                x-data
                x-init="$nextTick( () => $el.querySelector( '[data-confirm]' )?.focus() )"
            >
                <x-artisanpack-icon name="triangle-alert" aria-hidden="true" />
                <div>
                    <p id="resource-table-confirm-title" class="ec-confirm-title">{{ $tableConfirming['label'] }}</p>
                    <p id="resource-table-confirm-text">{{ $tableConfirming['confirm'] }}</p>
                </div>
                <div class="ec-confirm-actions">
                    <x-artisanpack-button
                        color="error"
                        size="sm"
                        data-confirm
                        data-focus-return="bulk-{{ $tableConfirming['key'] }}"
                        wire:click="confirmBulkAction( {{ \Illuminate\Support\Js::from( $tableConfirmToken ) }} )"
                        wire:loading.attr="disabled"
                    >{{ __( 'Confirm' ) }}</x-artisanpack-button>
                    <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="cancelBulkAction" data-focus-return="bulk-{{ $tableConfirming['key'] }}">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                </div>
            </div>
        @endif

        @if ( 0 === $tableRows->total() )
            @if ( $tableFiltersActive )
                <x-artisanpack-ec-admin-empty-state
                    icon="search-x"
                    :title="__( 'No matches' )"
                    :description="__( 'Nothing matches the search and filters. Try a broader search or clear the filters.' )"
                >
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" wire:click="resetFilters">{{ __( 'Clear filters' ) }}</x-artisanpack-button>
                </x-artisanpack-ec-admin-empty-state>
            @else
                <x-artisanpack-ec-admin-empty-state :icon="$emptyIcon" :title="$emptyTitle" :description="$emptyDescription" />
            @endif
        @else
            <x-artisanpack-ec-resource-table
                :columns="$tableColumns"
                :rows="$tableRows"
                :caption="$tableCaption"
                :sort="$tableSort"
                :direction="$tableDirection"
                :selectable="$tableSelectable"
                :row-label="$rowLabel ?? null"
                :context="$cellContext ?? []"
                :selected="$selected ?? []"
            />

            <div data-scope="table" data-part="footer">
                <x-artisanpack-pagination :rows="$tableRows" summary :summary-label="__( 'Showing {from} to {to} of {total} results' )" />
            </div>
        @endif
    </div>
</div>
