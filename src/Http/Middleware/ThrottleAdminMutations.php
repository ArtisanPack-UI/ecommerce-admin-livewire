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
 * updates that only read: no property updates, and only calls listed in
 * {@see self::READ_CALLS} (polls, picker searches, sorting, paging).
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
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const READ_CALLS = [ '$refresh', 'searchPicker', 'sort', 'gotoPage', 'nextPage', 'previousPage', 'setPage' ];

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
        if ( ! self::isLivewireUpdate( $request ) || self::onlyReads( $request ) ) {
            return $next( $request );
        }

        $definition = $this->limiter->limiter( self::LIMITER );

        if ( null === $definition ) {
            return $next( $request );
        }

        foreach ( self::limits( $definition( $request ) ) as $limit ) {
            $key = self::LIMITER . ':' . $limit->key;

            if ( $this->limiter->tooManyAttempts( $key, $limit->maxAttempts ) ) {
                throw new HttpResponseException( self::tooManyRequests( $this->limiter->availableIn( $key ) ) );
            }

            $this->limiter->hit( $key, $limit->decaySeconds );
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
     * Normalizes a limiter result to a list of limits.
     *
     * @since 1.0.0
     *
     * @param  mixed  $result  What the limiter callback returned.
     *
     * @return array<int, Limit>
     */
    /**
     * Whether every component in the update only reads: it sends no
     * property updates, and every call it makes is one of
     * {@see self::READ_CALLS}. A commit with no calls at all is a read too:
     * it is how Livewire 3 sends `wire:poll` and `$refresh`.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The Livewire update request.
     *
     * @return bool
     */
    public static function onlyReads( Request $request ): bool
    {
        $components = $request->input( 'components' );

        if ( ! is_array( $components ) || [] === $components ) {
            return false;
        }

        foreach ( $components as $component ) {
            if ( ! is_array( $component ) || [] !== (array) ( $component['updates'] ?? [] ) ) {
                return false;
            }

            foreach ( (array) ( $component['calls'] ?? [] ) as $call ) {
                if ( ! is_array( $call ) || ! in_array( $call['method'] ?? null, self::READ_CALLS, true ) ) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function limits( mixed $result ): array
    {
        $limits = $result instanceof Limit ? [ $result ] : (array) $result;

        return array_values( array_filter( $limits, static fn ( mixed $limit ): bool => $limit instanceof Limit ) );
    }
}
