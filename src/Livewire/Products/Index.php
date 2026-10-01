<?php

/**
 * Products index screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products;

use ArtisanPackUI\Ecommerce\Exceptions\ProductWriteException;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Registries\ProductTypeRegistry;
use ArtisanPackUI\Ecommerce\Services\ProductService;
use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithResourceTable;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ProductsQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Csv;
use ArtisanPackUI\EcommerceAdminLivewire\Support\MinorUnits;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductCsv;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The products table (spec §7.2): image, name, SKU, type, status, base
 * currency price (a range for products with variants), stock (summed over
 * variants), categories, and last update; filtered by search, status,
 * type, category, tag, and stock state.
 *
 * Bulk actions publish, archive, delete, add or remove a category or tag,
 * and export. "Export catalog" writes the product CSV format the import
 * screen reads back (one row per product or variant, prices per currency). Writes go through the engine's `ProductService`, and products
 * whose type is missing are flagged in the type column and skipped by the
 * bulk edits (they are read-only, plan §16.6).
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
    use WithResourceTable;

    /**
     * Category the add/remove-category bulk actions use.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $bulkCategoryId = null;

    /**
     * Tag the add/remove-tag bulk actions use.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $bulkTagId = null;

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
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $data = $this->resourceTableData();

        return view( 'ecommerce-admin::livewire.products.index', $data + [
            'canCreate'           => $this->canEcommerce( 'create', Product::class ),
            'importRoute'         => AdminNav::ROUTE_PREFIX . 'products.import',
            'bulkCategoryOptions' => $data['tableSelectionCount'] > 0 ? self::categoryOptions() : [],
            'bulkTagOptions'      => $data['tableSelectionCount'] > 0 ? self::tagOptions() : [],
        ] );
    }

    /**
     * Exports every product matching the search and filters in the product
     * CSV format (`docs/product-csv.md`), which the import reads back.
     *
     * @since 1.0.0
     *
     * @return StreamedResponse
     */
    public function exportCatalog(): StreamedResponse
    {
        $this->authorizeTable();

        return $this->catalogDownload( $this->filteredQuery() );
    }

    /**
     * The label of a product type, or null when it is not registered.
     *
     * @since 1.0.0
     *
     * @param  string  $type  Type key.
     *
     * @return string|null
     */
    public static function typeLabel( string $type ): ?string
    {
        $registry = app( ProductTypeRegistry::class );

        return $registry->has( $type ) ? $registry->get( $type )->label() : null;
    }

    /**
     * The status label.
     *
     * @since 1.0.0
     *
     * @param  string  $status  Product status.
     *
     * @return string
     */
    public static function statusLabel( string $status ): string
    {
        return self::statuses()[ $status ] ?? $status;
    }

    /**
     * Product statuses and their labels.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'draft'    => __( 'Draft' ),
            'active'   => __( 'Active' ),
            'archived' => __( 'Archived' ),
        ];
    }

    /**
     * Streams products in the product CSV format, one row per product or
     * variant, capped at `tables.export_max_rows` rows.
     *
     * @since 1.0.0
     *
     * @param  Builder<Product>  $products  Products to export.
     *
     * @return StreamedResponse
     */
    protected function catalogDownload( Builder $products ): StreamedResponse
    {
        $limit = max( 1, (int) config( 'artisanpack.ecommerce-admin-livewire.tables.export_max_rows', 10_000 ) );
        $rows  = [];
        $cut   = false;

        foreach ( ProductCsv::exportRows( $products ) as $row ) {
            if ( count( $rows ) > $limit ) {
                $cut = true;

                break;
            }

            $rows[] = $row;
        }

        if ( $cut ) {
            $this->toastWarning(
                __( 'The export was cut short.' ),
                trans_choice( 'Only the first :count row was exported.|Only the first :count rows were exported.', $limit, [ 'count' => $limit ] ),
            );
        }

        $csv = Csv::build( (array) array_shift( $rows ), $rows );

        return response()->streamDownload( static function () use ( $csv ): void {
            echo $csv;
        }, 'products-catalog-' . Carbon::now()->format( 'Y-m-d-His' ) . '.csv', [ 'Content-Type' => 'text/csv; charset=UTF-8' ] );
    }

    /**
     * Sets the status of every editable selected product.
     *
     * @since 1.0.0
     *
     * @param  Builder<Product>  $selection  Selected products.
     * @param  string            $status     `active` or `archived`.
     *
     * @return string|null
     */
    protected function setStatus( Builder $selection, string $status ): ?string
    {
        $service = app( ProductService::class );

        [ $changed, $skipped, $failed ] = $this->eachEditable( $selection, 'update', static function ( Product $product ) use ( $service, $status ): void {
            if ( $status !== $product->status ) {
                $service->update( $product, [ 'status' => $status ] );
            }
        } );

        return $this->summary(
            'active' === $status
                ? trans_choice( ':count product published.|:count products published.', $changed, [ 'count' => $changed ] )
                : trans_choice( ':count product archived.|:count products archived.', $changed, [ 'count' => $changed ] ),
            $skipped,
            $failed,
        );
    }

    /**
     * Deletes the selected products.
     *
     * @since 1.0.0
     *
     * @param  Builder<Product>  $selection  Selected products.
     *
     * @return string|null
     */
    protected function deleteSelection( Builder $selection ): ?string
    {
        $service = app( ProductService::class );
        $ids     = ( clone $selection )->reorder()->pluck( 'products.id' )->all();
        $deleted = 0;

        DB::transaction( function () use ( $ids, $service, &$deleted ): void {
            foreach ( array_chunk( $ids, 200 ) as $chunk ) {
                foreach ( Product::query()->whereKey( $chunk )->get() as $product ) {
                    $this->authorizeEcommerce( 'delete', $product );
                    $service->delete( $product );
                    ++$deleted;
                }
            }
        } );

        return trans_choice( ':count product deleted.|:count products deleted.', $deleted, [ 'count' => $deleted ] );
    }

    /**
     * Adds or removes the chosen category on the selected products.
     *
     * @since 1.0.0
     *
     * @param  Builder<Product>  $selection  Selected products.
     * @param  string            $mode       `attach` or `detach`.
     *
     * @return string|null
     */
    protected function changeCategory( Builder $selection, string $mode ): ?string
    {
        $this->validate(
            [ 'bulkCategoryId' => [ 'required', 'integer', Rule::exists( ProductCategory::class, 'id' ) ] ],
            [],
            [ 'bulkCategoryId' => __( 'category' ) ],
        );

        $category = ProductCategory::query()->findOrFail( (int) $this->bulkCategoryId );
        $service  = app( ProductService::class );

        [ $changed, $skipped, $failed ] = $this->eachEditable( $selection, 'update', static fn ( Product $product ) => $service->setCategories( $product, [ $category->id ], $mode ) );

        $this->bulkCategoryId = null;

        return $this->summary(
            'attach' === $mode
                ? trans_choice( 'Added ":category" to :count product.|Added ":category" to :count products.', $changed, [ 'count' => $changed, 'category' => $category->name ] )
                : trans_choice( 'Removed ":category" from :count product.|Removed ":category" from :count products.', $changed, [ 'count' => $changed, 'category' => $category->name ] ),
            $skipped,
            $failed,
        );
    }

    /**
     * Adds or removes the chosen tag on the selected products.
     *
     * @since 1.0.0
     *
     * @param  Builder<Product>  $selection  Selected products.
     * @param  string            $mode       `attach` or `detach`.
     *
     * @return string|null
     */
    protected function changeTag( Builder $selection, string $mode ): ?string
    {
        $this->validate(
            [ 'bulkTagId' => [ 'required', 'integer', Rule::exists( ProductTag::class, 'id' ) ] ],
            [],
            [ 'bulkTagId' => __( 'tag' ) ],
        );

        $tag     = ProductTag::query()->findOrFail( (int) $this->bulkTagId );
        $service = app( ProductService::class );

        [ $changed, $skipped, $failed ] = $this->eachEditable( $selection, 'update', static fn ( Product $product ) => $service->setTags( $product, [ $tag->id ], $mode ) );

        $this->bulkTagId = null;

        return $this->summary(
            'attach' === $mode
                ? trans_choice( 'Tagged :count product ":tag".|Tagged :count products ":tag".', $changed, [ 'count' => $changed, 'tag' => $tag->name ] )
                : trans_choice( 'Removed ":tag" from :count product.|Removed ":tag" from :count products.', $changed, [ 'count' => $changed, 'tag' => $tag->name ] ),
            $skipped,
            $failed,
        );
    }

    /**
     * Runs `$write` on every selected product the user may `$ability`,
     * skipping read-only (missing-type) ones, in one transaction.
     *
     * @since 1.0.0
     *
     * @param  Builder<Product>         $selection  Selected products.
     * @param  string                   $ability    Policy ability.
     * @param  callable(Product): mixed $write      Write.
     *
     * @return array{0: int, 1: int, 2: int} Changed, skipped (read-only), and refused counts.
     */
    protected function eachEditable( Builder $selection, string $ability, callable $write ): array
    {
        // Collect ids first: the write can take rows out of the filter.
        $ids     = ( clone $selection )->reorder()->pluck( 'products.id' )->all();
        $changed = 0;
        $skipped = 0;
        $failed  = 0;

        DB::transaction( function () use ( $ids, $ability, $write, &$changed, &$skipped, &$failed ): void {
            foreach ( array_chunk( $ids, 200 ) as $chunk ) {
                foreach ( Product::query()->whereKey( $chunk )->get() as $product ) {
                    if ( $product->typeIsMissing() ) {
                        ++$skipped;
                        continue;
                    }

                    $this->authorizeEcommerce( $ability, $product );

                    try {
                        $write( $product );
                        ++$changed;
                    } catch ( ProductWriteException ) {
                        ++$failed;
                    }
                }
            }
        } );

        return [ $changed, $skipped, $failed ];
    }

    /**
     * A bulk result, with a note about skipped products.
     *
     * @since 1.0.0
     *
     * @param  string  $message  Success message.
     * @param  int     $skipped  Read-only products skipped.
     * @param  int     $failed   Products the engine refused.
     *
     * @return string
     */
    protected function summary( string $message, int $skipped, int $failed = 0 ): string
    {
        if ( $skipped > 0 ) {
            $message .= ' ' . trans_choice( ':count read-only product was skipped.|:count read-only products were skipped.', $skipped, [ 'count' => $skipped ] );
        }

        if ( $failed > 0 ) {
            $message .= ' ' . trans_choice( ':count product could not be changed.|:count products could not be changed.', $failed, [ 'count' => $failed ] );
        }

        return $message;
    }

    /**
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeTable(): void
    {
        $this->authorizeEcommerce( 'viewAny', Product::class );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableScreen(): string
    {
        return 'products';
    }

    /**
     * @since 1.0.0
     *
     * @return ResourceQuery
     */
    protected function tableQuery(): ResourceQuery
    {
        return new ProductsQuery();
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableCaption(): string
    {
        return __( 'Products' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableColumns(): array
    {
        $cells    = 'ecommerce-admin::livewire.products.cells.';
        $currency = ProductsQuery::baseCurrency();

        return [
            [
                'key'        => 'image',
                'label'      => __( 'Image' ),
                'view'       => $cells . 'image',
                'exportable' => false,
            ],
            [
                'key'      => 'name',
                'label'    => __( 'Name' ),
                'sortable' => true,
                'view'     => $cells . 'name',
                'export'   => static fn ( Product $product ): string => (string) $product->name,
            ],
            [
                'key'      => 'sku',
                'label'    => __( 'SKU' ),
                'sortable' => true,
                'value'    => static fn ( Product $product ): string => (string) ( $product->sku ?? '' ),
            ],
            [
                'key'      => 'type',
                'label'    => __( 'Type' ),
                'sortable' => true,
                'view'     => $cells . 'type',
                'export'   => static fn ( Product $product ): string => self::typeLabel( (string) $product->type ) ?? (string) $product->type,
            ],
            [
                'key'      => 'status',
                'label'    => __( 'Status' ),
                'sortable' => true,
                'view'     => $cells . 'status',
                'export'   => static fn ( Product $product ): string => self::statusLabel( (string) $product->status ),
            ],
            [
                'key'    => 'price',
                'label'  => __( 'Price (:currency)', [ 'currency' => $currency ] ),
                'class'  => 'text-end',
                'view'   => $cells . 'price',
                'export' => static function ( Product $product ) use ( $currency ): string {
                    $range = ProductsQuery::priceRange( $product );

                    if ( null === $range['min'] ) {
                        return '';
                    }

                    $min = MinorUnits::toMajor( $range['min'], $currency );

                    return $range['min'] === $range['max'] ? $min : $min . ' - ' . MinorUnits::toMajor( (int) $range['max'], $currency );
                },
            ],
            [
                'key'      => 'stock',
                'label'    => __( 'Stock' ),
                'sortable' => true,
                'class'    => 'text-end',
                'view'     => $cells . 'stock',
                'export'   => static fn ( Product $product ): string => (int) $product->stock_tracked > 0 ? (string) (int) $product->stock_available : '',
            ],
            [
                'key'    => 'categories',
                'label'  => __( 'Categories' ),
                'value'  => static fn ( Product $product ): string => $product->categories->pluck( 'name' )->implode( ', ' ),
            ],
            [
                'key'      => 'updated',
                'label'    => __( 'Updated' ),
                'sortable' => true,
                'value'    => static fn ( Product $product ): string => null === $product->updated_at ? '' : LocalizedDate::format( $product->updated_at ),
                'export'   => static fn ( Product $product ): string => $product->updated_at?->format( DATE_ATOM ) ?? '',
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
            [ 'key' => 'status', 'label' => __( 'Status' ), 'type' => 'select', 'options' => self::options( self::statuses() ) ],
            [ 'key' => 'type', 'label' => __( 'Type' ), 'type' => 'select', 'options' => self::typeOptions() ],
            [ 'key' => 'category', 'label' => __( 'Category' ), 'type' => 'select', 'options' => self::categoryOptions() ],
            [ 'key' => 'tag', 'label' => __( 'Tag' ), 'type' => 'select', 'options' => self::tagOptions() ],
            [
                'key'     => 'stock',
                'label'   => __( 'Stock' ),
                'type'    => 'select',
                'options' => self::options( [
                    'in'        => __( 'In stock' ),
                    'low'       => __( 'Low stock' ),
                    'out'       => __( 'Out of stock' ),
                    'untracked' => __( 'Not tracked' ),
                ] ),
            ],
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
                'key'     => 'publish',
                'label'   => __( 'Publish' ),
                'icon'    => 'o-eye',
                'ability' => 'product.update',
                'handler' => fn ( Builder $selection ): ?string => $this->setStatus( $selection, 'active' ),
            ],
            [
                'key'     => 'archive',
                'label'   => __( 'Archive' ),
                'icon'    => 'o-archive-box',
                'ability' => 'product.update',
                'handler' => fn ( Builder $selection ): ?string => $this->setStatus( $selection, 'archived' ),
            ],
            [
                'key'     => 'add-category',
                'label'   => __( 'Add category' ),
                'icon'    => 'o-folder-plus',
                'ability' => 'product.update',
                'handler' => fn ( Builder $selection ): ?string => $this->changeCategory( $selection, 'attach' ),
            ],
            [
                'key'     => 'remove-category',
                'label'   => __( 'Remove category' ),
                'icon'    => 'o-folder-minus',
                'ability' => 'product.update',
                'handler' => fn ( Builder $selection ): ?string => $this->changeCategory( $selection, 'detach' ),
            ],
            [
                'key'     => 'add-tag',
                'label'   => __( 'Add tag' ),
                'icon'    => 'o-tag',
                'ability' => 'product.update',
                'handler' => fn ( Builder $selection ): ?string => $this->changeTag( $selection, 'attach' ),
            ],
            [
                'key'     => 'remove-tag',
                'label'   => __( 'Remove tag' ),
                'icon'    => 'o-x-circle',
                'ability' => 'product.update',
                'handler' => fn ( Builder $selection ): ?string => $this->changeTag( $selection, 'detach' ),
            ],
            [
                'key'     => 'delete',
                'label'   => __( 'Delete' ),
                'icon'    => 'o-trash',
                'ability' => 'product.delete',
                'confirm' => __( 'Delete the selected products? Their variants, prices, and stock go with them. Past orders keep their copy of each product.' ),
                'handler' => fn ( Builder $selection ): ?string => $this->deleteSelection( $selection ),
            ],
            [
                'key'     => 'export-catalog',
                'label'   => __( 'Export catalog CSV' ),
                'icon'    => 'o-document-arrow-down',
                'handler' => fn ( Builder $selection ): StreamedResponse => $this->catalogDownload( $selection ),
            ],
            $this->exportBulkAction(),
        ];
    }

    /**
     * `id => name` pairs as select options.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $pairs  Options.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function options( array $pairs ): array
    {
        return array_values( array_map(
            static fn ( string $id, string $name ): array => [ 'id' => $id, 'name' => $name ],
            array_keys( $pairs ),
            $pairs,
        ) );
    }

    /**
     * Registered types, plus any type in the catalog that is missing.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function typeOptions(): array
    {
        $registry = app( ProductTypeRegistry::class );
        $options  = [];

        foreach ( $registry->all() as $key => $type ) {
            $options[ (string) $key ] = $type->label();
        }

        foreach ( Product::query()->distinct()->pluck( 'type' ) as $key ) {
            $options[ (string) $key ] ??= __( ':type (missing)', [ 'type' => $key ] );
        }

        return self::options( $options );
    }

    /**
     * Every category, by name.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function categoryOptions(): array
    {
        return ProductCategory::query()
            ->orderBy( 'name' )
            ->get( [ 'id', 'name' ] )
            ->map( static fn ( ProductCategory $category ): array => [ 'id' => (string) $category->id, 'name' => (string) $category->name ] )
            ->all();
    }

    /**
     * Every tag, by name.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function tagOptions(): array
    {
        return ProductTag::query()
            ->orderBy( 'name' )
            ->get( [ 'id', 'name' ] )
            ->map( static fn ( ProductTag $tag ): array => [ 'id' => (string) $tag->id, 'name' => (string) $tag->name ] )
            ->all();
    }
}
