<?php

/**
 * Admin mutation rate-limit middleware.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Http\Middleware;

use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminBroadcasts;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs this package's Livewire update requests through the engine's
 * `ecommerce.admin.mutate` limiter (spec §5.2; 120 a minute per user by default).
 *
 * Registered on the admin routes as `ecommerce-admin.throttle` and as
 * Livewire persistent middleware, so it re-runs on every update request from
 * an admin page. The page loads themselves are not counted, and neither are
 * updates that only read: no property updates, and only read calls (see
 * {@see self::isReadCall()}: polls, lazy loads, picker searches, sorting,
 * paging and the admin's broadcast refreshes).
 *
 * An update is weighed rather than counted once: one Livewire request can
 * carry many components, each with many calls, so it costs one attempt per
 * writing call plus one per component that sends property updates
 * (see {@see self::weight()}).
 *
 * Over the limit it answers 429 with `Retry-After` and a JSON body
 * (`message`, `retry_after`); the page assets turn that into a toast. The
 * response is thrown, because Livewire discards responses that persistent
 * middleware return (other than redirects).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class ThrottleAdminMutations
{
    /**
     * The route-middleware alias.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ALIAS = 'ecommerce-admin.throttle';

    /**
     * The engine's named limiter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const LIMITER = 'ecommerce.admin.mutate';

    /**
     * Calls that only read, so a request made of them isn't counted.
     *
     * `$refresh`, `$set`, `$sync` and `$commit` are Livewire 4's magic
     * actions, which do nothing on the server (a `$set` carries its change
     * in the component's `updates`, which are counted on their own).
     * `__lazyLoad` and `__lazyLoadIsland` mount lazy components and islands.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const READ_CALLS = [
        '$refresh',
        '$set',
        '$sync',
        '$commit',
        '__lazyLoad',
        '__lazyLoadIsland',
        'searchPicker',
        'sort',
        'gotoPage',
        'nextPage',
        'previousPage',
        'setPage',
    ];

    /**
     * Events whose `__dispatch` only refreshes what the component shows:
     * the sidebar badge refresh. Broadcasts on the admin channel are
     * matched by prefix (see {@see self::isReadCall()}).
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const READ_EVENTS = [ 'ecommerce-admin-nav-refresh' ];

    /**
     * @since 1.0.0
     *
     * @param  RateLimiter  $limiter  The rate limiter.
     */
    public function __construct( private readonly RateLimiter $limiter )
    {
    }

    /**
     * Handles the request.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The request.
     * @param  Closure  $next     The next handler.
     *
     * @throws HttpResponseException With the 429 response when over the limit.
     *
     * @return Response
     */
    public function handle( Request $request, Closure $next ): Response
    {
        if ( ! self::isLivewireUpdate( $request ) ) {
            return $next( $request );
        }

        $weight = self::weight( $request );

        if ( 0 === $weight ) {
            return $next( $request );
        }

        $definition = $this->limiter->limiter( self::LIMITER );

        if ( null === $definition ) {
            return $next( $request );
        }

        $limits = self::limits( $definition( $request ) );

        foreach ( $limits as $limit ) {
            $key       = self::LIMITER . ':' . $limit->key;
            $threshold = $limit->maxAttempts - $weight + 1;

            if ( $threshold < 1 || $this->limiter->tooManyAttempts( $key, $threshold ) ) {
                throw new HttpResponseException( self::tooManyRequests( $this->limiter->availableIn( $key ) ) );
            }
        }

        foreach ( $limits as $limit ) {
            $this->limiter->increment( self::LIMITER . ':' . $limit->key, $limit->decaySeconds, $weight );
        }

        return $next( $request );
    }

    /**
     * Whether this is a Livewire update request (an action or property sync).
     *
     * Checks that the request being served is Livewire's update route rather
     * than trusting a client header. Livewire runs persistent middleware on a
     * copy of the update request that carries the page's path and method, so
     * `$request` itself looks like a page load.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The request.
     *
     * @return bool
     */
    public static function isLivewireUpdate( Request $request ): bool
    {
        return app( HandleRequests::class )->isLivewireRoute();
    }

    /**
     * The 429 response.
     *
     * @since 1.0.0
     *
     * @param  int  $retryAfter  Seconds until the next request is allowed.
     *
     * @return JsonResponse
     */
    public static function tooManyRequests( int $retryAfter ): JsonResponse
    {
        $retryAfter = max( 1, $retryAfter );

        return new JsonResponse(
            [
                'message'     => trans_choice(
                    'Too many changes in a short time. Try again in :count second.|Too many changes in a short time. Try again in :count seconds.',
                    $retryAfter,
                    [ 'count' => $retryAfter ],
                ),
                'retry_after' => $retryAfter,
            ],
            429,
            [ 'Retry-After' => (string) $retryAfter ],
        );
    }

    /**
     * Whether every component in the update only reads: it sends no
     * property updates, and every call it makes is a read call (see
     * {@see self::isReadCall()}). A commit with no calls at all is a read
     * too: it is how Livewire 3 sends `wire:poll` and `$refresh`.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The Livewire update request.
     *
     * @return bool
     */
    public static function onlyReads( Request $request ): bool
    {
        return 0 === self::weight( $request );
    }

    /**
     * How many attempts an update costs: one per call that isn't a read,
     * plus one per component that sends property updates. A request that
     * writes costs at least one, and a malformed one counts as one write.
     *
     * Livewire 3 and 4 send the same shape (`components[].updates` and
     * `components[].calls[].method`; Livewire 3 adds a `path` and Livewire 4
     * `metadata` to each call), so one count serves both.
     *
     * @since 2.0.0
     *
     * @param  Request  $request  The Livewire update request.
     *
     * @return int Zero when the update only reads.
     */
    public static function weight( Request $request ): int
    {
        $components = $request->input( 'components' );

        if ( ! is_array( $components ) || [] === $components ) {
            return 1;
        }

        $weight = 0;

        foreach ( $components as $component ) {
            if ( ! is_array( $component ) ) {
                $weight++;

                continue;
            }

            if ( [] !== (array) ( $component['updates'] ?? [] ) ) {
                $weight++;
            }

            foreach ( (array) ( $component['calls'] ?? [] ) as $call ) {
                if ( ! self::isReadCall( $call ) ) {
                    $weight++;
                }
            }
        }

        return $weight;
    }

    /**
     * Whether a Livewire call only reads: one of {@see self::READ_CALLS},
     * or a `__dispatch` of an event that only refreshes the component: a
     * broadcast on the admin channel (`echo-private:ecommerce.admin,…`) or
     * one of {@see self::READ_EVENTS}.
     *
     * @since 2.0.0
     *
     * @param  mixed  $call  One entry of a component's `calls`.
     *
     * @return bool
     */
    public static function isReadCall( mixed $call ): bool
    {
        if ( ! is_array( $call ) ) {
            return false;
        }

        $method = $call['method'] ?? null;

        if ( in_array( $method, self::READ_CALLS, true ) ) {
            return true;
        }

        if ( '__dispatch' !== $method ) {
            return false;
        }

        $event = ( (array) ( $call['params'] ?? [] ) )[0] ?? null;

        if ( ! is_string( $event ) ) {
            return false;
        }

        return in_array( $event, self::READ_EVENTS, true )
            || str_starts_with( $event, 'echo-private:' . AdminBroadcasts::CHANNEL . ',' );
    }

    /**
     * Normalizes a limiter result to a list of limits.
     *
     * @since 1.0.0
     *
     * @param  mixed  $result  What the limiter callback returned.
     *
     * @return array<int, Limit>
     */
    private static function limits( mixed $result ): array
    {
        $limits = $result instanceof Limit ? [ $result ] : (array) $result;

        return array_values( array_filter( $limits, static fn ( mixed $limit ): bool => $limit instanceof Limit ) );
    }
}
