<?php

/**
 * Base Eloquent picker source.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Pickers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A picker source backed by an Eloquent query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
abstract class EloquentPickerSource implements PickerSource
{
    /**
     * The LIKE escape character. Not a backslash, whose quoting differs
     * between MySQL and SQLite / PostgreSQL.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const LIKE_ESCAPE = '!';

    /**
     * {@inheritDoc}
     */
    public function search( string $term, int $limit ): array
    {
        $query = $this->query();
        $term  = trim( $term );

        if ( '' !== $term ) {
            $escaped = str_replace( [ self::LIKE_ESCAPE, '%', '_' ], [ self::LIKE_ESCAPE . self::LIKE_ESCAPE, self::LIKE_ESCAPE . '%', self::LIKE_ESCAPE . '_' ], $term );

            $query->where( fn ( Builder $inner ) => $this->applySearch( $inner, '%' . $escaped . '%' ) );
        }

        return $query->limit( $limit )->get()->map( fn ( Model $model ): array => $this->toOption( $model ) )->all();
    }

    /**
     * {@inheritDoc}
     */
    public function find( array $ids ): array
    {
        $ids = array_values( array_filter( $ids, static fn ( mixed $id ): bool => is_int( $id ) || ( is_string( $id ) && '' !== $id ) ) );

        if ( [] === $ids ) {
            return [];
        }

        $query = $this->query();

        return $query->whereKey( $ids )->get()->map( fn ( Model $model ): array => $this->toOption( $model ) )->all();
    }

    /**
     * The base query.
     *
     * @since 1.0.0
     *
     * @return Builder<Model>
     */
    abstract protected function query(): Builder;

    /**
     * Narrows the query to a search term.
     *
     * @since 1.0.0
     *
     * Use {@see self::whereLike()} for each column so wildcards in the term
     * match literally.
     *
     * @param  Builder<Model>  $query  The query.
     * @param  string          $like   The escaped `%term%` pattern.
     *
     * @return void
     */
    abstract protected function applySearch( Builder $query, string $like ): void;

    /**
     * One model as an option.
     *
     * @since 1.0.0
     *
     * @param  Model  $model  The model.
     *
     * @return array{id: int|string, name: string, description: string|null}
     */
    abstract protected function toOption( Model $model ): array;

    /**
     * Adds an `{column} LIKE ? ESCAPE '!'` clause, joined with OR when asked.
     *
     * @since 1.0.0
     *
     * @param  Builder<Model>  $query   The query.
     * @param  string          $column  The column.
     * @param  string          $like    The escaped pattern.
     * @param  string          $boolean `and` or `or`.
     *
     * @return void
     */
    protected function whereLike( Builder $query, string $column, string $like, string $boolean = 'and' ): void
    {
        $query->whereRaw(
            $query->getQuery()->getGrammar()->wrap( $query->qualifyColumn( $column ) ) . " LIKE ? ESCAPE '" . self::LIKE_ESCAPE . "'",
            [ $like ],
            $boolean,
        );
    }
}
