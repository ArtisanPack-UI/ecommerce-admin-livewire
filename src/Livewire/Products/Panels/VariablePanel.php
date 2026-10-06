<?php

/**
 * Variable product panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels;

use ArtisanPackUI\Ecommerce\Exceptions\ProductWriteException;
use ArtisanPackUI\Ecommerce\Models\InventoryItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductAttribute;
use ArtisanPackUI\Ecommerce\Models\ProductAttributeValue;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Services\ProductService;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StoreCurrencies;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;

/**
 * The `variable` type panel (spec §7.2): attributes, the variant generator,
 * and the variants table.
 *
 * - Attributes have a key, label, ordered values (each with an optional
 *   swatch), and a "used for variations" flag. Rows carry a client id
 *   (`uid`) so variants keep pointing at the right values while attributes
 *   are renamed or reordered before saving.
 * - "Generate variants" adds a row for every combination of the variation
 *   attributes' values that has none yet; above
 *   {@see self::CONFIRM_ABOVE} new variants it asks first.
 * - Each variant has an SKU, a price per enabled currency, stock, an image,
 *   and a position. Stock changes on saved variants need a reason.
 *
 * On save, attributes go through `ProductService::syncAttributes()` and each
 * variant through `createVariant()` / `updateVariant()`; variants removed
 * from the table are deleted.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class VariablePanel extends ProductTypePanel
{
    /**
     * Generating more new variants than this asks for confirmation.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const CONFIRM_ABOVE = 50;

    /**
     * Most variants the table holds.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_VARIANTS = ProductService::MAX_GENERATED_VARIANTS;

    /**
     * Media-library context prefix for variant images.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const MEDIA_CONTEXT = 'ecommerce-variant-image-';

    /**
     * New variants waiting for confirmation (0 when not asking).
     *
     * @since 1.0.0
     *
     * @var int
     */
    public int $pendingGenerate = 0;

    /**
     * Enabled currencies, cached per request.
     *
     * @since 1.0.0
     *
     * @var array<int, string>|null
     */
    private ?array $currencies = null;

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public static function label(): string
    {
        return __( 'Variants' );
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
        if ( null === $product ) {
            return [ 'attributes' => [], 'variants' => [], 'stock_reason' => '' ];
        }

        $attributes = $product->productAttributes()
            ->with( [ 'values' => static fn ( $query ) => $query->orderBy( 'position' )->orderBy( 'id' ) ] )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get();

        $currencies = StoreCurrencies::enabled( $product );
        $variants   = $product->variants()
            ->with( [ 'optionValues', 'prices' => static fn ( $query ) => $query->whereNull( 'starts_at' )->whereNull( 'ends_at' ) ] )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get();

        $stock = InventoryItem::query()
            ->where( 'stockable_type', ( new ProductVariant() )->getMorphClass() )
            ->whereIn( 'stockable_id', $variants->pluck( 'id' ) )
            ->where( 'warehouse_id', InventoryItem::DEFAULT_WAREHOUSE )
            ->get()
            ->keyBy( 'stockable_id' );

        return [
            'attributes'   => $attributes->map( static fn ( ProductAttribute $attribute ): array => [
                'uid'          => 'a' . $attribute->id,
                'id'           => (int) $attribute->id,
                'key'          => (string) $attribute->key,
                'label'        => (string) $attribute->label,
                'is_variation' => (bool) $attribute->is_variation,
                'values'       => $attribute->values->map( static fn ( ProductAttributeValue $value ): array => [
                    'uid'    => 'v' . $value->id,
                    'id'     => (int) $value->id,
                    'value'  => (string) $value->value,
                    'label'  => (string) $value->label,
                    'swatch' => (string) ( $value->swatch ?? '' ),
                ] )->all(),
            ] )->all(),
            'variants'     => $variants->map( static function ( ProductVariant $variant ) use ( $currencies, $stock ): array {
                $prices = [];

                foreach ( $currencies as $currency ) {
                    $row                 = $variant->prices->firstWhere( 'currency', $currency );
                    $prices[ $currency ] = null === $row ? null : (int) $row->price_amount;
                }

                $item = $stock->get( $variant->id );

                return [
                    'id'             => (int) $variant->id,
                    'name'           => (string) ( $variant->name ?? '' ),
                    'sku'            => (string) ( $variant->sku ?? '' ),
                    'options'        => $variant->optionValues->mapWithKeys( static fn ( $option ): array => [ 'a' . $option->product_attribute_id => 'v' . $option->product_attribute_value_id ] )->all(),
                    'prices'         => $prices,
                    'stock'          => null === $item ? 0 : (int) $item->quantity_on_hand,
                    'stock_loaded'   => null === $item ? 0 : (int) $item->quantity_on_hand,
                    'image_media_id' => $variant->image_media_id,
                    'image_url'      => (string) ( $variant->meta['image_url'] ?? '' ),
                ];
            } )->all(),
            'stock_reason' => '',
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
            'attributes'                   => [ 'array', 'max:20', self::distinctKeys() ],
            'attributes.*.label'           => [ 'required', 'string', 'max:120' ],
            'attributes.*.key'             => [ 'nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9_\- ]*$/' ],
            'attributes.*.is_variation'    => [ 'boolean' ],
            'attributes.*.values'          => [ 'array', 'max:100' ],
            'attributes.*.values.*.label'  => [ 'required', 'string', 'max:120' ],
            'attributes.*.values.*.swatch' => [ 'nullable', 'string', 'max:60', 'regex:/^(#[0-9a-fA-F]{3,8}|[a-zA-Z]+|[A-Za-z0-9_\-\/\.]+)$/' ],
            'variants'                     => [ 'array', 'max:' . self::MAX_VARIANTS, self::distinctSkus() ],
            'variants.*.name'              => [ 'nullable', 'string', 'max:255' ],
            'variants.*.sku'               => [ 'nullable', 'string', 'max:100' ],
            'variants.*.prices.*'          => [ 'nullable', 'integer', 'min:0', 'max:999999999999999' ],
            'variants.*.stock'             => [ 'nullable', 'integer', 'min:-1000000', 'max:1000000' ],
            'variants.*.image_media_id'    => [ 'nullable', 'integer', 'min:1' ],
            'variants.*.image_url'         => [ 'nullable', 'string', 'max:1000', 'url:http,https' ],
            'stock_reason'                 => [ Rule::requiredIf( self::stockChanged( $state ) ), 'nullable', 'string', 'max:255' ],
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
            'attributes.*.label'           => __( 'attribute label' ),
            'attributes.*.key'             => __( 'attribute key' ),
            'attributes.*.values.*.label'  => __( 'value' ),
            'attributes.*.values.*.swatch' => __( 'swatch' ),
            'variants.*.name'              => __( 'variant name' ),
            'variants.*.sku'               => __( 'SKU' ),
            'variants.*.prices.*'          => __( 'price' ),
            'variants.*.stock'             => __( 'stock' ),
            'variants.*.image_url'         => __( 'image URL' ),
            'stock_reason'                 => __( 'reason' ),
        ];
    }

    /**
     * @since 1.0.0
     *
     * @param  Product               $product  Saved product.
     * @param  array<string, mixed>  $state    Validated state.
     *
     * @throws ProductWriteException When the engine refuses a row.
     *
     * @return void
     */
    public static function save( Product $product, array $state ): void
    {
        $service    = app( ProductService::class );
        $currencies = StoreCurrencies::enabled( $product );
        $rows       = array_values( (array) ( $state['attributes'] ?? [] ) );

        $saved = $service->syncAttributes( $product, array_map( static fn ( array $attribute ): array => [
            'id'           => $attribute['id'] ?? null,
            'key'          => '' === trim( (string) ( $attribute['key'] ?? '' ) ) ? (string) $attribute['label'] : (string) $attribute['key'],
            'label'        => (string) $attribute['label'],
            'is_variation' => (bool) ( $attribute['is_variation'] ?? true ),
            'values'       => array_map( static fn ( array $value ): array => [
                'id'     => $value['id'] ?? null,
                'value'  => $value['value'] ?? null,
                'label'  => (string) $value['label'],
                'swatch' => $value['swatch'] ?? null,
            ], array_values( (array) ( $attribute['values'] ?? [] ) ) ),
        ], $rows ) );

        // Map client ids to the saved rows (both lists are in the same order).
        $attributeIds = [];
        $valueIds     = [];

        foreach ( $rows as $index => $attribute ) {
            $model = $saved->get( $index );

            if ( null === $model || ! (bool) ( $attribute['is_variation'] ?? true ) ) {
                continue;
            }

            $attributeIds[ $attribute['uid'] ] = (int) $model->id;
            $values                            = $model->values->sortBy( 'position' )->values();

            foreach ( array_values( (array) ( $attribute['values'] ?? [] ) ) as $position => $value ) {
                if ( null !== $values->get( $position ) ) {
                    $valueIds[ $value['uid'] ] = (int) $values->get( $position )->id;
                }
            }
        }

        // Delete removed variants first, so a re-added combination or SKU
        // doesn't collide with the row it replaces.
        $incoming = array_values( array_filter( array_map(
            static fn ( array $variant ): ?int => isset( $variant['id'] ) && '' !== $variant['id'] ? (int) $variant['id'] : null,
            (array) ( $state['variants'] ?? [] ),
        ) ) );

        foreach ( $product->variants()->whereNotIn( 'id', $incoming )->get() as $removed ) {
            $service->deleteVariant( $removed );
        }

        foreach ( array_values( (array) ( $state['variants'] ?? [] ) ) as $position => $variant ) {
            $options = [];

            foreach ( (array) ( $variant['options'] ?? [] ) as $attributeUid => $valueUid ) {
                if ( isset( $attributeIds[ $attributeUid ], $valueIds[ $valueUid ] ) ) {
                    $options[ $attributeIds[ $attributeUid ] ] = $valueIds[ $valueUid ];
                }
            }

            $data = [
                'name'           => (string) ( $variant['name'] ?? '' ),
                'sku'            => (string) ( $variant['sku'] ?? '' ),
                'position'       => $position,
                'image_media_id' => isset( $variant['image_media_id'] ) && '' !== $variant['image_media_id'] ? (int) $variant['image_media_id'] : null,
                'meta'           => [ 'image_url' => ProductMedia::safeUrl( $variant['image_url'] ?? null ) ],
                'option_values'  => $options,
            ];

            try {
                $model = self::writeVariant( $service, $product, $variant, $data, (string) ( $state['stock_reason'] ?? '' ) );
                self::writePrices( $model, (array) ( $variant['prices'] ?? [] ), $currencies );
            } catch ( ProductWriteException $exception ) {
                throw new ProductWriteException( array_map(
                    static fn ( array $error ): array => self::variantError( $position, $error ),
                    $exception->errors,
                ) );
            }
        }
    }

    /**
     * Adds an attribute.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addAttribute(): void
    {
        $this->assertWritable();

        $this->state['attributes'][] = [ 'uid' => self::uid( 'a' ), 'id' => null, 'key' => '', 'label' => '', 'is_variation' => true, 'values' => [] ];
    }

    /**
     * Removes an attribute (and its options from every variant).
     *
     * @since 1.0.0
     *
     * @param  int  $index  Attribute index.
     *
     * @return void
     */
    public function removeAttribute( int $index ): void
    {
        $this->assertWritable();

        $attributes = (array) ( $this->state['attributes'] ?? [] );
        $uid        = $attributes[ $index ]['uid'] ?? null;

        unset( $attributes[ $index ] );
        $this->state['attributes'] = array_values( $attributes );

        foreach ( (array) ( $this->state['variants'] ?? [] ) as $key => $variant ) {
            unset( $this->state['variants'][ $key ]['options'][ $uid ] );
        }
    }

    /**
     * Moves an attribute up (-1) or down (+1).
     *
     * @since 1.0.0
     *
     * @param  int  $index      Attribute index.
     * @param  int  $direction  -1 or 1.
     *
     * @return void
     */
    public function moveAttribute( int $index, int $direction ): void
    {
        $this->assertWritable();

        $this->state['attributes'] = self::swap( (array) ( $this->state['attributes'] ?? [] ), $index, $direction );
    }

    /**
     * Adds a value to an attribute.
     *
     * @since 1.0.0
     *
     * @param  int  $attribute  Attribute index.
     *
     * @return void
     */
    public function addValue( int $attribute ): void
    {
        $this->assertWritable();

        if ( isset( $this->state['attributes'][ $attribute ] ) ) {
            $this->state['attributes'][ $attribute ]['values'][] = [ 'uid' => self::uid( 'v' ), 'id' => null, 'value' => null, 'label' => '', 'swatch' => '' ];
        }
    }

    /**
     * Removes a value (variants using it lose that option).
     *
     * @since 1.0.0
     *
     * @param  int  $attribute  Attribute index.
     * @param  int  $value      Value index.
     *
     * @return void
     */
    public function removeValue( int $attribute, int $value ): void
    {
        $this->assertWritable();

        $attributeUid = $this->state['attributes'][ $attribute ]['uid'] ?? null;
        $valueUid     = $this->state['attributes'][ $attribute ]['values'][ $value ]['uid'] ?? null;

        unset( $this->state['attributes'][ $attribute ]['values'][ $value ] );

        if ( isset( $this->state['attributes'][ $attribute ] ) ) {
            $this->state['attributes'][ $attribute ]['values'] = array_values( $this->state['attributes'][ $attribute ]['values'] );
        }

        foreach ( (array) ( $this->state['variants'] ?? [] ) as $key => $variant ) {
            if ( ( $variant['options'][ $attributeUid ] ?? null ) === $valueUid ) {
                unset( $this->state['variants'][ $key ]['options'][ $attributeUid ] );
            }
        }
    }

    /**
     * Moves a value up (-1) or down (+1).
     *
     * @since 1.0.0
     *
     * @param  int  $attribute  Attribute index.
     * @param  int  $value      Value index.
     * @param  int  $direction  -1 or 1.
     *
     * @return void
     */
    public function moveValue( int $attribute, int $value, int $direction ): void
    {
        $this->assertWritable();

        if ( isset( $this->state['attributes'][ $attribute ] ) ) {
            $this->state['attributes'][ $attribute ]['values'] = self::swap( (array) $this->state['attributes'][ $attribute ]['values'], $value, $direction );
        }
    }

    /**
     * Adds a row for every missing combination, asking first when there
     * are more than {@see self::CONFIRM_ABOVE}.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function generateVariants(): void
    {
        $this->assertWritable();

        $combinations = $this->missingCombinations();

        if ( null === $combinations ) {
            $this->addError( 'state.attributes', __( 'Add at least one value to each attribute used for variations first.' ) );

            return;
        }

        $count = count( $combinations );

        if ( 0 === $count ) {
            $this->pendingGenerate = 0;
            $this->dispatch( 'ecommerce-admin-variants-generated', count: 0 );

            return;
        }

        if ( count( (array) ( $this->state['variants'] ?? [] ) ) + $count > self::MAX_VARIANTS ) {
            $this->addError( 'state.variants', __( 'That would make more than :max variants.', [ 'max' => self::MAX_VARIANTS ] ) );

            return;
        }

        if ( $count > self::CONFIRM_ABOVE ) {
            $this->pendingGenerate = $count;

            return;
        }

        $this->appendVariants( $combinations );
    }

    /**
     * Generates after the confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function confirmGenerate(): void
    {
        $this->assertWritable();

        $combinations          = $this->missingCombinations() ?? [];
        $this->pendingGenerate = 0;

        if ( count( (array) ( $this->state['variants'] ?? [] ) ) + count( $combinations ) > self::MAX_VARIANTS ) {
            $this->addError( 'state.variants', __( 'That would make more than :max variants.', [ 'max' => self::MAX_VARIANTS ] ) );

            return;
        }

        $this->appendVariants( $combinations );
    }

    /**
     * Cancels a pending generation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelGenerate(): void
    {
        $this->pendingGenerate = 0;
    }

    /**
     * Adds an empty variant row.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addVariant(): void
    {
        $this->assertWritable();

        $this->state['variants'][] = $this->variantRow( [], '' );
    }

    /**
     * Removes a variant row (deleted on save).
     *
     * @since 1.0.0
     *
     * @param  int  $index  Variant index.
     *
     * @return void
     */
    public function removeVariant( int $index ): void
    {
        $this->assertWritable();

        unset( $this->state['variants'][ $index ] );
        $this->state['variants'] = array_values( (array) ( $this->state['variants'] ?? [] ) );
    }

    /**
     * Moves a variant up (-1) or down (+1).
     *
     * @since 1.0.0
     *
     * @param  int  $index      Variant index.
     * @param  int  $direction  -1 or 1.
     *
     * @return void
     */
    public function moveVariant( int $index, int $direction ): void
    {
        $this->assertWritable();

        $this->state['variants'] = self::swap( (array) ( $this->state['variants'] ?? [] ), $index, $direction );
    }

    /**
     * Receives a media-library image for a variant.
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
        if ( $this->readOnly || ! str_starts_with( $context, self::MEDIA_CONTEXT ) ) {
            return;
        }

        $index = (int) Str::after( $context, self::MEDIA_CONTEXT );
        $id    = $media[0]['id'] ?? null;

        if ( isset( $this->state['variants'][ $index ] ) && is_numeric( $id ) ) {
            $this->state['variants'][ $index ]['image_media_id'] = (int) $id;
            $this->state['variants'][ $index ]['image_url']      = '';
        }
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $attributes = (array) ( $this->state['attributes'] ?? [] );
        $labels     = [];

        foreach ( $attributes as $attribute ) {
            foreach ( (array) ( $attribute['values'] ?? [] ) as $value ) {
                $labels[ $attribute['uid'] ][ $value['uid'] ] = (string) $value['label'];
            }
        }

        return view( 'ecommerce-admin::livewire.products.panels.variable', [
            'currencies'   => $this->currencies(),
            'optionLabels' => $labels,
            'matrixSize'   => $this->matrixSize(),
            'mediaLibrary' => ProductMedia::libraryInstalled(),
            'stockChanged' => self::stockChanged( $this->state ),
        ] );
    }

    /**
     * Combinations of the variation attributes' values that no variant has,
     * as `attributeUid => valueUid` maps; null when an attribute has no values.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, string>>|null
     */
    protected function missingCombinations(): ?array
    {
        $attributes = array_values( array_filter(
            (array) ( $this->state['attributes'] ?? [] ),
            static fn ( array $attribute ): bool => (bool) ( $attribute['is_variation'] ?? true ),
        ) );

        if ( [] === $attributes ) {
            return null;
        }

        $combinations = [ [] ];

        foreach ( $attributes as $attribute ) {
            $values = array_values( array_filter( (array) ( $attribute['values'] ?? [] ), static fn ( array $value ): bool => '' !== trim( (string) $value['label'] ) ) );

            if ( [] === $values ) {
                return null;
            }

            $next = [];

            foreach ( $combinations as $combination ) {
                foreach ( $values as $value ) {
                    $next[] = $combination + [ $attribute['uid'] => $value['uid'] ];
                }

                if ( count( $next ) > self::MAX_VARIANTS * 4 ) {
                    break;
                }
            }

            $combinations = $next;
        }

        $existing = array_map(
            static fn ( array $variant ): string => self::comboKey( (array) ( $variant['options'] ?? [] ) ),
            (array) ( $this->state['variants'] ?? [] ),
        );

        return array_values( array_filter( $combinations, static fn ( array $combination ): bool => ! in_array( self::comboKey( $combination ), $existing, true ) ) );
    }

    /**
     * Appends variant rows for the combinations.
     *
     * @since 1.0.0
     *
     * @param  array<int, array<string, string>>  $combinations  Option maps.
     *
     * @return void
     */
    protected function appendVariants( array $combinations ): void
    {
        $labels = [];

        foreach ( (array) ( $this->state['attributes'] ?? [] ) as $attribute ) {
            foreach ( (array) ( $attribute['values'] ?? [] ) as $value ) {
                $labels[ $value['uid'] ] = trim( (string) $value['label'] );
            }
        }

        foreach ( $combinations as $combination ) {
            $name                      = implode( ' / ', array_map( static fn ( string $uid ): string => $labels[ $uid ] ?? '', $combination ) );
            $this->state['variants'][] = $this->variantRow( $combination, $name );
        }

        $this->dispatch( 'ecommerce-admin-variants-generated', count: count( $combinations ) );
    }

    /**
     * An empty variant row.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $options  Option map.
     * @param  string                 $name     Name.
     *
     * @return array<string, mixed>
     */
    protected function variantRow( array $options, string $name ): array
    {
        return [
            'id'             => null,
            'name'           => $name,
            'sku'            => '',
            'options'        => $options,
            'prices'         => array_fill_keys( $this->currencies(), null ),
            'stock'          => 0,
            'stock_loaded'   => null,
            'image_media_id' => null,
            'image_url'      => '',
        ];
    }

    /**
     * Enabled currencies, once per request.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function currencies(): array
    {
        return $this->currencies ??= StoreCurrencies::enabled( $this->product() );
    }

    /**
     * The size of the full attribute matrix, capped well above the most
     * variants the engine generates, so many large attributes can't
     * overflow an int.
     *
     * @since 1.0.0
     *
     * @return int
     */
    protected function matrixSize(): int
    {
        $cap  = ProductService::MAX_GENERATED_VARIANTS * 4;
        $size = 0;

        foreach ( (array) ( $this->state['attributes'] ?? [] ) as $attribute ) {
            if ( ! (bool) ( $attribute['is_variation'] ?? true ) ) {
                continue;
            }

            $size = max( 1, $size ) * count( (array) ( $attribute['values'] ?? [] ) );

            if ( $size > $cap ) {
                return $cap;
            }
        }

        return $size;
    }

    /**
     * Creates or updates one variant.
     *
     * @since 1.0.0
     *
     * @param  ProductService        $service  Service.
     * @param  Product               $product  Product.
     * @param  array<string, mixed>  $variant  State row.
     * @param  array<string, mixed>  $data     Service payload.
     * @param  string                $reason   Stock-change reason.
     *
     * @return ProductVariant
     */
    protected static function writeVariant( ProductService $service, Product $product, array $variant, array $data, string $reason ): ProductVariant
    {
        $existing = isset( $variant['id'] ) ? $product->variants()->whereKey( (int) $variant['id'] )->first() : null;
        $stock    = isset( $variant['stock'] ) && '' !== $variant['stock'] ? (int) $variant['stock'] : 0;

        if ( null === $existing ) {
            return $service->createVariant( $product, $data + [ 'inventory' => [ 'track_inventory' => true, 'quantity_on_hand' => $stock ] ] );
        }

        $delta = $stock - (int) ( $variant['stock_loaded'] ?? 0 );

        if ( 0 !== $delta ) {
            $data['stock_adjustment'] = [ 'delta' => $delta, 'reason' => $reason ];
        }

        return $service->updateVariant( $existing, $data );
    }

    /**
     * Sets a variant's unscheduled price per currency (an empty price
     * removes that currency's row; scheduled rows are left alone).
     *
     * @since 1.0.0
     *
     * @param  ProductVariant              $variant     Variant.
     * @param  array<string, mixed>        $prices      Currency => minor units.
     * @param  array<int, string>          $currencies  Enabled currencies.
     *
     * @return void
     */
    protected static function writePrices( ProductVariant $variant, array $prices, array $currencies ): void
    {
        $service = app( ProductService::class );

        foreach ( $currencies as $currency ) {
            $amount = $prices[ $currency ] ?? null;

            if ( null === $amount || '' === $amount ) {
                // The engine has no single-row delete (syncPrices() would
                // recreate every row, scheduled ones included), so the row is
                // removed here, after the same editability check.
                $service->assertEditable( $variant->product );

                ProductPrice::query()
                    ->where( 'priceable_type', $variant->getMorphClass() )
                    ->where( 'priceable_id', $variant->id )
                    ->where( 'currency', $currency )
                    ->whereNull( 'starts_at' )
                    ->whereNull( 'ends_at' )
                    ->delete();
                continue;
            }

            try {
                $service->upsertPrice( $variant, [ 'currency' => $currency, 'price_amount' => (int) $amount ] );
            } catch ( ProductWriteException $exception ) {
                throw ProductWriteException::field( "prices.{$currency}", (string) $exception->errors[0]['code'], (string) $exception->errors[0]['message'] );
            }
        }
    }

    /**
     * A service error on variant `$position`, moved to the matching state
     * path. Errors without a field of their own (options, stock settings)
     * go to the table with the variant's number.
     *
     * @since 1.0.0
     *
     * @param  int                   $position  Variant index.
     * @param  array<string, mixed>  $error     Service error.
     *
     * @return array<string, mixed>
     */
    protected static function variantError( int $position, array $error ): array
    {
        $field = (string) ( $error['field'] ?? '' );

        if ( 'stock_adjustment.reason' === $field ) {
            return [ 'field' => 'stock_reason' ] + $error;
        }

        if ( str_starts_with( $field, 'prices.' ) || in_array( $field, [ 'sku', 'name', 'image_media_id' ], true ) ) {
            return [ 'field' => "variants.{$position}.{$field}" ] + $error;
        }

        return [
            'field'   => 'variants',
            'message' => __( 'Variant :number: :message', [ 'number' => $position + 1, 'message' => (string) ( $error['message'] ?? '' ) ] ),
        ] + $error;
    }

    /**
     * Rule: attribute keys (from key or label) are unique.
     *
     * @since 1.0.0
     *
     * @return Closure
     */
    protected static function distinctKeys(): Closure
    {
        return static function ( string $attribute, mixed $value, Closure $fail ): void {
            $keys = array_map(
                static fn ( mixed $row ): string => Str::slug( (string) ( '' === trim( (string) ( $row['key'] ?? '' ) ) ? ( $row['label'] ?? '' ) : $row['key'] ), '_' ),
                (array) $value,
            );

            if ( count( $keys ) !== count( array_unique( $keys ) ) ) {
                $fail( __( 'Each attribute needs its own key.' ) );
            }
        };
    }

    /**
     * Rule: non-empty variant SKUs are unique within the product.
     *
     * @since 1.0.0
     *
     * @return Closure
     */
    protected static function distinctSkus(): Closure
    {
        return static function ( string $attribute, mixed $value, Closure $fail ): void {
            $skus = array_filter( array_map( static fn ( mixed $row ): string => strtolower( trim( (string) ( $row['sku'] ?? '' ) ) ), (array) $value ) );

            if ( count( $skus ) !== count( array_unique( $skus ) ) ) {
                $fail( __( 'Two variants have the same SKU.' ) );
            }
        };
    }

    /**
     * Whether any saved variant's stock was edited.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state  State.
     *
     * @return bool
     */
    protected static function stockChanged( array $state ): bool
    {
        foreach ( (array) ( $state['variants'] ?? [] ) as $variant ) {
            if ( null !== ( $variant['id'] ?? null ) && is_numeric( $variant['stock'] ?? null ) && (int) $variant['stock'] !== (int) ( $variant['stock_loaded'] ?? 0 ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stable key for an option map.
     *
     * @since 1.0.0
     *
     * @param  array<string, string>  $options  Option map.
     *
     * @return string
     */
    protected static function comboKey( array $options ): string
    {
        ksort( $options );

        return implode( ',', array_map( static fn ( string $attribute, string $value ): string => $attribute . ':' . $value, array_keys( $options ), $options ) );
    }

    /**
     * A new client id.
     *
     * @since 1.0.0
     *
     * @param  string  $prefix  `a` or `v`.
     *
     * @return string
     */
    protected static function uid( string $prefix ): string
    {
        return $prefix . 'n' . Str::lower( Str::random( 10 ) );
    }

    /**
     * Swaps a list item with its neighbour.
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $list       List.
     * @param  int                $index      Item index.
     * @param  int                $direction  -1 or 1.
     *
     * @return array<int, mixed>
     */
    protected static function swap( array $list, int $index, int $direction ): array
    {
        $list   = array_values( $list );
        $target = $index + ( $direction < 0 ? -1 : 1 );

        if ( isset( $list[ $index ], $list[ $target ] ) ) {
            [ $list[ $index ], $list[ $target ] ] = [ $list[ $target ], $list[ $index ] ];
        }

        return $list;
    }
}
