{{--
    Inventory table. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Inventory\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels;

    $modeOptions = [
        [ 'id' => 'delta', 'name' => __( 'Add or remove units' ) ],
        [ 'id' => 'set', 'name' => __( 'Set the count' ) ],
    ];
@endphp
<div>
    <x-artisanpack-page-header :title="__( 'Inventory' )" :level="1">
        @if ( $canAdjust )
            <x-slot:actions>
                <x-artisanpack-button variant="outline" leading-icon="o-arrow-up-tray" wire:click="openBulkAdjust">{{ __( 'Bulk adjust from CSV' ) }}</x-artisanpack-button>
            </x-slot:actions>
        @endif
    </x-artisanpack-page-header>

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'o-archive-box',
        'emptyTitle'       => __( 'No stock records yet' ),
        'emptyDescription' => __( 'Products and variants that track inventory appear here.' ),
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\InventoryItem $item ): string => StockLevels::label( $item ),
        'cellContext'      => [ 'canAdjust' => $canAdjust, 'editingThresholdId' => $editingThresholdId ],
    ] )

    @if ( null !== $adjustingItem )
        @php $adjustTitle = __( 'Adjust stock of :item', [ 'item' => StockLevels::label( $adjustingItem ) ] ); @endphp
        <x-artisanpack-dialog wire:model="adjusting" :title="$adjustTitle">
            <form wire:submit="adjust( {{ \Illuminate\Support\Js::from( $adjustToken ) }} )" id="inventory-adjust-form" class="flex flex-col gap-4" data-adjust-form>
                @include( 'ecommerce-admin::partials.error-summary' )
                <p class="text-sm">
                    {{ __( 'On hand: :on_hand. Reserved: :reserved. Available: :available.', [
                        'on_hand'   => $adjustingItem->quantity_on_hand,
                        'reserved'  => $adjustingItem->quantity_reserved,
                        'available' => $adjustingItem->availableQuantity(),
                    ] ) }}
                </p>
                <x-artisanpack-radio-group id="inventory-adjust-mode" :label="__( 'Adjustment' )" :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $modeOptions )" wire:model.live="adjustMode" orientation="horizontal" />
                <x-artisanpack-input
                    id="inventory-adjust-quantity"
                    type="number"
                    step="1"
                    :label="'set' === $adjustMode ? __( 'New count' ) : __( 'Units to add (negative removes)' )"
                    wire:model="adjustQuantity"
                    required
                />
                <x-artisanpack-input id="inventory-adjust-reason" :label="__( 'Reason' )" :hint="__( 'Kept in the stock history, e.g. \'Stock take\' or \'Delivery received\'.' )" wire:model="adjustReason" required />
            </form>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" wire:click="closeAdjust">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                <x-artisanpack-button type="submit" form="inventory-adjust-form" color="primary" wire:loading.attr="disabled">{{ __( 'Save adjustment' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif

    @if ( $bulkAdjusting )
        <x-artisanpack-dialog wire:model="bulkAdjusting" :title="__( 'Bulk adjust from CSV' )" :classes="['root' => 'max-w-4xl']">
            <div class="flex flex-col gap-4" data-bulk-adjust>
                <p class="text-sm">
                    {{ __( 'Upload a CSV with "sku" and "quantity" columns. Optional "mode" (delta or set) and "reason" columns override the choices below for that row. At most :count rows.', [ 'count' => $maxRows ] ) }}
                </p>

                <x-artisanpack-file id="inventory-bulk-file" :label="__( 'CSV file' )" accept=".csv,text/csv,text/plain" wire:model="stockCsv" />
                <x-artisanpack-radio-group id="inventory-bulk-mode" :label="__( 'Quantities in the file' )" :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $modeOptions )" wire:model.live="bulkMode" orientation="horizontal" />
                <x-artisanpack-input id="inventory-bulk-reason" :label="__( 'Reason' )" wire:model.blur="bulkReason" required />

                @if ( null !== $bulkReport )
                    <div class="overflow-x-auto" data-bulk-report>
                        <table class="table table-sm">
                            <caption class="text-start font-semibold">
                                {{ trans_choice( ':count row will change stock.|:count rows will change stock.', $bulkValidRows, [ 'count' => $bulkValidRows ] ) }}
                                @if ( count( $bulkReport ) > $bulkValidRows )
                                    {{ trans_choice( ':count row has an error and will be skipped.|:count rows have errors and will be skipped.', count( $bulkReport ) - $bulkValidRows, [ 'count' => count( $bulkReport ) - $bulkValidRows ] ) }}
                                @endif
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __( 'Line' ) }}</th>
                                    <th scope="col">{{ __( 'SKU' ) }}</th>
                                    <th scope="col">{{ __( 'Product' ) }}</th>
                                    <th scope="col" class="text-end">{{ __( 'On hand' ) }}</th>
                                    <th scope="col" class="text-end">{{ __( 'Change' ) }}</th>
                                    <th scope="col" class="text-end">{{ __( 'New on hand' ) }}</th>
                                    <th scope="col">{{ __( 'Problem' ) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ( $bulkReport as $row )
                                    <tr wire:key="bulk-row-{{ $row['line'] }}" @class( [ 'text-error' => null !== $row['error'] ] ) data-bulk-row="{{ $row['line'] }}">
                                        <td class="tabular-nums">{{ $row['line'] }}</td>
                                        <td>{{ $row['sku'] }}</td>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="text-end tabular-nums">{{ $row['on_hand'] }}</td>
                                        <td class="text-end tabular-nums">{{ null === $row['change'] ? '' : ( $row['change'] > 0 ? '+' . $row['change'] : $row['change'] ) }}</td>
                                        <td class="text-end tabular-nums">{{ $row['result'] }}</td>
                                        <td>{{ $row['error'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" wire:click="$set( 'bulkAdjusting', false )">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                <x-artisanpack-button variant="outline" wire:click="dryRunBulkAdjust" wire:loading.attr="disabled">{{ __( 'Check file' ) }}</x-artisanpack-button>
                @if ( null !== $bulkReport && $bulkValidRows > 0 )
                    <x-artisanpack-button
                        color="primary"
                        wire:click="applyBulkAdjust( {{ \Illuminate\Support\Js::from( $bulkToken ) }} )"
                        wire:loading.attr="disabled">{{ trans_choice( 'Apply :count change|Apply :count changes', $bulkValidRows, [ 'count' => $bulkValidRows ] ) }}</x-artisanpack-button>
                @endif
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
