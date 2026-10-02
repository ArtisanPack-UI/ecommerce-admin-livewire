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
            'class'    => 'tax_rates.tax_class_key',
            'country'  => 'tax_rates.country_code',
            'region'   => 'tax_rates.region_code',
            'rate'     => 'tax_rates.rate_ubps',
            'label'    => 'tax_rates.label',
            'priority' => 'tax_rates.priority',
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
            static::orWhereContains( $where, 'tax_rates.label', $search );
            static::orWhereContains( $where, 'tax_rates.region_code', $search );
            static::orWhereContains( $where, 'tax_rates.postal_pattern', $search );
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
            'class'   => static fn ( Builder $query, mixed $value ) => $query->where( 'tax_rates.tax_class_key', (string) $value ),
            'country' => static fn ( Builder $query, mixed $value ) => $query->where( 'tax_rates.country_code', strtoupper( (string) $value ) ),
            'active'  => static fn ( Builder $query, mixed $value ) => $query->where( 'tax_rates.is_active', '1' === (string) $value ),
        ];
    }
}
