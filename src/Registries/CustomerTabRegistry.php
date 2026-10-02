<?php

/**
 * Customer detail tab registry.
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
 * The tabs on the customer detail page (spec §7.4, §8.5).
 *
 * Each tab is a Livewire component mounted with the customer. The package
 * registers its own tabs (orders, addresses, notification preferences,
 * notes, activity) at positions 10–50; satellites register theirs after
 * them (default position 100), for example `ecommerce-loyalty-points`:
 *
 * ```php
 * app( CustomerTabRegistry::class )->register(
 *     'points',
 *     fn (): string => __( 'Points' ),
 *     'loyalty-points-customer-tab',
 * );
 * ```
 *
 * A satellite may also unregister or replace a built-in tab by key. The
 * component receives the customer under `$parameter` (`customer` unless
 * given) and should authorize what it shows itself; `$ability`, when set,
 * also hides the tab from users who lack it.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class CustomerTabRegistry
{
    /**
     * The event a tab dispatches after changing the customer, so the page
     * header (stats, details) refreshes.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CUSTOMER_UPDATED_EVENT = 'ecommerce-admin-customer-updated';

    /**
     * Registered tabs, keyed by tab key.
     *
     * @since 1.0.0
     *
     * @var array<string, array{key: string, label: Closure(): string|string, component: string, position: int, parameter: string, ability: string|null}>
     */
    private array $tabs = [];

    /**
     * Registers (or replaces) a tab.
     *
     * @since 1.0.0
     *
     * @param  string                  $key        Unique key; also the tab's URL fragment.
     * @param  Closure(): string|string  $label      The label, or a closure returning it (so it is translated per request).
     * @param  string                  $component  The Livewire component name or class.
     * @param  int                     $position   Lower positions come first.
     * @param  string                  $parameter  The mount parameter the customer is passed as.
     * @param  string|null             $ability    An ecommerce ability (`{resource}.{action}`) the tab needs.
     *
     * @throws InvalidArgumentException When the key, component, or parameter is blank.
     *
     * @return void
     */
    public function register( string $key, string|Closure $label, string $component, int $position = 100, string $parameter = 'customer', ?string $ability = null ): void
    {
        if ( 1 !== preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $key ) || '' === trim( $component ) || '' === trim( $parameter ) ) {
            throw new InvalidArgumentException( 'A customer tab needs a kebab-case key, a Livewire component, and a parameter name.' );
        }

        $this->tabs[ $key ] = [
            'key'       => $key,
            'label'     => $label,
            'component' => $component,
            'position'  => $position,
            'parameter' => $parameter,
            'ability'   => null === $ability || '' === $ability ? null : $ability,
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
     * Every tab in display order, with labels resolved.
     *
     * @since 1.0.0
     *
     * @return array<int, array{key: string, label: string, component: string, position: int, parameter: string, ability: string|null}>
     */
    public function all(): array
    {
        $tabs = array_map( static function ( array $tab ): array {
            $tab['label'] = (string) ( $tab['label'] instanceof Closure ? ( $tab['label'] )() : $tab['label'] );

            return $tab;
        }, array_values( $this->tabs ) );

        usort( $tabs, static fn ( array $a, array $b ): int => [ $a['position'], $a['key'] ] <=> [ $b['position'], $b['key'] ] );

        return $tabs;
    }
}
