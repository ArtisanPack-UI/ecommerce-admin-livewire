<?php

/**
 * Variant picker source.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Pickers;

use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Product variants, searched by variant name, variant SKU, and product name.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class VariantPickerSource extends EloquentPickerSource
{
    /**
     * {@inheritDoc}
     */
    public function ability(): string
    {
        return 'product.viewAny';
    }

    /**
     * {@inheritDoc}
     */
    protected function query(): Builder
    {
        return ProductVariant::query()->with( 'product' )->orderBy( 'product_id' )->orderBy( 'position' );
    }

    /**
     * {@inheritDoc}
     */
    protected function applySearch( Builder $query, string $like ): void
    {
        $this->whereLike( $query, 'name', $like );
        $this->whereLike( $query, 'sku', $like, 'or' );
        $query->orWhereHas( 'product', fn ( Builder $product ) => $this->whereLike( $product, 'name', $like ) );
    }

    /**
     * {@inheritDoc}
     */
    protected function toOption( Model $model ): array
    {
        $productName = (string) $model->getRelationValue( 'product' )?->getAttribute( 'name' );
        $variantName = (string) $model->getAttribute( 'name' );

        return [
            'id'          => $model->getKey(),
            'name'        => '' === $variantName ? $productName : __( ':product — :variant', [ 'product' => $productName, 'variant' => $variantName ] ),
            'description' => $model->getAttribute( 'sku' ) ? __( 'SKU: :sku', [ 'sku' => $model->getAttribute( 'sku' ) ] ) : null,
        ];
    }
}
