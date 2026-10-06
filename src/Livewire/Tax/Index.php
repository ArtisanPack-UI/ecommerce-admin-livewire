<?php

/**
 * Tax screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tax;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ShippingMethod;
use ArtisanPackUI\Ecommerce\Models\TaxClass;
use ArtisanPackUI\Ecommerce\Models\TaxRate;
use ArtisanPackUI\Ecommerce\Services\TaxService;
use ArtisanPackUI\Ecommerce\Support\TaxRateMath;
use ArtisanPackUI\Ecommerce\Tax\ManualTaxProvider;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithResourceTable;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\TaxRatesQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Countries;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Csv;
use ArtisanPackUI\EcommerceAdminLivewire\Support\TaxRateCsv;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Tax classes and the rates table (spec §7.6).
 *
 * - **Classes**: key and label. The key is fixed once created, since
 *   products, rates, and shipping methods refer to it. A class cannot be
 *   deleted while rates, products, or shipping methods use it, or while it
 *   is the store's default or shipping tax class.
 * - **Rates**: created and edited inline, one row at a time. The rate is
 *   entered as a percent and stored as `rate_ubps` (the percent input's
 *   scale matches the engine's units, so no float is ever involved).
 *   Filters: class, country, active.
 * - **CSV**: rates import with a dry run first ({@see TaxRateCsv} has the
 *   format and the matching rule), and export in the same format. The
 *   apply writes the valid rows and skips the rest. A row that would update
 *   an existing rate needs `taxRate.update`; without it the row is refused
 *   in the dry run.
 * - **Provider**: shows the active `TaxProvider`; when it is not the
 *   built-in manual provider, the rates are labelled as unused.
 *
 * The engine has no tax write service; these are plain model writes behind
 * `TaxRatePolicy`, which also covers tax classes (spec §9 "not blocked").
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
     * Rows the dry-run report lists before summarising the rest.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const REPORT_ROWS = 100;

    /**
     * Whether the new-rate row is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $creatingRate = false;

    /**
     * The rate being edited in its row.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $editingRateId = null;

    /**
     * The rate fields being created or edited.
     *
     * @since 1.0.0
     *
     * @var array{tax_class_key: string, country_code: string, region_code: string, postal_pattern: string, rate_ubps: int|string|null, label: string, is_compound: bool, is_shipping_taxable: bool, priority: int|string, is_active: bool}
     */
    public array $rateForm = [];

    /**
     * The new class's key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $newClassKey = '';

    /**
     * The new class's label.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $newClassLabel = '';

    /**
     * The class being relabelled.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $editingClassId = null;

    /**
     * The new label of the class being relabelled.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $editingClassLabel = '';

    /**
     * The class waiting for delete confirmation.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $deletingClassId = null;

    /**
     * Whether the class delete confirmation is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $confirmingClassDelete = false;

    /**
     * Whether the import drawer is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $importing = false;

    /**
     * The uploaded rates CSV.
     *
     * @since 1.0.0
     *
     * @var TemporaryUploadedFile|null
     */
    public $ratesCsv = null;

    /**
     * The dry-run report.
     *
     * @since 1.0.0
     *
     * @var array{total: int, create: int, update: int, invalid: int, rows: array<int, array{line: int, action: string, summary: string, errors: array<int, string>}>}|null
     */
    #[Locked]
    public ?array $importReport = null;

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
        $this->rateForm = self::emptyRateForm();
    }

    /**
     * Opens the new-rate row, prefilled from the class and country filters.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function createRate(): void
    {
        $this->authorizeEcommerce( 'create', TaxRate::class );

        $filters = $this->normalizedFilters();

        $this->resetErrorBag();
        $this->editingRateId = null;
        $this->rateForm      = [
            'tax_class_key' => (string) ( $filters['class'] ?? TaxClass::DEFAULT_KEY ),
            'country_code'  => (string) ( $filters['country'] ?? '' ),
        ] + self::emptyRateForm();
        $this->creatingRate  = true;
    }

    /**
     * Starts editing a rate in its row.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Rate id.
     *
     * @return void
     */
    public function editRate( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'taxRate.update' );

        $rate = TaxRate::query()->find( $id );

        if ( null === $rate ) {
            return;
        }

        $this->resetErrorBag();
        $this->creatingRate  = false;
        $this->editingRateId = (int) $rate->id;
        $this->rateForm      = [
            'tax_class_key'       => (string) $rate->tax_class_key,
            'country_code'        => (string) $rate->country_code,
            'region_code'         => (string) ( $rate->region_code ?? '' ),
            'postal_pattern'      => (string) ( $rate->postal_pattern ?? '' ),
            'rate_ubps'           => (int) $rate->rate_ubps,
            'label'               => (string) $rate->label,
            'is_compound'         => (bool) $rate->is_compound,
            'is_shipping_taxable' => (bool) $rate->is_shipping_taxable,
            'priority'            => (int) $rate->priority,
            'is_active'           => (bool) $rate->is_active,
        ];
    }

    /**
     * Saves the new or edited rate.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveRate(): void
    {
        $rate = null === $this->editingRateId ? null : TaxRate::query()->find( $this->editingRateId );

        if ( null === $rate ) {
            if ( null !== $this->editingRateId ) {
                $this->authorizeEcommerceAbility( 'taxRate.update' );
                $this->cancelRate();
                $this->toastWarning( __( 'This tax rate was deleted while you were editing it.' ) );

                return;
            }

            $this->authorizeEcommerce( 'create', TaxRate::class );

            if ( ! $this->creatingRate ) {
                return;
            }
        } else {
            $this->authorizeEcommerce( 'update', $rate );
        }

        $this->rateForm['country_code'] = strtoupper( trim( (string) ( $this->rateForm['country_code'] ?? '' ) ) );
        $this->rateForm['region_code']  = strtoupper( trim( (string) ( $this->rateForm['region_code'] ?? '' ) ) );

        $this->validate(
            [
                'rateForm.tax_class_key'       => [ 'required', 'string', Rule::exists( TaxClass::class, 'key' ) ],
                'rateForm.country_code'        => [ 'required', 'string', Rule::in( Countries::CODES ) ],
                'rateForm.region_code'         => [ 'nullable', 'string', 'max:10' ],
                'rateForm.postal_pattern'      => [ 'nullable', 'string', 'max:60' ],
                'rateForm.rate_ubps'           => [ 'required', 'integer', 'min:0', 'max:' . TaxRateMath::UNITS_PER_WHOLE ],
                'rateForm.label'               => [ 'required', 'string', 'max:120' ],
                'rateForm.is_compound'         => [ 'boolean' ],
                'rateForm.is_shipping_taxable' => [ 'boolean' ],
                'rateForm.priority'            => [ 'required', 'integer', 'between:-' . TaxRateCsv::MAX_PRIORITY . ',' . TaxRateCsv::MAX_PRIORITY ],
                'rateForm.is_active'           => [ 'boolean' ],
            ],
            [
                'rateForm.rate_ubps.max'        => __( 'A tax rate cannot exceed 100%.' ),
                'rateForm.country_code.in'      => __( 'Choose a country.' ),
                'rateForm.tax_class_key.exists' => __( 'Choose a tax class.' ),
            ],
            [
                'rateForm.tax_class_key'       => __( 'tax class' ),
                'rateForm.country_code'        => __( 'country' ),
                'rateForm.region_code'         => __( 'region' ),
                'rateForm.postal_pattern'      => __( 'postal pattern' ),
                'rateForm.rate_ubps'           => __( 'rate' ),
                'rateForm.label'               => __( 'label' ),
                'rateForm.is_compound'         => __( 'compound' ),
                'rateForm.is_shipping_taxable' => __( 'shipping taxable' ),
                'rateForm.priority'            => __( 'priority' ),
                'rateForm.is_active'           => __( 'active' ),
            ],
        );

        $attributes = [
            'tax_class_key'       => (string) $this->rateForm['tax_class_key'],
            'country_code'        => (string) $this->rateForm['country_code'],
            'region_code'         => '' === $this->rateForm['region_code'] ? null : (string) $this->rateForm['region_code'],
            'postal_pattern'      => '' === trim( (string) $this->rateForm['postal_pattern'] ) ? null : trim( (string) $this->rateForm['postal_pattern'] ),
            'rate_ubps'           => (int) $this->rateForm['rate_ubps'],
            'label'               => trim( (string) $this->rateForm['label'] ),
            'is_compound'         => (bool) $this->rateForm['is_compound'],
            'is_shipping_taxable' => (bool) $this->rateForm['is_shipping_taxable'],
            'priority'            => (int) $this->rateForm['priority'],
            'is_active'           => (bool) $this->rateForm['is_active'],
        ];

        if ( null === $rate ) {
            $rate = TaxRate::query()->create( $attributes );
        } else {
            $rate->fill( $attributes )->save();
        }

        $this->cancelRate();
        $this->toastSuccess( __( 'Tax rate ":label" (:rate%) saved.', [ 'label' => $rate->label, 'rate' => $rate->percent() ] ) );
    }

    /**
     * Closes the new or edited rate row.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelRate(): void
    {
        $this->creatingRate  = false;
        $this->editingRateId = null;
        $this->rateForm      = self::emptyRateForm();
        $this->resetErrorBag();
    }

    /**
     * Asks to confirm deleting one rate (through the bulk delete).
     *
     * @since 1.0.0
     *
     * @param  int  $id  Rate id.
     *
     * @return void
     */
    public function confirmDeleteRate( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'taxRate.delete' );

        $this->selected          = [ $id ];
        $this->selectAllMatching = false;

        $this->runBulkAction( 'delete' );
    }

    /**
     * Adds a tax class.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function createClass(): void
    {
        $this->authorizeEcommerce( 'create', TaxClass::class );

        $this->newClassKey = Str::lower( trim( $this->newClassKey ) );

        $this->validate(
            [
                'newClassKey'   => [ 'required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique( TaxClass::class, 'key' ) ],
                'newClassLabel' => [ 'required', 'string', 'max:120' ],
            ],
            [
                'newClassKey.regex'  => __( 'Use lowercase letters, numbers, and single hyphens, like "reduced-rate".' ),
                'newClassKey.unique' => __( 'A tax class with this key already exists.' ),
            ],
            [ 'newClassKey' => __( 'key' ), 'newClassLabel' => __( 'label' ) ],
        );

        $class = TaxClass::query()->create( [ 'key' => $this->newClassKey, 'label' => trim( $this->newClassLabel ) ] );

        $this->reset( 'newClassKey', 'newClassLabel' );
        $this->toastSuccess( __( 'Tax class ":label" added.', [ 'label' => $class->label ] ) );
    }

    /**
     * Starts relabelling a class.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Class id.
     *
     * @return void
     */
    public function editClass( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'taxRate.update' );

        $class = TaxClass::query()->find( $id );

        $this->resetErrorBag();
        $this->editingClassId    = null === $class ? null : (int) $class->id;
        $this->editingClassLabel = (string) ( $class->label ?? '' );
    }

    /**
     * Saves the class label.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveClass(): void
    {
        $this->authorizeEcommerceAbility( 'taxRate.update' );

        $class = null === $this->editingClassId ? null : TaxClass::query()->find( $this->editingClassId );

        if ( null === $class ) {
            $this->cancelClass();

            return;
        }

        $this->authorizeEcommerce( 'update', $class );

        $this->validate(
            [ 'editingClassLabel' => [ 'required', 'string', 'max:120' ] ],
            [],
            [ 'editingClassLabel' => __( 'label' ) ],
        );

        $class->forceFill( [ 'label' => trim( $this->editingClassLabel ) ] )->save();

        $this->cancelClass();
        $this->toastSuccess( __( 'Tax class ":label" saved.', [ 'label' => $class->label ] ) );
    }

    /**
     * Stops relabelling.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelClass(): void
    {
        $this->editingClassId    = null;
        $this->editingClassLabel = '';
    }

    /**
     * Asks to confirm deleting a class.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Class id.
     *
     * @return void
     */
    public function confirmDeleteClass( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'taxRate.delete' );

        $this->deletingClassId       = TaxClass::query()->whereKey( $id )->exists() ? $id : null;
        $this->confirmingClassDelete = null !== $this->deletingClassId;
    }

    /**
     * Dismisses the class delete confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelDeleteClass(): void
    {
        $this->deletingClassId       = null;
        $this->confirmingClassDelete = false;
    }

    /**
     * Deletes the class waiting for confirmation, once per token, unless
     * something still uses it.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the confirmation.
     *
     * @return void
     */
    public function deleteClass( string $token ): void
    {
        $this->authorizeEcommerceAbility( 'taxRate.delete' );

        $class = null === $this->deletingClassId || ! $this->confirmingClassDelete ? null : TaxClass::query()->find( $this->deletingClassId );

        if ( null === $class ) {
            $this->cancelDeleteClass();

            return;
        }

        $this->authorizeEcommerce( 'delete', $class );

        $label   = (string) $class->label;
        $blocked = null;

        $deleted = $this->withActionToken( $token, 'delete-class', static function () use ( $class, &$blocked ): bool {
            // Re-checked under a lock so a rate or product added meanwhile still blocks the delete.
            $locked  = TaxClass::query()->lockForUpdate()->find( $class->id );
            $blocked = null === $locked ? null : self::classBlocker( $locked );

            if ( null === $locked || null !== $blocked ) {
                return false;
            }

            $locked->delete();

            return true;
        }, $class );

        $this->cancelDeleteClass();

        if ( null !== $blocked ) {
            $this->toastError( __( 'The tax class was not deleted.' ), $blocked );

            return;
        }

        if ( true === $deleted ) {
            $this->toastSuccess( __( 'Tax class ":label" deleted.', [ 'label' => $label ] ) );
        }
    }

    /**
     * Opens the import drawer.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function openImport(): void
    {
        $this->authorizeEcommerce( 'create', TaxRate::class );

        $this->resetErrorBag();
        $this->discardUpload();
        $this->importReport = null;
        $this->importing    = true;
    }

    /**
     * Reads the uploaded CSV and builds the dry-run report. Nothing changes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function checkImport(): void
    {
        $this->authorizeEcommerce( 'create', TaxRate::class );
        $this->validateImport();

        $plan  = $this->importPlan();
        $count = static fn ( string $action ): int => count( array_filter( $plan, static fn ( array $row ): bool => $action === $row['action'] ) );

        $this->importReport = [
            'total'   => count( $plan ),
            'create'  => $count( 'create' ),
            'update'  => $count( 'update' ),
            'invalid' => $count( 'invalid' ),
            'rows'    => array_map(
                static fn ( array $row ): array => array_diff_key( $row, [ 'attributes' => true, 'rate' => true ] ),
                array_slice( array_values( array_filter( $plan, static fn ( array $row ): bool => 'invalid' === $row['action'] ) ), 0, self::REPORT_ROWS ),
            ),
        ];
    }

    /**
     * Writes the valid rows of the uploaded CSV, once per token, then removes
     * the upload.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the report.
     *
     * @return void
     */
    public function applyImport( string $token ): void
    {
        $this->authorizeEcommerce( 'create', TaxRate::class );

        if ( null === $this->importReport ) {
            return;
        }

        $this->validateImport();

        $plan   = $this->importPlan();
        $result = $this->withActionToken( $token, 'import', static function () use ( $plan ): array {
            $created = 0;
            $updated = 0;

            foreach ( $plan as $row ) {
                if ( 'create' === $row['action'] ) {
                    TaxRate::query()->create( $row['attributes'] );
                    ++$created;
                } elseif ( 'update' === $row['action'] && $row['rate'] instanceof TaxRate ) {
                    $row['rate']->fill( $row['attributes'] )->save();
                    ++$updated;
                }
            }

            return [ $created, $updated ];
        } );

        if ( null === $result ) {
            return;
        }

        [ $created, $updated ] = $result;
        $skipped               = count( $plan ) - $created - $updated;

        $this->discardUpload();
        $this->importing    = false;
        $this->importReport = null;

        $this->toastSuccess(
            __( 'Rates imported: :created added, :updated updated.', [ 'created' => $created, 'updated' => $updated ] ),
            $skipped > 0 ? trans_choice( ':count row was skipped.|:count rows were skipped.', $skipped, [ 'count' => $skipped ] ) : null,
        );
    }

    /**
     * Closes the import drawer and removes the upload.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function closeImport(): void
    {
        $this->discardUpload();
        $this->importing    = false;
        $this->importReport = null;
    }

    /**
     * Downloads a sample rates file.
     *
     * @since 1.0.0
     *
     * @return StreamedResponse
     */
    public function downloadSample(): StreamedResponse
    {
        $this->authorizeTable();

        $csv = TaxRateCsv::sample();

        return response()->streamDownload( static function () use ( $csv ): void {
            echo $csv;
        }, 'tax-rates-sample.csv', [ 'Content-Type' => 'text/csv; charset=UTF-8' ] );
    }

    /**
     * Downloads the rates matching the search and filters in the import
     * format.
     *
     * @since 1.0.0
     *
     * @return StreamedResponse
     */
    public function exportRates(): StreamedResponse
    {
        $this->authorizeTable();

        $csv = TaxRateCsv::export( $this->filteredQuery()->reorder()->orderBy( ( new TaxRate() )->qualifyColumn( 'country_code' ) )->orderBy( ( new TaxRate() )->qualifyColumn( 'region_code' ) )->orderBy( ( new TaxRate() )->qualifyColumn( 'priority' ) )->orderBy( ( new TaxRate() )->qualifyColumn( 'id' ) ) );

        return response()->streamDownload( static function () use ( $csv ): void {
            echo $csv;
        }, 'tax-rates-' . Carbon::now()->format( 'Y-m-d-His' ) . '.csv', [ 'Content-Type' => 'text/csv; charset=UTF-8' ] );
    }

    /**
     * Clears the dry-run report after a new file is chosen.
     *
     * @since 1.0.0
     *
     * @param  string  $property  The updated property.
     *
     * @return void
     */
    public function updated( string $property ): void
    {
        if ( 'ratesCsv' === $property ) {
            $this->importReport = null;
        }
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $user    = auth()->user();
        $classes = TaxClass::query()->withCount( 'rates' )->orderBy( 'label' )->get();
        $usage   = self::classUsage( $classes->pluck( 'key' )->all() );

        $deletingClass = null === $this->deletingClassId || ! $this->confirmingClassDelete ? null : $classes->firstWhere( 'id', $this->deletingClassId );

        return view( 'ecommerce-admin::livewire.tax.index', $this->resourceTableData() + [
            'provider'         => self::provider(),
            'classes'          => $classes,
            'classUsage'       => $usage,
            'classOptions'     => $classes->map( static fn ( TaxClass $class ): array => [ 'id' => (string) $class->key, 'name' => (string) $class->label ] )->all(),
            'countryOptions'   => Countries::options(),
            'canCreate'        => $this->canEcommerce( 'create', TaxRate::class ),
            'canUpdate'        => Authorization::allows( $user, 'taxRate.update' ),
            'canDelete'        => Authorization::allows( $user, 'taxRate.delete' ),
            'deletingClass'    => $deletingClass,
            'deletingBlocker'  => null === $deletingClass ? null : self::classBlocker( $deletingClass ),
            'deleteClassToken' => null === $deletingClass ? null : $this->actionToken( 'delete-class', $deletingClass ),
            'importToken'      => null === $this->importReport ? null : $this->actionToken( 'import' ),
            'maxRows'          => self::maxRows(),
            'csvColumns'       => TaxRateCsv::COLUMNS,
        ] );
    }

    /**
     * The active tax provider: its key, label, and whether it reads the
     * rates table.
     *
     * @since 1.0.0
     *
     * @return array{key: string, label: string, manual: bool}
     */
    public static function provider(): array
    {
        $key = (string) config( 'artisanpack.ecommerce.tax.provider', ManualTaxProvider::KEY );

        try {
            $provider = app( TaxService::class )->activeProvider();
            $key      = $provider->key();
            $label    = $provider->label();
        } catch ( Throwable ) {
            $label = Str::headline( $key );
        }

        return [ 'key' => $key, 'label' => $label, 'manual' => ManualTaxProvider::KEY === $key ];
    }

    /**
     * Why a class cannot be deleted, or null when it can.
     *
     * @since 1.0.0
     *
     * @param  TaxClass  $class  Class.
     *
     * @return string|null
     */
    public static function classBlocker( TaxClass $class ): ?string
    {
        $key = (string) $class->key;

        if ( in_array( $key, self::reservedClassKeys(), true ) ) {
            return __( 'This is the store\'s default or shipping tax class, so it cannot be deleted.' );
        }

        $usage = self::classUsage( [ $key ] )[ $key ];
        $parts = array_filter( [
            $usage['rates'] > 0 ? trans_choice( ':count rate|:count rates', $usage['rates'], [ 'count' => $usage['rates'] ] ) : null,
            $usage['products'] > 0 ? trans_choice( ':count product|:count products', $usage['products'], [ 'count' => $usage['products'] ] ) : null,
            $usage['methods'] > 0 ? trans_choice( ':count shipping method|:count shipping methods', $usage['methods'], [ 'count' => $usage['methods'] ] ) : null,
        ] );

        return [] === $parts ? null : __( 'It is still used by :uses. Move them to another class first.', [ 'uses' => implode( ', ', $parts ) ] );
    }

    /**
     * Rates, products, and shipping methods per class key.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $keys  Class keys.
     *
     * @return array<string, array{rates: int, products: int, methods: int}>
     */
    public static function classUsage( array $keys ): array
    {
        $count = static fn ( string $model ): array => $model::query()
            ->whereIn( 'tax_class_key', $keys )
            ->groupBy( 'tax_class_key' )
            ->select( 'tax_class_key', DB::raw( 'COUNT(*) as aggregate' ) )
            ->pluck( 'aggregate', 'tax_class_key' )
            ->all();

        $rates    = $count( TaxRate::class );
        $products = $count( Product::class );
        $methods  = $count( ShippingMethod::class );
        $usage    = [];

        foreach ( $keys as $key ) {
            $usage[ $key ] = [
                'rates'    => (int) ( $rates[ $key ] ?? 0 ),
                'products' => (int) ( $products[ $key ] ?? 0 ),
                'methods'  => (int) ( $methods[ $key ] ?? 0 ),
            ];
        }

        return $usage;
    }

    /**
     * Class keys the engine falls back to, which must not be deleted.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public static function reservedClassKeys(): array
    {
        return array_values( array_unique( [
            TaxClass::DEFAULT_KEY,
            (string) config( 'artisanpack.ecommerce.tax.default_class', TaxClass::DEFAULT_KEY ),
            (string) config( 'artisanpack.ecommerce.tax.shipping_tax_class', TaxClass::DEFAULT_KEY ),
        ] ) );
    }

    /**
     * Most rows an import may have (`imports.max_rows`).
     *
     * @since 1.0.0
     *
     * @return int
     */
    public static function maxRows(): int
    {
        return max( 1, (int) config( 'artisanpack.ecommerce-admin-livewire.imports.max_rows', 5000 ) );
    }

    /**
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeTable(): void
    {
        $this->authorizeEcommerce( 'viewAny', TaxRate::class );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableScreen(): string
    {
        return 'tax-rates';
    }

    /**
     * @since 1.0.0
     *
     * @return ResourceQuery
     */
    protected function tableQuery(): ResourceQuery
    {
        return new TaxRatesQuery();
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableCaption(): string
    {
        return __( 'Tax rates' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableColumns(): array
    {
        $cell = 'ecommerce-admin::livewire.tax.cells.rate';
        $yes  = static fn ( bool $value ): string => $value ? __( 'Yes' ) : __( 'No' );

        return [
            [ 'key' => 'class', 'label' => __( 'Class' ), 'sortable' => true, 'view' => $cell, 'export' => static fn ( TaxRate $rate ): string => (string) $rate->tax_class_key ],
            [ 'key' => 'country', 'label' => __( 'Country' ), 'sortable' => true, 'view' => $cell, 'export' => static fn ( TaxRate $rate ): string => (string) $rate->country_code ],
            [ 'key' => 'region', 'label' => __( 'Region' ), 'sortable' => true, 'view' => $cell, 'export' => static fn ( TaxRate $rate ): string => (string) ( $rate->region_code ?? '' ) ],
            [ 'key' => 'postal', 'label' => __( 'Postal codes' ), 'view' => $cell, 'export' => static fn ( TaxRate $rate ): string => (string) ( $rate->postal_pattern ?? '' ) ],
            [ 'key' => 'rate', 'label' => __( 'Rate' ), 'sortable' => true, 'class' => 'text-end', 'view' => $cell, 'export' => static fn ( TaxRate $rate ): string => $rate->percent() ],
            [ 'key' => 'label', 'label' => __( 'Label' ), 'sortable' => true, 'view' => $cell, 'export' => static fn ( TaxRate $rate ): string => (string) $rate->label ],
            [ 'key' => 'compound', 'label' => __( 'Compound' ), 'view' => $cell, 'export' => static fn ( TaxRate $rate ): string => $yes( (bool) $rate->is_compound ) ],
            [ 'key' => 'shipping', 'label' => __( 'Taxes shipping' ), 'view' => $cell, 'export' => static fn ( TaxRate $rate ): string => $yes( (bool) $rate->is_shipping_taxable ) ],
            [ 'key' => 'priority', 'label' => __( 'Priority' ), 'sortable' => true, 'class' => 'text-end', 'view' => $cell, 'export' => static fn ( TaxRate $rate ): int => (int) $rate->priority ],
            [ 'key' => 'active', 'label' => __( 'Active' ), 'view' => $cell, 'export' => static fn ( TaxRate $rate ): string => $yes( (bool) $rate->is_active ) ],
            [ 'key' => 'actions', 'label' => __( 'Actions' ), 'class' => 'text-end', 'view' => $cell, 'exportable' => false ],
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
                'key'     => 'class',
                'label'   => __( 'Tax class' ),
                'type'    => 'select',
                'options' => TaxClass::query()->orderBy( 'label' )->get( [ 'key', 'label' ] )
                    ->map( static fn ( TaxClass $class ): array => [ 'id' => (string) $class->key, 'name' => (string) $class->label ] )
                    ->all(),
            ],
            [
                'key'     => 'country',
                'label'   => __( 'Country' ),
                'type'    => 'select',
                'options' => TaxRate::query()->distinct()->orderBy( 'country_code' )->pluck( 'country_code' )
                    ->map( static fn ( mixed $code ): array => [ 'id' => (string) $code, 'name' => Countries::name( (string) $code ) ] )
                    ->sortBy( 'name' )
                    ->values()
                    ->all(),
            ],
            [ 'key' => 'active', 'label' => __( 'Active' ), 'type' => 'boolean' ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableBulkActions(): array
    {
        return [
            [
                'key'     => 'activate',
                'label'   => __( 'Switch on' ),
                'icon'    => 'o-play',
                'ability' => 'taxRate.update',
                'handler' => fn ( Builder $selection ): string => $this->setActive( $selection, true ),
            ],
            [
                'key'     => 'deactivate',
                'label'   => __( 'Switch off' ),
                'icon'    => 'o-pause',
                'ability' => 'taxRate.update',
                'handler' => fn ( Builder $selection ): string => $this->setActive( $selection, false ),
            ],
            [
                'key'     => 'delete',
                'label'   => __( 'Delete' ),
                'icon'    => 'o-trash',
                'ability' => 'taxRate.delete',
                'confirm' => __( 'Delete the selected tax rates? Orders already placed keep the tax they were charged.' ),
                'handler' => fn ( Builder $selection ): string => $this->deleteSelection( $selection ),
            ],
            $this->exportBulkAction(),
        ];
    }

    /**
     * Switches the selected rates on or off.
     *
     * @since 1.0.0
     *
     * @param  Builder<TaxRate>  $selection  Selected rates.
     * @param  bool              $active     New state.
     *
     * @return string
     */
    protected function setActive( Builder $selection, bool $active ): string
    {
        $ids     = ( clone $selection )->reorder()->pluck( $selection->qualifyColumn( 'id' ) )->all();
        $changed = TaxRate::query()->whereKey( $ids )->where( 'is_active', ! $active )->update( [ 'is_active' => $active, 'updated_at' => Carbon::now() ] );

        return $active
            ? trans_choice( ':count rate switched on.|:count rates switched on.', $changed, [ 'count' => $changed ] )
            : trans_choice( ':count rate switched off.|:count rates switched off.', $changed, [ 'count' => $changed ] );
    }

    /**
     * Deletes the selected rates.
     *
     * @since 1.0.0
     *
     * @param  Builder<TaxRate>  $selection  Selected rates.
     *
     * @return string
     */
    protected function deleteSelection( Builder $selection ): string
    {
        $ids     = array_map( 'intval', ( clone $selection )->reorder()->pluck( $selection->qualifyColumn( 'id' ) )->all() );
        $deleted = TaxRate::query()->whereKey( $ids )->delete();

        if ( null !== $this->editingRateId && in_array( $this->editingRateId, $ids, true ) ) {
            $this->cancelRate();
        }

        return trans_choice( ':count tax rate deleted.|:count tax rates deleted.', $deleted, [ 'count' => $deleted ] );
    }

    /**
     * Validates the import upload.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function validateImport(): void
    {
        $this->validate(
            [ 'ratesCsv' => [ 'required', 'file', 'mimes:' . implode( ',', Csv::MIMES ), 'max:' . self::MAX_UPLOAD_KB ] ],
            [],
            [ 'ratesCsv' => __( 'CSV file' ) ],
        );
    }

    /**
     * The rows of the uploaded CSV, each checked and matched to the rate it
     * would update.
     *
     * @since 1.0.0
     *
     * @return array<int, array{line: int, action: string, summary: string, errors: array<int, string>, attributes: array<string, mixed>|null, rate: TaxRate|null}>
     */
    protected function importPlan(): array
    {
        $csv = Csv::withLocalUpload( $this->ratesCsv, static fn ( string $path ): array => Csv::read( $path, self::maxRows() + 1 ) );

        $missing = array_diff( TaxRateCsv::REQUIRED_COLUMNS, $csv['headers'] );

        if ( [] !== $missing ) {
            $this->failImport( __( 'The file needs these columns: :columns.', [ 'columns' => implode( ', ', $missing ) ] ) );
        }

        if ( 0 === $csv['total'] ) {
            $this->failImport( __( 'The file has no rows.' ) );
        }

        if ( $csv['total'] > self::maxRows() ) {
            $this->failImport( trans_choice( 'The file has more than :count row.|The file has more than :count rows.', self::maxRows(), [ 'count' => self::maxRows() ] ) );
        }

        $classKeys = TaxClass::query()->pluck( 'key' )->map( static fn ( mixed $key ): string => (string) $key )->all();
        $existing  = TaxRateCsv::existing( array_map( static fn ( array $row ): string => strtoupper( trim( (string) ( $row['country_code'] ?? '' ) ) ), $csv['rows'] ) );
        $canUpdate = Authorization::allows( auth()->user(), 'taxRate.update' );
        $plan      = [];
        $seen      = [];

        foreach ( $csv['rows'] as $row ) {
            $checked = TaxRateCsv::check( $row, $classKeys );
            $errors  = $checked['errors'];
            $key     = null === $checked['attributes'] ? null : TaxRateCsv::matchKey( $checked['attributes'] );
            $rate    = null === $key ? null : ( $existing[ $key ] ?? null );

            if ( null !== $key ) {

                if ( isset( $seen[ $key ] ) ) {
                    $errors[] = __( 'Line :line already sets this rate.', [ 'line' => $seen[ $key ] ] );
                }

                $seen[ $key ] ??= (int) $row['__line'];

                if ( null !== $rate && ! $canUpdate ) {
                    $errors[] = __( 'This rate already exists and you are not allowed to change existing rates.' );
                }
            }

            $plan[] = [
                'line'       => (int) $row['__line'],
                'action'     => [] !== $errors ? 'invalid' : ( null === $rate ? 'create' : 'update' ),
                'summary'    => self::rowSummary( $row ),
                'errors'     => $errors,
                'attributes' => [] === $errors ? $checked['attributes'] : null,
                'rate'       => [] === $errors ? $rate : null,
            ];
        }

        return $plan;
    }

    /**
     * Stops the request with an import error on the upload field.
     *
     * @since 1.0.0
     *
     * @param  string  $message  Error.
     *
     * @return never
     */
    protected function failImport( string $message ): never
    {
        $this->importReport = null;

        throw ValidationException::withMessages( [ 'ratesCsv' => $message ] );
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
        if ( $this->ratesCsv instanceof TemporaryUploadedFile ) {
            try {
                $this->ratesCsv->delete();
            } catch ( Throwable $exception ) {
                report( $exception );
            }
        }

        $this->ratesCsv = null;
    }

    /**
     * A blank rate form.
     *
     * @since 1.0.0
     *
     * @return array{tax_class_key: string, country_code: string, region_code: string, postal_pattern: string, rate_ubps: null, label: string, is_compound: bool, is_shipping_taxable: bool, priority: int, is_active: bool}
     */
    private static function emptyRateForm(): array
    {
        return [
            'tax_class_key'       => TaxClass::DEFAULT_KEY,
            'country_code'        => '',
            'region_code'         => '',
            'postal_pattern'      => '',
            'rate_ubps'           => null,
            'label'               => '',
            'is_compound'         => false,
            'is_shipping_taxable' => false,
            'priority'            => 0,
            'is_active'           => true,
        ];
    }

    /**
     * A short description of a CSV row for the dry-run report.
     *
     * @since 1.0.0
     *
     * @param  array<string, int|string>  $row  CSV row.
     *
     * @return string
     */
    private static function rowSummary( array $row ): string
    {
        $parts = array_filter( [
            (string) ( $row['country_code'] ?? '' ),
            (string) ( $row['region_code'] ?? '' ),
            (string) ( $row['label'] ?? '' ),
            '' === (string) ( $row['rate_percent'] ?? '' ) ? '' : $row['rate_percent'] . '%',
        ], static fn ( string $part ): bool => '' !== trim( $part ) );

        return Str::limit( implode( ' · ', $parts ), 120 );
    }
}
