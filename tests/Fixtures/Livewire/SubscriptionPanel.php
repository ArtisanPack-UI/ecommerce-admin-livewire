<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use ArtisanPackUI\Ecommerce\Models\Order;
use Livewire\Component;

/**
 * A satellite's order panel, registered through OrderPanelRegistry.
 */
class SubscriptionPanel extends Component
{
    public Order $order;

    public function render(): string
    {
        return '<div>Subscription panel for #{{ $order->order_number }}</div>';
    }
}
