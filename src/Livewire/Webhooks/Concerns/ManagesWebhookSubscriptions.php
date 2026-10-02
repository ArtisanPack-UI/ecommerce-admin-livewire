<?php

/**
 * Secret rotation and re-enabling for the webhook screens.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Webhooks\Concerns;

use ArtisanPackUI\Ecommerce\Models\WebhookSubscription;
use ArtisanPackUI\Ecommerce\Services\WebhookSubscriptionService;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Webhooks;
use Livewire\Attributes\Locked;

/**
 * Actions both webhook screens offer on a subscription:
 *
 * - "Rotate secret" asks for confirmation, then replaces the signing secret
 *   through `WebhookSubscriptionService::update()` under a one-time action
 *   token, and reveals the new secret once.
 * - "Re-enable" switches a subscription back on; the engine clears its
 *   consecutive-failure count so it gets the full failure budget again.
 *
 * A revealed secret lives in `$revealedSecret` only until the user closes
 * the notice; it is never read back from the database for display.
 *
 * The using component needs AuthorizesEcommerce, SendsToasts, and
 * WithActionToken.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
trait ManagesWebhookSubscriptions
{
    /**
     * A secret to show once (after create or rotate), or null.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $revealedSecret = null;

    /**
     * Whether the one-time secret notice is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $showingSecret = false;

    /**
     * The subscription waiting for rotate confirmation.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $rotatingId = null;

    /**
     * Whether the rotate confirmation is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $confirmingRotate = false;

    /**
     * Asks to confirm rotating a subscription's secret.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Subscription id.
     *
     * @return void
     */
    public function confirmRotate( int $id ): void
    {
        $subscription = WebhookSubscription::query()->find( $id );

        $this->authorizeEcommerceAbility( 'webhookSubscription.update', $subscription );

        $this->rotatingId       = $subscription?->id;
        $this->confirmingRotate = null !== $subscription;
    }

    /**
     * Dismisses the rotate confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelRotate(): void
    {
        $this->rotatingId       = null;
        $this->confirmingRotate = false;
    }

    /**
     * Replaces the secret of the subscription waiting for confirmation,
     * once per token, and reveals the new one.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the confirmation.
     *
     * @return void
     */
    public function rotateSecret( string $token ): void
    {
        $subscription = null === $this->rotatingId || ! $this->confirmingRotate ? null : WebhookSubscription::query()->find( $this->rotatingId );

        $this->authorizeEcommerceAbility( 'webhookSubscription.update', $subscription );

        if ( null === $subscription ) {
            $this->cancelRotate();

            return;
        }

        $secret = Webhooks::newSecret();

        $rotated = $this->withActionToken( $token, 'rotate', static function () use ( $subscription, $secret ): bool {
            app( WebhookSubscriptionService::class )->update( $subscription, [ 'secret' => $secret ] );

            return true;
        }, $subscription );

        $this->cancelRotate();

        if ( true === $rotated ) {
            $this->revealSecret( $secret );
            $this->toastSuccess( __( 'Secret rotated for ":name".', [ 'name' => $subscription->name ] ), __( 'Update the receiving endpoint with the new secret.' ) );
        }
    }

    /**
     * Switches a subscription back on.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Subscription id.
     *
     * @return void
     */
    public function reEnable( int $id ): void
    {
        $subscription = WebhookSubscription::query()->find( $id );

        $this->authorizeEcommerceAbility( 'webhookSubscription.update', $subscription );

        if ( null === $subscription || $subscription->is_active ) {
            return;
        }

        app( WebhookSubscriptionService::class )->update( $subscription, [ 'is_active' => true ] );

        $this->toastSuccess( __( 'Subscription ":name" re-enabled.', [ 'name' => $subscription->name ] ) );
    }

    /**
     * Closes the one-time secret notice and forgets the secret.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function dismissSecret(): void
    {
        $this->revealedSecret = null;
        $this->showingSecret  = false;
    }

    /**
     * Forgets the secret when the notice is closed from the client (escape,
     * backdrop, or close button).
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedShowingSecret(): void
    {
        if ( ! $this->showingSecret ) {
            $this->revealedSecret = null;
        }
    }

    /**
     * Opens the one-time secret notice.
     *
     * @since 1.0.0
     *
     * @param  string  $secret  The secret.
     *
     * @return void
     */
    protected function revealSecret( string $secret ): void
    {
        $this->revealedSecret = $secret;
        $this->showingSecret  = true;
    }
}
