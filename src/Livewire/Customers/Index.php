<?php

/**
 * Customers index screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithResourceTable;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\CustomersQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\MinorUnits;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StoreCurrencies;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The customers table (spec §7.4): name, email, order count, total spent,
 * last order, marketing consent, and whether the customer has an account.
 *
 * Filters: search (name, email, phone), has account, accepts marketing,
 * order-count and spend ranges, and a last-order date range. The only bulk
 * action is export; editing and deleting happen on the detail page.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Index extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithActionToken;
    use WithResourceTable;

    /**
     * Authorizes the screen.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeTable();
    }

    /**
     * A customer's full name, or null when neither name is set.
     *
     * @since 1.0.0
     *
     * @param  Customer  $customer  The customer.
     *
     * @return string|null
     */
    public static function customerName( Customer $customer ): ?string
    {
        $name = trim( ( $customer->first_name ?? '' ) . ' ' . ( $customer->last_name ?? '' ) );

        return '' === $name ? null : $name;
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        return view( 'ecommerce-admin::livewire.customers.index', $this->resourceTableData() );
    }

    /**
     * The currency a customer's total spent is kept in.
     *
     * @since 1.0.0
     *
     * @param  Customer  $customer  The customer.
     *
     * @return string
     */
    public static function spentCurrency( Customer $customer ): string
    {
        $currency = (string) $customer->total_spent_currency;

        return '' === $currency ? StoreCurrencies::base() : strtoupper( $currency );
    }

    /**
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeTable(): void
    {
        $this->authorizeEcommerce( 'viewAny', Customer::class );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableScreen(): string
    {
        return 'customers';
    }

    /**
     * @since 1.0.0
     *
     * @return ResourceQuery
     */
    protected function tableQuery(): ResourceQuery
    {
        return new CustomersQuery();
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableCaption(): string
    {
        return __( 'Customers' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableColumns(): array
    {
        $cells = 'ecommerce-admin::livewire.customers.cells.';

        return [
            [
                'key'      => 'name',
                'label'    => __( 'Name' ),
                'sortable' => true,
                'view'     => $cells . 'name',
                'export'   => static fn ( Customer $customer ): string => self::customerName( $customer ) ?? '',
            ],
            [
                'key'      => 'email',
                'label'    => __( 'Email' ),
                'sortable' => true,
            ],
            [
                'key'      => 'orders',
                'label'    => __( 'Orders' ),
                'sortable' => true,
                'class'    => 'text-end tabular-nums',
                'value'    => static fn ( Customer $customer ): string => (string) (int) $customer->orders_count,
                'export'   => static fn ( Customer $customer ): int => (int) $customer->orders_count,
            ],
            [
                'key'      => 'spent',
                'label'    => __( 'Total spent' ),
                'sortable' => true,
                'class'    => 'text-end',
                'view'     => $cells . 'spent',
                'export'   => static fn ( Customer $customer ): string => MinorUnits::toMajor( (int) $customer->total_spent_amount, self::spentCurrency( $customer ) ),
            ],
            [
                'key'      => 'last_order',
                'label'    => __( 'Last order' ),
                'sortable' => true,
                'view'     => $cells . 'last-order',
                'export'   => static fn ( Customer $customer ): mixed => $customer->last_ordered_at,
            ],
            [
                'key'    => 'marketing',
                'label'  => __( 'Accepts marketing' ),
                'view'   => $cells . 'marketing',
                'export' => static fn ( Customer $customer ): string => $customer->accepts_marketing ? __( 'Yes' ) : __( 'No' ),
            ],
            [
                'key'    => 'account',
                'label'  => __( 'Account' ),
                'view'   => $cells . 'account',
                'export' => static fn ( Customer $customer ): string => null === $customer->user_id ? __( 'Guest' ) : __( 'Has an account' ),
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
        return [
            [ 'key' => 'has_account', 'label' => __( 'Has an account' ), 'type' => 'boolean' ],
            [ 'key' => 'accepts_marketing', 'label' => __( 'Accepts marketing' ), 'type' => 'boolean' ],
            [ 'key' => 'orders', 'label' => __( 'Number of orders' ), 'type' => 'number-range' ],
            [ 'key' => 'spent', 'label' => __( 'Total spent (:currency)', [ 'currency' => StoreCurrencies::base() ] ), 'type' => 'number-range' ],
            [ 'key' => 'last_order', 'label' => __( 'Last order' ), 'type' => 'date-range' ],
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
}
