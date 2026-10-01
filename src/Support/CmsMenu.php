<?php

/**
 * cms-framework menu integration.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

/**
 * Adds the admin navigation to the cms-framework admin menu.
 *
 * Hooks `ap.cmsFramework.admin.menu`. The dashboard becomes a top-level
 * "Store" item and every other section becomes a menu section of its own.
 * Entries are filtered for the signed-in user here, so they carry no
 * `permission` key for cms-framework to re-check (the engine abilities are
 * not plain Gate abilities). An entry the host already has under the same
 * slug wins.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class CmsMenu
{
    /**
     * The filter cms-framework applies to its admin menu.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const FILTER = 'ap.cmsFramework.admin.menu';

    /**
     * The slug prefix for everything this package adds to the menu.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SLUG_PREFIX = 'ecommerce-';

    /**
     * Where the store sections start in the cms-framework menu order.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const BASE_ORDER = 40;

    /**
     * Subscribes to the cms-framework menu filter.
     *
     * Does nothing when cms-framework is absent, when the admin routes are
     * disabled, or when `admin.auto_register_cms_nav` is false.
     *
     * @since 1.0.0
     *
     * @return bool Whether the filter was added.
     */
    public static function subscribe(): bool
    {
        if ( ! CmsFramework::isInstalled() ) {
            return false;
        }

        if ( ! (bool) config( 'artisanpack.ecommerce-admin-livewire.admin.routes_enabled', true ) ) {
            return false;
        }

        if ( ! (bool) config( 'artisanpack.ecommerce-admin-livewire.admin.auto_register_cms_nav', true ) ) {
            return false;
        }

        addFilter( self::FILTER, static fn ( array $menu ): array => self::injectInto( $menu ) );

        return true;
    }

    /**
     * Merges the signed-in user's admin entries into a cms-framework menu,
     * then re-sorts it by `order`.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $menu  The cms-framework menu.
     *
     * @return array<string, mixed>
     */
    public static function injectInto( array $menu ): array
    {
        foreach ( self::entries() as $slug => $entry ) {
            $menu[ $slug ] = array_merge( $entry, $menu[ $slug ] ?? [] );
        }

        // cms-framework sorts before it applies this filter and not after.
        uasort( $menu, static fn ( mixed $a, mixed $b ): int => ( is_array( $a ) ? ( $a['order'] ?? 99 ) : 99 ) <=> ( is_array( $b ) ? ( $b['order'] ?? 99 ) : 99 ) );

        return $menu;
    }

    /**
     * The menu nodes for the signed-in user, keyed by slug.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public static function entries(): array
    {
        $nodes = [];

        foreach ( AdminNav::grouped( auth()->user() ) as $sectionKey => $section ) {
            if ( AdminNav::TOP === $sectionKey ) {
                foreach ( $section['items'] as $item ) {
                    $label = 'dashboard' === $item['key'] ? __( 'Store' ) : $item['label'];

                    $nodes[ self::SLUG_PREFIX . $item['key'] ] = self::item( $item, $label, self::BASE_ORDER );
                }

                continue;
            }

            $items = [];

            foreach ( $section['items'] as $item ) {
                $items[ self::SLUG_PREFIX . $item['key'] ] = self::item( $item, $item['label'], $item['position'] );
            }

            $nodes[ self::SLUG_PREFIX . $sectionKey ] = [
                'title' => $section['label'],
                'order' => self::BASE_ORDER + ( AdminNav::sections()[ $sectionKey ]['position'] ?? 90 ) / 10,
                'items' => $items,
            ];
        }

        return $nodes;
    }

    /**
     * One decorated cms-framework menu item.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $item   The AdminNav entry.
     * @param  string                $label  The label to show.
     * @param  float|int             $order  The menu order.
     *
     * @return array<string, mixed>
     */
    private static function item( array $item, string $label, int|float $order ): array
    {
        $node = [
            'slug'      => self::SLUG_PREFIX . $item['key'],
            'label'     => $label,
            'menuTitle' => $label,
            'title'     => $label,
            'url'       => AdminNav::url( $item ),
            'icon'      => $item['icon'],
            'iconId'    => $item['icon'],
            'order'     => $order,
            'external'  => false,
        ];

        if ( null !== $item['badge'] && null !== ( $count = NavBadges::count( $item['badge'] ) ) ) {
            $node['badge']      = $count;
            $node['badgeLabel'] = NavBadges::describe( $item['badge'], $count );
        }

        return $node;
    }
}
