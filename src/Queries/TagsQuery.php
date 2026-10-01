<?php

/**
 * Tags table query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Queries;

use ArtisanPackUI\Ecommerce\Models\ProductTag;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Search and sorts for the tags table (spec §7.2). Every row carries
 * `products_count`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class TagsQuery extends ResourceQuery
{
    /**
     * @since 1.0.0
     *
     * @return array<string, Closure|string>
     */
    public function sorts(): array
    {
        return [
            'name'     => 'product_tags.name',
            'slug'     => 'product_tags.slug',
            'products' => 'products_count',
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array{0: string, 1: string}
     */
    public function defaultSort(): array
    {
        return [ 'name', 'asc' ];
    }

    /**
     * @since 1.0.0
     *
     * @return Builder<ProductTag>
     */
    protected function baseQuery(): Builder
    {
        return ProductTag::query()->withCount( 'products' );
    }

    /**
     * Matches the name or slug.
     *
     * @since 1.0.0
     *
     * @param  Builder<ProductTag>  $query   The query.
     * @param  string               $search  Search text.
     *
     * @return void
     */
    protected function applySearch( Builder $query, string $search ): void
    {
        $query->where( static function ( Builder $where ) use ( $search ): void {
            static::orWhereContains( $where, 'product_tags.name', $search );
            static::orWhereContains( $where, 'product_tags.slug', $search );
        } );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, Closure>
     */
    protected function filters(): array
    {
        return [];
    }
}
