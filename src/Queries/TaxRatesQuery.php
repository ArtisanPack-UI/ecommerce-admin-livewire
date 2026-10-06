<?php

/**
 * Tax rates table query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Queries;

use ArtisanPackUI\Ecommerce\Models\TaxRate;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Search, filters, and sorts for the tax rates table (spec §7.6).
 *
 * Search matches the label, region, and postal pattern. Filters: tax class,
 * country, and active.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class TaxRatesQuery extends ResourceQuery
{
    /**
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public function sorts(): array
    {
        return [
            'class'    => static::column( TaxRate::class, 'tax_class_key' ),
            'country'  => static::column( TaxRate::class, 'country_code' ),
            'region'   => static::column( TaxRate::class, 'region_code' ),
            'rate'     => static::column( TaxRate::class, 'rate_ubps' ),
            'label'    => static::column( TaxRate::class, 'label' ),
            'priority' => static::column( TaxRate::class, 'priority' ),
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array{0: string, 1: string}
     */
    public function defaultSort(): array
    {
        return [ 'country', 'asc' ];
    }

    /**
     * @since 1.0.0
     *
     * @return Builder<TaxRate>
     */
    protected function baseQuery(): Builder
    {
        return TaxRate::query()->with( 'taxClass' );
    }

    /**
     * @since 1.0.0
     *
     * @param  Builder<TaxRate>  $query   Query.
     * @param  string            $search  Search text.
     *
     * @return void
     */
    protected function applySearch( Builder $query, string $search ): void
    {
        $query->where( static function ( Builder $where ) use ( $search ): void {
            static::orWhereContains( $where, static::column( TaxRate::class, 'label' ), $search );
            static::orWhereContains( $where, static::column( TaxRate::class, 'region_code' ), $search );
            static::orWhereContains( $where, static::column( TaxRate::class, 'postal_pattern' ), $search );
        } );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, Closure(Builder<TaxRate>, mixed): mixed>
     */
    protected function filters(): array
    {
        return [
            'class'   => static fn ( Builder $query, mixed $value ) => $query->where( static::column( TaxRate::class, 'tax_class_key' ), (string) $value ),
            'country' => static fn ( Builder $query, mixed $value ) => $query->where( static::column( TaxRate::class, 'country_code' ), strtoupper( (string) $value ) ),
            'active'  => static fn ( Builder $query, mixed $value ) => $query->where( static::column( TaxRate::class, 'is_active' ), '1' === (string) $value ),
        ];
    }
}
