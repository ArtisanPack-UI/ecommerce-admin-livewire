<?php

/**
 * Customer detail screen.
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
use ArtisanPackUI\Ecommerce\Services\CustomerService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\CustomerTabRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A customer's detail page (spec §7.4).
 *
 * The header shows lifetime value, order count, average order value, and
 * the first and last order dates. Below it, the tabs from the
 * {@see CustomerTabRegistry}: orders, addresses, notification preferences,
 * notes, activity, and any a satellite registers.
 *
 * Editing changes the name, phone, and marketing consent; turning consent on
 * or off records `accepts_marketing_at`. Deleting is the GDPR
 * delete-and-anonymize (plan §19.6): it explains what is erased and what is
 * kept, requires the customer's email typed back, carries a one-time action
 * token, and calls the engine's `CustomerService::delete()`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Show extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithActionToken;

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
     * The open tab.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( as: 'tab', except: '' )]
    public string $tab = '';

    /**
     * Whether the details form is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $editing = false;

    /**
     * Edited first name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $firstName = '';

    /**
     * Edited last name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $lastName = '';

    /**
     * Edited phone.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $phone = '';

    /**
     * Edited marketing consent.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $acceptsMarketing = false;

    /**
     * Whether the delete confirmation is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $confirmingDelete = false;

    /**
     * The email typed to confirm the delete.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $deleteConfirmation = '';

    /**
     * Refreshes after a tab changes the customer.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    protected $listeners = [ CustomerTabRegistry::CUSTOMER_UPDATED_EVENT => 'customerChanged' ];

    /**
     * The customer, loaded once per request.
     *
     * @since 1.0.0
     *
     * @var Customer|null
     */
    private ?Customer $loadedCustomer = null;

    /**
     * Loads and authorizes the customer.
     *
     * @since 1.0.0
     *
     * @param  int|string  $customer  The customer id.
     *
     * @return void
     */
    public function mount( int|string $customer ): void
    {
        $this->customerId = (int) $customer;

        $this->authorizeEcommerce( 'view', $this->customer() );
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
     * Drops the cached customer so the header shows a tab's change.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function customerChanged(): void
    {
        $this->loadedCustomer = null;
    }

    /**
     * Opens the details form.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function startEdit(): void
    {
        $customer = $this->customer();

        $this->authorizeEcommerce( 'update', $customer );

        $this->resetErrorBag();
        $this->firstName        = (string) $customer->first_name;
        $this->lastName         = (string) $customer->last_name;
        $this->phone            = (string) $customer->phone;
        $this->acceptsMarketing = (bool) $customer->accepts_marketing;
        $this->editing          = true;
    }

    /**
     * Closes the details form.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelEdit(): void
    {
        $this->editing = false;
        $this->resetErrorBag();
    }

    /**
     * Saves the name, phone, and marketing consent.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveDetails(): void
    {
        $customer = $this->customer();

        $this->authorizeEcommerce( 'update', $customer );

        $this->firstName = sanitizeText( $this->firstName );
        $this->lastName  = sanitizeText( $this->lastName );
        $this->phone     = sanitizeText( $this->phone );

        $this->validate(
            [
                'firstName'        => [ 'nullable', 'string', 'max:120' ],
                'lastName'         => [ 'nullable', 'string', 'max:120' ],
                'phone'            => [ 'nullable', 'string', 'max:50', 'regex:/^[0-9+().\\-\\s\\/x]*$/i' ],
                'acceptsMarketing' => [ 'boolean' ],
            ],
            [ 'phone.regex' => __( 'Use only digits, spaces, and + ( ) . - / x in the phone number.' ) ],
            [
                'firstName'        => __( 'first name' ),
                'lastName'         => __( 'last name' ),
                'phone'            => __( 'phone' ),
                'acceptsMarketing' => __( 'marketing consent' ),
            ],
        );

        $data = [
            'first_name' => '' === trim( $this->firstName ) ? null : trim( $this->firstName ),
            'last_name'  => '' === trim( $this->lastName ) ? null : trim( $this->lastName ),
            'phone'      => '' === trim( $this->phone ) ? null : trim( $this->phone ),
        ];

        if ( $this->acceptsMarketing !== (bool) $customer->accepts_marketing ) {
            $data['accepts_marketing']    = $this->acceptsMarketing;
            $data['accepts_marketing_at'] = $this->acceptsMarketing ? Carbon::now() : null;
        }

        $customer->fill( $data )->save();

        $this->editing        = false;
        $this->loadedCustomer = null;

        $this->toastSuccess( __( 'Customer saved.' ) );
    }

    /**
     * Opens the delete confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function startDelete(): void
    {
        $this->authorizeEcommerce( 'delete', $this->customer() );

        $this->resetErrorBag( 'deleteConfirmation' );
        $this->deleteConfirmation = '';
        $this->confirmingDelete   = true;
    }

    /**
     * Closes the delete confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelDelete(): void
    {
        $this->confirmingDelete   = false;
        $this->deleteConfirmation = '';
        $this->resetErrorBag( 'deleteConfirmation' );
    }

    /**
     * Deletes and anonymizes the customer, then returns to the list.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The one-time action token.
     *
     * @return void
     */
    public function deleteCustomer( string $token ): void
    {
        $customer = $this->customer();

        $this->authorizeEcommerce( 'delete', $customer );

        $this->validate(
            [ 'deleteConfirmation' => [ 'required', 'string' ] ],
            [],
            [ 'deleteConfirmation' => __( 'email' ) ],
        );

        if ( mb_strtolower( trim( $this->deleteConfirmation ) ) !== mb_strtolower( trim( (string) $customer->email ) ) ) {
            $this->addError( 'deleteConfirmation', __( 'Type the customer\'s email exactly to confirm.' ) );

            return;
        }

        $deleted = $this->withActionToken( $token, 'delete', function () use ( $customer ): bool {
            app( CustomerService::class )->delete( $customer, $this->actorId() );

            return true;
        }, $customer );

        if ( true !== $deleted ) {
            return;
        }

        $this->toastSuccess( __( 'Customer deleted. Their orders were kept and anonymized.' ) );

        $indexRoute = AdminNav::ROUTE_PREFIX . 'customers.index';

        if ( Route::has( $indexRoute ) ) {
            $this->redirectRoute( $indexRoute );
        }
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $customer = $this->customer();
        $tabs     = $this->visibleTabs();
        $keys     = array_column( $tabs, 'key' );

        if ( ! in_array( $this->tab, $keys, true ) ) {
            $this->tab = $keys[0] ?? '';
        }

        return view( 'ecommerce-admin::livewire.customers.show', [
            'customer'     => $customer,
            'name'         => Index::customerName( $customer ),
            'stats'        => $this->stats( $customer ),
            'tabs'         => $tabs,
            'canUpdate'    => $this->canEcommerce( 'update', $customer ),
            'canDelete'    => $this->canEcommerce( 'delete', $customer ),
            'deleteToken'  => $this->confirmingDelete ? $this->actionToken( 'delete', $customer ) : null,
            'indexUrl'     => Route::has( AdminNav::ROUTE_PREFIX . 'customers.index' ) ? route( AdminNav::ROUTE_PREFIX . 'customers.index' ) : null,
        ] );
    }

    /**
     * The header stats: lifetime value, order count, average order value,
     * and first and last order dates.
     *
     * Lifetime value and the order count are the counters the engine keeps
     * on the customer; the first order date is read from the orders.
     *
     * @since 1.0.0
     *
     * @param  Customer  $customer  The customer.
     *
     * @return array{currency: string, lifetimeValue: int, orderCount: int, averageOrderValue: int|null, firstOrderAt: Carbon|null, lastOrderAt: Carbon|null}
     */
    protected function stats( Customer $customer ): array
    {
        $lifetime = (int) $customer->total_spent_amount;
        $count    = (int) $customer->orders_count;
        $first    = Order::query()->where( 'customer_id', $customer->id )->whereNotNull( 'placed_at' )->min( 'placed_at' );

        return [
            'currency'          => Index::spentCurrency( $customer ),
            'lifetimeValue'     => $lifetime,
            'orderCount'        => $count,
            'averageOrderValue' => $count > 0 ? intdiv( $lifetime, $count ) : null,
            'firstOrderAt'      => null === $first ? null : Carbon::parse( $first ),
            'lastOrderAt'       => $customer->last_ordered_at,
        ];
    }

    /**
     * The registered tabs the user may see.
     *
     * @since 1.0.0
     *
     * @return array<int, array{key: string, label: string, component: string, position: int, parameter: string, ability: string|null}>
     */
    protected function visibleTabs(): array
    {
        $user     = auth()->user();
        $customer = $this->customer();

        return array_values( array_filter(
            app( CustomerTabRegistry::class )->all(),
            static fn ( array $tab ): bool => null === $tab['ability'] || Authorization::allows( $user, $tab['ability'], $customer ),
        ) );
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
        return $this->loadedCustomer ??= Customer::query()->findOrFail( $this->customerId );
    }

    /**
     * The acting user's id.
     *
     * @since 1.0.0
     *
     * @return int|null
     */
    private function actorId(): ?int
    {
        $id = auth()->id();

        return is_numeric( $id ) ? (int) $id : null;
    }
}
