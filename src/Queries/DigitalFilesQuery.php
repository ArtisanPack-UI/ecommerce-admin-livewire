<?php

/**
 * Digital files table query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Queries;

use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Search, filters, and sorts for the digital files table (spec §7.2).
 *
 * Search matches the label, version, path, and the product or variant
 * name. Filters: `product` (id) and `streaming` (`1` for streaming-only
 * files, `0` for downloadable ones).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class DigitalFilesQuery extends ResourceQuery
{
    /**
     * @since 1.0.0
     *
     * @return array<string, Closure|string>
     */
    public function sorts(): array
    {
        return [
            'label'   => 'digital_files.label',
            'version' => 'digital_files.version',
            'updated' => 'digital_files.updated_at',
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array{0: string, 1: string}
     */
    public function defaultSort(): array
    {
        return [ 'updated', 'desc' ];
    }

    /**
     * @since 1.0.0
     *
     * @return Builder<DigitalFile>
     */
    protected function baseQuery(): Builder
    {
        return DigitalFile::query()->with( [ 'product:id,name,type', 'variant:id,product_id,name,sku', 'variant.product:id,name,type' ] );
    }

    /**
     * @since 1.0.0
     *
     * @param  Builder<DigitalFile>  $query   The query.
     * @param  string                $search  Search text.
     *
     * @return void
     */
    protected function applySearch( Builder $query, string $search ): void
    {
        $query->where( static function ( Builder $where ) use ( $search ): void {
            static::orWhereContains( $where, 'digital_files.label', $search );
            static::orWhereContains( $where, 'digital_files.version', $search );
            static::orWhereContains( $where, 'digital_files.path', $search );

            $where->orWhereHas( 'product', static fn ( Builder $product ) => $product->where( static fn ( Builder $name ) => static::orWhereContains( $name, 'products.name', $search ) ) )
                ->orWhereHas( 'variant.product', static fn ( Builder $product ) => $product->where( static fn ( Builder $name ) => static::orWhereContains( $name, 'products.name', $search ) ) );
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
            'product'   => static fn ( Builder $query, mixed $value ) => $query->where( static function ( Builder $owner ) use ( $value ): void {
                $owner->where( 'digital_files.product_id', (int) $value )
                    ->orWhereHas( 'variant', static fn ( Builder $variant ) => $variant->where( 'product_variants.product_id', (int) $value ) );
            } ),
            'streaming' => static fn ( Builder $query, mixed $value ) => $query->where( 'digital_files.is_streaming_only', '1' === (string) $value ),
        ];
    }
}
