<?php

/**
 * Promotion usage panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions;

use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\Ecommerce\Models\PromotionUsage;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Index as CustomersIndex;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every order a promotion discounted (spec §7.5): order, customer, amount
 * discounted, and date, newest first, with the total discounted per
 * currency. Customer names and emails are shown only to users who may view
 * customers.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class UsagePanel extends Component
{
    use AuthorizesEcommerce;
    use WithPagination;

    /**
     * Rows per page.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const PER_PAGE = 20;

    /**
     * The promotion id.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $promotionId = 0;

    /**
     * Loads and authorizes the promotion.
     *
     * @since 1.0.0
     *
     * @param  Promotion  $promotion  The promotion.
     *
     * @return void
     */
    public function mount( Promotion $promotion ): void
    {
        $this->authorizeEcommerce( 'view', $promotion );

        $this->promotionId = (int) $promotion->id;
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
        $this->authorizeEcommerce( 'view', Promotion::query()->findOrFail( $this->promotionId ) );
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $user          = auth()->user();
        $orderRoute    = AdminNav::ROUTE_PREFIX . 'orders.show';
        $customerRoute = AdminNav::ROUTE_PREFIX . 'customers.show';

        return view( 'ecommerce-admin::livewire.promotions.usage', [
            'usages'        => PromotionUsage::query()
                ->where( 'promotion_id', $this->promotionId )
                ->with( [ 'order:id,order_number,email', 'customer:id,first_name,last_name,email' ] )
                ->orderByDesc( 'created_at' )
                ->orderByDesc( 'id' )
                ->paginate( self::PER_PAGE, [ '*' ], 'usage-page' ),
            'totals'        => PromotionUsage::query()
                ->where( 'promotion_id', $this->promotionId )
                ->selectRaw( 'currency, SUM(amount_discounted) AS discounted, COUNT(*) AS uses' )
                ->groupBy( 'currency' )
                ->orderBy( 'currency' )
                ->get(),
            'orderRoute'    => Route::has( $orderRoute ) && Authorization::allows( $user, 'order.view' ) ? $orderRoute : null,
            'customerRoute' => Route::has( $customerRoute ) && Authorization::allows( $user, 'customer.view' ) ? $customerRoute : null,
            'canSeeNames'   => Authorization::allows( $user, 'customer.view' ),
            'customerName'  => static fn ( PromotionUsage $usage ): ?string => null === $usage->customer ? null : ( CustomersIndex::customerName( $usage->customer ) ?? (string) $usage->customer->email ),
        ] );
    }
}
