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
 * first (a unique index makes that atomic), so a reused token is a no-op with
 * a notice, and a write that throws releases the token for a retry.
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
     * `$write` throws, the token is released so the user can retry.
     *
     * @since 1.0.0
     *
     * @template TResult
     *
     * @param  string                $token    The token the client sent back.
     * @param  string                $action   The action name it was minted for.
     * @param  callable(): TResult   $write    The write.
     * @param  mixed|null            $subject  The subject it was minted for.
     *
     * @return TResult|null
     */
    protected function withActionToken( string $token, string $action, callable $write, mixed $subject = null ): mixed
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
            ActionTokens::release( $user, $action, $token );

            throw $exception;
        }
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
