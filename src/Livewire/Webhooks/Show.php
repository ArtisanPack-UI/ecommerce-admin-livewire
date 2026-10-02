<?php

/**
 * Webhook subscription detail and deliveries log.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Webhooks;

use ArtisanPackUI\Ecommerce\Models\WebhookDelivery;
use ArtisanPackUI\Ecommerce\Models\WebhookSubscription;
use ArtisanPackUI\Ecommerce\Services\WebhookSubscriptionService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithResourceTable;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Webhooks\Concerns\ManagesWebhookSubscriptions;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\WebhookDeliveriesQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Webhooks;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One webhook subscription (spec §7.8): its details, "rotate secret" and
 * "re-enable", and its deliveries log — event, status, attempts, next retry,
 * and delivered at, newest first, filterable by event and status.
 *
 * Opening a delivery shows its payload and the endpoint's response in a
 * drawer, with "replay". Replaying queues a new delivery through
 * `WebhookSubscriptionService::replay()` (the original row stays for the
 * audit trail) under a one-time action token. The engine refuses to replay
 * for an inactive subscription, so the screen says to re-enable it first.
 *
 * Webhook subscriptions have no `view` ability; the screen uses `viewAny`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Show extends Component
{
    use AuthorizesEcommerce;
    use ManagesWebhookSubscriptions;
    use SendsToasts;
    use WithActionToken;
    use WithResourceTable;

    /**
     * The subscription.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $subscriptionId;

    /**
     * The delivery open in the drawer.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $deliveryId = null;

    /**
     * Whether the delivery drawer is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $viewingDelivery = false;

    /**
     * Loads and authorizes the subscription.
     *
     * @since 1.0.0
     *
     * @param  int  $subscription  Subscription id.
     *
     * @return void
     */
    public function mount( int $subscription ): void
    {
        $this->authorizeTable();

        $this->subscriptionId = (int) WebhookSubscription::query()->findOrFail( $subscription )->id;
    }

    /**
     * Opens a delivery in the drawer.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Delivery id.
     *
     * @return void
     */
    public function openDelivery( int $id ): void
    {
        $this->authorizeTable();

        $this->deliveryId      = $this->delivery( $id )?->id;
        $this->viewingDelivery = null !== $this->deliveryId;
    }

    /**
     * Closes the delivery drawer.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function closeDelivery(): void
    {
        $this->deliveryId      = null;
        $this->viewingDelivery = false;
    }

    /**
     * Replays the delivery open in the drawer, once per token.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted for the open delivery.
     *
     * @return void
     */
    public function replay( string $token ): void
    {
        $subscription = WebhookSubscription::query()->find( $this->subscriptionId );

        $this->authorizeEcommerceAbility( 'webhookSubscription.update', $subscription );

        $delivery = null === $this->deliveryId ? null : $this->delivery( $this->deliveryId );

        if ( null === $subscription || null === $delivery ) {
            return;
        }

        if ( ! $subscription->is_active ) {
            $this->toastWarning( __( 'This subscription is disabled.' ), __( 'Re-enable it before replaying its deliveries.' ) );

            return;
        }

        $replayed = $this->withActionToken( $token, 'replay', static fn (): ?WebhookDelivery => app( WebhookSubscriptionService::class )->replay( $delivery ), $delivery );

        if ( $replayed instanceof WebhookDelivery ) {
            $this->closeDelivery();
            $this->toastSuccess( __( 'Delivery queued again.' ), __( 'The new attempt appears at the top of the log.' ) );
        }
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $subscription = WebhookSubscription::query()->findOrFail( $this->subscriptionId );
        $delivery     = null === $this->deliveryId || ! $this->viewingDelivery ? null : $this->delivery( $this->deliveryId );
        $rotating     = null === $this->rotatingId || ! $this->confirmingRotate ? null : WebhookSubscription::query()->find( $this->rotatingId );
        $indexRoute   = 'artisanpack.ecommerce.admin.webhooks.index';

        return view( 'ecommerce-admin::livewire.webhooks.show', $this->resourceTableData() + [
            'subscription'     => $subscription,
            'disabledByEngine' => Webhooks::disabledByEngine( $subscription ),
            'canUpdate'        => Authorization::allows( auth()->user(), 'webhookSubscription.update', $subscription ),
            'delivery'         => $delivery,
            'replayToken'      => null === $delivery ? null : $this->actionToken( 'replay', $delivery ),
            'rotating'         => $rotating,
            'rotateToken'      => null === $rotating ? null : $this->actionToken( 'rotate', $rotating ),
            'indexUrl'         => app( 'router' )->has( $indexRoute ) ? route( $indexRoute ) : null,
        ] );
    }

    /**
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeTable(): void
    {
        $this->authorizeEcommerce( 'viewAny', WebhookSubscription::class );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableScreen(): string
    {
        return 'webhook-deliveries';
    }

    /**
     * @since 1.0.0
     *
     * @return ResourceQuery
     */
    protected function tableQuery(): ResourceQuery
    {
        return new WebhookDeliveriesQuery( $this->subscriptionId );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableCaption(): string
    {
        return __( 'Deliveries' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableColumns(): array
    {
        $cells = 'ecommerce-admin::livewire.webhooks.cells.';

        return [
            [
                'key'   => 'event',
                'label' => __( 'Event' ),
                'value' => static fn ( WebhookDelivery $delivery ): string => (string) $delivery->event,
            ],
            [
                'key'    => 'status',
                'label'  => __( 'Status' ),
                'view'   => $cells . 'delivery-status',
                'export' => static fn ( WebhookDelivery $delivery ): string => Webhooks::statusLabel( Webhooks::status( $delivery ) ),
            ],
            [
                'key'      => 'attempts',
                'label'    => __( 'Attempts' ),
                'sortable' => true,
                'class'    => 'text-end',
                'value'    => static fn ( WebhookDelivery $delivery ): string => (string) (int) $delivery->attempts,
                'export'   => static fn ( WebhookDelivery $delivery ): int => (int) $delivery->attempts,
            ],
            [
                'key'   => 'next_retry',
                'label' => __( 'Next retry' ),
                'value' => static fn ( WebhookDelivery $delivery ): string => null === $delivery->delivered_at ? Webhooks::dateTime( $delivery->next_retry_at ) : '',
            ],
            [
                'key'      => 'delivered',
                'label'    => __( 'Delivered at' ),
                'sortable' => true,
                'value'    => static fn ( WebhookDelivery $delivery ): string => Webhooks::dateTime( $delivery->delivered_at ),
            ],
            [
                'key'      => 'created',
                'label'    => __( 'Created' ),
                'sortable' => true,
                'value'    => static fn ( WebhookDelivery $delivery ): string => Webhooks::dateTime( $delivery->created_at ),
            ],
            [
                'key'        => 'actions',
                'label'      => __( 'Actions' ),
                'class'      => 'text-end',
                'view'       => $cells . 'delivery-actions',
                'exportable' => false,
            ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableFilters(): array
    {
        $events = WebhookDelivery::query()
            ->where( 'subscription_id', $this->subscriptionId )
            ->distinct()
            ->orderBy( 'event' )
            ->pluck( 'event' )
            ->map( static fn ( mixed $event ): array => [ 'id' => (string) $event, 'name' => (string) $event ] )
            ->all();

        return [
            [ 'key' => 'event', 'label' => __( 'Event' ), 'type' => 'select', 'options' => $events ],
            [ 'key' => 'status', 'label' => __( 'Status' ), 'type' => 'select', 'options' => Webhooks::statusOptions() ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableBulkActions(): array
    {
        return [ $this->exportBulkAction() ];
    }

    /**
     * One of this subscription's deliveries.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Delivery id.
     *
     * @return WebhookDelivery|null
     */
    private function delivery( int $id ): ?WebhookDelivery
    {
        return WebhookDelivery::query()->where( 'subscription_id', $this->subscriptionId )->find( $id );
    }
}
