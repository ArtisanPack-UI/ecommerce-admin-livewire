<?php

/**
 * Product CSV import screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Csv;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductCsv;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductImports;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The product CSV import (spec §7.2): upload → column mapping → dry-run
 * report → queued apply with progress.
 *
 * - The upload is checked by type and size, copied to the `imports.disk`
 *   disk, and the temporary upload removed. Files over `imports.max_rows`
 *   rows are refused.
 * - The dry run checks every row the way the engine will (matching on SKU,
 *   then slug) and reports creates, updates, and errors per row; nothing
 *   is written.
 * - Applying queues {@see \ArtisanPackUI\EcommerceAdminLivewire\Jobs\RunProductImport}
 *   with a one-time action token. Each row is its own transaction through
 *   `ProductService`. An import that stops part-way can be resumed, and the
 *   failed rows can be downloaded as CSV.
 * - The uploaded file is deleted when the import completes or is discarded.
 *
 * The screen needs `product.create` or `product.update`; each row then
 * needs `product.create` or `product.update` for what it does.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Import extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithActionToken;
    use WithFileUploads;

    /**
     * Largest upload, in kilobytes.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_UPLOAD_KB = 10240;

    /**
     * Report rows shown on screen (errors are always all shown).
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const REPORT_PREVIEW = 100;

    /**
     * The uploaded CSV.
     *
     * @since 1.0.0
     *
     * @var TemporaryUploadedFile|null
     */
    public $csv = null;

    /**
     * The import in progress.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $importId = null;

    /**
     * The column for each CSV header, in header order (`''` ignores it).
     *
     * Indexed by position because headers can hold dots and spaces, which
     * a `wire:model` path cannot.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public array $mapping = [];

    /**
     * Authorizes the screen and picks up an unfinished import.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeScreen();

        $state = ProductImports::latestUnfinishedFor( auth()->id() );

        if ( null !== $state ) {
            $this->importId = (string) $state['id'];
            $this->mapping  = self::positional( $state );
        }
    }

    /**
     * Re-authorizes the screen on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeScreen();
    }

    /**
     * Stores the upload and moves on to the column mapping.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function upload(): void
    {
        $this->authorizeScreen();

        $this->validate(
            [ 'csv' => [ 'required', 'file', 'mimes:' . implode( ',', Csv::MIMES ), 'max:' . self::MAX_UPLOAD_KB ] ],
            [],
            [ 'csv' => __( 'CSV file' ) ],
        );

        $error = Csv::withLocalUpload( $this->csv, function ( string $path ): ?string {
            $read = Csv::read( $path, 0 );

            if ( [] === $read['headers'] || 0 === $read['total'] ) {
                return __( 'The file has no rows.' );
            }

            if ( $read['total'] > self::maxRows() ) {
                return trans_choice( 'The file has more than :count row.|The file has more than :count rows.', self::maxRows(), [ 'count' => self::maxRows() ] );
            }

            if ( count( $read['headers'] ) !== count( array_unique( $read['headers'] ) ) ) {
                return __( 'Two columns in the file have the same header.' );
            }

            $state = ProductImports::create( (string) auth()->id(), $path, $this->csv->getClientOriginalName(), $read['headers'], $read['total'] );

            $this->importId = (string) $state['id'];
            $this->mapping  = self::positional( $state );

            return null;
        } );

        if ( null !== $error ) {
            $this->addError( 'csv', $error );

            return;
        }

        $this->discardUpload();
    }

    /**
     * Saves the mapping and runs the dry run. Nothing is written.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function check(): void
    {
        $this->authorizeScreen();

        $state = $this->state();

        if ( null === $state || ! in_array( $state['status'], [ 'mapping', 'checked' ], true ) ) {
            return;
        }

        $columns = array_keys( ProductCsv::columns() );

        $this->validate(
            [
                'mapping'   => [ 'array' ],
                'mapping.*' => [ 'nullable', 'string', Rule::in( [ '', ...$columns ] ) ],
            ],
            [ 'mapping.*.in' => __( 'Choose a column from the list.' ) ],
        );

        $mapping = [];

        foreach ( (array) $state['headers'] as $index => $header ) {
            $mapping[ (string) $header ] = (string) ( $this->mapping[ $index ] ?? '' );
        }

        $used = array_values( array_filter( $mapping ) );

        if ( count( $used ) !== count( array_unique( $used ) ) ) {
            $this->addError( 'mapping', __( 'Each column can be mapped from only one header.' ) );

            return;
        }

        if ( [] === array_intersect( $used, [ 'sku', 'slug', 'name', 'variant_sku' ] ) ) {
            $this->addError( 'mapping', __( 'Map at least one of SKU, slug, name, or variant SKU so rows can be matched.' ) );

            return;
        }

        $state['mapping'] = $mapping;
        $state['report']  = ProductImports::withSource( $state, static function ( string $path ) use ( $mapping ): array {
            $context = [];
            $rows    = [];
            $counts  = [ 'create' => 0, 'update' => 0, 'error' => 0 ];

            foreach ( Csv::read( $path, self::maxRows() )['rows'] as $row ) {
                $result = ProductCsv::check( ProductImports::mapRow( $row, $mapping ), $context );
                $kind   = 'error' === $result['action'] ? 'error' : ( str_starts_with( $result['action'], 'create' ) ? 'create' : 'update' );

                ++$counts[ $kind ];

                $rows[] = [ 'line' => (int) $row['__line'] ] + $result;
            }

            return [ 'counts' => $counts, 'rows' => $rows ];
        } );
        $state['status']  = 'checked';

        ProductImports::save( $state );
    }

    /**
     * Goes back to the column mapping.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function editMapping(): void
    {
        $this->authorizeScreen();

        $state = $this->state();

        if ( null !== $state && 'checked' === $state['status'] ) {
            $state['status'] = 'mapping';
            $state['report'] = null;

            ProductImports::save( $state );
        }
    }

    /**
     * Queues the checked import, once per token.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the report.
     *
     * @return void
     */
    public function apply( string $token ): void
    {
        $this->authorizeScreen();

        $state = $this->state();

        if ( null === $state || 'checked' !== $state['status'] ) {
            return;
        }

        $this->withActionToken( $token, 'apply', static fn (): array => ProductImports::dispatch( $state ), $state['id'] );
    }

    /**
     * Queues a stopped import again from its next row.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function resume(): void
    {
        $this->authorizeScreen();

        $state = $this->state();

        if ( null === $state || 'failed' !== $state['status'] || $state['processed'] >= $state['total'] || ! ProductImports::hasSource( $state ) ) {
            return;
        }

        ProductImports::dispatch( $state );
    }

    /**
     * Discards an import that is not running, and its file.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function discard(): void
    {
        $this->authorizeScreen();

        $state = $this->state();

        if ( null !== $state && ! in_array( $state['status'], ProductImports::ACTIVE, true ) ) {
            ProductImports::delete( $state );
        }

        $this->reset( 'importId', 'mapping' );
        $this->discardUpload();
    }

    /**
     * Downloads the failed rows.
     *
     * @since 1.0.0
     *
     * @return StreamedResponse|null
     */
    public function downloadErrors(): ?StreamedResponse
    {
        $this->authorizeScreen();

        $state = $this->state();

        if ( null === $state ) {
            return null;
        }

        $csv = ProductImports::errorsCsv( $state );

        return response()->streamDownload( static function () use ( $csv ): void {
            echo $csv;
        }, 'product-import-errors-' . substr( (string) $state['id'], 0, 8 ) . '.csv', [ 'Content-Type' => 'text/csv; charset=UTF-8' ] );
    }

    /**
     * Downloads a sample file.
     *
     * @since 1.0.0
     *
     * @return StreamedResponse
     */
    public function downloadSample(): StreamedResponse
    {
        $this->authorizeScreen();

        $csv = (string) file_get_contents( dirname( __DIR__, 3 ) . '/resources/samples/products-import-sample.csv' );

        return response()->streamDownload( static function () use ( $csv ): void {
            echo $csv;
        }, 'products-import-sample.csv', [ 'Content-Type' => 'text/csv; charset=UTF-8' ] );
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $state  = $this->state();
        $report = (array) ( $state['report'] ?? [] );
        $rows   = (array) ( $report['rows'] ?? [] );

        return view( 'ecommerce-admin::livewire.products.import', [
            'state'         => $state,
            'step'          => null === $state ? 'upload' : (string) $state['status'],
            'columnOptions' => array_map( static fn ( string $key, string $label ): array => [ 'id' => $key, 'name' => $label . ' (' . $key . ')' ], array_keys( ProductCsv::columns() ), ProductCsv::columns() ),
            'reportCounts'  => (array) ( $report['counts'] ?? [] ),
            'reportErrors'  => array_values( array_filter( $rows, static fn ( array $row ): bool => 'error' === $row['action'] ) ),
            'reportPreview' => array_slice( array_values( array_filter( $rows, static fn ( array $row ): bool => 'error' !== $row['action'] ) ), 0, self::REPORT_PREVIEW ),
            'applyToken'    => null !== $state && 'checked' === $state['status'] ? $this->actionToken( 'apply', $state['id'] ) : null,
            'progress'      => null === $state || 0 === (int) $state['total'] ? 0 : (int) floor( 100 * (int) $state['processed'] / (int) $state['total'] ),
            'maxRows'       => self::maxRows(),
            'maxUploadMb'   => (int) ( self::MAX_UPLOAD_KB / 1024 ),
        ] );
    }

    /**
     * The import in progress, when it belongs to this user.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>|null
     */
    protected function state(): ?array
    {
        return null === $this->importId ? null : ProductImports::findFor( $this->importId, auth()->id() );
    }

    /**
     * The screen needs `product.create` or `product.update`, like the
     * import job; each row is then authorized for what it does.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeScreen(): void
    {
        if ( ! $this->canEcommerce( 'create', Product::class ) && ! Authorization::allows( auth()->user(), 'product.update' ) ) {
            $this->denyEcommerce();
        }
    }

    /**
     * An import's mapping in header order.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state  State.
     *
     * @return array<int, string>
     */
    protected static function positional( array $state ): array
    {
        return array_map(
            static fn ( mixed $header ): string => (string) ( $state['mapping'][ (string) $header ] ?? '' ),
            array_values( (array) $state['headers'] ),
        );
    }

    /**
     * Deletes the temporary upload.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function discardUpload(): void
    {
        if ( $this->csv instanceof TemporaryUploadedFile ) {
            try {
                $this->csv->delete();
            } catch ( Throwable $exception ) {
                report( $exception );
            }
        }

        $this->csv = null;
    }

    /**
     * Most rows an import may have (`imports.max_rows`).
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
