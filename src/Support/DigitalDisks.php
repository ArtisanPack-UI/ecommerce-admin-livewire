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

use ArtisanPackUI\Ecommerce\Models\DigitalFile;
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
     * Media disks faked by {@see self::fakeMediaDisks()}.
     *
     * @since 1.0.0
     *
     * @var array<int, string>|null
     */
    private static ?array $fakeMediaDisks = null;

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

    /**
     * Pretends media items live on these disks, in tests: `[ id => disk ]`.
     * Pass null to look them up again.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>|null  $disks  Disk by media id.
     *
     * @return void
     */
    public static function fakeMediaDisks( ?array $disks ): void
    {
        self::$fakeMediaDisks = $disks;
    }

    /**
     * The disk a media-library item is stored on (the library's default is
     * `public`), or null when the item, or the library, is missing.
     *
     * @since 1.0.0
     *
     * @param  int  $id  The media id.
     *
     * @return string|null
     */
    public static function mediaDisk( int $id ): ?string
    {
        if ( null !== self::$fakeMediaDisks ) {
            return self::$fakeMediaDisks[ $id ] ?? null;
        }

        $model = DigitalFile::MEDIA_MODEL;

        if ( ! class_exists( $model ) ) {
            return null;
        }

        $media = $model::query()->find( $id );

        return null === $media ? null : (string) ( $media->disk ?? 'public' );
    }

    /**
     * A rule requiring a media item that exists and is stored on one of the
     * allowed (private) disks, so a paid file is never served from a
     * guessable public URL that skips download limits and signing.
     *
     * @since 1.0.0
     *
     * @return Closure(string, mixed, Closure): void
     */
    public static function privateMedia(): Closure
    {
        return static function ( string $attribute, mixed $value, Closure $fail ): void {
            if ( ! is_numeric( $value ) ) {
                return;
            }

            $disk = self::mediaDisk( (int) $value );

            if ( null === $disk ) {
                $fail( __( 'That media item can\'t be found.' ) );
            } elseif ( ! in_array( $disk, self::allowed(), true ) ) {
                $fail( __( 'That media item is on a public disk. Digital products must live on a private disk.' ) );
            }
        };
    }
}
