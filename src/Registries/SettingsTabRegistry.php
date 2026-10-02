<?php

/**
 * Settings tab registry.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Registries;

use Closure;
use InvalidArgumentException;

/**
 * Custom tabs on the settings screen (spec §7.9, §8.5).
 *
 * Every group in the engine's `SettingsRegistry` gets a tab with a form
 * built from its definitions, so a satellite that only needs fields adds
 * them to the engine registry and is done. A satellite that needs its own
 * screen registers a Livewire component here instead:
 *
 * ```php
 * app( SettingsTabRegistry::class )->register(
 *     'paypal',
 *     fn (): string => __( 'PayPal' ),
 *     'paypal-settings-tab',
 *     60,
 * );
 * ```
 *
 * The tab appears at `/settings/{key}`. A key that matches an engine group
 * replaces that group's generated form. The component is mounted with no
 * parameters and must authorize what it shows and saves itself (the screen
 * has already checked `settings.view`).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class SettingsTabRegistry
{
    /**
     * Registered tabs, keyed by tab key.
     *
     * @since 1.0.0
     *
     * @var array<string, array{key: string, label: Closure(): string|string, component: string, position: int}>
     */
    private array $tabs = [];

    /**
     * Registers (or replaces) a tab.
     *
     * @since 1.0.0
     *
     * @param  string                    $key        Unique kebab-case key; also the URL segment.
     * @param  Closure(): string|string  $label      The label, or a closure returning it (so it is translated per request).
     * @param  string                    $component  The Livewire component name or class.
     * @param  int                       $position   Lower positions come first. The engine's groups sit at 10–100.
     *
     * @throws InvalidArgumentException When the key or component is blank.
     *
     * @return void
     */
    public function register( string $key, string|Closure $label, string $component, int $position = 100 ): void
    {
        if ( 1 !== preg_match( '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/D', $key ) || '' === trim( $component ) ) {
            throw new InvalidArgumentException( 'A settings tab needs a kebab-case key and a Livewire component.' );
        }

        $this->tabs[ $key ] = [
            'key'       => $key,
            'label'     => $label,
            'component' => $component,
            'position'  => $position,
        ];
    }

    /**
     * Removes a tab.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Tab key.
     *
     * @return void
     */
    public function unregister( string $key ): void
    {
        unset( $this->tabs[ $key ] );
    }

    /**
     * Whether a tab is registered.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Tab key.
     *
     * @return bool
     */
    public function has( string $key ): bool
    {
        return isset( $this->tabs[ $key ] );
    }

    /**
     * One tab, with its label resolved.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Tab key.
     *
     * @return array{key: string, label: string, component: string, position: int}|null
     */
    public function get( string $key ): ?array
    {
        return isset( $this->tabs[ $key ] ) ? self::resolve( $this->tabs[ $key ] ) : null;
    }

    /**
     * Every tab in display order, with labels resolved.
     *
     * @since 1.0.0
     *
     * @return array<int, array{key: string, label: string, component: string, position: int}>
     */
    public function all(): array
    {
        $tabs = array_map( self::resolve( ... ), array_values( $this->tabs ) );

        usort( $tabs, static fn ( array $a, array $b ): int => [ $a['position'], $a['key'] ] <=> [ $b['position'], $b['key'] ] );

        return $tabs;
    }

    /**
     * Resolves a tab's label.
     *
     * @since 1.0.0
     *
     * @param  array{key: string, label: Closure(): string|string, component: string, position: int}  $tab  The tab.
     *
     * @return array{key: string, label: string, component: string, position: int}
     */
    private static function resolve( array $tab ): array
    {
        $tab['label'] = (string) ( $tab['label'] instanceof Closure ? ( $tab['label'] )() : $tab['label'] );

        return $tab;
    }
}
