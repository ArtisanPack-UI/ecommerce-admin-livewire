<?php

/**
 * Product picker source.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Pickers;

use ArtisanPackUI\Ecommerce\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Products, searched by name and SKU.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class ProductPickerSource extends EloquentPickerSource
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
        return Product::query()->orderBy( 'name' );
    }

    /**
     * {@inheritDoc}
     */
    protected function applySearch( Builder $query, string $like ): void
    {
        $this->whereLike( $query, 'name', $like );
        $this->whereLike( $query, 'sku', $like, 'or' );
    }

    /**
     * {@inheritDoc}
     */
    protected function toOption( Model $model ): array
    {
        return [
            'id'          => $model->getKey(),
            'name'        => (string) $model->getAttribute( 'name' ),
            'description' => $model->getAttribute( 'sku' ) ? __( 'SKU: :sku', [ 'sku' => $model->getAttribute( 'sku' ) ] ) : null,
        ];
    }
}
