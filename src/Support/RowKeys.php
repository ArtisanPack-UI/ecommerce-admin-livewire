<?php

/**
 * Stable keys for editable list rows.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use Illuminate\Support\Str;

/**
 * Gives each row of an editable, removable list a key that survives removing
 * or reordering its neighbours, so `wire:key` follows the row rather than
 * its position. The key lives in the row under {@see self::KEY} and is
 * dropped wherever the row is saved, because saving maps known fields only.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class RowKeys
{
    /**
     * The row key's array key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const KEY = '_uid';

    /**
     * A new row key.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function make(): string
    {
        return 'r' . Str::lower( Str::random( 10 ) );
    }

    /**
     * The row with a key, keeping the one it has.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $row  The row.
     *
     * @return array<string, mixed>
     */
    public static function tag( array $row ): array
    {
        if ( ! is_string( $row[ self::KEY ] ?? null ) || '' === $row[ self::KEY ] ) {
            $row[ self::KEY ] = self::make();
        }

        return $row;
    }

    /**
     * Every row keyed with {@see self::tag()}; non-array rows are kept as-is.
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $rows  The rows.
     *
     * @return array<int, mixed>
     */
    public static function tagAll( array $rows ): array
    {
        return array_map( static fn ( mixed $row ): mixed => is_array( $row ) ? self::tag( $row ) : $row, $rows );
    }

    /**
     * The `wire:key` suffix for a row: its key, or its position for a row
     * that has none.
     *
     * @since 1.0.0
     *
     * @param  mixed       $row    The row.
     * @param  int|string  $index  Its position.
     *
     * @return string
     */
    public static function of( mixed $row, int|string $index ): string
    {
        return is_array( $row ) && is_string( $row[ self::KEY ] ?? null ) ? $row[ self::KEY ] : 'i' . $index;
    }
}
