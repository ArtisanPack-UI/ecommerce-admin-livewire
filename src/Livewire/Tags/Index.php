<?php

/**
 * Tags screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tags;

use ArtisanPackUI\Ecommerce\Exceptions\ProductWriteException;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Services\ProductTagService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithResourceTable;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\TagsQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The tags table (spec §7.2): name, slug, and product count, with inline
 * create, rename, and delete, and a merge bulk action.
 *
 * Merging moves every product of the selected tags to the chosen tag, then
 * deletes the selected tags (the engine's `ProductTagService::merge()`).
 * Deleting a row reuses the bulk delete, so it asks for confirmation and
 * carries a one-time action token.
 *
 * Tags have no policy of their own; the screen uses the product abilities
 * (spec §6).
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
     * The name of the tag being created.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $newTagName = '';

    /**
     * The tag being renamed.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $editingId = null;

    /**
     * The new name of the tag being renamed.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $editingName = '';

    /**
     * The new slug of the tag being renamed.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $editingSlug = '';

    /**
     * The tag the merge bulk action merges into.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $mergeTargetId = null;

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
     * Creates a tag from the inline form.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function createTag(): void
    {
        $this->authorizeEcommerce( 'create', Product::class );

        $this->validate(
            [ 'newTagName' => [ 'required', 'string', 'max:120' ] ],
            [],
            [ 'newTagName' => __( 'tag name' ) ],
        );

        try {
            $tag = app( ProductTagService::class )->create( [ 'name' => $this->newTagName ] );
        } catch ( ProductWriteException $exception ) {
            $this->addError( 'newTagName', (string) ( $exception->errors[0]['message'] ?? $exception->getMessage() ) );

            return;
        }

        $this->newTagName = '';
        $this->toastSuccess( __( 'Tag ":name" added.', [ 'name' => $tag->name ] ) );
    }

    /**
     * Starts renaming a tag in its row.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Tag id.
     *
     * @return void
     */
    public function startRename( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'product.update' );

        $tag = ProductTag::query()->find( $id );

        $this->resetErrorBag();
        $this->editingId   = $tag?->id;
        $this->editingName = (string) ( $tag->name ?? '' );
        $this->editingSlug = (string) ( $tag->slug ?? '' );
    }

    /**
     * Saves the rename.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveRename(): void
    {
        $this->authorizeEcommerceAbility( 'product.update' );

        $tag = null === $this->editingId ? null : ProductTag::query()->find( $this->editingId );

        if ( null === $tag ) {
            $this->cancelRename();

            return;
        }

        $this->validate(
            [
                'editingName' => [ 'required', 'string', 'max:120' ],
                'editingSlug' => [ 'required', 'string', 'max:120' ],
            ],
            [],
            [ 'editingName' => __( 'tag name' ), 'editingSlug' => __( 'slug' ) ],
        );

        try {
            app( ProductTagService::class )->update( $tag, [ 'name' => $this->editingName, 'slug' => $this->editingSlug ] );
        } catch ( ProductWriteException $exception ) {
            foreach ( $exception->errors as $error ) {
                $this->addError( 'slug' === ( $error['field'] ?? '' ) ? 'editingSlug' : 'editingName', (string) $error['message'] );
            }

            return;
        }

        $this->cancelRename();
        $this->toastSuccess( __( 'Tag renamed to ":name".', [ 'name' => $tag->name ] ) );
    }

    /**
     * Stops renaming.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelRename(): void
    {
        $this->editingId   = null;
        $this->editingName = '';
        $this->editingSlug = '';
    }

    /**
     * Asks to confirm deleting one tag (through the bulk delete).
     *
     * @since 1.0.0
     *
     * @param  int  $id  Tag id.
     *
     * @return void
     */
    public function confirmDelete( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'product.delete' );

        $this->selected          = [ $id ];
        $this->selectAllMatching = false;

        $this->runBulkAction( 'delete' );
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $data = $this->resourceTableData();

        return view( 'ecommerce-admin::livewire.tags.index', $data + [
            'canCreate'    => $this->canEcommerce( 'create', Product::class ),
            'canUpdate'    => Authorization::allows( auth()->user(), 'product.update' ),
            'canDelete'    => Authorization::allows( auth()->user(), 'product.delete' ),
            'mergeOptions' => $data['tableSelectionCount'] > 0 ? self::tagOptions() : [],
        ] );
    }

    /**
     * Merges the selected tags into the chosen tag.
     *
     * @since 1.0.0
     *
     * @param  Builder<ProductTag>  $selection  Selected tags.
     *
     * @return string|null
     */
    protected function mergeSelection( Builder $selection ): ?string
    {
        $this->validate(
            [ 'mergeTargetId' => [ 'required', 'integer', Rule::exists( ProductTag::class, 'id' ) ] ],
            [],
            [ 'mergeTargetId' => __( 'tag to merge into' ) ],
        );

        $target  = ProductTag::query()->findOrFail( (int) $this->mergeTargetId );
        $service = app( ProductTagService::class );
        $ids     = ( clone $selection )->reorder()->pluck( $selection->qualifyColumn( 'id' ) )->map( static fn ( mixed $id ): int => (int) $id )->all();
        $merged  = 0;

        DB::transaction( static function () use ( $ids, $target, $service, &$merged ): void {
            foreach ( ProductTag::query()->whereKey( $ids )->whereKeyNot( $target->id )->get() as $source ) {
                $service->merge( $source, $target );
                ++$merged;
            }
        } );

        $this->mergeTargetId = null;

        return trans_choice(
            'Merged :count tag into ":tag".|Merged :count tags into ":tag".',
            $merged,
            [ 'count' => $merged, 'tag' => $target->name ],
        );
    }

    /**
     * Deletes the selected tags. Their products stay; only the links go.
     *
     * @since 1.0.0
     *
     * @param  Builder<ProductTag>  $selection  Selected tags.
     *
     * @return string|null
     */
    protected function deleteSelection( Builder $selection ): ?string
    {
        $service = app( ProductTagService::class );
        $ids     = ( clone $selection )->reorder()->pluck( $selection->qualifyColumn( 'id' ) )->all();
        $deleted = 0;

        DB::transaction( static function () use ( $ids, $service, &$deleted ): void {
            foreach ( ProductTag::query()->whereKey( $ids )->get() as $tag ) {
                $service->delete( $tag );
                ++$deleted;
            }
        } );

        if ( null !== $this->editingId && in_array( $this->editingId, array_map( 'intval', $ids ), true ) ) {
            $this->cancelRename();
        }

        return trans_choice( ':count tag deleted.|:count tags deleted.', $deleted, [ 'count' => $deleted ] );
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
        return 'tags';
    }

    /**
     * @since 1.0.0
     *
     * @return ResourceQuery
     */
    protected function tableQuery(): ResourceQuery
    {
        return new TagsQuery();
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableCaption(): string
    {
        return __( 'Tags' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableColumns(): array
    {
        $cells = 'ecommerce-admin::livewire.tags.cells.';

        return [
            [
                'key'      => 'name',
                'label'    => __( 'Name' ),
                'sortable' => true,
                'view'     => $cells . 'name',
                'export'   => static fn ( ProductTag $tag ): string => (string) $tag->name,
            ],
            [
                'key'      => 'slug',
                'label'    => __( 'Slug' ),
                'sortable' => true,
            ],
            [
                'key'      => 'products',
                'label'    => __( 'Products' ),
                'sortable' => true,
                'class'    => 'text-end',
                'value'    => static fn ( ProductTag $tag ): string => (string) (int) $tag->products_count,
                'export'   => static fn ( ProductTag $tag ): int => (int) $tag->products_count,
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
        return [];
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
                'key'     => 'merge',
                'label'   => __( 'Merge into tag' ),
                'icon'    => 'o-arrows-pointing-in',
                'ability' => 'product.update',
                'handler' => fn ( Builder $selection ): ?string => $this->mergeSelection( $selection ),
            ],
            [
                'key'     => 'delete',
                'label'   => __( 'Delete' ),
                'icon'    => 'o-trash',
                'ability' => 'product.delete',
                'confirm' => __( 'Delete the selected tags? Products keep everything else; only these tags are removed from them.' ),
                'handler' => fn ( Builder $selection ): ?string => $this->deleteSelection( $selection ),
            ],
            $this->exportBulkAction(),
        ];
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
            ->get( [ 'id', 'name', 'slug' ] )
            ->map( static fn ( ProductTag $tag ): array => [ 'id' => (string) $tag->id, 'name' => $tag->name . ' (' . $tag->slug . ')' ] )
            ->all();
    }
}
