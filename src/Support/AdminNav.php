<?php

/**
 * Admin navigation.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * The single source of the admin navigation (spec §5.4).
 *
 * Both the standalone layout and the cms-framework menu render from here.
 * Each entry has the engine's `AdminMenuRegistry` shape:
 * `{ key, label, icon, route, position, permission, badge? }`, plus the
 * `section` it belongs to and optional route `parameters`.
 *
 * - An entry is visible when the user holds its `permission` and its route
 *   is registered, so entries for screens that have not shipped stay hidden.
 * - A null `permission` (the dashboard) is visible to anyone who can reach
 *   the admin, but never grants access on its own.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class AdminNav
{
    /**
     * The route-name prefix every admin route shares.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ROUTE_PREFIX = 'artisanpack.ecommerce.admin.';

    /**
     * The section for entries that sit above every other section.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const TOP = 'top';

    /**
     * The engine registry satellites add nav entries to.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const MENU_REGISTRY = 'ArtisanPackUI\\Ecommerce\\Registries\\AdminMenuRegistry';

    /**
     * The navigation sections.
     *
     * Satellites add sections through the `ap.ecommerceAdminLivewire.nav.sections`
     * filter. An entry whose section is unknown sits in the top section.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string|null, position: int}>
     */
    public static function sections(): array
    {
        return (array) applyFilters( 'ap.ecommerceAdminLivewire.nav.sections', [
            self::TOP       => [ 'label' => null, 'position' => 0 ],
            'orders'        => [ 'label' => __( 'Orders' ), 'position' => 10 ],
            'catalog'       => [ 'label' => __( 'Catalog' ), 'position' => 20 ],
            'customers'     => [ 'label' => __( 'Customers' ), 'position' => 30 ],
            'marketing'     => [ 'label' => __( 'Marketing' ), 'position' => 40 ],
            'reports'       => [ 'label' => __( 'Reports' ), 'position' => 50 ],
            'configuration' => [ 'label' => __( 'Configuration' ), 'position' => 60 ],
        ] );
    }

    /**
     * Every navigation entry, sorted by section and position.
     *
     * Core entries are merged with those satellites add to the engine's
     * `AdminMenuRegistry` (when the engine ships it), then passed through the
     * `ap.ecommerceAdminLivewire.nav.items` filter. A later entry replaces an
     * earlier one with the same `key`.
     *
     * @since 1.0.0
     *
     * @return array<int, array{key: string, label: string, icon: string, route: string, parameters: array<string, mixed>, position: int, permission: string|null, section: string, badge: string|null}>
     */
    public static function items(): array
    {
        $sections = self::sections();
        $items    = [];

        $raw = (array) applyFilters( 'ap.ecommerceAdminLivewire.nav.items', [ ...self::coreItems(), ...self::registryItems() ] );

        foreach ( $raw as $item ) {
            if ( ! is_array( $item ) || ! isset( $item['key'], $item['label'], $item['route'] ) ) {
                continue;
            }

            $item['permission'] = self::permission( $item['permission'] ?? null, (string) $item['key'] );

            if ( false === $item['permission'] ) {
                continue;
            }

            $items[ (string) $item['key'] ] = self::normalize( $item );
        }

        $items = array_values( $items );

        usort( $items, static fn ( array $a, array $b ): int => [
            $sections[ $a['section'] ]['position'] ?? PHP_INT_MAX,
            $a['position'],
        ] <=> [
            $sections[ $b['section'] ]['position'] ?? PHP_INT_MAX,
            $b['position'],
        ] );

        return $items;
    }

    /**
     * The entries the user may see.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user  The user.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function visibleItems( ?Authenticatable $user ): array
    {
        if ( ! self::canAccess( $user ) ) {
            return [];
        }

        return array_values( array_filter(
            self::items(),
            static fn ( array $item ): bool => Route::has( $item['route'] )
                && ( null === $item['permission'] || Authorization::allows( $user, $item['permission'] ) ),
        ) );
    }

    /**
     * The visible entries grouped by section, with empty sections dropped.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user  The user.
     *
     * @return array<string, array{label: string|null, items: array<int, array<string, mixed>>}>
     */
    public static function grouped( ?Authenticatable $user ): array
    {
        $sections = self::sections();
        $grouped  = [];

        foreach ( self::visibleItems( $user ) as $item ) {
            $grouped[ $item['section'] ] ??= [
                'label' => $sections[ $item['section'] ]['label'] ?? null,
                'items' => [],
            ];

            $grouped[ $item['section'] ]['items'][] = $item;
        }

        return $grouped;
    }

    /**
     * Whether the user may enter the admin at all.
     *
     * True when the user holds the permission of at least one entry. Route
     * registration is ignored here, so access does not depend on which
     * screens have shipped.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user  The user.
     *
     * @return bool
     */
    public static function canAccess( ?Authenticatable $user ): bool
    {
        if ( null === $user ) {
            return false;
        }

        foreach ( self::items() as $item ) {
            if ( null !== $item['permission'] && Authorization::allows( $user, $item['permission'] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The entry's URL, or `#` when its route is not registered.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $item  The entry.
     *
     * @return string
     */
    public static function url( array $item ): string
    {
        return Route::has( $item['route'] ) ? route( $item['route'], $item['parameters'] ?? [] ) : '#';
    }

    /**
     * Whether the entry is the current page.
     *
     * Matches the entry's route and any route nested under it, so
     * `products.edit` marks "Products" active.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $item  The entry.
     *
     * @return bool
     */
    public static function isActive( array $item ): bool
    {
        $route = (string) $item['route'];

        if ( request()->routeIs( $route ) ) {
            return true;
        }

        $base = preg_replace( '/\.(index|show)$/', '', $route );

        return $base !== $route && request()->routeIs( $base . '.*' );
    }

    /**
     * The entries this package ships.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    private static function coreItems(): array
    {
        $items = [
            [ 'key' => 'dashboard', 'section' => self::TOP, 'label' => __( 'Dashboard' ), 'icon' => 'o-home', 'route' => 'dashboard', 'position' => 10, 'permission' => null ],

            [ 'key' => 'orders', 'section' => 'orders', 'label' => __( 'Orders' ), 'icon' => 'o-shopping-bag', 'route' => 'orders.index', 'position' => 10, 'permission' => 'order.viewAny', 'badge' => 'orders-awaiting-fulfillment' ],
            [ 'key' => 'reviews', 'section' => 'orders', 'label' => __( 'Reviews' ), 'icon' => 'o-star', 'route' => 'reviews.index', 'position' => 20, 'permission' => 'review.viewAny', 'badge' => 'pending-reviews' ],

            [ 'key' => 'products', 'section' => 'catalog', 'label' => __( 'Products' ), 'icon' => 'o-cube', 'route' => 'products.index', 'position' => 10, 'permission' => 'product.viewAny' ],
            [ 'key' => 'categories', 'section' => 'catalog', 'label' => __( 'Categories' ), 'icon' => 'o-folder', 'route' => 'categories.index', 'position' => 20, 'permission' => 'product.viewAny' ],
            [ 'key' => 'tags', 'section' => 'catalog', 'label' => __( 'Tags' ), 'icon' => 'o-tag', 'route' => 'tags.index', 'position' => 30, 'permission' => 'product.viewAny' ],
            [ 'key' => 'inventory', 'section' => 'catalog', 'label' => __( 'Inventory' ), 'icon' => 'o-archive-box', 'route' => 'inventory.index', 'position' => 40, 'permission' => 'inventory.viewAny', 'badge' => 'low-stock' ],
            [ 'key' => 'digital-files', 'section' => 'catalog', 'label' => __( 'Digital files' ), 'icon' => 'o-arrow-down-tray', 'route' => 'digital-files.index', 'position' => 50, 'permission' => 'digitalFile.viewAny' ],
            [ 'key' => 'license-keys', 'section' => 'catalog', 'label' => __( 'License keys' ), 'icon' => 'o-key', 'route' => 'license-keys.index', 'position' => 60, 'permission' => 'licenseKey.view' ],

            [ 'key' => 'customers', 'section' => 'customers', 'label' => __( 'Customers' ), 'icon' => 'o-users', 'route' => 'customers.index', 'position' => 10, 'permission' => 'customer.viewAny' ],

            [ 'key' => 'promotions', 'section' => 'marketing', 'label' => __( 'Promotions' ), 'icon' => 'o-megaphone', 'route' => 'promotions.index', 'position' => 10, 'permission' => 'promotion.viewAny' ],

            [ 'key' => 'reports', 'section' => 'reports', 'label' => __( 'Reports' ), 'icon' => 'o-chart-bar', 'route' => 'reports.show', 'parameters' => [ 'report' => 'sales-over-time' ], 'position' => 10, 'permission' => 'report.view' ],

            [ 'key' => 'shipping', 'section' => 'configuration', 'label' => __( 'Shipping' ), 'icon' => 'o-truck', 'route' => 'shipping.index', 'position' => 10, 'permission' => 'shippingZone.viewAny' ],
            [ 'key' => 'tax', 'section' => 'configuration', 'label' => __( 'Tax' ), 'icon' => 'o-receipt-percent', 'route' => 'tax.index', 'position' => 20, 'permission' => 'taxRate.viewAny' ],
            [ 'key' => 'notifications', 'section' => 'configuration', 'label' => __( 'Notifications' ), 'icon' => 'o-bell', 'route' => 'notifications.index', 'position' => 30, 'permission' => 'notificationTemplate.viewAny' ],
            [ 'key' => 'webhooks', 'section' => 'configuration', 'label' => __( 'Webhooks' ), 'icon' => 'o-bolt', 'route' => 'webhooks.index', 'position' => 40, 'permission' => 'webhookSubscription.viewAny' ],
            [ 'key' => 'order-statuses', 'section' => 'configuration', 'label' => __( 'Order statuses' ), 'icon' => 'o-queue-list', 'route' => 'order-statuses.index', 'position' => 50, 'permission' => 'orderSubstatus.viewAny' ],
            [ 'key' => 'kanban-boards', 'section' => 'configuration', 'label' => __( 'Kanban boards' ), 'icon' => 'o-view-columns', 'route' => 'kanban-boards.index', 'position' => 60, 'permission' => 'kanbanBoard.viewAny' ],
            [ 'key' => 'settings', 'section' => 'configuration', 'label' => __( 'Settings' ), 'icon' => 'o-cog-6-tooth', 'route' => 'settings.show', 'parameters' => [ 'group' => 'general' ], 'position' => 70, 'permission' => 'settings.view' ],
        ];

        return array_map(
            static fn ( array $item ): array => [ 'route' => self::ROUTE_PREFIX . $item['route'] ] + $item,
            $items,
        );
    }

    /**
     * Entries satellites registered with the engine's `AdminMenuRegistry`.
     *
     * The registry is planned for engine 1.0 (engine issue #144); until it
     * exists the `ap.ecommerceAdminLivewire.nav.items` filter does the same job.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    private static function registryItems(): array
    {
        if ( ! class_exists( self::MENU_REGISTRY ) || ! app()->bound( self::MENU_REGISTRY ) ) {
            return [];
        }

        $registry = app( self::MENU_REGISTRY );

        if ( ! method_exists( $registry, 'all' ) ) {
            return [];
        }

        return array_values( array_map(
            static fn ( mixed $item ): array => is_object( $item ) && method_exists( $item, 'toArray' ) ? $item->toArray() : (array) $item,
            (array) $registry->all(),
        ) );
    }

    /**
     * Validates an entry's permission.
     *
     * Accepts `{resource}.{action}` and the Gate ability spelling
     * `ecommerce.{resource}.{action}`. Anything else is logged and the entry
     * dropped, so one malformed satellite entry cannot break the admin.
     *
     * @since 1.0.0
     *
     * @param  mixed   $permission  The raw permission.
     * @param  string  $key         The entry key, for the log message.
     *
     * @return false|string|null The ability, null for none, or false to drop the entry.
     */
    private static function permission( mixed $permission, string $key ): string|false|null
    {
        if ( null === $permission || '' === $permission ) {
            return null;
        }

        $ability = is_string( $permission ) ? preg_replace( '/^ecommerce\./', '', $permission ) : null;

        if ( is_string( $ability ) && 1 === preg_match( '/^[A-Za-z][A-Za-z0-9]*\.[A-Za-z][A-Za-z0-9-]*$/', $ability ) ) {
            return $ability;
        }

        Log::warning( sprintf( 'Ecommerce admin nav entry "%s" was skipped: its permission must be "{resource}.{action}".', $key ) );

        return false;
    }

    /**
     * Fills defaults.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $item  The raw entry.
     *
     * @return array{key: string, label: string, icon: string, route: string, parameters: array<string, mixed>, position: int, permission: string|null, section: string, badge: string|null}
     */
    private static function normalize( array $item ): array
    {
        return [
            'key'        => (string) $item['key'],
            'label'      => (string) $item['label'],
            'icon'       => (string) ( $item['icon'] ?? 'o-squares-2x2' ),
            'route'      => (string) $item['route'],
            'parameters' => (array) ( $item['parameters'] ?? [] ),
            'position'   => (int) ( $item['position'] ?? 100 ),
            'permission' => isset( $item['permission'] ) && '' !== $item['permission'] ? (string) $item['permission'] : null,
            'section'    => isset( $item['section'] ) && array_key_exists( (string) $item['section'], self::sections() ) ? (string) $item['section'] : self::TOP,
            'badge'      => isset( $item['badge'] ) ? (string) $item['badge'] : null,
        ];
    }
}
