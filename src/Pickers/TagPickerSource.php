<?php

/**
 * Tag picker source.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Pickers;

use ArtisanPackUI\Ecommerce\Models\ProductTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Product tags, searched by name and slug.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class TagPickerSource extends EloquentPickerSource
{
    /**
     * Tags are product data.
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
     * @return Builder<ProductTag>
     */
    protected function query(): Builder
    {
        return ProductTag::query()->orderBy( 'name' );
    }

    /**
     * @since 1.0.0
     *
     * @param  Builder<ProductTag>  $query  The query.
     * @param  string               $like   Escaped LIKE pattern.
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
     * @param  Model  $model  The tag.
     *
     * @return array{id: int|string, name: string, description: string|null}
     */
    protected function toOption( Model $model ): array
    {
        return [
            'id'          => $model->getKey(),
            'name'        => (string) $model->getAttribute( 'name' ),
            'description' => null,
        ];
    }
}
