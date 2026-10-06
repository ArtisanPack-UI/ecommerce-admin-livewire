<?php

/**
 * Digital files screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\DigitalFiles;

use ArtisanPackUI\Ecommerce\Exceptions\DigitalFileInUseException;
use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Services\DigitalFileService;
use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithPickers;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithResourceTable;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\DigitalFilesQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\DigitalDisks;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The digital files table (spec §7.2): product or variant, label, version,
 * file (a path on one of the engine's digital disks, or a media-library
 * item), and the streaming-only flag, with a create/edit drawer.
 *
 * Creates and updates go through the engine's `DigitalFileService`.
 * Changing the version of a saved file makes the engine tell everyone who
 * bought it, so the drawer warns before saving such a change.
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
    use WithPickers;
    use WithResourceTable;

    /**
     * Media-library context for the drawer's file.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const MEDIA_CONTEXT = 'ecommerce-digital-files-screen';

    /**
     * Whether the drawer is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $editing = false;

    /**
     * The file being edited, or null when creating.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $fileId = null;

    /**
     * The saved version of the file being edited.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $savedVersion = null;

    /**
     * The drawer fields.
     *
     * @since 1.0.0
     *
     * @var array{product_id: int|string|null, product_variant_id: int|string|null, label: string, version: string, is_streaming_only: bool, source: string, disk: string, path: string, media_id: int|string|null}
     */
    public array $form = [];

    /**
     * Files a delete kept because customers bought them, offered for archiving.
     *
     * @since 1.0.0
     *
     * @var array<int, int>
     */
    #[Locked]
    public array $keptIds = [];

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
        $this->form = self::blankForm();
    }

    /**
     * Opens the drawer for a new file.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function create(): void
    {
        $this->authorizeEcommerce( 'create', DigitalFile::class );

        $this->resetErrorBag();
        $this->fileId       = null;
        $this->savedVersion = null;
        $this->form         = self::blankForm();
        $this->editing      = true;
    }

    /**
     * Opens the drawer for a saved file.
     *
     * @since 1.0.0
     *
     * @param  int  $id  File id.
     *
     * @return void
     */
    public function edit( int $id ): void
    {
        $file = DigitalFile::query()->with( 'variant' )->find( $id );

        if ( null === $file ) {
            $this->authorizeEcommerceAbility( 'digitalFile.update' );

            return;
        }

        $this->authorizeEcommerce( 'update', $file );

        $this->resetErrorBag();
        $this->fileId       = (int) $file->id;
        $this->savedVersion = $file->version;
        $this->form         = [
            'product_id'         => $file->product_id ?? $file->variant?->product_id,
            'product_variant_id' => $file->product_variant_id,
            'label'              => (string) $file->label,
            'version'            => (string) ( $file->version ?? '' ),
            'is_streaming_only'  => (bool) $file->is_streaming_only,
            'source'             => null === $file->media_id ? 'path' : 'media',
            'disk'               => (string) ( $file->disk ?? DigitalDisks::default() ),
            'path'               => (string) ( $file->path ?? '' ),
            'media_id'           => $file->media_id,
            'is_archived'        => null !== $file->archived_at,
        ];
        $this->editing      = true;
    }

    /**
     * Saves the drawer through `DigitalFileService`.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function save(): void
    {
        $file = null === $this->fileId ? null : DigitalFile::query()->find( $this->fileId );

        if ( null === $file ) {
            $this->authorizeEcommerce( 'create', DigitalFile::class );
        } else {
            $this->authorizeEcommerce( 'update', $file );
        }

        $this->validate(
            [
                'form.product_id'         => [ 'required', 'integer', Rule::exists( Product::class, 'id' ) ],
                'form.product_variant_id' => [
                    'nullable',
                    'integer',
                    Rule::exists( ProductVariant::class, 'id' )->where( 'product_id', (int) ( $this->form['product_id'] ?? 0 ) ),
                ],
                'form.label'              => [ 'required', 'string', 'max:255' ],
                // The engine tells buyers whenever a saved version changes, including
                // to nothing, so a version can be changed but not cleared.
                'form.version'            => [ null === $file || null === $file->version ? 'nullable' : 'required', 'string', 'max:60' ],
                'form.is_streaming_only'  => [ 'boolean' ],
                'form.is_archived'        => [ 'boolean' ],
                'form.source'             => [ 'required', Rule::in( [ 'path', 'media' ] ) ],
                'form.disk'               => [ 'nullable', 'required_if:form.source,path', Rule::in( DigitalDisks::allowed() ) ],
                'form.path'               => [ 'nullable', 'required_if:form.source,path', 'string', 'max:1000', DigitalDisks::relativePath() ],
                'form.media_id'           => [ 'nullable', 'required_if:form.source,media', 'integer', 'min:1', DigitalDisks::privateMedia() ],
            ],
            [],
            [
                'form.product_id'         => __( 'product' ),
                'form.product_variant_id' => __( 'variant' ),
                'form.label'              => __( 'label' ),
                'form.version'            => __( 'version' ),
                'form.disk'               => __( 'disk' ),
                'form.path'               => __( 'path' ),
                'form.media_id'           => __( 'media file' ),
            ],
        );

        $variantId  = is_numeric( $this->form['product_variant_id'] ) ? (int) $this->form['product_variant_id'] : null;
        $media      = 'media' === $this->form['source'];
        $version    = trim( sanitizeText( (string) $this->form['version'] ) );
        $attributes = [
            'product_id'         => null === $variantId ? (int) $this->form['product_id'] : null,
            'product_variant_id' => $variantId,
            'label'              => trim( sanitizeText( (string) $this->form['label'] ) ),
            'version'            => '' === $version ? null : $version,
            'is_streaming_only'  => (bool) $this->form['is_streaming_only'],
            'is_archived'        => (bool) ( $this->form['is_archived'] ?? false ),
            'media_id'           => $media ? (int) $this->form['media_id'] : null,
            'disk'               => $media ? null : (string) $this->form['disk'],
            'path'               => $media ? null : ltrim( (string) $this->form['path'], '/' ),
        ];

        $service  = app( DigitalFileService::class );
        $notified = null !== $file && null !== $file->version && null !== $attributes['version'] && $file->version !== $attributes['version'];
        $file     = null === $file ? $service->create( $attributes ) : $service->update( $file, $attributes );

        $this->editing = false;
        $this->fileId  = null;

        $this->toastSuccess(
            __( 'File ":label" saved.', [ 'label' => $file->label ] ),
            $notified ? __( 'Customers who bought it are being told about version :version.', [ 'version' => (string) $file->version ] ) : null,
        );
    }

    /**
     * Asks to confirm deleting one file (through the bulk delete).
     *
     * @since 1.0.0
     *
     * @param  int  $id  File id.
     *
     * @return void
     */
    public function confirmDelete( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'digitalFile.delete' );

        $this->selected          = [ $id ];
        $this->selectAllMatching = false;

        $this->runBulkAction( 'delete' );
    }

    /**
     * Receives the drawer's file from the media library.
     *
     * @since 1.0.0
     *
     * @param  array<int, array<string, mixed>>  $media    Selected media.
     * @param  string                            $context  Modal context.
     *
     * @return void
     */
    /**
     * Archives the files the last delete kept because customers bought
     * them. Archived files stay downloadable for existing buyers.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function archiveKept(): void
    {
        $this->authorizeEcommerceAbility( 'digitalFile.update' );

        $ids           = $this->keptIds;
        $this->keptIds = [];

        [ $archived, $skipped ] = $this->archiveFiles( DigitalFile::query()->whereKey( $ids ) );

        $this->toastSuccess( self::archiveSummary( $archived, $skipped ) );
    }

    /**
     * Dismisses the archive offer.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function dismissKept(): void
    {
        $this->authorizeTable();

        $this->keptIds = [];
    }

    #[On( 'media-selected' )]
    public function mediaSelected( array $media = [], string $context = '' ): void
    {
        if ( self::MEDIA_CONTEXT !== $context || ! $this->editing ) {
            return;
        }

        $item = $media[0] ?? null;

        if ( is_array( $item ) && is_numeric( $item['id'] ?? null ) ) {
            $this->form['source']   = 'media';
            $this->form['media_id'] = (int) $item['id'];

            if ( '' === trim( (string) $this->form['label'] ) && is_string( $item['title'] ?? null ) ) {
                $this->form['label'] = $item['title'];
            }
        }
    }

    /**
     * Clears the variant when the product changes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedFormProductId(): void
    {
        $this->form['product_variant_id'] = null;
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $user      = auth()->user();
        $productId = is_numeric( $this->form['product_id'] ?? null ) ? (int) $this->form['product_id'] : null;
        $version   = trim( (string) ( $this->form['version'] ?? '' ) );

        return view( 'ecommerce-admin::livewire.digital-files.index', $this->resourceTableData() + [
            'canCreate'      => $this->canEcommerce( 'create', DigitalFile::class ),
            'canUpdate'      => Authorization::allows( $user, 'digitalFile.update' ),
            'canDelete'      => Authorization::allows( $user, 'digitalFile.delete' ),
            'variantOptions' => null === $productId ? [] : ProductVariant::query()
                ->where( 'product_id', $productId )
                ->orderBy( 'position' )
                ->orderBy( 'id' )
                ->get( [ 'id', 'name', 'sku' ] )
                ->map( static fn ( ProductVariant $variant ): array => [ 'id' => (int) $variant->id, 'name' => trim( (string) ( $variant->name ?? '' ) . ( filled( $variant->sku ) ? ' (' . $variant->sku . ')' : '' ) ) ?: '#' . $variant->id ] )
                ->all(),
            'versionBumped'  => null !== $this->fileId && null !== $this->savedVersion && '' !== $version && $version !== $this->savedVersion,
            'diskOptions'    => DigitalDisks::options(),
            'keptCount'      => count( $this->keptIds ),
            'mediaLibrary'   => ProductMedia::libraryInstalled(),
        ] );
    }

    /**
     * Where a file lives, for the table.
     *
     * @since 1.0.0
     *
     * @param  DigitalFile  $file  File.
     *
     * @return string
     */
    public static function location( DigitalFile $file ): string
    {
        return null === $file->media_id
            ? (string) ( $file->disk ?? DigitalDisks::default() ) . ':' . (string) $file->path
            : __( 'Media library file #:id', [ 'id' => $file->media_id ] );
    }

    /**
     * The product (and variant) a file belongs to.
     *
     * @since 1.0.0
     *
     * @param  DigitalFile  $file  File with `product` and `variant.product` loaded.
     *
     * @return string
     */
    public static function owner( DigitalFile $file ): string
    {
        if ( null !== $file->variant ) {
            $variant = trim( (string) ( $file->variant->name ?? '' ) );

            return trim( (string) ( $file->variant->product?->name ?? '' ) . ' — ' . ( '' === $variant ? '#' . $file->variant->id : $variant ) );
        }

        return (string) ( $file->product?->name ?? __( 'Deleted product' ) );
    }

    /**
     * Deletes the selected files. The engine refuses to delete a file that
     * customers bought (their downloads would vanish); those are kept and
     * offered for archiving instead.
     *
     * @since 1.0.0
     *
     * @param  Builder<DigitalFile>  $selection  Selected files.
     *
     * @return string|null
     */
    protected function deleteSelection( Builder $selection ): ?string
    {
        $service = app( DigitalFileService::class );
        $ids     = ( clone $selection )->reorder()->pluck( $selection->qualifyColumn( 'id' ) )->all();
        $deleted = 0;
        $kept    = [];
        $denied  = 0;

        foreach ( DigitalFile::query()->whereKey( $ids )->get() as $file ) {
            if ( ! $this->canEcommerce( 'delete', $file ) ) {
                ++$denied;
                continue;
            }

            try {
                $service->delete( $file );
                ++$deleted;
            } catch ( DigitalFileInUseException ) {
                $kept[] = (int) $file->id;
            }
        }

        $this->keptIds = $kept;

        $summary = trans_choice( ':count file deleted.|:count files deleted.', $deleted, [ 'count' => $deleted ] );

        if ( [] !== $kept ) {
            $summary .= ' ' . trans_choice( ':count file has buyers and was kept. Archive it instead.|:count files have buyers and were kept. Archive them instead.', count( $kept ), [ 'count' => count( $kept ) ] );
        }

        return self::withDeniedNote( $summary, $denied );
    }

    /**
     * Archives the selected files.
     *
     * @since 1.0.0
     *
     * @param  Builder<DigitalFile>  $selection  Selected files.
     *
     * @return string|null
     */
    protected function archiveSelection( Builder $selection ): ?string
    {
        [ $archived, $skipped ] = $this->archiveFiles( DigitalFile::query()->whereKey( ( clone $selection )->reorder()->pluck( $selection->qualifyColumn( 'id' ) )->all() ) );

        return self::archiveSummary( $archived, $skipped );
    }

    /**
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeTable(): void
    {
        $this->authorizeEcommerce( 'viewAny', DigitalFile::class );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableScreen(): string
    {
        return 'digital-files';
    }

    /**
     * @since 1.0.0
     *
     * @return ResourceQuery
     */
    protected function tableQuery(): ResourceQuery
    {
        return new DigitalFilesQuery();
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableCaption(): string
    {
        return __( 'Digital files' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableColumns(): array
    {
        return [
            [
                'key'   => 'product',
                'label' => __( 'Product' ),
                'value' => static fn ( DigitalFile $file ): string => self::owner( $file ),
            ],
            [
                'key'      => 'label',
                'label'    => __( 'Label' ),
                'sortable' => true,
            ],
            [
                'key'      => 'version',
                'label'    => __( 'Version' ),
                'sortable' => true,
                'value'    => static fn ( DigitalFile $file ): string => (string) ( $file->version ?? '' ),
            ],
            [
                'key'   => 'file',
                'label' => __( 'File' ),
                'value' => static fn ( DigitalFile $file ): string => self::location( $file ),
            ],
            [
                'key'    => 'archived',
                'label'  => __( 'Archived' ),
                'value'  => static fn ( DigitalFile $file ): string => null === $file->archived_at ? __( 'No' ) : __( 'Yes' ),
                'export' => static fn ( DigitalFile $file ): int => null === $file->archived_at ? 0 : 1,
            ],
            [
                'key'    => 'streaming',
                'label'  => __( 'Streaming only' ),
                'value'  => static fn ( DigitalFile $file ): string => $file->is_streaming_only ? __( 'Yes' ) : __( 'No' ),
                'export' => static fn ( DigitalFile $file ): int => $file->is_streaming_only ? 1 : 0,
            ],
            [
                'key'      => 'updated',
                'label'    => __( 'Updated' ),
                'sortable' => true,
                'value'    => static fn ( DigitalFile $file ): string => null === $file->updated_at ? '' : LocalizedDate::format( $file->updated_at ),
                'export'   => static fn ( DigitalFile $file ): string => $file->updated_at?->format( DATE_ATOM ) ?? '',
            ],
            [
                'key'        => 'actions',
                'label'      => __( 'Actions' ),
                'class'      => 'text-end',
                'view'       => 'ecommerce-admin::livewire.digital-files.cells.actions',
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
            [ 'key' => 'product', 'label' => __( 'Product' ), 'type' => 'select', 'options' => self::productOptions() ],
            [ 'key' => 'streaming', 'label' => __( 'Streaming only' ), 'type' => 'boolean' ],
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
                'key'     => 'delete',
                'label'   => __( 'Delete' ),
                'icon'    => 'o-trash',
                'ability' => 'digitalFile.delete',
                'confirm' => __( 'Delete the selected files? Files customers already bought are kept, and you can archive them instead.' ),
                'handler' => fn ( Builder $selection ): ?string => $this->deleteSelection( $selection ),
            ],
            [
                'key'     => 'archive',
                'label'   => __( 'Archive' ),
                'icon'    => 'o-archive-box',
                'ability' => 'digitalFile.update',
                'confirm' => null,
                'handler' => fn ( Builder $selection ): ?string => $this->archiveSelection( $selection ),
            ],
            $this->exportBulkAction(),
        ];
    }

    /**
     * Archives each file the user may update, through the engine service.
     *
     * @since 1.0.0
     *
     * @param  Builder<DigitalFile>  $files  Files to archive.
     *
     * @return array{0: int, 1: int} The archived and skipped counts.
     */
    private function archiveFiles( Builder $files ): array
    {
        $service  = app( DigitalFileService::class );
        $archived = 0;
        $skipped  = 0;

        foreach ( $files->get() as $file ) {
            if ( ! $this->canEcommerce( 'update', $file ) ) {
                ++$skipped;

                continue;
            }

            $service->update( $file, [ 'is_archived' => true ] );
            ++$archived;
        }

        return [ $archived, $skipped ];
    }

    /**
     * The toast for an archive run.
     *
     * @since 1.0.0
     *
     * @param  int  $archived  Files archived.
     * @param  int  $skipped   Files the user may not change.
     *
     * @return string
     */
    private static function archiveSummary( int $archived, int $skipped ): string
    {
        $summary = trans_choice( ':count file archived.|:count files archived.', $archived, [ 'count' => $archived ] );

        if ( $skipped > 0 ) {
            $summary .= ' ' . trans_choice( ':count file skipped: you may not change it.|:count files skipped: you may not change them.', $skipped, [ 'count' => $skipped ] );
        }

        return $summary;
    }

    /**
     * The empty drawer.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    private static function blankForm(): array
    {
        return [
            'product_id'         => null,
            'product_variant_id' => null,
            'label'              => '',
            'version'            => '',
            'is_streaming_only'  => false,
            'is_archived'        => false,
            'source'             => 'path',
            'disk'               => DigitalDisks::default(),
            'path'               => '',
            'media_id'           => null,
        ];
    }

    /**
     * Products that have digital files (directly or through a variant), by name.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function productOptions(): array
    {
        return Product::query()
            ->where( static function ( Builder $owner ): void {
                $owner->whereIn( 'id', DigitalFile::query()->select( 'product_id' )->whereNotNull( 'product_id' ) )
                    ->orWhereIn( 'id', ProductVariant::query()->select( 'product_id' )->whereIn( 'id', DigitalFile::query()->select( 'product_variant_id' )->whereNotNull( 'product_variant_id' ) ) );
            } )
            ->orderBy( 'name' )
            ->get( [ 'id', 'name' ] )
            ->map( static fn ( Product $product ): array => [ 'id' => (string) $product->id, 'name' => (string) $product->name ] )
            ->all();
    }
}
