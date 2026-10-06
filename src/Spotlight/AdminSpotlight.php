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

use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Throwable;

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
     * Rendered icon markup, keyed by icon name.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    private static array $icons = [];

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
        // admin results come only from the admin's own route.
        if ( ! self::enabled() || ! $user instanceof Authenticatable || ! is_string( $search ) || request()->routeIs( self::SHARED_ROUTE ) ) {
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

        foreach ( self::providers( $user ) as $provider ) {
            $ability = $provider->ability();

            if ( null !== $ability && ! Authorization::allows( $user, $ability ) ) {
                continue;
            }

            foreach ( $provider->search( $search, $user, $limit ) as $result ) {
                if ( ! is_array( $result ) || ! isset( $result['name'], $result['link'] ) || ! self::safeLink( (string) $result['link'] ) ) {
                    continue;
                }

                $results[] = [
                    'name'        => (string) $result['name'],
                    'description' => isset( $result['description'] ) ? (string) $result['description'] : null,
                    'link'        => (string) $result['link'],
                    'icon'        => self::icon( isset( $result['icon'] ) ? (string) $result['icon'] : null ),
                ];
            }
        }

        return $results;
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
     * The palette renders `icon` as HTML, so icons are rendered here from a
     * Heroicon name and never taken as markup from a provider.
     *
     * @since 1.0.0
     *
     * @param  string|null  $name  Heroicon name (`o-cube`).
     *
     * @return string|null
     */
    public static function icon( ?string $name ): ?string
    {
        if ( null === $name || 1 !== preg_match( '/^[a-z0-9-]+$/', $name ) ) {
            return null;
        }

        if ( ! array_key_exists( $name, self::$icons ) ) {
            try {
                self::$icons[ $name ] = trim( Blade::render( '<x-artisanpack-icon :name="$name" class="w-5 h-5 opacity-70" aria-hidden="true" />', [ 'name' => $name ] ) );
            } catch ( Throwable ) {
                self::$icons[ $name ] = '';
            }
        }

        return '' === self::$icons[ $name ] ? null : self::$icons[ $name ];
    }
}
