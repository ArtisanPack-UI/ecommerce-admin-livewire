<?php

/**
 * Category picker source.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Pickers;

use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Product categories, searched by name and slug, with the parent's name as
 * the sub-label so same-named categories in different branches can be told
 * apart.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class CategoryPickerSource extends EloquentPickerSource
{
    /**
     * Categories are product data.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function ability(): string
    {
        return 'product.viewAny';
    }

    /**
     * @since 1.0.0
     *
     * @return Builder<ProductCategory>
     */
    protected function query(): Builder
    {
        return ProductCategory::query()->with( 'parent:id,name' )->orderBy( 'name' );
    }

    /**
     * @since 1.0.0
     *
     * @param  Builder<ProductCategory>  $query  The query.
     * @param  string                    $like   Escaped LIKE pattern.
     *
     * @return void
     */
    protected function applySearch( Builder $query, string $like ): void
    {
        $this->whereLike( $query, 'name', $like );
        $this->whereLike( $query, 'slug', $like, 'or' );
    }

    /**
     * @since 1.0.0
     *
     * @param  Model  $model  The category.
     *
     * @return array{id: int|string, name: string, description: string|null}
     */
    protected function toOption( Model $model ): array
    {
        $parent = $model->getRelationValue( 'parent' );

        return [
            'id'          => $model->getKey(),
            'name'        => (string) $model->getAttribute( 'name' ),
            'description' => null === $parent ? null : __( 'In :parent', [ 'parent' => $parent->getAttribute( 'name' ) ] ),
        ];
    }
}
