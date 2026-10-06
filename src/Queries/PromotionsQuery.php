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

use ArtisanPackUI\Ecommerce\Models\Coupon;
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
            'name'     => static::column( Promotion::class, 'name' ),
            'priority' => static::column( Promotion::class, 'priority' ),
            'window'   => static::column( Promotion::class, 'starts_at' ),
            'uses'     => static::column( Promotion::class, 'times_used' ),
            'created'  => static::column( Promotion::class, 'created_at' ),
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
        return Promotion::query()->select( static::column( Promotion::class, '*' ) )->withCount( 'coupons' );
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
            static::orWhereContains( $where, static::column( Promotion::class, 'name' ), $search );
            static::orWhereContains( $where, static::column( Promotion::class, 'key' ), $search );
            static::orWhereContains( $where, static::column( Promotion::class, 'description' ), $search );

            $where->orWhereHas( 'coupons', static fn ( Builder $coupons ) => $coupons->where( static::column( Coupon::class, 'code' ), strtoupper( trim( $search ) ) ) );
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
                    'disabled'  => $query->where( static::column( Promotion::class, 'is_active' ), false ),
                    'scheduled' => $query->where( static::column( Promotion::class, 'is_active' ), true )->where( static::column( Promotion::class, 'starts_at' ), '>', $now ),
                    'expired'   => $query->where( static::column( Promotion::class, 'is_active' ), true )
                        ->where( static fn ( Builder $started ) => $started->whereNull( static::column( Promotion::class, 'starts_at' ) )->orWhere( static::column( Promotion::class, 'starts_at' ), '<=', $now ) )
                        ->where( static::column( Promotion::class, 'ends_at' ), '<=', $now ),
                    'active'    => $query->where( static::column( Promotion::class, 'is_active' ), true )
                        ->where( static fn ( Builder $started ) => $started->whereNull( static::column( Promotion::class, 'starts_at' ) )->orWhere( static::column( Promotion::class, 'starts_at' ), '<=', $now ) )
                        ->where( static fn ( Builder $ending ) => $ending->whereNull( static::column( Promotion::class, 'ends_at' ) )->orWhere( static::column( Promotion::class, 'ends_at' ), '>', $now ) ),
                    default     => null,
                };
            },
            'source_type' => static fn ( Builder $query, mixed $value ) => $query->where( static::column( Promotion::class, 'source_type' ), (string) $value ),
            'exclusive'   => static fn ( Builder $query, mixed $value ) => $query->where( static::column( Promotion::class, 'is_exclusive' ), '1' === (string) $value ),
        ];
    }
}
