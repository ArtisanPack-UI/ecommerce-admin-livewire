<?php

/**
 * One-time action token concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns;

use ArtisanPackUI\EcommerceAdminLivewire\Support\ActionTokens;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Makes destructive and money-moving actions idempotent (spec §5.2).
 *
 * Mint a token when the button renders and pass it back as the action's
 * argument; disable the button while the request runs:
 *
 * ```blade
 * <x-artisanpack-button
 *     wire:click="refund( '{{ $this->actionToken( 'refund', $order ) }}' )"
 *     wire:loading.attr="disabled"
 * />
 * ```
 *
 * The action wraps its write in `withActionToken()`. The token is consumed
 * first, in its own autocommitted insert (the unique index makes it atomic),
 * so a reused or concurrently resubmitted token is a no-op with a notice. A
 * write that throws releases the token for a retry and rethrows.
 *
 * Releasing on every exception assumes a failed write did nothing. When a
 * write can fail after its side effect (a carrier or gateway call that timed
 * out but may have gone through), pass `$releaseOn`: only those exception
 * classes release the token, and any other failure keeps it spent, so the
 * user reloads (getting a fresh token) after checking what happened.
 *
 * The write does **not** run inside a transaction: engine services such as
 * `RefundService::issue()` and `ShipmentService::buyLabel()` call the
 * gateway or carrier outside any transaction on purpose, and an outer
 * rollback would erase the ledger rows they record around that call. A write
 * that changes several local rows opens its own `DB::transaction()`.
 *
 * Uses {@see SendsToasts}.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
trait WithActionToken
{
    /**
     * Mints a token for an action, optionally bound to a subject.
     *
     * @since 1.0.0
     *
     * @param  string      $action   The action name, e.g. `refund`.
     * @param  mixed|null  $subject  A model, or a key, the token is limited to.
     *
     * @return string
     */
    protected function actionToken( string $action, mixed $subject = null ): string
    {
        return ActionTokens::mint( auth()->user(), $this->actionTokenScope( $action, $subject ) );
    }

    /**
     * Runs a write once per token.
     *
     * Returns null without running `$write` when the token is invalid,
     * expired, or already used; the user gets a notice instead. When
     * `$write` throws, the exception is rethrown, and the token is released
     * so the user can retry: always, or, when `$releaseOn` is given, only
     * for an exception that is an instance of one of those classes.
     *
     * @since 1.0.0
     * @since 2.0.0 Added the `$releaseOn` parameter.
     *
     * @template TResult
     *
     * @param  string                                 $token      The token the client sent back.
     * @param  string                                 $action     The action name it was minted for.
     * @param  callable(): TResult                    $write      The write.
     * @param  mixed|null                             $subject    The subject it was minted for.
     * @param  array<int, class-string<Throwable>>|null  $releaseOn  The exceptions that release the token; null releases on any.
     *
     * @return TResult|null
     */
    protected function withActionToken( string $token, string $action, callable $write, mixed $subject = null, ?array $releaseOn = null ): mixed
    {
        $user  = auth()->user();
        $scope = $this->actionTokenScope( $action, $subject );

        if ( ! ActionTokens::isValid( $user, $scope, $token ) ) {
            $this->toastWarning(
                __( 'This action has expired.' ),
                __( 'Reload the page and try again.' ),
            );

            return null;
        }

        if ( ! ActionTokens::consume( $user, $action, $scope, $token ) ) {
            $this->toastWarning(
                __( 'That action was already submitted.' ),
                __( 'Nothing was changed the second time.' ),
            );

            return null;
        }

        try {
            return $write();
        } catch ( Throwable $exception ) {
            if ( self::releasesActionToken( $exception, $releaseOn ) ) {
                ActionTokens::release( $user, $action, $token );
            }

            throw $exception;
        }
    }

    /**
     * Whether a failed write frees its token.
     *
     * @since 2.0.0
     *
     * @param  Throwable                                 $exception  What the write threw.
     * @param  array<int, class-string<Throwable>>|null  $releaseOn  The exceptions that release the token; null releases on any.
     *
     * @return bool
     */
    private static function releasesActionToken( Throwable $exception, ?array $releaseOn ): bool
    {
        if ( null === $releaseOn ) {
            return true;
        }

        foreach ( $releaseOn as $class ) {
            if ( is_string( $class ) && $exception instanceof $class ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The scope a token is bound to: this component, the action, and the subject.
     *
     * @since 1.0.0
     *
     * @param  string      $action   The action name.
     * @param  mixed|null  $subject  The subject.
     *
     * @return string
     */
    private function actionTokenScope( string $action, mixed $subject ): string
    {
        $subjectKey = match ( true ) {
            $subject instanceof Model             => $subject::class . ':' . $subject->getKey(),
            is_scalar( $subject )                 => (string) $subject,
            default                               => '',
        };

        return static::class . '|' . $action . '|' . $subjectKey;
    }
}
