<?php

/**
 * Command palette.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Spotlight;

use ArtisanPack\LivewireUiComponents\Support\IconRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Composer\InstalledVersions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The admin's command palette (spec §8.1).
 *
 * The palette is livewire-ui-components' `x-artisanpack-spotlight`, mounted
 * once in the standalone layout. Results come from this class, registered
 * on the component library's `ap.livewireUiComponents.spotlightCommands`
 * filter, so they are appended to whatever the host's own spotlight class
 * returns and a host spotlight keeps working (including one mounted by a
 * CMS layout).
 *
 * Each provider is skipped unless the user holds its ability, runs one
 * query, and returns at most `spotlight.limit` results. The core providers
 * are orders, products, customers, promotions, coupons, and actions; add
 * or replace providers with the `ap.ecommerceAdminLivewire.spotlight.providers`
 * filter, which receives the providers keyed by name and the user.
 *
 * Users who cannot open any admin screen get nothing from this class, and
 * each user is limited to {@see self::SEARCHES_PER_MINUTE} searches a
 * minute.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class AdminSpotlight
{
    /**
     * The component library's results filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const COMMANDS_FILTER = 'ap.livewireUiComponents.spotlightCommands';

    /**
     * The component library's shared spotlight route, which gets no admin
     * results.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SHARED_ROUTE = 'artisanpack.spotlight';

    /**
     * The admin's own spotlight route (under the admin middleware).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ADMIN_ROUTE = AdminNav::ROUTE_PREFIX . 'spotlight';

    /**
     * The provider extension filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PROVIDERS_FILTER = 'ap.ecommerceAdminLivewire.spotlight.providers';

    /**
     * Searches allowed per user per minute. The component library's route
     * has no throttle of its own.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const SEARCHES_PER_MINUTE = 120;

    /**
     * Upper bound for `spotlight.limit`.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_LIMIT = 20;

    /**
     * The design-system icon for each Heroicon the providers use. A Heroicon
     * with no counterpart in the design system's registry gets no icon.
     *
     * @since 2.0.0
     *
     * @var array<string, string>
     */
    public const HEROICON_ICONS = [
        'o-archive-box'               => 'archive',
        'o-arrow-down-tray'           => 'file-down',
        'o-arrow-right'               => 'arrow-right',
        'o-banknotes'                 => 'banknote',
        'o-bell'                      => 'bell',
        'o-bolt'                      => 'zap',
        'o-chart-bar'                 => 'chart-column',
        'o-chat-bubble-left-ellipsis' => 'message-square',
        'o-cog-6-tooth'               => 'settings',
        'o-cube'                      => 'box',
        'o-document-text'             => 'file-text',
        'o-envelope'                  => 'mail',
        'o-folder'                    => 'folder',
        'o-home'                      => 'house',
        'o-key'                       => 'key',
        'o-megaphone'                 => 'megaphone',
        'o-pencil'                    => 'pencil',
        'o-plus'                      => 'plus',
        'o-queue-list'                => 'list',
        'o-receipt-percent'           => 'percent',
        'o-receipt-refund'            => 'rotate-ccw',
        'o-shopping-bag'              => 'shopping-bag',
        'o-squares-2x2'               => 'layout-grid',
        'o-star'                      => 'star',
        'o-tag'                       => 'tag',
        'o-ticket'                    => 'ticket',
        'o-trash'                     => 'trash',
        'o-truck'                     => 'truck',
        'o-user'                      => 'user',
        'o-users'                     => 'users',
        'o-view-columns'              => 'columns-3',
    ];

    /**
     * Whether the palette is enabled.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function enabled(): bool
    {
        return (bool) config( 'artisanpack.ecommerce-admin-livewire.spotlight.enabled', true );
    }

    /**
     * Whether a page should mount the palette and its button: it is
     * enabled, the component library's search route exists, and the user
     * can open the admin.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function mountable(): bool
    {
        return self::enabled()
            && Route::has( 'artisanpack.spotlight' )
            && AdminNav::canAccess( auth()->user() );
    }

    /**
     * The keyboard shortcut, in Alpine key-modifier form (`meta.k`).
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function shortcut(): string
    {
        $shortcut = (string) config( 'artisanpack.ecommerce-admin-livewire.spotlight.shortcut', 'meta.k' );

        return 1 === preg_match( '/^[a-z0-9]+(\.[a-z0-9-]+)*$/i', $shortcut ) ? $shortcut : 'meta.k';
    }

    /**
     * The `aria-keyshortcuts` value for the shortcut (`Meta+K`).
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function ariaShortcut(): string
    {
        return implode( '+', array_map( static fn ( string $key ): string => ucfirst( $key ), explode( '.', self::shortcut() ) ) );
    }

    /**
     * The shortcut in the design-system Spotlight's spelling (`mod+k`).
     *
     * The configured value keeps the 1.x Alpine form (`meta.k`, `ctrl.slash`).
     * `meta` becomes `mod`, so the palette opens with ⌘ on Apple platforms and
     * Ctrl elsewhere, and Alpine's named keys become the characters they name.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public static function paletteShortcut(): string
    {
        $keys = [
            'slash'  => '/',
            'period' => '.',
            'comma'  => ',',
            'minus'  => '-',
            'equal'  => '=',
        ];

        $parts = array_map(
            static fn ( string $part ): string => match ( true ) {
                in_array( $part, [ 'meta', 'cmd', 'super' ], true ) => 'mod',
                default                                             => $keys[ $part ] ?? $part,
            },
            explode( '.', strtolower( self::shortcut() ) ),
        );

        return implode( '+', $parts );
    }

    /**
     * Results per provider.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public static function limit(): int
    {
        return max( 1, min( self::MAX_LIMIT, (int) config( 'artisanpack.ecommerce-admin-livewire.spotlight.limit', 5 ) ) );
    }

    /**
     * The `ap.livewireUiComponents.spotlightCommands` callback.
     *
     * @since 1.0.0
     *
     * @param  mixed  $commands  Results so far.
     * @param  mixed  $user      The current user.
     *
     * @return array<int|string, mixed>
     */
    public static function appendTo( mixed $commands, mixed $user = null ): array
    {
        $commands = (array) $commands;
        $user ??= auth()->user();
        $search   = request()->query( 'search' );

        // The component library's shared route runs only the `web`
        // middleware, so it would skip the host's 2FA or verified checks;
        // admin results come only from the admin's own route, which groups
        // them by provider itself (see AdminScreenController::spotlight()).
        if ( ! self::enabled() || ! $user instanceof Authenticatable || ! is_string( $search ) || request()->routeIs( self::SHARED_ROUTE, self::ADMIN_ROUTE ) ) {
            return $commands;
        }

        return [ ...array_values( $commands ), ...self::search( $search, $user ) ];
    }

    /**
     * Every provider's results for a search, in provider order.
     *
     * @since 1.0.0
     *
     * @param  string           $search  The raw search text.
     * @param  Authenticatable  $user    The current user.
     *
     * @return array<int, array{name: string, description: string|null, link: string, icon: string|null}>
     */
    public static function search( string $search, Authenticatable $user ): array
    {
        return array_merge( [], ...array_values( self::resultsByProvider( $search, $user ) ) );
    }

    /**
     * Every provider's results for a search, as the palette's groups: one
     * per provider with results, labeled with what it finds, in provider
     * order.
     *
     * @since 2.0.0
     *
     * @param  string              $search  The raw search text.
     * @param  Authenticatable     $user    The current user.
     * @param  array<int, string>  $except  Provider keys to leave out.
     *
     * @return array<int, array{id: string, label: string, items: array<int, array{name: string, description: string|null, link: string, icon: string|null}>}>
     */
    public static function groups( string $search, Authenticatable $user, array $except = [] ): array
    {
        $providers = self::providers( $user );
        $groups    = [];

        foreach ( self::resultsByProvider( $search, $user, $except ) as $key => $results ) {
            if ( [] === $results ) {
                continue;
            }

            $groups[] = [
                'id'    => $key,
                'label' => self::groupLabel( $key, $providers[ $key ] ),
                'items' => $results,
            ];
        }

        return $groups;
    }

    /**
     * The palette's Actions group for an empty search: every action the
     * user may run, filtered in the browser as they type. Null when the
     * actions provider was replaced or removed, or there is no action.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The current user.
     *
     * @return array{id: string, label: string, items: array<int, array{name: string, description: string|null, link: string, icon: string|null, keywords: array<int, string>}>}|null
     */
    public static function actionGroup( Authenticatable $user ): ?array
    {
        $provider = self::actionsProvider( $user );

        if ( null === $provider || ! AdminNav::canAccess( $user ) ) {
            return null;
        }

        $items = [];

        foreach ( $provider->commands( $user ) as $action ) {
            if ( ! self::safeLink( $action['link'] ) ) {
                continue;
            }

            $items[] = [
                'name'        => $action['name'],
                'description' => $action['description'],
                'link'        => $action['link'],
                'icon'        => self::icon( $action['icon'] ),
                'keywords'    => array_values( array_filter( explode( ' ', $action['keywords'] ) ) ),
            ];
        }

        return [] === $items ? null : [ 'id' => 'actions', 'label' => __( 'Actions' ), 'items' => $items ];
    }

    /**
     * The core actions provider, when the extension filter kept it under its
     * key and the user holds its ability. A subclass doesn't count: it may
     * add results in its own search(), so it stays in the searched results.
     *
     * @since 2.0.0
     *
     * @param  Authenticatable  $user  The current user.
     *
     * @return ActionsProvider|null
     */
    public static function actionsProvider( Authenticatable $user ): ?ActionsProvider
    {
        $provider = self::providers( $user )['actions'] ?? null;

        if ( ! $provider instanceof ActionsProvider || ActionsProvider::class !== $provider::class ) {
            return null;
        }

        $ability = $provider->ability();

        return null === $ability || Authorization::allows( $user, $ability ) ? $provider : null;
    }

    /**
     * A provider's group label: what it finds.
     *
     * @since 2.0.0
     *
     * @param  string             $key       The provider key.
     * @param  SpotlightProvider  $provider  The provider.
     *
     * @return string
     */
    public static function groupLabel( string $key, SpotlightProvider $provider ): string
    {
        return match ( $key ) {
            'orders'     => __( 'Orders' ),
            'products'   => __( 'Products' ),
            'customers'  => __( 'Customers' ),
            'promotions' => __( 'Promotions' ),
            'coupons'    => __( 'Coupons' ),
            'actions'    => __( 'Actions' ),
            default      => method_exists( $provider, 'label' ) && is_string( $label = $provider->label() ) && '' !== $label
                ? $label
                : Str::headline( $key ),
        };
    }

    /**
     * The providers after the extension filter.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user  The current user.
     *
     * @return array<string, SpotlightProvider>
     */
    /**
     * Whether a result link may be bound to the palette's `href`: a
     * relative path (not `//host`), or an http(s) URL on the app's host.
     * Anything else (`javascript:`, other hosts) is dropped.
     *
     * @since 1.0.0
     *
     * @param  string  $link  The link.
     *
     * @return bool
     */
    public static function safeLink( string $link ): bool
    {
        if ( str_starts_with( $link, '/' ) ) {
            return ! str_starts_with( $link, '//' ) && ! str_starts_with( $link, '/\\' );
        }

        $scheme = strtolower( (string) parse_url( $link, PHP_URL_SCHEME ) );
        $host   = strtolower( (string) parse_url( $link, PHP_URL_HOST ) );

        if ( ! in_array( $scheme, [ 'http', 'https' ], true ) || '' === $host ) {
            return false;
        }

        $hosts = array_filter( [
            strtolower( (string) parse_url( (string) config( 'app.url' ), PHP_URL_HOST ) ),
            strtolower( request()->getHost() ),
        ] );

        return in_array( $host, $hosts, true );
    }

    public static function providers( ?Authenticatable $user = null ): array
    {
        $providers = (array) applyFilters( self::PROVIDERS_FILTER, [
            'orders'     => new OrdersProvider(),
            'products'   => new ProductsProvider(),
            'customers'  => new CustomersProvider(),
            'promotions' => new PromotionsProvider(),
            'coupons'    => new CouponsProvider(),
            'actions'    => new ActionsProvider(),
        ], $user );

        $resolved = [];

        foreach ( $providers as $key => $provider ) {
            if ( is_string( $provider ) && class_exists( $provider ) ) {
                $provider = app( $provider );
            }

            if ( $provider instanceof SpotlightProvider ) {
                $resolved[ (string) $key ] = $provider;
            }
        }

        return $resolved;
    }

    /**
     * The palette's icon for a result: a design-system icon name.
     *
     * The livewire-ui-components 3.0 palette draws result icons from the
     * design system's icon registry by name, so a provider's Heroicon name
     * (`o-user`) maps to its registry counterpart, a registry name passes
     * through, and anything else (including markup) gets no icon.
     *
     * @since 1.0.0
     * @since 2.0.0 Returns a design-system icon name instead of rendered SVG.
     *
     * @param  string|null  $name  A Heroicon name (`o-user`) or a design-system icon name (`user`).
     *
     * @return string|null
     */
    public static function icon( ?string $name ): ?string
    {
        if ( null === $name || 1 !== preg_match( '/^[a-z0-9-]+$/', $name ) ) {
            return null;
        }

        $name = self::HEROICON_ICONS[ $name ] ?? $name;

        return IconRegistry::has( $name ) ? $name : null;
    }

    /**
     * A searched result's icon: as icon(), but only a name the design system
     * ships. Searched results are drawn in the browser, whose icon registry
     * holds only those; the admin's extra icons are registered in PHP, so
     * they would draw an empty tile there.
     *
     * @since 2.0.0
     *
     * @param  string|null  $name  A Heroicon name (`o-user`) or a design-system icon name (`user`).
     *
     * @return string|null
     */
    public static function searchIcon( ?string $name ): ?string
    {
        $icon = self::icon( $name );

        return null !== $icon && in_array( $icon, self::shippedIcons(), true ) ? $icon : null;
    }

    /**
     * The icon names livewire-ui-components ships to the browser.
     *
     * @since 2.0.0
     *
     * @return array<int, string>
     */
    private static function shippedIcons(): array
    {
        static $names = null;

        if ( null === $names ) {
            $path  = InstalledVersions::isInstalled( 'artisanpack-ui/livewire-ui-components' ) ? InstalledVersions::getInstallPath( 'artisanpack-ui/livewire-ui-components' ) : null;
            $file  = null === $path ? null : $path . '/resources/design-system/icons/lucide.json';
            $data  = null !== $file && is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
            $names = is_array( $data )
                ? array_values( array_filter( array_map( 'strval', array_keys( $data ) ), static fn ( string $key ): bool => ! str_starts_with( $key, '$' ) ) )
                : [];
        }

        return $names;
    }

    /**
     * Each provider's results for a search, keyed by provider, after the
     * access check and the per-user throttle (one hit per search).
     *
     * @since 2.0.0
     *
     * @param  string              $search  The raw search text.
     * @param  Authenticatable     $user    The current user.
     * @param  array<int, string>  $except  Provider keys to leave out.
     *
     * @return array<string, array<int, array{name: string, description: string|null, link: string, icon: string|null}>>
     */
    private static function resultsByProvider( string $search, Authenticatable $user, array $except = [] ): array
    {
        $search = trim( mb_substr( $search, 0, ResourceQuery::MAX_SEARCH_LENGTH ) );

        if ( '' === $search || ! AdminNav::canAccess( $user ) ) {
            return [];
        }

        $throttle = 'ecommerce-admin-spotlight:' . $user->getAuthIdentifier();

        if ( RateLimiter::tooManyAttempts( $throttle, self::SEARCHES_PER_MINUTE ) ) {
            return [];
        }

        RateLimiter::hit( $throttle, 60 );

        $limit   = self::limit();
        $results = [];

        foreach ( self::providers( $user ) as $key => $provider ) {
            if ( in_array( $key, $except, true ) ) {
                continue;
            }

            $ability = $provider->ability();

            if ( null !== $ability && ! Authorization::allows( $user, $ability ) ) {
                continue;
            }

            $results[ $key ] = [];

            foreach ( $provider->search( $search, $user, $limit ) as $result ) {
                if ( ! is_array( $result ) || ! isset( $result['name'], $result['link'] ) || ! self::safeLink( (string) $result['link'] ) ) {
                    continue;
                }

                $results[ $key ][] = [
                    'name'        => (string) $result['name'],
                    'description' => isset( $result['description'] ) ? (string) $result['description'] : null,
                    'link'        => (string) $result['link'],
                    'icon'        => self::searchIcon( isset( $result['icon'] ) ? (string) $result['icon'] : null ),
                ];
            }
        }

        return $results;
    }
}
