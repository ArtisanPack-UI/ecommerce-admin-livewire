<?php

/**
 * Order panel concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use Livewire\Attributes\Locked;

/**
 * Shared plumbing for the panels on the order detail page: mounting from
 * the order (or its id), re-authorizing `view` on every request, loading
 * the order once per request, and telling the page and the other panels
 * when the order changed so they re-render.
 *
 * Panels listen for {@see OrderPanelRegistry::ORDER_UPDATED_EVENT} with
 * `protected $listeners = [ OrderPanelRegistry::ORDER_UPDATED_EVENT => '$refresh' ]`.
 *
 * Uses {@see AuthorizesEcommerce}.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
trait InteractsWithOrderPanel
{
    /**
     * The order id.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $orderId = 0;

    /**
     * The order, loaded once per request.
     *
     * @since 1.0.0
     *
     * @var Order|null
     */
    private ?Order $panelOrder = null;

    /**
     * Loads and authorizes the order.
     *
     * @since 1.0.0
     *
     * @param  int|Order|string  $order  The order or its id.
     *
     * @return void
     */
    public function mount( Order|int|string $order ): void
    {
        $this->orderId = $order instanceof Order ? (int) $order->getKey() : (int) $order;

        $this->authorizeEcommerce( 'view', $this->order() );
    }

    /**
     * Re-authorizes on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeEcommerce( 'view', $this->order() );
    }

    /**
     * The order.
     *
     * @since 1.0.0
     *
     * @return Order
     */
    protected function order(): Order
    {
        return $this->panelOrder ??= Order::query()->findOrFail( $this->orderId );
    }

    /**
     * Reloads the order and tells the page and the other panels it changed.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function orderChanged(): void
    {
        $this->panelOrder = null;

        $this->dispatch( OrderPanelRegistry::ORDER_UPDATED_EVENT, orderId: $this->orderId );
    }

    /**
     * The signed-in user's id, for the engine's audit columns.
     *
     * @since 1.0.0
     *
     * @return int|null
     */
    protected function actorId(): ?int
    {
        $id = auth()->id();

        return is_numeric( $id ) ? (int) $id : null;
    }
}
