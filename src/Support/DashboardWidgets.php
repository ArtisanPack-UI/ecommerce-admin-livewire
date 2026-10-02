<?php

/**
 * Dashboard widget list.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The widgets the dashboard renders (spec §7.1).
 *
 * Each widget is `{ key, label, view?, component?, permission, position,
 * width }`:
 *
 * - `view` — a Blade view rendered with `$widget` and `$dashboard` (the
 *   data the core widgets share);
 * - `component` — a Livewire component name, mounted with no parameters;
 * - `permission` — an engine ability (`order.viewAny`); the widget is
 *   hidden when the user lacks it. `null` shows it to everyone who can
 *   open the admin;
 * - `width` — `full` or `half` (half-width widgets sit side by side on
 *   large screens).
 *
 * Other packages add, replace (same `key`), or remove widgets through the
 * `ap.ecommerceAdminLivewire.dashboard.widgets` filter, which receives the
 * widget list and the current user.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class DashboardWidgets
{
    /**
     * The extension filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const FILTER = 'ap.ecommerceAdminLivewire.dashboard.widgets';

    /**
     * The KPI row.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const KPIS = 'kpis';

    /**
     * The 30-day sales sparkline.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SALES = 'sales';

    /**
     * The five lowest-stock items.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const LOW_STOCK = 'low-stock';

    /**
     * The ten most recent orders.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const RECENT_ORDERS = 'recent-orders';

    /**
     * The widgets the user may see, in position order.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user  The current user.
     *
     * @return array<int, array{key: string, label: string, view: string|null, component: string|null, permission: string|null, position: int, width: string}>
     */
    public static function visible( ?Authenticatable $user ): array
    {
        return array_values( array_filter(
            self::all( $user ),
            static fn ( array $widget ): bool => null === $widget['permission'] || Authorization::allows( $user, $widget['permission'] ),
        ) );
    }

    /**
     * Every widget after the extension filter, normalized and sorted.
     *
     * Entries without a `key`, or with neither a `view` nor a `component`,
     * are dropped. A later entry with the same key replaces an earlier one.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user  The current user.
     *
     * @return array<int, array{key: string, label: string, view: string|null, component: string|null, permission: string|null, position: int, width: string}>
     */
    public static function all( ?Authenticatable $user ): array
    {
        $widgets = [];

        foreach ( (array) applyFilters( self::FILTER, self::core(), $user ) as $widget ) {
            if ( ! is_array( $widget ) || ! isset( $widget['key'] ) || ( empty( $widget['view'] ) && empty( $widget['component'] ) ) ) {
                continue;
            }

            $permission = $widget['permission'] ?? null;

            $widgets[ (string) $widget['key'] ] = [
                'key'        => (string) $widget['key'],
                'label'      => (string) ( $widget['label'] ?? $widget['key'] ),
                'view'       => empty( $widget['view'] ) ? null : (string) $widget['view'],
                'component'  => empty( $widget['view'] ) ? (string) $widget['component'] : null,
                'permission' => is_string( $permission ) && '' !== $permission ? $permission : null,
                'position'   => (int) ( $widget['position'] ?? 100 ),
                'width'      => 'half' === ( $widget['width'] ?? 'full' ) ? 'half' : 'full',
            ];
        }

        uasort( $widgets, static fn ( array $a, array $b ): int => $a['position'] <=> $b['position'] );

        return array_values( $widgets );
    }

    /**
     * The core widgets.
     *
     * The KPI row has no permission of its own: each KPI in it is gated by
     * the ability of the screen it links to.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    public static function core(): array
    {
        $views = 'ecommerce-admin::livewire.dashboard.';

        return [
            [ 'key' => self::KPIS, 'label' => __( 'Key figures' ), 'view' => $views . 'kpis', 'permission' => null, 'position' => 10, 'width' => 'full' ],
            [ 'key' => self::SALES, 'label' => __( 'Sales, last 30 days' ), 'view' => $views . 'sales', 'permission' => 'report.view', 'position' => 20, 'width' => 'half' ],
            [ 'key' => self::LOW_STOCK, 'label' => __( 'Lowest stock' ), 'view' => $views . 'low-stock', 'permission' => 'inventory.viewAny', 'position' => 30, 'width' => 'half' ],
            [ 'key' => self::RECENT_ORDERS, 'label' => __( 'Recent orders' ), 'view' => $views . 'recent-orders', 'permission' => 'order.viewAny', 'position' => 40, 'width' => 'full' ],
        ];
    }
}
