<?php

/**
 * Inventory screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Inventory;

use ArtisanPackUI\Ecommerce\Exceptions\ProductWriteException;
use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Services\ProductService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithResourceTable;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\InventoryQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Csv;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StockLevels;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

/**
 * The inventory table (spec §7.2): one row per stock record with its product
 * or variant, SKU, on hand, reserved, available, low-stock threshold, and
 * backorder setting; filtered by stock state and tracking, and searched by
 * name and SKU.
 *
 * - **Adjust** changes the count by a delta or sets it, with a required
 *   reason, through the engine's `InventoryService::adjust()` (audited, and
 *   the stock hooks fire). It carries a one-time action token.
 * - **Threshold** and **backorder** are edited in the row.
 * - **Bulk adjust** reads a CSV (`sku`, `quantity`, and optional `mode` and
 *   `reason` columns), shows a dry-run report, and applies the valid rows.
 *
 * Abilities: `inventory.viewAny` for the screen and `inventory.adjust` for
 * every change. They are separate from `product.*`, so staff can count stock
 * without editing products.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Index extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithActionToken;
    use WithFileUploads;
    use WithResourceTable;

    /**
     * Largest CSV upload, in kilobytes.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_UPLOAD_KB = 2048;

    /**
     * The stock row being adjusted.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $adjustingId = null;

    /**
     * Whether the adjust dialog is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $adjusting = false;

    /**
     * `delta` or `set`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $adjustMode = 'delta';

    /**
     * Units to add (negative removes), or the new count.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $adjustQuantity = null;

    /**
     * Why the stock changes.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $adjustReason = '';

    /**
     * The stock row whose threshold is being edited.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $editingThresholdId = null;

    /**
     * The threshold being edited (empty for none).
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $thresholdValue = null;

    /**
     * Whether the bulk-adjust dialog is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $bulkAdjusting = false;

    /**
     * The uploaded bulk-adjust CSV.
     *
     * @since 1.0.0
     *
     * @var TemporaryUploadedFile|null
     */
    public $stockCsv = null;

    /**
     * The mode for rows without a `mode` column.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $bulkMode = 'set';

    /**
     * The reason for rows without a `reason` column.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $bulkReason = '';

    /**
     * The dry-run report: one entry per CSV row.
     *
     * @since 1.0.0
     *
     * @var array<int, array{line: int, sku: string, label: string, on_hand: int|null, change: int|null, result: int|null, error: string|null}>|null
     */
    #[Locked]
    public ?array $bulkReport = null;

    /**
     * Authorizes the screen.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeTable();
    }

    /**
     * Opens the adjust dialog for a stock row.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Stock row id.
     *
     * @return void
     */
    public function startAdjust( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'inventory.adjust' );

        $item = InventoryItem::query()->find( $id );

        $this->resetErrorBag();
        $this->adjustingId    = null === $item || StockLevels::isReadOnly( $item ) ? null : (int) $item->id;
        $this->adjusting      = null !== $this->adjustingId;
        $this->adjustMode     = 'delta';
        $this->adjustQuantity = null;
        $this->adjustReason   = '';
    }

    /**
     * Applies the adjustment, once per token.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the dialog.
     *
     * @return void
     */
    public function adjust( string $token ): void
    {
        $this->authorizeEcommerceAbility( 'inventory.adjust' );

        $item = null === $this->adjustingId ? null : InventoryItem::query()->find( $this->adjustingId );

        if ( null === $item || ! $this->adjusting ) {
            $this->closeAdjust();

            return;
        }

        $this->validate(
            [
                'adjustMode'     => [ 'required', Rule::in( StockLevels::MODES ) ],
                'adjustQuantity' => [ 'required', 'integer', 'between:-1000000,1000000', 'set' === $this->adjustMode ? 'min:0' : 'not_in:0' ],
                'adjustReason'   => [ 'required', 'string', 'max:255' ],
            ],
            [ 'adjustQuantity.not_in' => __( 'Enter a number of units other than 0.' ) ],
            [ 'adjustMode' => __( 'adjustment type' ), 'adjustQuantity' => __( 'quantity' ), 'adjustReason' => __( 'reason' ) ],
        );

        try {
            $result = $this->withActionToken(
                $token,
                'adjust',
                fn (): array => StockLevels::adjust( $item, $this->adjustMode, (int) $this->adjustQuantity, sanitizeText( $this->adjustReason ) ),
                $item,
            );
        } catch ( ProductWriteException $exception ) {
            $this->addError( 'adjustQuantity', (string) ( $exception->errors[0]['message'] ?? $exception->getMessage() ) );

            return;
        }

        $this->closeAdjust();

        if ( null !== $result ) {
            $this->dispatch( 'ecommerce-admin-nav-refresh' );
            $this->toastSuccess( __( ':item now has :count on hand.', [
                'item'  => StockLevels::label( $item ),
                'count' => (int) $result['item']->quantity_on_hand,
            ] ) );
        }
    }

    /**
     * Closes the adjust dialog.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function closeAdjust(): void
    {
        $this->adjusting   = false;
        $this->adjustingId = null;
    }

    /**
     * Starts editing a row's low-stock threshold.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Stock row id.
     *
     * @return void
     */
    public function editThreshold( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'inventory.adjust' );

        $item = InventoryItem::query()->find( $id );

        $this->resetErrorBag();
        $this->editingThresholdId = null === $item || StockLevels::isReadOnly( $item ) ? null : (int) $item->id;
        $this->thresholdValue     = $item?->low_stock_threshold;
    }

    /**
     * Saves the threshold.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveThreshold(): void
    {
        $this->authorizeEcommerceAbility( 'inventory.adjust' );

        $item = null === $this->editingThresholdId ? null : InventoryItem::query()->find( $this->editingThresholdId );

        if ( null === $item ) {
            $this->cancelThreshold();

            return;
        }

        $this->validate(
            [ 'thresholdValue' => [ 'nullable', 'integer', 'min:0', 'max:1000000' ] ],
            [],
            [ 'thresholdValue' => __( 'low-stock threshold' ) ],
        );

        $threshold = null === $this->thresholdValue || '' === $this->thresholdValue ? null : (int) $this->thresholdValue;

        try {
            StockLevels::updateSettings( $item, [ 'low_stock_threshold' => $threshold ] );
        } catch ( ProductWriteException $exception ) {
            $this->addError( 'thresholdValue', (string) ( $exception->errors[0]['message'] ?? $exception->getMessage() ) );

            return;
        }

        $this->cancelThreshold();
        $this->dispatch( 'ecommerce-admin-nav-refresh' );
    }

    /**
     * Stops editing the threshold.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelThreshold(): void
    {
        $this->editingThresholdId = null;
        $this->thresholdValue     = null;
    }

    /**
     * Turns backorders on or off for a row.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Stock row id.
     *
     * @return void
     */
    public function toggleBackorder( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'inventory.adjust' );

        $item = InventoryItem::query()->find( $id );

        if ( null === $item ) {
            return;
        }

        try {
            StockLevels::updateSettings( $item, [ 'allow_backorder' => ! $item->allow_backorder ] );
        } catch ( ProductWriteException $exception ) {
            $this->toastError( __( 'The setting was not changed.' ), (string) ( $exception->errors[0]['message'] ?? '' ) );
        }
    }

    /**
     * Opens the bulk-adjust dialog.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function openBulkAdjust(): void
    {
        $this->authorizeEcommerceAbility( 'inventory.adjust' );

        $this->resetErrorBag();
        $this->reset( 'stockCsv', 'bulkReport', 'bulkReason' );
        $this->bulkMode      = 'set';
        $this->bulkAdjusting = true;
    }

    /**
     * Reads the uploaded CSV and builds the dry-run report. Nothing changes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function dryRunBulkAdjust(): void
    {
        $this->authorizeEcommerceAbility( 'inventory.adjust' );
        $this->validateBulkAdjust();

        $this->bulkReport = array_map(
            static fn ( array $row ): array => array_diff_key( $row, [ 'stockable' => true, 'mode' => true, 'quantity' => true, 'reason' => true ] ),
            $this->bulkPlan(),
        );
    }

    /**
     * Applies the valid rows of the uploaded CSV, once per token, then
     * removes the upload.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the report.
     *
     * @return void
     */
    public function applyBulkAdjust( string $token ): void
    {
        $this->authorizeEcommerceAbility( 'inventory.adjust' );

        if ( null === $this->bulkReport ) {
            return;
        }

        $this->validateBulkAdjust();

        $plan = $this->bulkPlan();

        // The token only guards against a double submit; each row is then
        // written in its own transaction, so one bad row fails alone.
        if ( true !== $this->withActionToken( $token, 'bulk-adjust', static fn (): bool => true ) ) {
            return;
        }

        $applied = 0;
        $failed  = 0;
        $service = app( ProductService::class );

        foreach ( $plan as $row ) {
            if ( null !== $row['error'] || null === $row['stockable'] ) {
                ++$failed;
                continue;
            }

            try {
                StockLevels::adjust( $service->inventoryItemFor( $row['stockable'] ), $row['mode'], $row['quantity'], $row['reason'] );
                ++$applied;
            } catch ( Throwable $exception ) {
                if ( ! $exception instanceof ProductWriteException ) {
                    report( $exception );
                }

                ++$failed;
            }
        }

        $result = [ $applied, $failed ];

        if ( null === $result ) {
            return;
        }

        $this->discardUpload();
        $this->bulkAdjusting = false;
        $this->bulkReport    = null;

        [ $applied, $failed ] = $result;

        $this->dispatch( 'ecommerce-admin-nav-refresh' );

        $this->toastSuccess(
            trans_choice( ':count stock row updated.|:count stock rows updated.', $applied, [ 'count' => $applied ] ),
            $failed > 0 ? trans_choice( ':count row was skipped.|:count rows were skipped.', $failed, [ 'count' => $failed ] ) : null,
        );
    }

    /**
     * Clears the dry-run report after the inputs change.
     *
     * @since 1.0.0
     *
     * @param  string  $property  The updated property.
     *
     * @return void
     */
    public function updated( string $property ): void
    {
        if ( in_array( $property, [ 'stockCsv', 'bulkMode', 'bulkReason' ], true ) ) {
            $this->bulkReport = null;
        }
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $canAdjust = Authorization::allows( auth()->user(), 'inventory.adjust' );
        $adjusting = null === $this->adjustingId || ! $this->adjusting ? null : InventoryItem::query()->with( 'stockable' )->find( $this->adjustingId );

        return view( 'ecommerce-admin::livewire.inventory.index', $this->resourceTableData() + [
            'canAdjust'       => $canAdjust,
            'adjustingItem'   => $adjusting,
            'adjustToken'     => null === $adjusting ? null : $this->actionToken( 'adjust', $adjusting ),
            'bulkToken'       => null === $this->bulkReport ? null : $this->actionToken( 'bulk-adjust' ),
            'bulkValidRows'   => count( array_filter( (array) $this->bulkReport, static fn ( array $row ): bool => null === $row['error'] ) ),
            'maxRows'         => self::maxRows(),
        ] );
    }

    /**
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeTable(): void
    {
        $this->authorizeEcommerceAbility( 'inventory.viewAny' );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableScreen(): string
    {
        return 'inventory';
    }

    /**
     * @since 1.0.0
     *
     * @return ResourceQuery
     */
    protected function tableQuery(): ResourceQuery
    {
        return new InventoryQuery();
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableCaption(): string
    {
        return __( 'Inventory' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableColumns(): array
    {
        $cells = 'ecommerce-admin::livewire.inventory.cells.';

        return [
            [
                'key'    => 'item',
                'label'  => __( 'Product' ),
                'view'   => $cells . 'item',
                'export' => static fn ( InventoryItem $item ): string => StockLevels::label( $item ),
            ],
            [
                'key'   => 'sku',
                'label' => __( 'SKU' ),
                'value' => static fn ( InventoryItem $item ): string => StockLevels::sku( $item ),
            ],
            [
                'key'      => 'on_hand',
                'label'    => __( 'On hand' ),
                'sortable' => true,
                'class'    => 'text-end tabular-nums',
                'value'    => static fn ( InventoryItem $item ): string => (string) $item->quantity_on_hand,
                'export'   => static fn ( InventoryItem $item ): int => (int) $item->quantity_on_hand,
            ],
            [
                'key'      => 'reserved',
                'label'    => __( 'Reserved' ),
                'sortable' => true,
                'class'    => 'text-end tabular-nums',
                'value'    => static fn ( InventoryItem $item ): string => (string) $item->quantity_reserved,
                'export'   => static fn ( InventoryItem $item ): int => (int) $item->quantity_reserved,
            ],
            [
                'key'      => 'available',
                'label'    => __( 'Available' ),
                'sortable' => true,
                'class'    => 'text-end',
                'view'     => $cells . 'available',
                'export'   => static fn ( InventoryItem $item ): int => $item->availableQuantity(),
            ],
            [
                'key'      => 'threshold',
                'label'    => __( 'Low-stock threshold' ),
                'sortable' => true,
                'view'     => $cells . 'threshold',
                'export'   => static fn ( InventoryItem $item ): string => null === $item->low_stock_threshold ? '' : (string) $item->low_stock_threshold,
            ],
            [
                'key'    => 'backorder',
                'label'  => __( 'Backorders' ),
                'view'   => $cells . 'backorder',
                'export' => static fn ( InventoryItem $item ): int => $item->allow_backorder ? 1 : 0,
            ],
            [
                'key'        => 'actions',
                'label'      => __( 'Actions' ),
                'class'      => 'text-end',
                'view'       => $cells . 'actions',
                'exportable' => false,
            ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableFilters(): array
    {
        return [
            [
                'key'     => 'stock',
                'label'   => __( 'Stock' ),
                'type'    => 'select',
                'options' => [
                    [ 'id' => 'low', 'name' => __( 'Low stock' ) ],
                    [ 'id' => 'out', 'name' => __( 'Out of stock' ) ],
                    [ 'id' => 'reorder', 'name' => __( 'At or below threshold' ) ],
                ],
            ],
            [ 'key' => 'tracked', 'label' => __( 'Tracked' ), 'type' => 'boolean' ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableBulkActions(): array
    {
        return [ $this->exportBulkAction() ];
    }

    /**
     * Validates the bulk-adjust inputs.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function validateBulkAdjust(): void
    {
        $this->validate(
            [
                'stockCsv'   => [ 'required', 'file', 'mimes:' . implode( ',', Csv::MIMES ), 'max:' . self::MAX_UPLOAD_KB ],
                'bulkMode'   => [ 'required', Rule::in( StockLevels::MODES ) ],
                'bulkReason' => [ 'required', 'string', 'max:255' ],
            ],
            [],
            [ 'stockCsv' => __( 'CSV file' ), 'bulkMode' => __( 'adjustment type' ), 'bulkReason' => __( 'reason' ) ],
        );
    }

    /**
     * The rows of the uploaded CSV, each matched to its stock row and checked.
     *
     * @since 1.0.0
     *
     * @return array<int, array{line: int, sku: string, label: string, on_hand: int|null, change: int|null, result: int|null, error: string|null, stockable: Product|ProductVariant|null, mode: string, quantity: int, reason: string}>
     */
    protected function bulkPlan(): array
    {
        $csv = Csv::withLocalUpload( $this->stockCsv, static fn ( string $path ): array => Csv::read( $path, self::maxRows() + 1 ) );

        if ( ! in_array( 'sku', $csv['headers'], true ) || ! in_array( 'quantity', $csv['headers'], true ) ) {
            $this->addError( 'stockCsv', __( 'The file needs "sku" and "quantity" columns.' ) );
            $this->failBulk();
        }

        if ( $csv['total'] > self::maxRows() ) {
            $this->addError( 'stockCsv', trans_choice( 'The file has more than :count row.|The file has more than :count rows.', self::maxRows(), [ 'count' => self::maxRows() ] ) );
            $this->failBulk();
        }

        if ( 0 === $csv['total'] ) {
            $this->addError( 'stockCsv', __( 'The file has no rows.' ) );
            $this->failBulk();
        }

        $plan      = [];
        $projected = [];

        foreach ( $csv['rows'] as $row ) {
            $sku       = (string) $row['sku'];
            $mode      = '' === (string) ( $row['mode'] ?? '' ) ? $this->bulkMode : strtolower( (string) $row['mode'] );
            $reason    = sanitizeText( '' === (string) ( $row['reason'] ?? '' ) ? $this->bulkReason : (string) $row['reason'] );
            $stockable = StockLevels::findStockable( $sku );
            $quantity  = (string) $row['quantity'];
            $error     = match ( true ) {
                '' === trim( $sku )                               => __( 'The SKU is missing.' ),
                null === $stockable                               => __( 'No product or variant has this SKU.' ),
                StockLevels::stockableIsReadOnly( $stockable )    => __( 'This product\'s type is not installed, so its stock cannot be changed here.' ),
                ! in_array( $mode, StockLevels::MODES, true )     => __( 'The mode must be "delta" or "set".' ),
                1 !== preg_match( '/^-?\d{1,7}$/', $quantity )    => __( 'The quantity must be a whole number.' ),
                'set' === $mode && (int) $quantity < 0            => __( 'The count cannot be negative.' ),
                default                                           => null,
            };

            // Read only: the dry run must not create stock rows. A SKU listed
            // again starts from the count earlier rows leave, as the apply
            // writes the rows in order.
            $key    = null === $stockable ? null : $stockable->getMorphClass() . ':' . $stockable->getKey();
            $onHand = null === $key ? null : ( $projected[ $key ] ?? (int) ( StockLevels::existingItem( $stockable )->quantity_on_hand ?? 0 ) );
            $change = null === $error && null !== $onHand ? ( 'set' === $mode ? (int) $quantity - $onHand : (int) $quantity ) : null;

            if ( null !== $key && null !== $change ) {
                $projected[ $key ] = $onHand + $change;
            }

            $plan[] = [
                'line'      => (int) $row['__line'],
                'sku'       => $sku,
                'label'     => null === $stockable ? '' : StockLevels::stockableLabel( $stockable ),
                'on_hand'   => $onHand,
                'change'    => $change,
                'result'    => null === $change ? null : $onHand + $change,
                'error'     => $error,
                'stockable' => $stockable,
                'mode'      => $mode,
                'quantity'  => (int) $quantity,
                'reason'    => $reason,
            ];
        }

        return $plan;
    }

    /**
     * Stops the request with the bulk-adjust errors added so far.
     *
     * @since 1.0.0
     *
     * @return never
     */
    protected function failBulk(): never
    {
        $this->bulkReport = null;

        throw ValidationException::withMessages( $this->getErrorBag()->toArray() );
    }

    /**
     * Deletes the uploaded file.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function discardUpload(): void
    {
        if ( $this->stockCsv instanceof TemporaryUploadedFile ) {
            try {
                $this->stockCsv->delete();
            } catch ( Throwable $exception ) {
                report( $exception );
            }
        }

        $this->stockCsv = null;
    }

    /**
     * Most rows a CSV may have (`imports.max_rows`).
     *
     * @since 1.0.0
     *
     * @return int
     */
    protected static function maxRows(): int
    {
        return max( 1, (int) config( 'artisanpack.ecommerce-admin-livewire.imports.max_rows', 5000 ) );
    }
}
