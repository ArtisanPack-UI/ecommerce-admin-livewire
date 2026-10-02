<?php

/**
 * Icon choices for icon pickers.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use ReflectionClass;
use Throwable;

/**
 * The icon names an icon picker offers when `artisanpack-ui/icons` is
 * installed (spec §4.2): the outline Heroicons the admin already renders
 * (`o-…`), plus every SVG in the icon sets configured under
 * `artisanpack.icons.sets` (`{prefix}.{name}`, the form `x-artisanpack-icon`
 * resolves to the blade-icons name `{prefix}-{name}`).
 *
 * {@see self::exists()} checks a stored name still resolves, so a list can
 * skip an icon that would otherwise throw while rendering.
 *
 * livewire-ui-components has no icon picker, so screens feed these names to
 * `x-artisanpack-choices-offline`. Without the icons package they fall back
 * to a plain text input.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class IconChoices
{
    /**
     * Class probed to detect the icons package.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PROBE = 'ArtisanPackUI\\Icons\\IconsServiceProvider';

    /**
     * Overrides detection in tests.
     *
     * @since 1.0.0
     *
     * @var bool|null
     */
    private static ?bool $fake = null;

    /**
     * The options, built once per process.
     *
     * @since 1.0.0
     *
     * @var array<int, array{id: string, name: string}>|null
     */
    private static ?array $options = null;

    /**
     * Whether each name resolved, per process.
     *
     * @since 1.0.0
     *
     * @var array<string, bool>
     */
    private static array $resolved = [];

    /**
     * Whether the icons package is installed.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return self::$fake ?? class_exists( self::PROBE );
    }

    /**
     * Forces detection on or off; null restores real detection.
     *
     * @since 1.0.0
     *
     * @param  bool|null  $available  Whether to report the package as installed.
     *
     * @return void
     */
    public static function fake( ?bool $available ): void
    {
        self::$options  = null;
        self::$resolved = [];

        self::$fake = $available;
    }

    /**
     * Icon options, sorted by name, with `$current` kept when it is not in
     * the list.
     *
     * @since 1.0.0
     *
     * @param  string|null  $current  The value already chosen.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function options( ?string $current = null ): array
    {
        self::$options ??= self::build();

        $options = self::$options;

        if ( null !== $current && '' !== $current && ! in_array( $current, array_column( $options, 'id' ), true ) ) {
            array_unshift( $options, [ 'id' => $current, 'name' => $current ] );
        }

        return $options;
    }

    /**
     * The blade-icons name `x-artisanpack-icon` renders for `$name`: a dotted
     * name becomes `{prefix}-{name}`, anything else is a Heroicon.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Icon name as stored.
     *
     * @return string
     */
    public static function resolvedName( string $name ): string
    {
        return str_contains( $name, '.' ) ? str_replace( '.', '-', $name ) : 'heroicon-' . $name;
    }

    /**
     * Whether `x-artisanpack-icon` can render `$name` (blade-icons finds the
     * SVG), checked once per name.
     *
     * @since 1.0.0
     *
     * @param  string|null  $name  Icon name as stored.
     *
     * @return bool
     */
    public static function exists( ?string $name ): bool
    {
        $name = trim( (string) $name );

        if ( '' === $name ) {
            return false;
        }

        if ( ! array_key_exists( $name, self::$resolved ) ) {
            try {
                svg( self::resolvedName( $name ) );
                self::$resolved[ $name ] = true;
            } catch ( Throwable ) {
                self::$resolved[ $name ] = false;
            }
        }

        return self::$resolved[ $name ];
    }

    /**
     * Collects the icon names.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function build(): array
    {
        $names = [];

        try {
            if ( class_exists( BladeHeroiconsServiceProvider::class ) ) {
                $directory = dirname( (string) ( new ReflectionClass( BladeHeroiconsServiceProvider::class ) )->getFileName(), 2 ) . '/resources/svg';
                $names     = array_merge( $names, self::svgNames( $directory, '', 'o-' ) );
            }

            foreach ( (array) config( 'artisanpack.icons.sets', [] ) as $set ) {
                if ( is_array( $set ) && isset( $set['path'], $set['prefix'] ) && is_string( $set['path'] ) && is_string( $set['prefix'] ) ) {
                    $names = array_merge( $names, self::svgNames( $set['path'], $set['prefix'] . '.' ) );
                }
            }
        } catch ( Throwable $exception ) {
            report( $exception );
        }

        $names = array_values( array_unique( $names ) );
        sort( $names );

        return array_map( static fn ( string $name ): array => [ 'id' => $name, 'name' => $name ], $names );
    }

    /**
     * The icon names of the SVGs in a directory.
     *
     * @since 1.0.0
     *
     * @param  string  $directory  Directory.
     * @param  string  $prefix     Prefix added to each name.
     * @param  string  $only       Keep only files starting with this.
     *
     * @return array<int, string>
     */
    private static function svgNames( string $directory, string $prefix, string $only = '' ): array
    {
        $files = is_dir( $directory ) ? (array) glob( rtrim( $directory, '/' ) . '/*.svg' ) : [];
        $names = [];

        foreach ( $files as $file ) {
            $name = basename( (string) $file, '.svg' );

            if ( '' === $only || str_starts_with( $name, $only ) ) {
                $names[] = $prefix . $name;
            }
        }

        return $names;
    }
}
