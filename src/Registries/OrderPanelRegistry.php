<?php

/**
 * Order detail panel registry.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Registries;

use InvalidArgumentException;

/**
 * Panels other issues and satellites add to the order detail page (spec §8.5).
 *
 * ```php
 * app( OrderPanelRegistry::class )->register( 'subscription', 'subscriptions-order-panel', 'side', 30 );
 * ```
 *
 * Each panel is a registered Livewire component. It is mounted with
 * `[ 'order' => $order ]` and must authorize what it shows itself; the page
 * only guarantees the user may view the order. Panels sort by position,
 * then key.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class OrderPanelRegistry
{
    /**
     * The page's columns.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const COLUMNS = [ 'main', 'side' ];

    /**
     * The browser event a panel dispatches after it changes the order. The
     * order page and every panel listening for it re-render, so a panel
     * that shows order state should listen too:
     * `protected $listeners = [ OrderPanelRegistry::ORDER_UPDATED_EVENT => '$refresh' ];`
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ORDER_UPDATED_EVENT = 'ecommerce-admin-order-updated';

    /**
     * The registered panels, keyed by panel key.
     *
     * @since 1.0.0
     *
     * @var array<string, array{key: string, component: string, column: string, position: int}>
     */
    private array $panels = [];

    /**
     * Registers (or replaces) a panel.
     *
     * @since 1.0.0
     *
     * @param  string  $key        A unique key, e.g. `subscription`.
     * @param  string  $component  The Livewire component name.
     * @param  string  $column     `main` or `side`.
     * @param  int     $position   Lower renders first.
     *
     * @throws InvalidArgumentException When the key or component is blank or the column is unknown.
     *
     * @return void
     */
    public function register( string $key, string $component, string $column = 'main', int $position = 100 ): void
    {
        if ( '' === trim( $key ) || '' === trim( $component ) ) {
            throw new InvalidArgumentException( 'An order panel needs a key and a Livewire component.' );
        }

        if ( ! in_array( $column, self::COLUMNS, true ) ) {
            throw new InvalidArgumentException( sprintf( 'Order panel "%s" has unknown column "%s"; use "main" or "side".', $key, $column ) );
        }

        $this->panels[ $key ] = [
            'key'       => $key,
            'component' => $component,
            'column'    => $column,
            'position'  => $position,
        ];
    }

    /**
     * Removes a panel.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The panel key.
     *
     * @return void
     */
    public function unregister( string $key ): void
    {
        unset( $this->panels[ $key ] );
    }

    /**
     * Whether a panel is registered.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The panel key.
     *
     * @return bool
     */
    public function has( string $key ): bool
    {
        return isset( $this->panels[ $key ] );
    }

    /**
     * Every panel, keyed by panel key.
     *
     * @since 1.0.0
     *
     * @return array<string, array{key: string, component: string, column: string, position: int}>
     */
    public function all(): array
    {
        return $this->panels;
    }

    /**
     * The panels of one column, in order.
     *
     * @since 1.0.0
     *
     * @param  string  $column  `main` or `side`.
     *
     * @return array<int, array{key: string, component: string, column: string, position: int}>
     */
    public function forColumn( string $column ): array
    {
        $panels = array_values( array_filter( $this->panels, static fn ( array $panel ): bool => $column === $panel['column'] ) );

        usort( $panels, static fn ( array $a, array $b ): int => [ $a['position'], $a['key'] ] <=> [ $b['position'], $b['key'] ] );

        return $panels;
    }
}
