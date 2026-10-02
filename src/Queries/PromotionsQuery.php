<?php

/**
 * Promotions table query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Queries;

use ArtisanPackUI\Ecommerce\Models\Promotion;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Search, filters, and sorts for the promotions table (spec §7.5).
 *
 * The state filter matches the states {@see self::state()} reports:
 * disabled (switched off), scheduled (starts later), expired (ended), and
 * active (everything else).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class PromotionsQuery extends ResourceQuery
{
    /**
     * The states a promotion can be in.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const STATES = [ 'active', 'scheduled', 'expired', 'disabled' ];

    /**
     * A promotion's state at a moment (now by default).
     *
     * @since 1.0.0
     *
     * @param  Promotion    $promotion  The promotion.
     * @param  Carbon|null  $at         The moment.
     *
     * @return string One of {@see self::STATES}.
     */
    public static function state( Promotion $promotion, ?Carbon $at = null ): string
    {
        $at ??= Carbon::now();

        return match ( true ) {
            ! $promotion->is_active                                                       => 'disabled',
            null !== $promotion->starts_at && $promotion->starts_at->greaterThan( $at )   => 'scheduled',
            null !== $promotion->ends_at && $promotion->ends_at->lessThanOrEqualTo( $at ) => 'expired',
            default                                                                       => 'active',
        };
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, Closure|string>
     */
    public function sorts(): array
    {
        return [
            'name'     => 'promotions.name',
            'priority' => 'promotions.priority',
            'window'   => 'promotions.starts_at',
            'uses'     => 'promotions.times_used',
            'created'  => 'promotions.created_at',
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array{0: string, 1: string}
     */
    public function defaultSort(): array
    {
        return [ 'created', 'desc' ];
    }

    /**
     * @since 1.0.0
     *
     * @return Builder<Promotion>
     */
    protected function baseQuery(): Builder
    {
        return Promotion::query()->select( 'promotions.*' )->withCount( 'coupons' );
    }

    /**
     * Matches the name, key, or description.
     *
     * @since 1.0.0
     *
     * @param  Builder<Promotion>  $query   The query.
     * @param  string              $search  Search text.
     *
     * @return void
     */
    protected function applySearch( Builder $query, string $search ): void
    {
        $query->where( static function ( Builder $where ) use ( $search ): void {
            static::orWhereContains( $where, 'promotions.name', $search );
            static::orWhereContains( $where, 'promotions.key', $search );
            static::orWhereContains( $where, 'promotions.description', $search );

            $where->orWhereHas( 'coupons', static fn ( Builder $coupons ) => $coupons->where( 'coupons.code', strtoupper( trim( $search ) ) ) );
        } );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, Closure(Builder, mixed): void>
     */
    protected function filters(): array
    {
        return [
            'state'       => static function ( Builder $query, mixed $value ): void {
                $now = Carbon::now();

                match ( (string) $value ) {
                    'disabled'  => $query->where( 'promotions.is_active', false ),
                    'scheduled' => $query->where( 'promotions.is_active', true )->where( 'promotions.starts_at', '>', $now ),
                    'expired'   => $query->where( 'promotions.is_active', true )
                        ->where( static fn ( Builder $started ) => $started->whereNull( 'promotions.starts_at' )->orWhere( 'promotions.starts_at', '<=', $now ) )
                        ->where( 'promotions.ends_at', '<=', $now ),
                    'active'    => $query->where( 'promotions.is_active', true )
                        ->where( static fn ( Builder $started ) => $started->whereNull( 'promotions.starts_at' )->orWhere( 'promotions.starts_at', '<=', $now ) )
                        ->where( static fn ( Builder $ending ) => $ending->whereNull( 'promotions.ends_at' )->orWhere( 'promotions.ends_at', '>', $now ) ),
                    default     => null,
                };
            },
            'source_type' => static fn ( Builder $query, mixed $value ) => $query->where( 'promotions.source_type', (string) $value ),
            'exclusive'   => static fn ( Builder $query, mixed $value ) => $query->where( 'promotions.is_exclusive', '1' === (string) $value ),
        ];
    }
}
