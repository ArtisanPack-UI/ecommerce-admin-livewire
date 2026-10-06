<?php

/**
 * Product create/edit form.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products;

use ArtisanPackUI\Ecommerce\Exceptions\ProductWriteException;
use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductImage;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Models\ProductRelation;
use ArtisanPackUI\Ecommerce\Models\TaxClass;
use ArtisanPackUI\Ecommerce\Registries\ProductTypeRegistry;
use ArtisanPackUI\Ecommerce\Services\ProductService;
use ArtisanPackUI\Ecommerce\Services\ProductTagService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithPickers;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\ProductTypePanel;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ProductTypePanelRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Html;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
use ArtisanPackUI\EcommerceAdminLivewire\Support\RowKeys;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StoreCurrencies;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The product form shared by create and edit (spec §7.2).
 *
 * Tabs: General, Pricing, Inventory, Shipping, Tax, Organization, Media,
 * the type panel from {@see ProductTypePanelRegistry}, and (when editing)
 * Activity. One Save validates every tab — including the type panel —
 * switches to the first tab with an error, and writes everything through
 * the engine's `ProductService` in one transaction.
 *
 * - The slug follows the name until it is edited.
 * - Pricing has one row per enabled currency (the base currency, the
 *   currencies the store converts to, and any the product already has),
 *   each with price, compare-at, and cost, plus scheduled rows.
 * - Stock on an existing product changes through an audited adjustment
 *   with a reason; a new product's quantity is its opening stock.
 * - Images come from the media library's modal when it is installed, or
 *   from URLs otherwise.
 * - The description is cleaned with `kses()` before it is saved.
 * - A product whose type is missing is shown read-only with the engine's
 *   warning.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Form extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithPickers;

    /**
     * The tabs, in order. The type panel and activity tabs are added when
     * they apply.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const TABS = [ 'general', 'pricing', 'inventory', 'shipping', 'tax', 'organization', 'linked', 'media', 'panel', 'activity' ];

    /**
     * The most products one relation list holds.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_RELATIONS = 50;

    /**
     * Media-library modal contexts.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const MEDIA_FEATURED = 'ecommerce-product-featured';

    /**
     * @since 1.0.0
     *
     * @var string
     */
    public const MEDIA_GALLERY = 'ecommerce-product-gallery';

    /**
     * Browser event dispatched after a successful save.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SAVED_EVENT = 'ecommerce-admin-product-form-saved';

    /**
     * Browser event dispatched when Save finds errors.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const INVALID_EVENT = 'ecommerce-admin-product-form-invalid';

    /**
     * The product being edited (null while creating).
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $productId = null;

    /**
     * Whether the product is read-only (missing type or no update ability).
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $readOnly = false;

    /**
     * The selected tab.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $tab = 'general';

    /**
     * @since 1.0.0
     *
     * @var string
     */
    public string $name = '';

    /**
     * @since 1.0.0
     *
     * @var string
     */
    public string $slug = '';

    /**
     * Whether the slug was edited by hand (it stops following the name).
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $slugEdited = false;

    /**
     * Product type (chosen on create only).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $type = 'simple';

    /**
     * @since 1.0.0
     *
     * @var string
     */
    public string $shortDescription = '';

    /**
     * @since 1.0.0
     *
     * @var string
     */
    public string $description = '';

    /**
     * @since 1.0.0
     *
     * @var string
     */
    public string $status = 'draft';

    /**
     * `Y-m-d\TH:i` or empty.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $publishedAt = '';

    /**
     * Price rows: `currency`, `price_amount`, `compare_at_amount`,
     * `cost_amount` (minor units), `starts_at`, `ends_at`, `scheduled`.
     *
     * @since 1.0.0
     *
     * @var array<int, array<string, mixed>>
     */
    public array $prices = [];

    /**
     * @since 1.0.0
     *
     * @var string
     */
    public string $sku = '';

    /**
     * @since 1.0.0
     *
     * @var string
     */
    public string $barcode = '';

    /**
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $trackInventory = true;

    /**
     * On-hand quantity (opening stock on create; target on edit).
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $quantity = 0;

    /**
     * The on-hand quantity when the form loaded.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $quantityLoaded = 0;

    /**
     * Why the stock changed (required when it did).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $stockReason = '';

    /**
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $allowBackorder = false;

    /**
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $lowStockThreshold = null;

    /**
     * @since 1.0.0
     *
     * @var float|int|string|null
     */
    public string|int|float|null $weight = null;

    /**
     * @since 1.0.0
     *
     * @var string
     */
    public string $weightUnit = '';

    /**
     * @since 1.0.0
     *
     * @var float|int|string|null
     */
    public string|int|float|null $length = null;

    /**
     * @since 1.0.0
     *
     * @var float|int|string|null
     */
    public string|int|float|null $width = null;

    /**
     * @since 1.0.0
     *
     * @var float|int|string|null
     */
    public string|int|float|null $height = null;

    /**
     * @since 1.0.0
     *
     * @var string
     */
    public string $dimUnit = '';

    /**
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $isTaxable = true;

    /**
     * @since 1.0.0
     *
     * @var string
     */
    public string $taxClassKey = '';

    /**
     * @since 1.0.0
     *
     * @var array<int, int|string>
     */
    public array $categoryIds = [];

    /**
     * Tag names (new names create tags on save).
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public array $tagNames = [];

    /**
     * Whether the product is featured.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $isFeatured = false;

    /**
     * The manual catalog order (lower first).
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $catalogPosition = 0;

    /**
     * Featured media-library item.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    public ?int $featuredMediaId = null;

    /**
     * Featured image URL (without the media library).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $featuredImageUrl = '';

    /**
     * Gallery rows: `id`, `media_id`, `image_url`, `alt_text`.
     *
     * @since 1.0.0
     *
     * @var array<int, array<string, mixed>>
     */
    public array $gallery = [];

    /**
     * The type panel's state (bound to the panel component).
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    public array $panelState = [];

    /**
     * Related product ids per relation type (`upsell`, `cross_sell`,
     * `related`), in order.
     *
     * @since 1.0.0
     *
     * @var array<string, array<int, int>>
     */
    public array $relations = [];

    /**
     * The product chosen in each relation list's "add" picker; it is moved
     * into the list right away. Empty is `''`, not null: the component
     * library's single-select choices read `selection.length` even when
     * hidden, which throws on null.
     *
     * @since 1.0.0
     *
     * @var array<string, int|string>
     */
    public array $relationPick = [];

    /**
     * The product, loaded once per request.
     *
     * @since 1.0.0
     *
     * @var Product|null
     */
    private ?Product $loadedProduct = null;

    /**
     * Service price-row index => form price-row index, for the last save.
     *
     * @since 1.0.0
     *
     * @var array<int, int>
     */
    private array $priceRowIndex = [];

    /**
     * Loads the product (or defaults) and authorizes.
     *
     * @since 1.0.0
     *
     * @param  int|Product|string|null  $product  Product or id; null to create.
     *
     * @return void
     */
    public function mount( Product|int|string|null $product = null ): void
    {
        if ( null === $product || '' === $product ) {
            $this->authorizeEcommerce( 'create', Product::class );
            $this->prices       = self::emptyPrices( null );
            $this->panelState   = $this->panelDefaults( $this->type, null );
            $this->relations    = self::emptyRelations();
            $this->relationPick = self::emptyRelationPicks();

            return;
        }

        $model = $product instanceof Product ? $product : Product::query()->findOrFail( (int) $product );

        $this->authorizeEcommerce( 'view', $model );

        $this->productId     = (int) $model->id;
        $this->loadedProduct = $model;
        $this->readOnly      = $this->productIsReadOnly( $model );

        $this->fillFromProduct( $model );
    }

    /**
     * Re-checks access on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $product = $this->product();

        null === $product
            ? $this->authorizeEcommerce( 'create', Product::class )
            : $this->authorizeEcommerce( 'view', $product );
    }

    /**
     * Fills the slug from the name until the slug is edited.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedName(): void
    {
        if ( ! $this->slugEdited ) {
            $this->slug = Str::slug( $this->name );
        }
    }

    /**
     * Stops the slug following the name once it is edited.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedSlug(): void
    {
        $this->slugEdited = '' !== trim( $this->slug );

        if ( ! $this->slugEdited ) {
            $this->slug = Str::slug( $this->name );
        }
    }

    /**
     * Resets the type panel when the type changes (create only).
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedType(): void
    {
        if ( null !== $this->productId ) {
            $this->type = (string) $this->product()?->type;

            return;
        }

        $this->panelState = $this->panelDefaults( $this->type, null );
    }

    /**
     * Adds a scheduled price row.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addScheduledPrice(): void
    {
        $this->assertWritable();

        $this->prices[] = self::priceRow( StoreCurrencies::base(), null, true );
    }

    /**
     * Removes a scheduled price row.
     *
     * @since 1.0.0
     *
     * @param  int  $index  Row index.
     *
     * @return void
     */
    public function removePrice( int $index ): void
    {
        $this->assertWritable();

        if ( true === ( $this->prices[ $index ]['scheduled'] ?? false ) ) {
            unset( $this->prices[ $index ] );
            $this->prices = array_values( $this->prices );
        }
    }

    /**
     * Adds an empty gallery row (URL mode).
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addGalleryUrl(): void
    {
        $this->assertWritable();

        $this->gallery[] = [ 'uid' => self::rowUid(), 'id' => null, 'media_id' => null, 'image_url' => '', 'alt_text' => '' ];
    }

    /**
     * Removes a gallery row.
     *
     * @since 1.0.0
     *
     * @param  int  $index  Row index.
     *
     * @return void
     */
    public function removeGalleryImage( int $index ): void
    {
        $this->assertWritable();

        unset( $this->gallery[ $index ] );
        $this->gallery = array_values( $this->gallery );
    }

    /**
     * Moves a gallery row up (-1) or down (+1).
     *
     * @since 1.0.0
     *
     * @param  int  $index      Row index.
     * @param  int  $direction  -1 or 1.
     *
     * @return void
     */
    public function moveGalleryImage( int $index, int $direction ): void
    {
        $this->assertWritable();

        $target = $index + ( $direction < 0 ? -1 : 1 );

        if ( ! isset( $this->gallery[ $index ], $this->gallery[ $target ] ) ) {
            return;
        }

        [ $this->gallery[ $index ], $this->gallery[ $target ] ] = [ $this->gallery[ $target ], $this->gallery[ $index ] ];
    }

    /**
     * Clears the featured image.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function clearFeaturedImage(): void
    {
        $this->assertWritable();

        $this->featuredMediaId  = null;
        $this->featuredImageUrl = '';
    }

    /**
     * Receives a media-library selection for the featured image or gallery.
     *
     * @since 1.0.0
     *
     * @param  array<int, array<string, mixed>>  $media    Selected media.
     * @param  string                            $context  Modal context.
     *
     * @return void
     */
    /**
     * Adds the product picked in a relation list's picker to the end of
     * that list. The product itself, a product already in the list, and a
     * full list are ignored.
     *
     * @since 1.0.0
     *
     * @param  mixed   $value  The picked product id.
     * @param  string  $type   The relation type (the property key).
     *
     * @return void
     */
    public function updatedRelationPick( mixed $value, string $type ): void
    {
        $this->authorizeRelationWrite();

        $this->relationPick = self::emptyRelationPicks();

        if ( ! in_array( $type, ProductRelation::TYPES, true ) || ! is_numeric( $value ) ) {
            return;
        }

        $id   = (int) $value;
        $list = $this->relationList( $type );

        if ( $id === $this->productId || in_array( $id, $list, true ) || count( $list ) >= self::MAX_RELATIONS || ! Product::query()->whereKey( $id )->exists() ) {
            return;
        }

        $this->relations[ $type ] = [ ...$list, $id ];
    }

    /**
     * Moves a related product up or down its list.
     *
     * @since 1.0.0
     *
     * @param  string  $type       The relation type.
     * @param  int     $index      The product's position.
     * @param  int     $direction  -1 up, 1 down.
     *
     * @return void
     */
    public function moveRelation( string $type, int $index, int $direction ): void
    {
        $this->authorizeRelationWrite();

        $list   = $this->relationList( $type );
        $target = $index + ( $direction < 0 ? -1 : 1 );

        if ( ! isset( $list[ $index ], $list[ $target ] ) ) {
            return;
        }

        [ $list[ $index ], $list[ $target ] ] = [ $list[ $target ], $list[ $index ] ];

        $this->relations[ $type ] = $list;
    }

    /**
     * Puts a relation list in the order a drag left it in. The ids must be
     * exactly the list's current ids.
     *
     * @since 1.0.0
     *
     * @param  string             $type  The relation type.
     * @param  array<int, mixed>  $ids   The product ids, in their new order.
     *
     * @return void
     */
    public function reorderRelations( string $type, array $ids ): void
    {
        $this->authorizeRelationWrite();

        $list = $this->relationList( $type );
        $ids  = array_map( 'intval', array_values( array_filter( $ids, 'is_numeric' ) ) );

        if ( count( $ids ) !== count( $list ) || [] !== array_diff( $list, $ids ) || count( array_unique( $ids ) ) !== count( $ids ) ) {
            return;
        }

        $this->relations[ $type ] = $ids;
    }

    /**
     * Removes a related product from its list.
     *
     * @since 1.0.0
     *
     * @param  string  $type   The relation type.
     * @param  int     $index  The product's position.
     *
     * @return void
     */
    public function removeRelation( string $type, int $index ): void
    {
        $this->authorizeRelationWrite();

        $list = $this->relationList( $type );

        unset( $list[ $index ] );

        $this->relations[ $type ] = array_values( $list );
        $this->resetErrorBag( 'relations.' . $type );
    }

    #[On( 'media-selected' )]
    public function mediaSelected( array $media = [], string $context = '' ): void
    {
        if ( $this->readOnly || ! in_array( $context, [ self::MEDIA_FEATURED, self::MEDIA_GALLERY ], true ) ) {
            return;
        }

        $items = array_values( array_filter( $media, static fn ( mixed $item ): bool => is_array( $item ) && isset( $item['id'] ) && is_numeric( $item['id'] ) ) );

        if ( self::MEDIA_FEATURED === $context ) {
            if ( [] !== $items ) {
                $this->featuredMediaId  = (int) $items[0]['id'];
                $this->featuredImageUrl = '';
            }

            return;
        }

        foreach ( $items as $item ) {
            $this->gallery[] = [
                'uid'       => self::rowUid(),
                'id'        => null,
                'media_id'  => (int) $item['id'],
                'image_url' => '',
                'alt_text'  => is_string( $item['alt_text'] ?? null ) ? $item['alt_text'] : '',
            ];
        }
    }

    /**
     * Validates every tab and saves the product.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function save(): void
    {
        $this->assertWritable();

        $product = $this->product();

        null === $product
            ? $this->authorizeEcommerce( 'create', Product::class )
            : $this->authorizeEcommerce( 'update', $product );

        $panelClass = $this->panelClass();

        try {
            $this->validateForm( $panelClass, $product );
        } catch ( ValidationException $exception ) {
            $this->failValidation( $exception->errors() );

            throw $exception;
        }

        try {
            $saved = DB::transaction( function () use ( $product, $panelClass ): Product {
                $service = app( ProductService::class );
                $data    = $this->productData( null === $product );
                $saved   = null === $product ? $service->create( $data ) : $service->update( $product, $data );

                if ( null !== $panelClass ) {
                    try {
                        $panelClass::save( $saved, $this->panelState );
                    } catch ( ProductWriteException $exception ) {
                        throw new ProductWriteException( array_map(
                            static fn ( array $error ): array => [ 'field' => 'panelState.' . ( $error['field'] ?? 'form' ) ] + $error,
                            $exception->errors,
                        ) );
                    }
                }

                return $saved;
            } );
        } catch ( ProductWriteException $exception ) {
            $this->reportWriteErrors( $exception );

            return;
        }

        if ( null === $product ) {
            $this->toastSuccess( __( 'Product created.' ) );
            $this->dispatch( self::SAVED_EVENT );

            $editRoute = AdminNav::ROUTE_PREFIX . 'products.edit';

            if ( Route::has( $editRoute ) ) {
                $this->redirectRoute( $editRoute, [ 'product' => $saved->id ] );
            }

            $this->productId     = (int) $saved->id;
            $this->loadedProduct = $saved;

            return;
        }

        $this->loadedProduct = $saved->fresh();
        $this->fillFromProduct( $this->loadedProduct );
        $this->stockReason = '';

        $this->dispatch( self::SAVED_EVENT );
        $this->dispatch( ProductTypePanel::SAVED_EVENT );
        $this->toastSuccess( __( 'Product saved.' ) );
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $product    = $this->product();
        $registry   = app( ProductTypePanelRegistry::class );
        $panelType  = null === $product ? $this->type : (string) $product->type;
        $panelClass = $this->panelClass();

        return view( 'ecommerce-admin::livewire.products.form', [
            'product'         => $product,
            'isCreate'        => null === $product,
            'typeOptions'     => self::typeOptions(),
            'statusOptions'   => self::options( Index::statuses() ),
            'taxClassOptions' => TaxClass::query()->orderBy( 'label' )->get( [ 'key', 'label' ] )->map( static fn ( TaxClass $class ): array => [ 'id' => (string) $class->key, 'name' => (string) $class->label ] )->all(),
            'weightUnits'     => self::options( [ 'g' => 'g', 'kg' => 'kg', 'oz' => 'oz', 'lb' => 'lb' ] ),
            'dimUnits'        => self::options( [ 'mm' => 'mm', 'cm' => 'cm', 'in' => 'in' ] ),
            'tracksStock'     => app( ProductTypeRegistry::class )->get( $panelType )->isInventoryTracked(),
            'mediaLibrary'    => ProductMedia::libraryInstalled(),
            'featuredPreview' => ProductMedia::mediaUrl( $this->featuredMediaId ) ?? ProductMedia::safeUrl( $this->featuredImageUrl ),
            'galleryPreviews' => array_map( static fn ( array $row ): ?string => ProductMedia::mediaUrl( isset( $row['media_id'] ) ? (int) $row['media_id'] : null ) ?? ProductMedia::safeUrl( $row['image_url'] ?? null ), $this->gallery ),
            'panelComponent'  => $registry->component( $panelType ),
            'panelLabel'      => null === $panelClass ? null : $panelClass::label(),
            'panelRegistered' => $registry->has( $panelType ),
            'panelErrors'     => $this->panelErrors(),
            'errorTabs'       => $this->errorTabs( array_keys( $this->getErrorBag()->toArray() ) ),
            'categoryOptions' => $this->optionsForPicker( 'category', 'categoryIds' ),
            'relationLists'   => $this->relationLists(),
            'canWrite'        => ! $this->readOnly && ( null === $product ? $this->canEcommerce( 'create', Product::class ) : $this->canEcommerce( 'update', $product ) ),
            'quantityDelta'   => null === $product || ! is_numeric( $this->quantity ) ? 0 : (int) $this->quantity - $this->quantityLoaded,
            'indexUrl'        => Route::has( AdminNav::ROUTE_PREFIX . 'products.index' ) ? route( AdminNav::ROUTE_PREFIX . 'products.index' ) : null,
        ] );
    }

    /**
     * The tab a field belongs to.
     *
     * @since 1.0.0
     *
     * @param  string  $field  Property path.
     *
     * @return string
     */
    public static function tabFor( string $field ): string
    {
        $root = Str::before( $field, '.' );

        return match ( $root ) {
            'prices'                                                                                             => 'pricing',
            'sku', 'barcode', 'trackInventory', 'quantity', 'stockReason', 'allowBackorder', 'lowStockThreshold' => 'inventory',
            'weight', 'weightUnit', 'length', 'width', 'height', 'dimUnit'                                       => 'shipping',
            'isTaxable', 'taxClassKey'                                                                           => 'tax',
            'categoryIds', 'tagNames', 'isFeatured', 'catalogPosition'                                           => 'organization',
            'relations'                                                                                          => 'linked',
            'featuredMediaId', 'featuredImageUrl', 'gallery'                                                     => 'media',
            'panelState'                                                                                         => 'panel',
            default                                                                                              => 'general',
        };
    }

    /**
     * A stable key for a new gallery row, so moving rows keeps their DOM
     * (and focus) instead of re-creating them.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected static function rowUid(): string
    {
        return 'n' . Str::lower( Str::random( 10 ) );
    }

    /**
     * Validates the form and the type panel together.
     *
     * @since 1.0.0
     *
     * @param  class-string<ProductTypePanel>|null  $panelClass  Panel class.
     * @param  Product|null                         $product     Product being edited.
     *
     * @throws ValidationException When anything is invalid.
     *
     * @return void
     */
    protected function validateForm( ?string $panelClass, ?Product $product ): void
    {
        $this->description = $this->cleanHtml( $this->description );

        $this->withValidator( function ( $validator ) use ( $panelClass, $product ): void {
            $validator->after( function ( $validator ) use ( $panelClass, $product ): void {
                if ( null === $panelClass ) {
                    return;
                }

                $panel = Validator::make( $this->panelState, $panelClass::rules( $this->panelState, $product ), [], $panelClass::validationAttributes() );

                foreach ( $panel->errors()->messages() as $field => $messages ) {
                    foreach ( $messages as $message ) {
                        $validator->errors()->add( 'panelState.' . $field, $message );
                    }
                }
            } );
        } )->validate(
            array_merge( $this->rules( $product ), $this->scheduledPriceRules(), $this->compareAtRules() ),
            [ 'prices.*.compare_at_amount.gt' => __( 'The compare-at price must be higher than the price.' ) ],
            $this->validationAttributes(),
        );
    }

    /**
     * Validation rules for the form's own fields.
     *
     * @since 1.0.0
     *
     * @param  Product|null  $product  Product being edited.
     *
     * @return array<string, mixed>
     */
    protected function rules( ?Product $product = null ): array
    {
        $amount = [ 'nullable', 'integer', 'min:0', 'max:999999999999999' ];
        $dim    = [ 'nullable', 'numeric', 'min:0', 'max:99999.999' ];

        return [
            'name'                         => [ 'required', 'string', 'max:255' ],
            'slug'                         => [ 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/' ],
            'type'                         => null === $product ? [ 'required', 'string', Rule::in( app( ProductTypeRegistry::class )->keys() ) ] : [ 'string' ],
            'shortDescription'             => [ 'nullable', 'string', 'max:5000' ],
            'description'                  => [ 'nullable', 'string', 'max:200000' ],
            'status'                       => [ 'required', Rule::in( array_keys( Index::statuses() ) ) ],
            'publishedAt'                  => [ 'nullable', 'date' ],
            'prices'                       => [ 'array' ],
            'prices.*.currency'            => [ 'required', 'string', 'size:3', 'alpha' ],
            'prices.*.price_amount'        => [ 'nullable', 'integer', 'min:0', 'max:999999999999999', 'required_if:prices.*.scheduled,true' ],
            'prices.*.compare_at_amount'   => $amount,
            'prices.*.cost_amount'         => $amount,
            'prices.*.starts_at'           => [ 'nullable', 'date' ],
            'prices.*.ends_at'             => [ 'nullable', 'date' ],
            'sku'                          => [ 'nullable', 'string', 'max:100' ],
            'barcode'                      => [ 'nullable', 'string', 'max:100' ],
            'trackInventory'               => [ 'boolean' ],
            'quantity'                     => [ 'nullable', 'integer', 'min:-1000000', 'max:1000000' ],
            'stockReason'                  => [ Rule::requiredIf( fn (): bool => $this->stockChanged() ), 'nullable', 'string', 'max:255' ],
            'allowBackorder'               => [ 'boolean' ],
            'lowStockThreshold'            => [ 'nullable', 'integer', 'min:0', 'max:1000000' ],
            'weight'                       => $dim,
            'weightUnit'                   => [ 'nullable', 'required_with:weight', Rule::in( [ 'g', 'kg', 'oz', 'lb' ] ) ],
            'length'                       => $dim,
            'width'                        => $dim,
            'height'                       => $dim,
            'dimUnit'                      => [ 'nullable', Rule::requiredIf( fn (): bool => $this->hasDimensions() ), Rule::in( [ 'mm', 'cm', 'in' ] ) ],
            'isTaxable'                    => [ 'boolean' ],
            'taxClassKey'                  => [ 'nullable', 'string', Rule::exists( TaxClass::class, 'key' ) ],
            'categoryIds'                  => [ 'array' ],
            'categoryIds.*'                => [ 'integer', Rule::exists( ProductCategory::class, 'id' ) ],
            'tagNames'                     => [ 'array', 'max:50' ],
            'tagNames.*'                   => [ 'string', 'max:120' ],
            'isFeatured'                   => [ 'boolean' ],
            'catalogPosition'              => [ 'nullable', 'integer', 'min:0', 'max:4294967295' ],
            'featuredMediaId'              => [ 'nullable', 'integer', 'min:1' ],
            'featuredImageUrl'             => [ 'nullable', 'string', 'max:1000', 'url:http,https' ],
            'gallery'                      => [ 'array', 'max:50' ],
            'gallery.*.media_id'           => [ 'nullable', 'integer', 'min:1' ],
            'gallery.*.image_url'          => [ 'nullable', 'string', 'max:1000', 'url:http,https', 'required_without:gallery.*.media_id' ],
            'gallery.*.alt_text'           => [ 'nullable', 'string', 'max:255' ],
            ...$this->relationRules(),
        ];
    }

    /**
     * Rules for the relation lists: known types only, capped, existing
     * products, no duplicates.
     *
     * @since 1.0.0
     *
     * @return array<string, array<int, mixed>>
     */
    protected function relationRules(): array
    {
        $rules = [ 'relations' => [ 'array', static function ( string $attribute, mixed $value, Closure $fail ): void {
            if ( [] !== array_diff( array_keys( (array) $value ), ProductRelation::TYPES ) ) {
                $fail( __( 'Unknown relation type.' ) );
            }
        } ] ];

        foreach ( ProductRelation::TYPES as $type ) {
            $rules[ 'relations.' . $type ]        = [ 'array', 'max:' . self::MAX_RELATIONS ];
            $rules[ 'relations.' . $type . '.*' ] = [ 'integer', 'distinct', Rule::exists( Product::class, 'id' ) ];
        }

        return $rules;
    }

    /**
     * Readable names for validation messages.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name'                       => __( 'name' ),
            'slug'                       => __( 'slug' ),
            'type'                       => __( 'type' ),
            'shortDescription'           => __( 'short description' ),
            'description'                => __( 'description' ),
            'status'                     => __( 'status' ),
            'publishedAt'                => __( 'publish date' ),
            'prices.*.currency'          => __( 'currency' ),
            'prices.*.price_amount'      => __( 'price' ),
            'prices.*.compare_at_amount' => __( 'compare-at price' ),
            'prices.*.cost_amount'       => __( 'cost' ),
            'prices.*.starts_at'         => __( 'start' ),
            'prices.*.ends_at'           => __( 'end' ),
            'sku'                        => __( 'SKU' ),
            'barcode'                    => __( 'barcode' ),
            'quantity'                   => __( 'quantity' ),
            'stockReason'                => __( 'reason' ),
            'lowStockThreshold'          => __( 'low-stock threshold' ),
            'weight'                     => __( 'weight' ),
            'weightUnit'                 => __( 'weight unit' ),
            'length'                     => __( 'length' ),
            'width'                      => __( 'width' ),
            'height'                     => __( 'height' ),
            'dimUnit'                    => __( 'dimension unit' ),
            'taxClassKey'                => __( 'tax class' ),
            'categoryIds.*'              => __( 'category' ),
            'tagNames.*'                 => __( 'tag' ),
            'catalogPosition'            => __( 'catalog position' ),
            'featuredImageUrl'           => __( 'featured image URL' ),
            'gallery.*.image_url'        => __( 'image URL' ),
            'gallery.*.alt_text'         => __( 'alt text' ),
            'relations.upsell'           => __( 'upsells' ),
            'relations.cross_sell'       => __( 'cross-sells' ),
            'relations.related'          => __( 'related products' ),
            'relations.upsell.*'         => __( 'upsell' ),
            'relations.cross_sell.*'     => __( 'cross-sell' ),
            'relations.related.*'        => __( 'related product' ),
        ];
    }

    /**
     * Whether an existing product's stock was edited (a reason is then
     * required).
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function stockChanged(): bool
    {
        return null !== $this->productId && $this->tracksStock() && is_numeric( $this->quantity ) && (int) $this->quantity !== $this->quantityLoaded;
    }

    /**
     * Whether the form's product type keeps stock of its own.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function tracksStock(): bool
    {
        $type = null === $this->productId ? $this->type : (string) $this->product()?->type;

        return app( ProductTypeRegistry::class )->get( $type )->isInventoryTracked();
    }

    /**
     * Rules for each scheduled price row: a start or an end, and an end
     * after the start.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    /**
     * A compare-at price must be above the row's price (rows without a
     * price are skipped).
     *
     * @since 1.0.0
     *
     * @return array<string, array<int, string>>
     */
    protected function compareAtRules(): array
    {
        $rules = [];

        foreach ( $this->prices as $index => $row ) {
            if ( ! is_numeric( $row['price_amount'] ?? null ) || ! is_numeric( $row['compare_at_amount'] ?? null ) ) {
                continue;
            }

            $rules[ "prices.{$index}.compare_at_amount" ] = [ 'nullable', 'integer', 'min:0', 'max:999999999999999', "gt:prices.{$index}.price_amount" ];
        }

        return $rules;
    }

    protected function scheduledPriceRules(): array
    {
        $rules = [];

        foreach ( $this->prices as $index => $row ) {
            if ( true !== ( $row['scheduled'] ?? false ) ) {
                continue;
            }

            $rules[ "prices.{$index}.starts_at" ] = [ 'nullable', 'date', "required_without:prices.{$index}.ends_at" ];

            if ( '' !== (string) ( $row['starts_at'] ?? '' ) ) {
                $rules[ "prices.{$index}.ends_at" ] = [ 'nullable', 'date', "after:prices.{$index}.starts_at" ];
            }
        }

        return $rules;
    }

    /**
     * The `ProductService` payload.
     *
     * @since 1.0.0
     *
     * @param  bool  $creating  Whether this is a new product.
     *
     * @return array<string, mixed>
     */
    protected function productData( bool $creating ): array
    {
        $data = [
            'name'                    => trim( $this->name ),
            'slug'                    => '' === trim( $this->slug ) ? null : $this->slug,
            'short_description'       => sanitizeText( $this->shortDescription ),
            'description'             => $this->cleanHtml( $this->description ),
            'status'                  => $this->status,
            'published_at'            => '' === $this->publishedAt ? null : Carbon::parse( $this->publishedAt ),
            'sku'                     => $this->sku,
            'barcode'                 => $this->barcode,
            'weight'                  => self::decimalOrNull( $this->weight ),
            'weight_unit'             => '' === $this->weightUnit ? null : $this->weightUnit,
            'length'                  => self::decimalOrNull( $this->length ),
            'width'                   => self::decimalOrNull( $this->width ),
            'height'                  => self::decimalOrNull( $this->height ),
            'dim_unit'                => '' === $this->dimUnit ? null : $this->dimUnit,
            'is_taxable'              => $this->isTaxable,
            'tax_class_key'           => '' === $this->taxClassKey ? null : $this->taxClassKey,
            'prices'                  => $this->priceRows(),
            'category_ids'            => array_map( 'intval', $this->categoryIds ),
            'tag_ids'                 => $this->tagIds(),
            'is_featured'             => $this->isFeatured,
            'position'                => is_numeric( $this->catalogPosition ) ? (int) $this->catalogPosition : 0,
            'featured_image_media_id' => $this->featuredMediaId,
            'featured_image_url'      => null === $this->featuredMediaId ? $this->featuredImageUrl : '',
            'relations'               => array_map(
                static fn ( array $ids ): array => array_map( 'intval', array_values( $ids ) ),
                array_intersect_key( $this->relations, array_flip( ProductRelation::TYPES ) ) + self::emptyRelations(),
            ),
            'images'                  => array_map( static fn ( array $row ): array => [
                'id'        => $row['id'] ?? null,
                'media_id'  => $row['media_id'] ?? null,
                'image_url' => $row['image_url'] ?? null,
                'alt_text'  => $row['alt_text'] ?? null,
            ], $this->gallery ),
        ];

        if ( null === $data['slug'] ) {
            unset( $data['slug'] );
        }

        // Types without stock of their own (digital, grouped) get no stock row.
        if ( ! $this->tracksStock() ) {
            if ( $creating ) {
                $data['type'] = $this->type;
            }

            return $data;
        }

        $data['inventory'] = [
            'track_inventory'     => $this->trackInventory,
            'allow_backorder'     => $this->allowBackorder,
            'low_stock_threshold' => '' === (string) $this->lowStockThreshold ? null : (int) $this->lowStockThreshold,
        ];

        if ( $creating ) {
            $data['type']                          = $this->type;
            $data['inventory']['quantity_on_hand'] = (int) ( $this->quantity ?? 0 );

            return $data;
        }

        $delta = is_numeric( $this->quantity ) ? (int) $this->quantity - $this->quantityLoaded : 0;

        if ( 0 !== $delta ) {
            $data['stock_adjustment'] = [ 'delta' => $delta, 'reason' => $this->stockReason ];
        }

        return $data;
    }

    /**
     * Non-empty price rows in the service's shape.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function priceRows(): array
    {
        $rows                = [];
        $this->priceRowIndex = [];

        foreach ( $this->prices as $index => $row ) {
            if ( null === ( $row['price_amount'] ?? null ) || '' === $row['price_amount'] ) {
                continue;
            }

            $this->priceRowIndex[ count( $rows ) ] = (int) $index;

            $scheduled = true === ( $row['scheduled'] ?? false );

            $rows[] = [
                'currency'          => strtoupper( (string) $row['currency'] ),
                'price_amount'      => (int) $row['price_amount'],
                'compare_at_amount' => self::intOrNull( $row['compare_at_amount'] ?? null ),
                'cost_amount'       => self::intOrNull( $row['cost_amount'] ?? null ),
                'starts_at'         => $scheduled && '' !== (string) ( $row['starts_at'] ?? '' ) ? Carbon::parse( $row['starts_at'] ) : null,
                'ends_at'           => $scheduled && '' !== (string) ( $row['ends_at'] ?? '' ) ? Carbon::parse( $row['ends_at'] ) : null,
            ];
        }

        return $rows;
    }

    /**
     * Tag ids for the tag names, creating new tags.
     *
     * @since 1.0.0
     *
     * @return array<int, int>
     */
    protected function tagIds(): array
    {
        $tags = app( ProductTagService::class );
        $ids  = [];

        foreach ( $this->tagNames as $name ) {
            $name = trim( sanitizeText( (string) $name ) );

            if ( '' !== $name ) {
                $ids[] = (int) $tags->findOrCreate( $name )->id;
            }
        }

        return array_values( array_unique( $ids ) );
    }

    /**
     * Puts `ProductService` errors on the matching fields and switches to the
     * first tab with an error.
     *
     * @since 1.0.0
     *
     * @param  ProductWriteException  $exception  Refusal.
     *
     * @return void
     */
    protected function reportWriteErrors( ProductWriteException $exception ): void
    {
        $fields = [];

        foreach ( $exception->errors as $error ) {
            $field = $this->formField( (string) ( $error['field'] ?? '' ) );

            $this->addError( $field, (string) $error['message'] );
            $fields[ $field ] = [ (string) $error['message'] ];
        }

        $this->failValidation( $fields );
        $this->toastError( __( 'The product was not saved.' ), (string) ( $exception->errors[0]['message'] ?? '' ) );
    }

    /**
     * Switches to the first tab with an error and asks the page to focus the
     * first invalid field (by its Livewire property path).
     *
     * @since 1.0.0
     *
     * @param  array<string, array<int, string>>  $errors  Errors by field.
     *
     * @return void
     */
    protected function failValidation( array $errors ): void
    {
        $tabs = $this->errorTabs( array_keys( $errors ) );

        if ( [] !== $tabs ) {
            $this->tab = $tabs[0];
        }

        $this->dispatch( self::INVALID_EVENT, tab: $this->tab, field: (string) array_key_first( $errors ) );
    }

    /**
     * Tabs that have errors, in tab order.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $fields  Field paths.
     *
     * @return array<int, string>
     */
    protected function errorTabs( array $fields ): array
    {
        $tabs = array_unique( array_map( static fn ( string $field ): string => self::tabFor( $field ), $fields ) );

        return array_values( array_intersect( self::TABS, $tabs ) );
    }

    /**
     * The form field for a `ProductService` error field.
     *
     * @since 1.0.0
     *
     * @param  string  $field  Service field (`prices.0.price_amount`, …).
     *
     * @return string
     */
    protected function formField( string $field ): string
    {
        // The service numbers only the non-empty price rows it was sent.
        if ( 1 === preg_match( '/^prices\.(\d+)\.(.+)$/', $field, $matches ) ) {
            return 'prices.' . ( $this->priceRowIndex[ (int) $matches[1] ] ?? (int) $matches[1] ) . '.' . $matches[2];
        }

        if ( str_starts_with( $field, 'panelState.' ) || str_starts_with( $field, 'prices.' ) || str_starts_with( $field, 'relations' ) ) {
            return $field;
        }

        if ( str_starts_with( $field, 'images.' ) ) {
            return 'gallery.' . Str::after( $field, 'images.' );
        }

        return match ( $field ) {
            'name'                          => 'name',
            'slug'                          => 'slug',
            'type'                          => 'type',
            'status'                        => 'status',
            'sku'                           => 'sku',
            'tax_class_key'                 => 'taxClassKey',
            'featured_image_url'            => 'featuredImageUrl',
            'category_ids'                  => 'categoryIds',
            'tag_ids'                       => 'tagNames',
            'is_featured'                   => 'isFeatured',
            'position'                      => 'catalogPosition',
            'stock_adjustment.reason'       => 'stockReason',
            'inventory.low_stock_threshold' => 'lowStockThreshold',
            default                         => 'name',
        };
    }

    /**
     * The type panel's errors, keyed by state path.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function panelErrors(): array
    {
        $errors = [];

        foreach ( $this->getErrorBag()->toArray() as $field => $messages ) {
            if ( str_starts_with( (string) $field, 'panelState.' ) ) {
                $errors[ Str::after( (string) $field, 'panelState.' ) ] = (string) ( $messages[0] ?? '' );
            }
        }

        return $errors;
    }

    /**
     * Copies a product into the form.
     *
     * @since 1.0.0
     *
     * @param  Product  $product  Product.
     *
     * @return void
     */
    protected function fillFromProduct( Product $product ): void
    {
        $stock = InventoryItem::query()
            ->where( 'stockable_type', $product->getMorphClass() )
            ->where( 'stockable_id', $product->id )
            ->where( 'warehouse_id', InventoryItem::DEFAULT_WAREHOUSE )
            ->first();

        $this->type              = (string) $product->type;
        $this->name              = (string) $product->name;
        $this->slug              = (string) $product->slug;
        $this->slugEdited        = true;
        $this->shortDescription  = (string) ( $product->short_description ?? '' );
        $this->description       = (string) ( $product->description ?? '' );
        $this->status            = (string) $product->status;
        $this->publishedAt       = $product->published_at?->format( 'Y-m-d\TH:i' ) ?? '';
        $this->prices            = self::existingPrices( $product );
        $this->sku               = (string) ( $product->sku ?? '' );
        $this->barcode           = (string) ( $product->barcode ?? '' );
        $this->trackInventory    = null === $stock ? true : (bool) $stock->track_inventory;
        $this->quantity          = null === $stock ? 0 : (int) $stock->quantity_on_hand;
        $this->quantityLoaded    = (int) $this->quantity;
        $this->allowBackorder    = null !== $stock && (bool) $stock->allow_backorder;
        $this->lowStockThreshold = $stock?->low_stock_threshold;
        $this->weight            = $product->weight;
        $this->weightUnit        = (string) ( $product->weight_unit ?? '' );
        $this->length            = $product->length;
        $this->width             = $product->width;
        $this->height            = $product->height;
        $this->dimUnit           = (string) ( $product->dim_unit ?? '' );
        $this->isTaxable         = (bool) $product->is_taxable;
        $this->taxClassKey       = (string) ( $product->tax_class_key ?? '' );
        $this->categoryIds       = $product->categories()->allRelatedIds()->map( static fn ( $id ): int => (int) $id )->all();
        $this->tagNames          = $product->tags()->orderBy( 'name' )->pluck( 'name' )->all();
        $this->isFeatured        = (bool) $product->is_featured;
        $this->catalogPosition   = (int) $product->position;
        $this->featuredMediaId   = $product->featured_image_media_id;
        $this->featuredImageUrl  = (string) ( $product->meta['featured_image_url'] ?? '' );
        $this->gallery           = $product->images()->get()->map( static fn ( ProductImage $image ): array => [
            'uid'       => 'i' . $image->id,
            'id'        => (int) $image->id,
            'media_id'  => $image->media_id,
            'image_url' => (string) ( $image->image_url ?? '' ),
            'alt_text'  => (string) ( $image->alt_text ?? '' ),
        ] )->all();
        $this->panelState        = $this->panelDefaults( $this->type, $product );
        $this->relations         = self::emptyRelations();
        $this->relationPick      = self::emptyRelationPicks();

        foreach ( $product->productRelations()->get( [ 'type', 'related_product_id', 'position' ] ) as $relation ) {
            if ( isset( $this->relations[ $relation->type ] ) ) {
                $this->relations[ $relation->type ][] = (int) $relation->related_product_id;
            }
        }
    }

    /**
     * Ids the related-product pickers must not offer: the product itself
     * and the products already in that list.
     *
     * @since 1.0.0
     *
     * @param  string  $type   The source key.
     * @param  string  $field  The property path.
     *
     * @return array<int, int>
     */
    protected function pickerExcludedIds( string $type, string $field ): array
    {
        if ( 'product' !== $type || ! str_starts_with( $field, 'relationPick.' ) ) {
            return [];
        }

        return array_values( array_filter( [ $this->productId, ...$this->relationList( Str::after( $field, 'relationPick.' ) ) ] ) );
    }

    /**
     * Each relation list with its label and products, the products loaded
     * in one query.
     *
     * @since 1.0.0
     *
     * @return array<string, array{type: string, label: string, hint: string, products: array<int, array{id: int, name: string, sku: string|null}>}>
     */
    protected function relationLists(): array
    {
        $ids      = array_merge( ...array_values( array_map( fn ( string $type ): array => $this->relationList( $type ), ProductRelation::TYPES ) ) );
        $products = [] === $ids ? collect() : Product::query()->whereKey( array_unique( $ids ) )->get( [ 'id', 'name', 'sku' ] )->keyBy( 'id' );
        $labels   = [
            ProductRelation::UPSELL     => [ __( 'Upsells' ), __( 'Pricier or better alternatives, offered on this product\'s page.' ) ],
            ProductRelation::CROSS_SELL => [ __( 'Cross-sells' ), __( 'Products that go with this one, offered in the cart.' ) ],
            ProductRelation::RELATED    => [ __( 'Related products' ), __( 'Similar products shown alongside this one.' ) ],
        ];
        $lists    = [];

        foreach ( ProductRelation::TYPES as $type ) {
            $lists[ $type ] = [
                'type'     => $type,
                'label'    => $labels[ $type ][0],
                'hint'     => $labels[ $type ][1],
                'products' => array_map( static fn ( int $id ): array => [
                    'id'   => $id,
                    'name' => (string) ( $products->get( $id )?->name ?? __( 'Product #:id', [ 'id' => $id ] ) ),
                    'sku'  => $products->get( $id )?->sku,
                ], $this->relationList( $type ) ),
            ];
        }

        return $lists;
    }

    /**
     * One relation list's ids, or an empty list for an unknown type.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The relation type.
     *
     * @return array<int, int>
     */
    protected function relationList( string $type ): array
    {
        if ( ! in_array( $type, ProductRelation::TYPES, true ) ) {
            return [];
        }

        return array_values( array_map( 'intval', array_filter( (array) ( $this->relations[ $type ] ?? [] ), 'is_numeric' ) ) );
    }

    /**
     * Allows a change to the relation lists: the form must be writable and
     * the user may create (new product) or update (existing) it.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeRelationWrite(): void
    {
        $this->assertWritable();

        $product = $this->product();

        null === $product
            ? $this->authorizeEcommerce( 'create', Product::class )
            : $this->authorizeEcommerce( 'update', $product );
    }

    /**
     * An empty list per relation type.
     *
     * @since 1.0.0
     *
     * @return array<string, array<int, int>>
     */
    protected static function emptyRelations(): array
    {
        return array_fill_keys( ProductRelation::TYPES, [] );
    }

    /**
     * The product, loaded once per request.
     *
     * @since 1.0.0
     *
     * @return Product|null
     */
    protected function product(): ?Product
    {
        if ( null === $this->productId ) {
            return null;
        }

        return $this->loadedProduct ??= Product::query()->findOrFail( $this->productId );
    }

    /**
     * The registered panel class for the form's type, or null.
     *
     * @since 1.0.0
     *
     * @return class-string<ProductTypePanel>|null
     */
    protected function panelClass(): ?string
    {
        $type = null === $this->productId ? $this->type : (string) $this->product()?->type;

        return app( ProductTypePanelRegistry::class )->panelClass( $type );
    }

    /**
     * Panel state for a type.
     *
     * @since 1.0.0
     *
     * @param  string        $type     Type key.
     * @param  Product|null  $product  Product.
     *
     * @return array<string, mixed>
     */
    protected function panelDefaults( string $type, ?Product $product ): array
    {
        $class = app( ProductTypePanelRegistry::class )->panelClass( $type );

        return null === $class ? [] : $class::initialState( $product );
    }

    /**
     * Refuses changes on a read-only form.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function assertWritable(): void
    {
        if ( $this->readOnly ) {
            $this->denyEcommerce();
        }
    }

    /**
     * Whether any dimension is filled in.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function hasDimensions(): bool
    {
        foreach ( [ $this->length, $this->width, $this->height ] as $value ) {
            if ( null !== $value && '' !== (string) $value ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rich text cleaned with `kses()` in safe mode ({@see Html::clean()}).
     *
     * @since 1.0.0
     *
     * @param  string  $html  HTML.
     *
     * @return string
     */
    protected function cleanHtml( string $html ): string
    {
        return Html::clean( $html );
    }

    /**
     * One empty base row per enabled currency.
     *
     * @since 1.0.0
     *
     * @param  Product|null  $product  Product (adds its currencies).
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function emptyPrices( ?Product $product ): array
    {
        return array_map( static fn ( string $currency ): array => self::priceRow( $currency, null, false ), StoreCurrencies::enabled( $product ) );
    }

    /**
     * The product's price rows: a base row per enabled currency (filled
     * where one exists) followed by its scheduled rows.
     *
     * @since 1.0.0
     *
     * @param  Product  $product  Product.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function existingPrices( Product $product ): array
    {
        $rows      = $product->prices()->orderBy( 'currency' )->orderBy( 'starts_at' )->get();
        $base      = [];
        $scheduled = [];

        foreach ( StoreCurrencies::enabled( $product ) as $currency ) {
            $base[ $currency ] = self::priceRow( $currency, $rows->first( static fn ( ProductPrice $row ): bool => $row->currency === $currency && null === $row->starts_at && null === $row->ends_at ), false );
        }

        foreach ( $rows as $row ) {
            if ( null !== $row->starts_at || null !== $row->ends_at ) {
                $scheduled[] = self::priceRow( (string) $row->currency, $row, true );
            }
        }

        return [ ...array_values( $base ), ...$scheduled ];
    }

    /**
     * Each relation list's empty "add" picker value.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected static function emptyRelationPicks(): array
    {
        return array_fill_keys( ProductRelation::TYPES, '' );
    }

    /**
     * One price row.
     *
     * @since 1.0.0
     *
     * @param  string             $currency   Currency.
     * @param  ProductPrice|null  $row        Stored row.
     * @param  bool               $scheduled  Scheduled row.
     *
     * @return array<string, mixed>
     */
    protected static function priceRow( string $currency, ?ProductPrice $row, bool $scheduled ): array
    {
        return [
            RowKeys::KEY        => RowKeys::make(),
            'currency'          => $currency,
            'price_amount'      => null === $row ? null : (int) $row->price_amount,
            'compare_at_amount' => null === $row?->compare_at_amount ? null : (int) $row->compare_at_amount,
            'cost_amount'       => null === $row?->cost_amount ? null : (int) $row->cost_amount,
            'starts_at'         => $row?->starts_at?->format( 'Y-m-d\TH:i' ) ?? '',
            'ends_at'           => $row?->ends_at?->format( 'Y-m-d\TH:i' ) ?? '',
            'scheduled'         => $scheduled,
        ];
    }

    /**
     * Registered product types as options.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected static function typeOptions(): array
    {
        $options = [];

        foreach ( app( ProductTypeRegistry::class )->all() as $key => $type ) {
            $options[ (string) $key ] = $type->label();
        }

        return self::options( $options );
    }

    /**
     * `id => name` pairs as select options.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $pairs  Pairs.
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected static function options( array $pairs ): array
    {
        return array_values( array_map(
            static fn ( string $id, string $name ): array => [ 'id' => $id, 'name' => $name ],
            array_map( 'strval', array_keys( $pairs ) ),
            $pairs,
        ) );
    }

    /**
     * A decimal input as a float, or null.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  Input.
     *
     * @return float|null
     */
    protected static function decimalOrNull( mixed $value ): ?float
    {
        return null === $value || '' === $value ? null : (float) $value;
    }

    /**
     * An integer input, or null.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  Input.
     *
     * @return int|null
     */
    protected static function intOrNull( mixed $value): ?int
    {
        return null === $value || '' === $value ? null : (int) $value;
    }
}
