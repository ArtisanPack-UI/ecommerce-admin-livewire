<?php

/**
 * Where digital files may live.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use Closure;

/**
 * The engine's allowed digital disks and the path rule it enforces, shared
 * by the digital product panel and the digital files screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class DigitalDisks
{
    /**
     * The engine's allowed digital disks.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public static function allowed(): array
    {
        return array_values( array_map( 'strval', (array) config( 'artisanpack.ecommerce.digital.allowed_disks', [ 'local' ] ) ) );
    }

    /**
     * The default digital disk.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function default(): string
    {
        $disk = (string) config( 'artisanpack.ecommerce.digital.disk', 'local' );

        return in_array( $disk, self::allowed(), true ) ? $disk : ( self::allowed()[0] ?? 'local' );
    }

    /**
     * The allowed disks as select options.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map( static fn ( string $disk ): array => [ 'id' => $disk, 'name' => $disk ], self::allowed() );
    }

    /**
     * Rule: a path inside the disk (no `..`, not absolute), as the engine
     * requires.
     *
     * @since 1.0.0
     *
     * @return Closure
     */
    public static function relativePath(): Closure
    {
        return static function ( string $attribute, mixed $value, Closure $fail ): void {
            if ( is_string( $value ) && ( str_contains( $value, '..' ) || str_starts_with( $value, '/' ) || str_contains( $value, "\0" ) ) ) {
                $fail( __( 'The :attribute must be a relative path inside the disk.' ) );
            }
        };
    }
}
