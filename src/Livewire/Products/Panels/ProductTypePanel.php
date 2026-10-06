<?php

/**
 * Base class for product-type panels.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * A tab of type-specific fields in the product form (spec §7.2, §8.5).
 *
 * The form mounts the panel registered for the product's type with
 * `wire:model="panelState"`, so the panel's {@see self::$state} is the
 * form's. On Save the form validates that state with {@see self::rules()}
 * and, in the same transaction as the product, persists it with
 * {@see self::save()}. Errors come back through {@see self::$panelErrors}
 * keyed by state path and are shown on the panel's fields.
 *
 * A panel extends this class and implements the four static methods; its
 * own actions (add a row, generate variants, …) only change `$state`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
abstract class ProductTypePanel extends Component
{
    use AuthorizesEcommerce;

    /**
     * Browser event the form dispatches after saving, so panels re-render
     * with the saved ids.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SAVED_EVENT = 'ecommerce-admin-product-saved';

    /**
     * The panel's state, bound to the form.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    #[Modelable]
    public array $state = [];

    /**
     * Validation errors from the form, keyed by state path.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    #[Reactive]
    public array $panelErrors = [];

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
     * Error keys last copied from {@see self::$panelErrors}, so they can be
     * replaced without clearing the panel's own action errors.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $appliedPanelErrors = [];

    /**
     * Whether the form is read-only.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $readOnly = false;

    /**
     * The product, loaded once per request.
     *
     * @since 1.0.0
     *
     * @var Product|null
     */
    private ?Product $loadedProduct = null;

    /**
     * Authorizes the panel like the form does.
     *
     * @since 1.0.0
     *
     * @param  int|null  $productId  Product being edited.
     * @param  bool      $readOnly   Read-only form.
     *
     * @return void
     */
    public function mount( ?int $productId = null, bool $readOnly = false ): void
    {
        $this->productId = $productId;
        $this->readOnly  = $readOnly;

        $this->authorizePanel();
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
        $this->authorizePanel();
    }

    /**
     * Re-renders after the form saves.
     *
     * @since 1.0.0
     *
     * @return void
     */
    #[On( self::SAVED_EVENT )]
    public function productSaved(): void
    {
        $this->resetErrorBag();
    }

    /**
     * Shows the form's errors on this panel's fields.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function rendering(): void
    {
        $bag = $this->getErrorBag();

        foreach ( $this->appliedPanelErrors as $key ) {
            $bag->forget( $key );
        }

        $applied = [];

        foreach ( $this->panelErrors as $path => $message ) {
            $this->addError( 'state.' . $path, $message );
            $applied[] = 'state.' . $path;
        }

        $this->appliedPanelErrors = $applied;
    }

    /**
     * The panel's state for a product (null while creating).
     *
     * @since 1.0.0
     *
     * @param  Product|null  $product  Product.
     *
     * @return array<string, mixed>
     */
    abstract public static function initialState( ?Product $product ): array;

    /**
     * Validation rules for the state, keyed by state path.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state    State.
     * @param  Product|null          $product  Product being edited.
     *
     * @return array<string, mixed>
     */
    abstract public static function rules( array $state, ?Product $product ): array;

    /**
     * Persists the state for a saved product. Runs inside the form's
     * transaction; throw (e.g. ProductWriteException) to roll back.
     *
     * @since 1.0.0
     *
     * @param  Product               $product  Saved product.
     * @param  array<string, mixed>  $state    Validated state.
     *
     * @return void
     */
    abstract public static function save( Product $product, array $state ): void;

    /**
     * Readable names for validation messages, keyed by state path.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public static function validationAttributes(): array
    {
        return [];
    }

    /**
     * The tab label.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function label(): string
    {
        return __( 'Type settings' );
    }

    /**
     * The product being edited, or null.
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

        return $this->loadedProduct ??= Product::query()->find( $this->productId );
    }

    /**
     * View for an existing product, create for a new one.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizePanel(): void
    {
        $product = $this->product();

        null === $product
            ? $this->authorizeEcommerce( 'create', Product::class )
            : $this->authorizeEcommerce( 'view', $product );
    }

    /**
     * Refuses a state change on a read-only form.
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
}
