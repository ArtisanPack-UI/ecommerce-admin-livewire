{{--
    Inventory (#124). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Inventory\Index.

    Stock for every tracked product and variant on the shared Resource table
    (#79): on hand, reserved and available, with Low stock and Out of stock
    badges beside the number. Thresholds edit in the row and backorders
    toggle in it; every count change goes through the Adjust dialog so a
    reason is recorded, one item at a time or in bulk from a CSV that is
    checked before anything is applied.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels;

    $modeOptions = [
        [ 'id' => 'delta', 'name' => __( 'Add or remove units' ) ],
        [ 'id' => 'set', 'name' => __( 'Set the count' ) ],
    ];
@endphp
<div class="ec-stack" data-inventory-index>
    <x-artisanpack-page-header :title="__( 'Inventory' )" :description="__( 'Stock for every product and variant that tracks it.' )" :level="1">
        @if ( $canAdjust )
            <x-slot:actions>
                <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="file-down" wire:click="openBulkAdjust" data-focus-key="inventory-bulk-adjust" data-bulk-adjust-open>{{ __( 'Bulk adjust from CSV' ) }}</x-artisanpack-button>
            </x-slot:actions>
        @endif
    </x-artisanpack-page-header>

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'box',
        'emptyTitle'       => __( 'No stock records yet' ),
        'emptyDescription' => __( 'Products and variants that track inventory appear here.' ),
        'searchHint'       => __( 'Search by product or SKU' ),
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\InventoryItem $item ): string => StockLevels::label( $item ),
        'cellContext'      => [ 'canAdjust' => $canAdjust, 'editingThresholdId' => $editingThresholdId, 'backorderRevision' => $backorderRevision ],
    ] )

    @if ( null !== $adjustingItem )
        @php
            $adjustLabel     = StockLevels::label( $adjustingItem );
            $adjustFocusKey  = 'inventory-adjust-' . $adjustingItem->getKey();
            $onHand          = (int) $adjustingItem->quantity_on_hand;
            $reserved        = (int) $adjustingItem->quantity_reserved;
            $enteredQuantity = trim( (string) $adjustQuantity );
            // Only quantities adjust() accepts get a preview: set 0 to 1,000,000; change by a non-zero ±1,000,000.
            $enteredNumber   = 1 === preg_match( '/^[+-]?\d{1,7}$/', $enteredQuantity ) ? (int) $enteredQuantity : null;
            $projectedOnHand = match ( true ) {
                null === $enteredNumber || abs( $enteredNumber ) > 1000000 => null,
                'set' === $adjustMode                                      => $enteredNumber >= 0 ? $enteredNumber : null,
                default                                                    => 0 !== $enteredNumber ? $onHand + $enteredNumber : null,
            };
        @endphp
        <x-artisanpack-dialog
            wire:model="adjusting"
            :title="__( 'Adjust stock of :item', [ 'item' => $adjustLabel ] )"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
            data-adjust-dialog
        >
            <form wire:submit="adjust( {{ \Illuminate\Support\Js::from( $adjustToken ) }} )" id="inventory-adjust-form" class="ec-stack" data-adjust-form>
                @include( 'ecommerce-admin::partials.error-summary' )

                <p class="ec-muted" data-adjust-levels>
                    {{ __( 'On hand: :on_hand. Reserved: :reserved. Available: :available.', [
                        'on_hand'   => $onHand,
                        'reserved'  => $reserved,
                        'available' => $adjustingItem->availableQuantity(),
                    ] ) }}
                </p>

                <x-artisanpack-radio-group
                    id="inventory-adjust-mode"
                    variant="segmented"
                    orientation="horizontal"
                    :label="__( 'Adjustment' )"
                    :classes="[ 'label' => 'apui-sr-only' ]"
                    :options="ControlOptions::from( $modeOptions )"
                    :value="$adjustMode"
                    wire:model.live="adjustMode"
                />

                <x-artisanpack-input
                    id="inventory-adjust-quantity"
                    type="number"
                    step="1"
                    inputmode="numeric"
                    :label="'set' === $adjustMode ? __( 'New count' ) : __( 'Units to add (negative removes)' )"
                    :hint="null === $projectedOnHand ? null : __( 'New on hand: :on_hand. New available: :available.', [ 'on_hand' => $projectedOnHand, 'available' => $projectedOnHand - $reserved ] )"
                    wire:model.live.debounce.300ms="adjustQuantity"
                    required
                    data-adjust-quantity
                />

                <x-artisanpack-input
                    id="inventory-adjust-reason"
                    :label="__( 'Reason' )"
                    :hint="__( 'Kept in the stock history, e.g. “Damaged in storage”.' )"
                    wire:model="adjustReason"
                    required
                />
            </form>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="closeAdjust" data-focus-return="{{ $adjustFocusKey }}">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                <x-artisanpack-button type="submit" form="inventory-adjust-form" wire:loading.attr="disabled" wire:target="adjust" data-focus-return="{{ $adjustFocusKey }}" data-adjust-save>{{ __( 'Save adjustment' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif

    @if ( $bulkAdjusting )
        @php
            $bulkSkipped = null === $bulkReport ? 0 : count( $bulkReport ) - $bulkValidRows;
        @endphp
        <x-artisanpack-dialog
            wire:model="bulkAdjusting"
            size="lg"
            class="ec-dialog-wide"
            :title="__( 'Bulk adjust from CSV' )"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided', 'body' => 'ec-stack' ]"
            data-bulk-adjust
        >
            <p class="ec-muted">
                {{ __( 'Upload a CSV with "sku" and "quantity" columns. Optional "mode" (delta or set) and "reason" columns override the choices below for that row. At most :count rows.', [ 'count' => \Illuminate\Support\Number::format( $maxRows ) ] ) }}
            </p>

            <x-artisanpack-file
                id="inventory-bulk-file"
                :label="__( 'CSV file' )"
                :title="__( 'Drop a CSV here or choose a file' )"
                accept=".csv,text/csv,text/plain"
                wire:model="stockCsv"
            />

            <div class="ec-field-grid">
                <x-artisanpack-select
                    id="inventory-bulk-mode"
                    :label="__( 'Quantities in the file' )"
                    :options="ControlOptions::from( $modeOptions )"
                    placeholder=""
                    wire:model.live="bulkMode"
                />
                <x-artisanpack-input id="inventory-bulk-reason" :label="__( 'Reason' )" wire:model.live.blur="bulkReason" required />
            </div>

            @if ( null !== $bulkReport )
                <x-artisanpack-alert color="info" icon="info" data-bulk-summary>
                    {{ trans_choice( ':count row will change stock.|:count rows will change stock.', $bulkValidRows, [ 'count' => $bulkValidRows ] ) }}
                    @if ( $bulkSkipped > 0 )
                        {{ trans_choice( ':count row has an error and will be skipped.|:count rows have errors and will be skipped.', $bulkSkipped, [ 'count' => $bulkSkipped ] ) }}
                    @endif
                </x-artisanpack-alert>

                <div class="apui-table ec-table" data-bulk-report>
                    <div data-scope="table" data-part="scroll" data-density="compact">
                        <table data-scope="table" data-part="table">
                            <caption class="apui-sr-only">{{ __( 'Changes' ) }}</caption>
                            <thead>
                                <tr>
                                    <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Line' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'SKU' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Product' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'On hand' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Change' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'New on hand' ) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ( $bulkReport as $row )
                                    <tr data-scope="table" data-part="row" wire:key="bulk-row-{{ $row['line'] }}" data-bulk-row="{{ $row['line'] }}" @if ( null !== $row['error'] ) data-bulk-error @endif>
                                        <td data-scope="table" data-part="cell" data-numeric>{{ $row['line'] }}</td>
                                        <td data-scope="table" data-part="cell">
                                            @if ( '' === $row['sku'] )
                                                <span class="ec-muted-text" aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                            @else
                                                <bdi dir="ltr" class="ec-mono">{{ $row['sku'] }}</bdi>
                                            @endif
                                        </td>
                                        <td data-scope="table" data-part="cell" class="ec-cell-wrap">
                                            @if ( null !== $row['error'] )
                                                <span class="ec-text-error">{{ $row['error'] }}</span>
                                            @else
                                                {{ $row['label'] }}
                                            @endif
                                        </td>
                                        <td data-scope="table" data-part="cell" data-numeric>
                                            @if ( null === $row['on_hand'] || null !== $row['error'] )
                                                <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                            @else
                                                <bdi dir="ltr" class="ec-money">{{ $row['on_hand'] }}</bdi>
                                            @endif
                                        </td>
                                        <td data-scope="table" data-part="cell" data-numeric>
                                            @if ( null === $row['change'] )
                                                <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                            @else
                                                <bdi dir="ltr" class="ec-money" @if ( $row['change'] < 0 ) data-tone="negative" @endif>{{ $row['change'] > 0 ? '+' . $row['change'] : ( $row['change'] < 0 ? '−' . abs( $row['change'] ) : '0' ) }}</bdi>
                                            @endif
                                        </td>
                                        <td data-scope="table" data-part="cell" data-numeric>
                                            @if ( null === $row['result'] )
                                                <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                            @else
                                                <bdi dir="ltr" class="ec-money">{{ $row['result'] }}</bdi>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'bulkAdjusting', false )" data-focus-return="inventory-bulk-adjust">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    :variant="null !== $bulkReport && $bulkValidRows > 0 ? 'outline' : 'solid'"
                    :color="null !== $bulkReport && $bulkValidRows > 0 ? 'neutral' : 'primary'"
                    wire:click="dryRunBulkAdjust"
                    wire:loading.attr="disabled"
                    wire:target="dryRunBulkAdjust, stockCsv"
                    data-bulk-check
                >{{ __( 'Check file' ) }}</x-artisanpack-button>
                @if ( null !== $bulkReport && $bulkValidRows > 0 )
                    <x-artisanpack-button
                        wire:click="applyBulkAdjust( {{ \Illuminate\Support\Js::from( $bulkToken ) }} )"
                        wire:loading.attr="disabled"
                        wire:target="applyBulkAdjust"
                        data-focus-return="inventory-bulk-adjust"
                        data-bulk-apply
                    >{{ trans_choice( 'Apply :count change|Apply :count changes', $bulkValidRows, [ 'count' => $bulkValidRows ] ) }}</x-artisanpack-button>
                @endif
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
