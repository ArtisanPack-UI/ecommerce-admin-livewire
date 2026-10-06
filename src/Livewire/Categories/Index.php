<?php

/**
 * Category tree screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Categories;

use ArtisanPackUI\Ecommerce\Exceptions\ProductWriteException;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Services\ProductCategoryService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The category tree (spec §7.2): create, edit (name, slug, parent,
 * description, image, icon), delete, and reorder within a parent, with
 * each category's product count.
 *
 * - Writes go through the engine's `ProductCategoryService`, which refuses a
 *   parent that is the category itself or one of its descendants; the parent
 *   choices leave those out too.
 * - Deleting a category with subcategories asks where they go: up to its own
 *   parent (the engine's default), or under another category. The delete is
 *   blocked until a choice is made, and carries a one-time action token.
 * - Reordering is "move up" / "move down", so it works by keyboard.
 *
 * Categories have no policy of their own; the screen uses the product
 * abilities (spec §6).
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

    /**
     * Media-library context for the category image.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const MEDIA_CONTEXT = 'ecommerce-category-image';

    /**
     * Whether the edit drawer is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $editing = false;

    /**
     * The category being edited, or null when creating.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $categoryId = null;

    /**
     * The form fields.
     *
     * @since 1.0.0
     *
     * @var array{name: string, slug: string, parent_id: int|string|null, description: string, icon: string, image_media_id: int|string|null}
     */
    public array $form = [
        'name'           => '',
        'slug'           => '',
        'parent_id'      => null,
        'description'    => '',
        'icon'           => '',
        'image_media_id' => null,
    ];

    /**
     * The category waiting for delete confirmation.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $deletingId = null;

    /**
     * Whether the delete confirmation is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $confirmingDelete = false;

    /**
     * Where a deleted category's subcategories go: `parent` or `other`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $childrenTarget = 'parent';

    /**
     * The category subcategories move under when `childrenTarget` is `other`.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $childrenTargetId = null;

    /**
     * Authorizes the screen.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeEcommerce( 'viewAny', Product::class );
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
        $this->authorizeEcommerce( 'viewAny', Product::class );
    }

    /**
     * Opens the drawer for a new category, optionally under a parent.
     *
     * @since 1.0.0
     *
     * @param  int|null  $parentId  Parent category.
     *
     * @return void
     */
    public function create( ?int $parentId = null ): void
    {
        $this->authorizeEcommerce( 'create', Product::class );

        $this->resetErrorBag();
        $this->categoryId = null;
        $this->form       = [
            'name'           => '',
            'slug'           => '',
            'parent_id'      => null !== $parentId && ProductCategory::query()->whereKey( $parentId )->exists() ? $parentId : null,
            'description'    => '',
            'icon'           => '',
            'image_media_id' => null,
        ];
        $this->editing    = true;
    }

    /**
     * Opens the drawer for an existing category.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Category id.
     *
     * @return void
     */
    public function edit( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'product.update' );

        $category = ProductCategory::query()->find( $id );

        if ( null === $category ) {
            return;
        }

        $this->resetErrorBag();
        $this->categoryId = (int) $category->id;
        $this->form       = [
            'name'           => (string) $category->name,
            'slug'           => (string) $category->slug,
            'parent_id'      => $category->parent_id,
            'description'    => (string) ( $category->description ?? '' ),
            'icon'           => (string) ( $category->icon ?? '' ),
            'image_media_id' => $category->image_media_id,
        ];
        $this->editing    = true;
    }

    /**
     * Saves the drawer.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function save(): void
    {
        $category = null === $this->categoryId ? null : ProductCategory::query()->find( $this->categoryId );

        if ( null === $category ) {
            $this->authorizeEcommerce( 'create', Product::class );
        } else {
            $this->authorizeEcommerceAbility( 'product.update' );
        }

        $this->validate(
            [
                'form.name'           => [ 'required', 'string', 'max:255' ],
                'form.slug'           => [ 'nullable', 'string', 'max:255' ],
                'form.parent_id'      => [ 'nullable', 'integer', Rule::exists( ProductCategory::class, 'id' ) ],
                'form.description'    => [ 'nullable', 'string', 'max:5000' ],
                'form.icon'           => [ 'nullable', 'string', 'max:80' ],
                'form.image_media_id' => [ 'nullable', 'integer', 'min:1' ],
            ],
            [],
            [
                'form.name'           => __( 'name' ),
                'form.slug'           => __( 'slug' ),
                'form.parent_id'      => __( 'parent' ),
                'form.description'    => __( 'description' ),
                'form.icon'           => __( 'icon' ),
                'form.image_media_id' => __( 'image' ),
            ],
        );

        $data = [
            'name'           => (string) $this->form['name'],
            'parent_id'      => self::idOrNull( $this->form['parent_id'] ),
            'description'    => (string) $this->form['description'],
            'icon'           => (string) $this->form['icon'],
            'image_media_id' => self::idOrNull( $this->form['image_media_id'] ),
        ];

        if ( '' !== trim( (string) $this->form['slug'] ) || null !== $category ) {
            $data['slug'] = (string) $this->form['slug'];
        }

        $service = app( ProductCategoryService::class );

        try {
            if ( null === $category ) {
                $category = $service->create( $data );
            } else {
                $previousParent = $category->parent_id;
                $moved          = $previousParent !== $data['parent_id'];

                if ( $moved ) {
                    // Moving to a new parent puts the category at the end of its new siblings.
                    $data['position'] = (int) ProductCategory::query()->where( 'parent_id', $data['parent_id'] )->max( 'position' ) + 1;
                }

                $service->update( $category, $data );
            }
        } catch ( ProductWriteException $exception ) {
            foreach ( $exception->errors as $error ) {
                $this->addError( 'form.' . ( $error['field'] ?? 'name' ), (string) $error['message'] );
            }

            return;
        }

        $this->editing = false;
        $this->toastSuccess( __( 'Category ":name" saved.', [ 'name' => $category->name ] ) );
    }

    /**
     * Moves a category one place up or down among its siblings.
     *
     * @since 1.0.0
     *
     * @param  int  $id      Category id.
     * @param  int  $offset  -1 for up, 1 for down.
     *
     * @return void
     */
    public function move( int $id, int $offset ): void
    {
        $this->authorizeEcommerceAbility( 'product.update' );

        $category = ProductCategory::query()->find( $id );

        if ( null === $category || ! in_array( $offset, [ -1, 1 ], true ) ) {
            return;
        }

        $siblings = ProductCategory::query()
            ->where( 'parent_id', $category->parent_id )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->pluck( 'id' )
            ->map( static fn ( mixed $sibling ): int => (int) $sibling )
            ->all();

        $from = array_search( (int) $category->id, $siblings, true );
        $to   = false === $from ? false : $from + $offset;

        if ( false === $to || $to < 0 || $to >= count( $siblings ) ) {
            return;
        }

        [ $siblings[ $from ], $siblings[ $to ] ] = [ $siblings[ $to ], $siblings[ $from ] ];

        app( ProductCategoryService::class )->reorder( $category->parent_id, $siblings );
    }

    /**
     * Asks to confirm deleting a category.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Category id.
     *
     * @return void
     */
    public function confirmDelete( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'product.delete' );

        $this->deletingId       = ProductCategory::query()->whereKey( $id )->exists() ? $id : null;
        $this->confirmingDelete = null !== $this->deletingId;
        $this->childrenTarget   = 'parent';
        $this->childrenTargetId = null;
        $this->resetErrorBag();
    }

    /**
     * Dismisses the delete confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelDelete(): void
    {
        $this->deletingId       = null;
        $this->confirmingDelete = false;
    }

    /**
     * Deletes the category waiting for confirmation, once per token.
     *
     * Its subcategories move to the chosen target first; its products are
     * unlinked (they stay in the catalog).
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the confirmation.
     *
     * @return void
     */
    public function delete( string $token ): void
    {
        $this->authorizeEcommerceAbility( 'product.delete' );

        $category = null === $this->deletingId || ! $this->confirmingDelete ? null : ProductCategory::query()->find( $this->deletingId );

        if ( null === $category ) {
            $this->cancelDelete();

            return;
        }

        $hasChildren = ProductCategory::query()->where( 'parent_id', $category->id )->exists();

        if ( $hasChildren ) {
            $this->validate(
                [
                    'childrenTarget'   => [ 'required', Rule::in( [ 'parent', 'other' ] ) ],
                    'childrenTargetId' => [
                        'nullable',
                        'required_if:childrenTarget,other',
                        'integer',
                        Rule::exists( ProductCategory::class, 'id' ),
                        Rule::notIn( self::subtreeIds( (int) $category->id ) ),
                    ],
                ],
                [ 'childrenTargetId.not_in' => __( 'Choose a category outside the one being deleted.' ) ],
                [ 'childrenTargetId' => __( 'new parent' ) ],
            );
        }

        $name = (string) $category->name;

        try {
            $deleted = $this->withActionToken( $token, 'delete', fn (): bool => DB::transaction( function () use ( $category, $hasChildren ): bool {
                if ( $hasChildren && 'other' === $this->childrenTarget ) {
                    $service = app( ProductCategoryService::class );
                    $target  = (int) $this->childrenTargetId;
                    $next    = (int) ProductCategory::query()->where( 'parent_id', $target )->max( 'position' ) + 1;

                    foreach ( ProductCategory::query()->where( 'parent_id', $category->id )->orderBy( 'position' )->orderBy( 'id' )->get() as $child ) {
                        $service->update( $child, [ 'parent_id' => $target, 'position' => $next++ ] );
                    }
                }

                app( ProductCategoryService::class )->delete( $category );

                return true;
            } ), $category );
        } catch ( ProductWriteException $exception ) {
            // The transaction rolled back the child moves and the delete.
            $this->cancelDelete();
            $this->toastError( __( 'The category was not deleted.' ), (string) ( $exception->errors[0]['message'] ?? $exception->getMessage() ) );

            return;
        }

        $this->cancelDelete();

        if ( true === $deleted ) {
            $this->toastSuccess( __( 'Category ":name" deleted.', [ 'name' => $name ] ) );
        }
    }

    /**
     * Receives the category image from the media library.
     *
     * @since 1.0.0
     *
     * @param  array<int, array<string, mixed>>  $media    Selected media.
     * @param  string                            $context  Modal context.
     *
     * @return void
     */
    #[On( 'media-selected' )]
    public function mediaSelected( array $media = [], string $context = '' ): void
    {
        if ( self::MEDIA_CONTEXT !== $context || ! $this->editing ) {
            return;
        }

        $item = $media[0] ?? null;

        if ( is_array( $item ) && is_numeric( $item['id'] ?? null ) ) {
            $this->form['image_media_id'] = (int) $item['id'];
        }
    }

    /**
     * Removes the category image from the form.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function clearImage(): void
    {
        $this->form['image_media_id'] = null;
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $categories = ProductCategory::query()
            ->withCount( 'products' )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get( [ 'id', 'parent_id', 'name', 'slug', 'icon', 'image_media_id', 'position' ] );

        $byParent = $categories->groupBy( static fn ( ProductCategory $category ): string => (string) ( $category->parent_id ?? 0 ) );
        $deleting = null === $this->deletingId || ! $this->confirmingDelete ? null : $categories->firstWhere( 'id', $this->deletingId );
        $excluded = null === $this->categoryId ? [] : self::subtreeIds( $this->categoryId );

        return view( 'ecommerce-admin::livewire.categories.index', [
            'tree'             => self::flatten( $byParent, '0', 0 ),
            'total'            => $categories->count(),
            'canCreate'        => $this->canEcommerce( 'create', Product::class ),
            'canUpdate'        => $this->canAbility( 'product.update' ),
            'canDelete'        => $this->canAbility( 'product.delete' ),
            'parentOptions'    => self::options( $byParent, $excluded ),
            'deleting'         => $deleting,
            'deletingChildren' => null === $deleting ? 0 : count( $byParent->get( (string) $deleting->id, [] ) ),
            'deletingParent'   => null === $deleting || null === $deleting->parent_id ? null : $categories->firstWhere( 'id', $deleting->parent_id ),
            'targetOptions'    => null === $deleting ? [] : self::options( $byParent, self::subtreeIds( (int) $deleting->id ) ),
            'deleteToken'      => null === $deleting ? null : $this->actionToken( 'delete', $deleting ),
            'mediaLibrary'     => ProductMedia::libraryInstalled(),
            'imagePreview'     => ProductMedia::mediaUrl( self::idOrNull( $this->form['image_media_id'] ) ),
        ] );
    }

    /**
     * The category and every category below it.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Category id.
     *
     * @return array<int, int>
     */
    public static function subtreeIds( int $id ): array
    {
        $parents = DB::table( ( new ProductCategory() )->getTable() )->pluck( 'parent_id', 'id' );
        $ids     = [ $id ];

        for ( $i = 0; $i < count( $ids ); $i++ ) {
            foreach ( $parents as $child => $parent ) {
                if ( (int) $parent === $ids[ $i ] && ! in_array( (int) $child, $ids, true ) ) {
                    $ids[] = (int) $child;
                }
            }
        }

        return $ids;
    }

    /**
     * Whether the user holds an ability, for showing controls.
     *
     * @since 1.0.0
     *
     * @param  string  $ability  `{resource}.{action}` ability.
     *
     * @return bool
     */
    protected function canAbility( string $ability ): bool
    {
        return Authorization::allows( auth()->user(), $ability );
    }

    /**
     * The tree as a flat, depth-annotated list in display order.
     *
     * @since 1.0.0
     *
     * @param  Collection<string, Collection<int, ProductCategory>>  $byParent  Categories grouped by parent id (`0` for top level).
     * @param  string                                                                                       $parent    Parent key.
     * @param  int                                                                                          $depth     Depth of the parent's children.
     *
     * @return array<int, array{category: ProductCategory, depth: int, first: bool, last: bool, children: int}>
     */
    private static function flatten( Collection $byParent, string $parent, int $depth ): array
    {
        $rows     = [];
        $siblings = $byParent->get( $parent, collect() )->values();
        $count    = $siblings->count();

        foreach ( $siblings as $index => $category ) {
            $children = count( $byParent->get( (string) $category->id, [] ) );
            $rows[]   = [
                'category' => $category,
                'depth'    => $depth,
                'first'    => 0 === $index,
                'last'     => $count - 1 === $index,
                'children' => $children,
            ];

            if ( $depth < 50 ) {
                array_push( $rows, ...self::flatten( $byParent, (string) $category->id, $depth + 1 ) );
            }
        }

        return $rows;
    }

    /**
     * Select options for a parent choice: every category in tree order with
     * its depth shown, minus `$excluded`.
     *
     * @since 1.0.0
     *
     * @param  Collection<string, Collection<int, ProductCategory>>  $byParent  Categories grouped by parent id.
     * @param  array<int, int>                                                                              $excluded  Ids to leave out.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private static function options( Collection $byParent, array $excluded ): array
    {
        $options = [];

        foreach ( self::flatten( $byParent, '0', 0 ) as $row ) {
            if ( ! in_array( (int) $row['category']->id, $excluded, true ) ) {
                $options[] = [
                    'id'   => (int) $row['category']->id,
                    'name' => str_repeat( '— ', $row['depth'] ) . $row['category']->name,
                ];
            }
        }

        return $options;
    }

    /**
     * A positive id, or null.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  Input.
     *
     * @return int|null
     */
    private static function idOrNull( mixed $value ): ?int
    {
        return is_numeric( $value ) && (int) $value > 0 ? (int) $value : null;
    }
}
