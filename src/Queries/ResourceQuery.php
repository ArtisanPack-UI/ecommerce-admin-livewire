<?php

/**
 * Base query class for index screens.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Queries;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Builds the query behind an index screen: search, filters, and sort.
 *
 * Each screen has one subclass under `src/Queries/`, so its filters and
 * sorting can be unit-tested without rendering anything (spec §5.1):
 *
 * ```php
 * $orders = ( new OrdersQuery() )->build( 'A1B2', [ 'payment_status' => 'paid' ], 'placed', 'desc' )->get();
 * ```
 *
 * `build()` trusts nothing: unknown filters and sorts are ignored, and the
 * search is trimmed and capped. Filters satellites add through the table's
 * extension filter are passed as callbacks with `withFilters()`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
abstract class ResourceQuery
{
    /**
     * The longest search term applied.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_SEARCH_LENGTH = 200;

    /**
     * Extra filters, keyed by filter key.
     *
     * @since 1.0.0
     *
     * @var array<string, callable(Builder, mixed): void>
     */
    private array $extraFilters = [];

    /**
     * The columns this query can sort by: key => column, or a callback that
     * applies the order for a direction.
     *
     * @since 1.0.0
     *
     * @return array<string, Closure(Builder, string): void|string>
     */
    abstract public function sorts(): array;

    /**
     * The sort used when none (or an unknown one) is requested.
     *
     * @since 1.0.0
     *
     * @return array{0: string, 1: string} The sort key and direction.
     */
    abstract public function defaultSort(): array;

    /**
     * Adds filters that are not part of the query class, such as those a
     * satellite registers. A key the class already handles is ignored.
     *
     * @since 1.0.0
     *
     * @param  array<string, callable(Builder, mixed): void>  $filters  The callbacks.
     *
     * @return static
     */
    public function withFilters( array $filters ): static
    {
        foreach ( $filters as $key => $apply ) {
            if ( is_string( $key ) && is_callable( $apply ) ) {
                $this->extraFilters[ $key ] = $apply;
            }
        }

        return $this;
    }

    /**
     * The query for a search, a set of filter values, and a sort.
     *
     * @since 1.0.0
     *
     * @param  string                $search     The search term.
     * @param  array<string, mixed>  $filters    Filter values keyed by filter key; empty values are skipped.
     * @param  string                $sort       The sort key.
     * @param  string                $direction  `asc` or `desc`.
     *
     * @return Builder
     */
    public function build( string $search = '', array $filters = [], string $sort = '', string $direction = '' ): Builder
    {
        $query  = $this->baseQuery();
        $search = trim( mb_substr( $search, 0, self::MAX_SEARCH_LENGTH ) );

        if ( '' !== $search ) {
            $this->applySearch( $query, $search );
        }

        foreach ( [ ...$this->extraFilters, ...$this->filters() ] as $key => $apply ) {
            if ( array_key_exists( $key, $filters ) && ! self::isEmptyValue( $filters[ $key ] ) ) {
                $apply( $query, $filters[ $key ] );
            }
        }

        $this->applySort( $query, $sort, $direction );

        return $query;
    }

    /**
     * Whether a sort key is supported.
     *
     * @since 1.0.0
     *
     * @param  string  $sort  The sort key.
     *
     * @return bool
     */
    public function canSortBy( string $sort ): bool
    {
        return array_key_exists( $sort, $this->sorts() );
    }

    /**
     * Whether a filter value counts as "not set".
     *
     * Null, blank strings, empty arrays, and arrays whose values are all
     * empty (a date range with neither end) are empty.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  The value.
     *
     * @return bool
     */
    public static function isEmptyValue( mixed $value ): bool
    {
        if ( is_array( $value ) ) {
            foreach ( $value as $item ) {
                if ( ! self::isEmptyValue( $item ) ) {
                    return false;
                }
            }

            return true;
        }

        return null === $value || ( is_string( $value ) && '' === trim( $value ) );
    }

    /**
     * The query with its eager loads and scoping, before search and filters.
     *
     * @since 1.0.0
     *
     * @return Builder
     */
    abstract protected function baseQuery(): Builder;

    /**
     * Applies the search term.
     *
     * @since 1.0.0
     *
     * @param  Builder  $query   The query.
     * @param  string   $search  The trimmed, non-empty term.
     *
     * @return void
     */
    abstract protected function applySearch( Builder $query, string $search ): void;

    /**
     * The filters this query understands: key => callback applying a
     * non-empty value.
     *
     * @since 1.0.0
     *
     * @return array<string, Closure(Builder, mixed): void>
     */
    abstract protected function filters(): array;

    /**
     * Applies the requested sort, or the default, then the primary key so
     * pagination is stable.
     *
     * @since 1.0.0
     *
     * @param  Builder  $query      The query.
     * @param  string   $sort       The sort key.
     * @param  string   $direction  The direction.
     *
     * @return void
     */
    protected function applySort( Builder $query, string $sort, string $direction ): void
    {
        $sorts = $this->sorts();

        if ( ! array_key_exists( $sort, $sorts ) ) {
            [ $sort, $direction ] = $this->defaultSort();
        }

        $direction = 'asc' === strtolower( $direction ) ? 'asc' : 'desc';
        $column    = $sorts[ $sort ] ?? null;

        if ( $column instanceof Closure ) {
            $column( $query, $direction );
        } elseif ( is_string( $column ) ) {
            $query->orderBy( $column, $direction );
        }

        $query->orderBy( $query->getModel()->getQualifiedKeyName(), $direction );
    }

    /**
     * A `Y-m-d` date, or null when the value is not a real calendar date.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  The raw value.
     *
     * @return Carbon|null
     */
    protected static function date( mixed $value ): ?Carbon
    {
        if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts ) ) {
            return null;
        }

        if ( ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
            return null;
        }

        return Carbon::createFromFormat( '!Y-m-d', $value );
    }

    /**
     * Adds an `OR column contains term` clause: case-insensitive, with the
     * term's `%`, `_`, and `\` matched literally on every driver.
     *
     * @since 1.0.0
     *
     * @param  Builder  $query   The query (usually a nested where group).
     * @param  string   $column  The column, qualified when the query joins.
     * @param  string   $term    The raw term.
     *
     * @return void
     */
    protected static function orWhereContains( Builder $query, string $column, string $term ): void
    {
        $pattern = '%' . str_replace( [ '\\', '%', '_' ], [ '\\\\', '\\%', '\\_' ], mb_strtolower( $term ) ) . '%';
        $wrapped = $query->getQuery()->getGrammar()->wrap( $column );

        $query->orWhereRaw( 'LOWER(' . $wrapped . ') LIKE ? ESCAPE ?', [ $pattern, '\\' ] );
    }
}
