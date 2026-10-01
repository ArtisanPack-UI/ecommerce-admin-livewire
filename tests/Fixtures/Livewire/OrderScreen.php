<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use Livewire\Component;

/**
 * A stand-in order screen that authorizes the way real screens do.
 */
class OrderScreen extends Component
{
    use AuthorizesEcommerce;

    public string $message = '';

    public function mount(): void
    {
        $this->authorizeEcommerce( 'viewAny', Order::class );
    }

    public function refund( int $orderId ): void
    {
        $this->authorizeEcommerce( 'refund', Order::query()->findOrFail( $orderId ) );

        $this->message = 'refunded';
    }

    public function editFulfilled( int $orderId ): void
    {
        $this->authorizeEcommerce( 'edit-fulfilled', Order::query()->findOrFail( $orderId ) );

        $this->message = 'edited';
    }

    public function viewReport(): void
    {
        $this->authorizeEcommerceAbility( 'report.view' );

        $this->message = 'report';
    }

    public function render(): string
    {
        return '<div>{{ $message }}</div>';
    }
}
