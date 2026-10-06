<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderNote;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use RuntimeException;

/**
 * A stand-in money-moving screen: "refund" writes one order note per call.
 */
class TokenScreen extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithActionToken;

    public int $orderId = 0;

    public bool $failNext = false;

    public ?int $levelInside = null;

    public function mount( int $orderId ): void
    {
        $this->orderId = $orderId;
    }

    public function refund( string $token ): void
    {
        $order = Order::query()->findOrFail( $this->orderId );

        $this->withActionToken( $token, 'refund', function () use ( $order ): void {
            $this->levelInside = DB::transactionLevel();

            // withActionToken() opens no transaction; a write that must be
            // atomic opens its own.
            DB::transaction( function () use ( $order ): void {
                OrderNote::query()->create( [ 'order_id' => $order->id, 'body' => 'refunded', 'is_customer_visible' => false ] );

                if ( $this->failNext ) {
                    throw new RuntimeException( 'Gateway down.' );
                }
            } );
        }, $order );
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <button wire:click="refund( '{{ $this->actionToken( 'refund', \ArtisanPackUI\Ecommerce\Models\Order::query()->find( $orderId ) ) }}' )" wire:loading.attr="disabled">Refund</button>
            </div>
            BLADE;
    }
}
