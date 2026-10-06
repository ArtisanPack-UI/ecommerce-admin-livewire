{{--
    The table section of an index screen that uses WithResourceTable:
    search and filters, the bulk-action bar and its confirmation, the table,
    pagination, and the empty states.

    Expects the variables from WithResourceTable::resourceTableData(), plus:

    - $emptyIcon, $emptyTitle, $emptyDescription — the "nothing yet" state.
    - $bulkControls (optional) — a view rendered inside the bulk-action bar.
    - $rowLabel (optional) — a Closure naming a row for its checkbox.
    - $cellContext (optional) — screen state passed to cell views as $context.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-4" data-resource-table-section>
    {{-- Tells screen-reader users how many rows a search or filter left. --}}
    @include( 'ecommerce-admin::partials.live-region', [ 'message' => isset( $tableRows ) ? trans_choice( ':count result|:count results', $tableRows->total(), [ 'count' => $tableRows->total() ] ) : '' ] )

    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-end gap-3">
            <x-artisanpack-input
                id="resource-table-search"
                class="min-w-64"
                :label="__( 'Search' )"
                :hint="$searchHint ?? null"
                icon="o-magnifying-glass"
                type="search"
                wire:model.live.debounce.300ms="search"
            />

            <x-artisanpack-select
                id="resource-table-per-page"
                :label="__( 'Rows per page' )"
                :options="array_map( static fn ( int $value ): array => [ 'id' => $value, 'name' => (string) $value ], $tablePerPageValues )"
                wire:model.live="perPage"
            />

            <div class="flex flex-wrap gap-2 ms-auto">
                @if ( $tableFiltersActive )
                    <x-artisanpack-button variant="ghost" size="sm" icon="o-x-mark" wire:click="resetFilters" :label="__( 'Clear filters' )" />
                @endif

                <x-artisanpack-button
                    variant="outline"
                    size="sm"
                    icon="o-arrow-down-tray"
                    wire:click="exportCsv"
                    wire:loading.attr="disabled"
                    :label="__( 'Export CSV' )"
                />
            </div>
        </div>

        @if ( [] !== $tableFilters )
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" role="group" aria-label="{{ __( 'Filters' ) }}">
                @foreach ( $tableFilters as $filter )
                    <div wire:key="resource-filter-{{ $filter['key'] }}">
                        @switch ( $filter['type'] )
                            @case( 'text' )
                                <x-artisanpack-input
                                    id="resource-filter-{{ $filter['key'] }}"
                                    :label="$filter['label']"
                                    wire:model.live.debounce.300ms="filters.{{ $filter['key'] }}"
                                />
                                @break

                            @case( 'multiselect' )
                                <x-artisanpack-choices-offline
                                    id="resource-filter-{{ $filter['key'] }}"
                                    :label="$filter['label']"
                                    :options="$filter['options']"
                                    values-as-string
                                    searchable
                                    :no-result-text="__( 'No results found.' )"
                                    wire:model.live="filters.{{ $filter['key'] }}"
                                />
                                @break

                            @case( 'date-range' )
                                {{-- Two dates until livewire-ui-components ships a range picker (spec §10, U3). --}}
                                <fieldset class="fieldset">
                                    <legend class="fieldset-legend">{{ $filter['label'] }}</legend>
                                    <div class="flex gap-2">
                                        <x-artisanpack-input
                                            id="resource-filter-{{ $filter['key'] }}-from"
                                            type="date"
                                            :label="__( 'From' )"
                                            wire:model.live="filters.{{ $filter['key'] }}.from"
                                        />
                                        <x-artisanpack-input
                                            id="resource-filter-{{ $filter['key'] }}-to"
                                            type="date"
                                            :label="__( 'To' )"
                                            wire:model.live="filters.{{ $filter['key'] }}.to"
                                        />
                                    </div>
                                </fieldset>
                                @break

                            @case( 'number-range' )
                                <fieldset class="fieldset">
                                    <legend class="fieldset-legend">{{ $filter['label'] }}</legend>
                                    <div class="flex gap-2">
                                        <x-artisanpack-input
                                            id="resource-filter-{{ $filter['key'] }}-min"
                                            type="number"
                                            min="0"
                                            step="any"
                                            inputmode="decimal"
                                            :label="__( 'Min' )"
                                            wire:model.live.debounce.500ms="filters.{{ $filter['key'] }}.min"
                                        />
                                        <x-artisanpack-input
                                            id="resource-filter-{{ $filter['key'] }}-max"
                                            type="number"
                                            min="0"
                                            step="any"
                                            inputmode="decimal"
                                            :label="__( 'Max' )"
                                            wire:model.live.debounce.500ms="filters.{{ $filter['key'] }}.max"
                                        />
                                    </div>
                                </fieldset>
                                @break

                            @default
                                <x-artisanpack-select
                                    id="resource-filter-{{ $filter['key'] }}"
                                    :label="$filter['label']"
                                    :options="$filter['options']"
                                    :placeholder="__( 'Any' )"
                                    placeholder-value=""
                                    wire:model.live="filters.{{ $filter['key'] }}"
                                />
                        @endswitch
                    </div>
                @endforeach
            </div>
        @endif
    </div>

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
        <div role="alertdialog" aria-modal="false" aria-labelledby="resource-table-confirm-title" aria-describedby="resource-table-confirm-text" class="rounded-box border border-warning bg-base-100 p-4" x-data x-init="$nextTick( () => $el.querySelector( '[data-confirm]' )?.focus() )">
            <p id="resource-table-confirm-title" class="font-semibold">{{ $tableConfirming['label'] }}</p>
            <p id="resource-table-confirm-text" class="mb-3">{{ $tableConfirming['confirm'] }}</p>
            <div class="flex gap-2">
                <x-artisanpack-button
                    variant="error"
                    size="sm"
                    data-confirm
                    data-focus-return="bulk-{{ $tableConfirming['key'] }}"
                    wire:click="confirmBulkAction( {{ \Illuminate\Support\Js::from( $tableConfirmToken ) }} )"
                    wire:loading.attr="disabled"
                    :label="__( 'Confirm' )"
                />
                <x-artisanpack-button variant="ghost" size="sm" wire:click="cancelBulkAction" data-focus-return="bulk-{{ $tableConfirming['key'] }}" :label="__( 'Cancel' )" />
            </div>
        </div>
    @endif

    @if ( 0 === $tableRows->total() )
        @if ( $tableFiltersActive )
            <x-artisanpack-ec-empty-state
                icon="o-magnifying-glass"
                :title="__( 'No matches' )"
                :description="__( 'Nothing matches the search and filters. Try a broader search or clear the filters.' )"
            >
                <x-artisanpack-button variant="outline" size="sm" wire:click="resetFilters" :label="__( 'Clear filters' )" />
            </x-artisanpack-ec-empty-state>
        @else
            <x-artisanpack-ec-empty-state :icon="$emptyIcon" :title="$emptyTitle" :description="$emptyDescription" />
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
        />

        <x-artisanpack-pagination :rows="$tableRows" hide-per-page :page-info-template="__( 'Showing {from} to {to} of {total} results' )" />
    @endif
</div>
