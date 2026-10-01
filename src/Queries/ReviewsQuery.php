<?php

/**
 * Reviews queue query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Queries;

use ArtisanPackUI\Ecommerce\Models\ProductReview;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Search, filters, and sorts for the reviews queue (spec §7.2).
 *
 * Search matches the author's name and email, the title and body, and the
 * product name. Filters: `status`, `rating` (1–5), `product` (id), and
 * `verified` (`1` for verified purchases only, `0` for the rest).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class ReviewsQuery extends ResourceQuery
{
    /**
     * @since 1.0.0
     *
     * @return array<string, Closure|string>
     */
    public function sorts(): array
    {
        return [
            'submitted' => 'product_reviews.created_at',
            'rating'    => 'product_reviews.rating',
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array{0: string, 1: string}
     */
    public function defaultSort(): array
    {
        return [ 'submitted', 'desc' ];
    }

    /**
     * @since 1.0.0
     *
     * @return Builder<ProductReview>
     */
    protected function baseQuery(): Builder
    {
        return ProductReview::query()
            ->select( 'product_reviews.*' )
            ->with( [ 'product:id,name' ] )
            ->withCount( 'media' );
    }

    /**
     * @since 1.0.0
     *
     * @param  Builder<ProductReview>  $query   The query.
     * @param  string                  $search  Search text.
     *
     * @return void
     */
    protected function applySearch( Builder $query, string $search ): void
    {
        $query->where( static function ( Builder $where ) use ( $search ): void {
            static::orWhereContains( $where, 'product_reviews.author_name', $search );
            static::orWhereContains( $where, 'product_reviews.author_email', $search );
            static::orWhereContains( $where, 'product_reviews.title', $search );
            static::orWhereContains( $where, 'product_reviews.body', $search );

            $where->orWhereHas( 'product', static fn ( Builder $product ) => $product->where( static fn ( Builder $name ) => static::orWhereContains( $name, 'products.name', $search ) ) );
        } );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, Closure>
     */
    protected function filters(): array
    {
        return [
            'status'   => static fn ( Builder $query, mixed $value ) => $query->where( 'product_reviews.status', (string) $value ),
            'rating'   => static fn ( Builder $query, mixed $value ) => $query->where( 'product_reviews.rating', (int) $value ),
            'product'  => static fn ( Builder $query, mixed $value ) => $query->where( 'product_reviews.product_id', (int) $value ),
            'verified' => static fn ( Builder $query, mixed $value ) => $query->where( 'product_reviews.is_verified_purchase', '1' === (string) $value ),
        ];
    }
}
