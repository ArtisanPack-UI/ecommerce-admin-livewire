<?php

/**
 * Grouped / bundled product panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductChild;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Services\ProductService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithPickers;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The `grouped` and `bundled` type panel (spec §7.2): the products the
 * parent is made of, in order, each with a quantity and optionally one
 * variant.
 *
 * Rows are stored through `ProductService::syncChildren()` in the engine's
 * `product_children` table (engine spec §3.10a). The engine refuses a
 * product that contains itself or a product that already contains it
 * (directly or deeper), so the structure never loops; the panel also checks
 * self-reference and duplicates before saving.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class ChildrenPanel extends ProductTypePanel
{
    use WithPickers;

    /**
     * Most members per product.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_CHILDREN = 100;

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public static function label(): string
    {
        return __( 'Products included' );
    }

    /**
     * @since 1.0.0
     *
     * @param  Product|null  $product  Product.
     *
     * @return array<string, mixed>
     */
    public static function initialState( ?Product $product ): array
    {
        return [
            'children' => null === $product ? [] : ProductChild::query()
                ->where( 'parent_product_id', $product->id )
                ->orderBy( 'position' )
                ->orderBy( 'id' )
                ->get()
                ->map( static fn ( ProductChild $child ): array => [
                    'uid'        => 'c' . $child->id,
                    'product_id' => (int) $child->child_product_id,
                    'variant_id' => null === $child->child_variant_id ? '' : (string) $child->child_variant_id,
                    'quantity'   => (int) $child->quantity,
                ] )
                ->all(),
        ];
    }

    /**
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state    State.
     * @param  Product|null          $product  Product.
     *
     * @return array<string, mixed>
     */
    public static function rules( array $state, ?Product $product ): array
    {
        return [
            'children'              => [ 'array', 'max:' . self::MAX_CHILDREN, self::distinctChildren() ],
            'children.*.product_id' => [ 'required', 'integer', Rule::exists( Product::class, 'id' ), self::notSelf( $product ) ],
            'children.*.variant_id' => [ 'nullable', 'integer', Rule::exists( ProductVariant::class, 'id' ) ],
            'children.*.quantity'   => [ 'required', 'integer', 'min:1', 'max:1000' ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public static function validationAttributes(): array
    {
        return [
            'children.*.product_id' => __( 'product' ),
            'children.*.variant_id' => __( 'variant' ),
            'children.*.quantity'   => __( 'quantity' ),
        ];
    }

    /**
     * @since 1.0.0
     *
     * @param  Product               $product  Saved product.
     * @param  array<string, mixed>  $state    Validated state.
     *
     * @return void
     */
    public static function save( Product $product, array $state ): void
    {
        app( ProductService::class )->syncChildren( $product, array_map( static fn ( array $row ): array => [
            'product_id' => (int) $row['product_id'],
            'variant_id' => isset( $row['variant_id'] ) && '' !== $row['variant_id'] ? (int) $row['variant_id'] : null,
            'quantity'   => (int) $row['quantity'],
        ], array_values( (array) ( $state['children'] ?? [] ) ) ) );
    }

    /**
     * Adds an empty row.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addChild(): void
    {
        $this->assertWritable();

        $this->state['children'][] = [ 'uid' => 'n' . Str::lower( Str::random( 10 ) ), 'product_id' => null, 'variant_id' => '', 'quantity' => 1 ];
    }

    /**
     * Removes a row.
     *
     * @since 1.0.0
     *
     * @param  int  $index  Row index.
     *
     * @return void
     */
    public function removeChild( int $index ): void
    {
        $this->assertWritable();

        unset( $this->state['children'][ $index ] );
        $this->state['children'] = array_values( (array) ( $this->state['children'] ?? [] ) );
    }

    /**
     * Moves a row up (-1) or down (+1).
     *
     * @since 1.0.0
     *
     * @param  int  $index      Row index.
     * @param  int  $direction  -1 or 1.
     *
     * @return void
     */
    public function moveChild( int $index, int $direction ): void
    {
        $this->assertWritable();

        $rows   = array_values( (array) ( $this->state['children'] ?? [] ) );
        $target = $index + ( $direction < 0 ? -1 : 1 );

        if ( isset( $rows[ $index ], $rows[ $target ] ) ) {
            [ $rows[ $index ], $rows[ $target ] ] = [ $rows[ $target ], $rows[ $index ] ];
        }

        $this->state['children'] = $rows;
    }

    /**
     * Clears a row's variant when its product changes.
     *
     * @since 1.0.0
     *
     * @param  mixed   $value  New value.
     * @param  string|null  $key    Changed path (null when the whole state is set).
     *
     * @return void
     */
    public function updatedState( mixed $value, ?string $key = null ): void
    {
        if ( null !== $key && 1 === preg_match( '/^children\.(\d+)\.product_id$/', $key, $matches ) ) {
            $this->state['children'][ (int) $matches[1] ]['variant_id'] = '';
        }
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $rows       = array_values( (array) ( $this->state['children'] ?? [] ) );
        $productIds = array_filter( array_map( static fn ( array $row ): int => (int) ( $row['product_id'] ?? 0 ), $rows ) );
        $variants   = ProductVariant::query()
            ->whereIn( 'product_id', $productIds )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get( [ 'id', 'product_id', 'name', 'sku' ] )
            ->groupBy( 'product_id' );

        return view( 'ecommerce-admin::livewire.products.panels.children', [
            'pickerOptions'  => array_map( fn ( int $index ): array => $this->optionsForPicker( 'product', "state.children.{$index}.product_id" ), array_keys( $rows ) ),
            'variantOptions' => array_map( static fn ( array $row ): array => $variants->get( (int) ( $row['product_id'] ?? 0 ), collect() )->map( static fn ( ProductVariant $variant ): array => [
                'id'   => (string) $variant->id,
                'name' => (string) ( $variant->name ?: $variant->sku ?: __( 'Variant #:id', [ 'id' => $variant->id ] ) ),
            ] )->values()->all(), $rows ),
        ] );
    }

    /**
     * Rule: the same product and variant appear once.
     *
     * @since 1.0.0
     *
     * @return Closure
     */
    protected static function distinctChildren(): Closure
    {
        return static function ( string $attribute, mixed $value, Closure $fail ): void {
            $keys = array_map( static fn ( mixed $row ): string => (int) ( $row['product_id'] ?? 0 ) . ':' . (string) ( $row['variant_id'] ?? '' ), (array) $value );

            if ( count( $keys ) !== count( array_unique( $keys ) ) ) {
                $fail( __( 'A product is listed twice.' ) );
            }
        };
    }

    /**
     * Rule: a product can't contain itself.
     *
     * @since 1.0.0
     *
     * @param  Product|null  $product  Product being edited.
     *
     * @return Closure
     */
    protected static function notSelf( ?Product $product ): Closure
    {
        return static function ( string $attribute, mixed $value, Closure $fail ) use ( $product ): void {
            if ( null !== $product && (int) $value === (int) $product->id ) {
                $fail( __( 'A product can\'t contain itself.' ) );
            }
        };
    }
}
