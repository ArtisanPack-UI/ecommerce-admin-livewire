<?php

/**
 * Customer orders tab.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A customer's order history, newest first (spec §7.4).
 *
 * The list needs `order.viewAny` on top of seeing the customer; the tab is
 * registered with that ability, so it is hidden from users without it.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class OrdersTab extends Component
{
    use AuthorizesEcommerce;
    use WithPagination;

    /**
     * Orders per page.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const PER_PAGE = 10;

    /**
     * The customer id.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $customerId = 0;

    /**
     * Loads and authorizes the customer.
     *
     * @since 1.0.0
     *
     * @param  Customer  $customer  The customer.
     *
     * @return void
     */
    public function mount( Customer $customer ): void
    {
        $this->authorizeEcommerce( 'view', $customer );

        $this->customerId = (int) $customer->id;
    }

    /**
     * Re-checks access on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeEcommerce( 'view', $this->customer() );
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $canList = Authorization::allows( auth()->user(), 'order.viewAny' );
        $route   = AdminNav::ROUTE_PREFIX . 'orders.show';

        return view( 'ecommerce-admin::livewire.customers.tabs.orders', [
            'canList'   => $canList,
            'orders'    => $canList
                ? Order::query()
                    ->where( 'customer_id', $this->customerId )
                    ->withCount( 'items' )
                    ->orderByDesc( 'placed_at' )
                    ->orderByDesc( 'id' )
                    ->paginate( self::PER_PAGE, [ '*' ], 'orders-page' )
                : null,
            'showRoute' => Route::has( $route ) ? $route : null,
        ] );
    }

    /**
     * The customer.
     *
     * @since 1.0.0
     *
     * @return Customer
     */
    protected function customer(): Customer
    {
        return Customer::query()->findOrFail( $this->customerId );
    }
}
