<?php

/**
 * One-time action tokens.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\IdempotencyRecord;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Mints and consumes the one-time tokens that make admin actions idempotent
 * (spec §5.2).
 *
 * The REST API protects money-moving calls with an `Idempotency-Key`; the
 * admin calls engine services in-process, so it carries a token instead. A
 * token is minted when the button renders and is passed back as the action's
 * argument, so a second click sent before the page refreshes still carries the
 * first token.
 *
 * Minting is stateless: the token is a nonce and an issue time, signed with
 * the app key over the user, the scope (component, action, subject), and the
 * nonce. Consuming writes a row to the engine's `idempotency_records` table
 * with `insertOrIgnore`; run it inside the write's transaction so a failed
 * write releases the token for a retry, and a concurrent duplicate waits on
 * the unique index and then finds the token spent.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class ActionTokens
{
    /**
     * How long a minted token stays usable, in seconds.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const TTL_SECONDS = 43_200;

    /**
     * The `idempotency_records.endpoint_key` prefix for admin tokens.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ENDPOINT_PREFIX = 'ecommerce-admin.';

    /**
     * Mints a token for a user and scope.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user   The user who will submit it.
     * @param  string                $scope  What it authorizes, e.g. `{component}|refund|{order id}`.
     *
     * @return string
     */
    public static function mint( ?Authenticatable $user, string $scope ): string
    {
        $payload = Str::random( 40 ) . '.' . Carbon::now()->getTimestamp();

        return $payload . '.' . self::signature( $user, $scope, $payload );
    }

    /**
     * Whether a token was minted for this user and scope and has not expired.
     *
     * Says nothing about whether it has been used.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user   The user.
     * @param  string                $scope  The scope.
     * @param  string                $token  The submitted token.
     *
     * @return bool
     */
    public static function isValid( ?Authenticatable $user, string $scope, string $token ): bool
    {
        $parts = explode( '.', $token );

        if ( 3 !== count( $parts ) || 1 !== preg_match( '/^[0-9]{1,12}$/D', $parts[1] ) ) {
            return false;
        }

        [ $nonce, $issuedAt, $signature ] = $parts;

        if ( ! hash_equals( self::signature( $user, $scope, $nonce . '.' . $issuedAt ), $signature ) ) {
            return false;
        }

        $age = Carbon::now()->getTimestamp() - (int) $issuedAt;

        return $age >= 0 && $age <= self::TTL_SECONDS;
    }

    /**
     * Marks a token used.
     *
     * Call it inside the transaction that performs the write.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user    The user.
     * @param  string                $action  The action name, recorded for auditing.
     * @param  string                $scope   The scope.
     * @param  string                $token   The submitted token.
     *
     * @return bool True when this call spent the token; false when it was already spent.
     */
    public static function consume( ?Authenticatable $user, string $action, string $scope, string $token ): bool
    {
        $now = Carbon::now();

        $inserted = IdempotencyRecord::query()->insertOrIgnore( [
            'actor_scope'     => self::actor( $user ),
            'endpoint_key'    => Str::limit( self::ENDPOINT_PREFIX . $action, 191, '' ),
            'idempotency_key' => hash( 'sha256', $token ),
            'request_hash'    => hash( 'sha256', $scope ),
            'response_status' => 200,
            'locked_at'       => null,
            'expires_at'      => $now->copy()->addSeconds( self::TTL_SECONDS ),
            'created_at'      => $now,
            'updated_at'      => $now,
        ] );

        return 1 === $inserted;
    }

    /**
     * Frees a consumed token, so the action can be retried after its write
     * failed.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user    The user it was consumed for.
     * @param  string                $action  The action name.
     * @param  string                $token   The token.
     *
     * @return void
     */
    public static function release( ?Authenticatable $user, string $action, string $token ): void
    {
        IdempotencyRecord::query()
            ->where( 'actor_scope', self::actor( $user ) )
            ->where( 'endpoint_key', Str::limit( self::ENDPOINT_PREFIX . $action, 191, '' ) )
            ->where( 'idempotency_key', hash( 'sha256', $token ) )
            ->delete();
    }

    /**
     * The signature over the user, scope, and payload.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user     The user.
     * @param  string                $scope    The scope.
     * @param  string                $payload  The nonce and issue time.
     *
     * @return string
     */
    private static function signature( ?Authenticatable $user, string $scope, string $payload ): string
    {
        return hash_hmac( 'sha256', self::actor( $user ) . '|' . $scope . '|' . $payload, 'ecommerce-admin-action-token|' . config( 'app.key' ) );
    }

    /**
     * The actor scope for a user.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user  The user.
     *
     * @return string
     */
    private static function actor( ?Authenticatable $user ): string
    {
        return null === $user ? 'guest' : 'user:' . $user->getAuthIdentifier();
    }
}
