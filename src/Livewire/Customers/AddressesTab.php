<?php

/**
 * Customer addresses tab.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers;

use ArtisanPackUI\Ecommerce\Exceptions\CustomerWriteException;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\Services\CustomerAddressService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\CustomerTabRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Countries;
use ArtisanPackUI\EcommerceAdminLivewire\View\Components\AddressForm;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A customer's saved addresses (spec §7.4): add, edit, delete, and set the
 * default shipping and billing address.
 *
 * Writes go through the engine's `CustomerAddressService`, which keeps one
 * default of each kind per customer and records the change in the
 * customer's activity. Deleting asks for confirmation and carries a
 * one-time action token.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class AddressesTab extends Component
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
     * Whether the add/edit form is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $showForm = false;

    /**
     * The address being edited; null while adding.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $editingId = null;

    /**
     * The form state.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    public array $address = [];

    /**
     * The address waiting for delete confirmation.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $deletingId = null;

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
        $this->address    = self::emptyAddress();
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
     * Opens an empty form.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function startAdd(): void
    {
        $this->authorizeEcommerce( 'update', $this->customer() );

        $this->resetErrorBag();
        $this->editingId  = null;
        $this->deletingId = null;
        $this->address    = self::emptyAddress();
        $this->showForm   = true;
    }

    /**
     * Opens the form on an address.
     *
     * @since 1.0.0
     *
     * @param  int  $addressId  The address id.
     *
     * @return void
     */
    public function startEdit( int $addressId ): void
    {
        $customer = $this->customer();

        $this->authorizeEcommerce( 'update', $customer );

        $address = $this->findAddress( $addressId );

        if ( null === $address ) {
            $this->toastWarning( __( 'That address no longer exists.' ) );

            return;
        }

        $this->resetErrorBag();
        $this->editingId  = (int) $address->id;
        $this->deletingId = null;
        $this->address    = array_replace( self::emptyAddress(), array_map(
            static fn ( mixed $value ): mixed => $value ?? '',
            $address->only( [ 'label', 'is_default_shipping', 'is_default_billing', ...AddressForm::FIELDS ] ),
        ) );
        $this->showForm   = true;
    }

    /**
     * Closes the form.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelForm(): void
    {
        $this->showForm  = false;
        $this->editingId = null;
        $this->address   = self::emptyAddress();
        $this->resetErrorBag();
    }

    /**
     * Saves the form as a new address or over the one being edited.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveAddress(): void
    {
        $customer = $this->customer();

        $this->authorizeEcommerce( 'update', $customer );

        $this->address = array_map(
            static fn ( mixed $value ): mixed => is_string( $value ) ? sanitizeText( $value ) : $value,
            array_intersect_key( $this->address, self::emptyAddress() ) + self::emptyAddress(),
        );

        $this->validate( self::rules(), [], self::attributes() );

        $address = null === $this->editingId ? null : $this->findAddress( $this->editingId );

        if ( null !== $this->editingId && null === $address ) {
            $this->cancelForm();
            $this->toastWarning( __( 'That address no longer exists.' ) );

            return;
        }

        $service = app( CustomerAddressService::class );

        try {
            null === $address
                ? $service->create( $customer, $this->attributesForSave(), $this->actorId() )
                : $service->update( $address, $this->attributesForSave(), $this->actorId() );
        } catch ( CustomerWriteException $exception ) {
            foreach ( $exception->errors as $error ) {
                $field = (string) ( $error['field'] ?? '' );

                $this->addError( array_key_exists( $field, self::emptyAddress() ) ? 'address.' . $field : 'address', (string) $error['message'] );
            }

            return;
        }

        $this->cancelForm();
        $this->customerUpdated();
        $this->toastSuccess( null === $address ? __( 'Address added.' ) : __( 'Address saved.' ) );
    }

    /**
     * Makes an address the default shipping or billing address.
     *
     * @since 1.0.0
     *
     * @param  int     $addressId  The address id.
     * @param  string  $kind       `shipping` or `billing`.
     *
     * @return void
     */
    public function makeDefault( int $addressId, string $kind ): void
    {
        $this->authorizeEcommerce( 'update', $this->customer() );

        $address = $this->findAddress( $addressId );

        if ( null === $address || ! in_array( $kind, [ 'shipping', 'billing' ], true ) ) {
            return;
        }

        try {
            app( CustomerAddressService::class )->update( $address, [ 'is_default_' . $kind => true ], $this->actorId() );
        } catch ( CustomerWriteException $exception ) {
            $this->toastError( __( 'The address could not be updated.' ), (string) ( $exception->errors[0]['message'] ?? $exception->getMessage() ) );

            return;
        }

        $this->customerUpdated();
        $this->toastSuccess( 'shipping' === $kind ? __( 'Default shipping address set.' ) : __( 'Default billing address set.' ) );
    }

    /**
     * Asks to confirm deleting an address.
     *
     * @since 1.0.0
     *
     * @param  int  $addressId  The address id.
     *
     * @return void
     */
    public function confirmDelete( int $addressId ): void
    {
        $this->authorizeEcommerce( 'update', $this->customer() );

        $this->deletingId = null === $this->findAddress( $addressId ) ? null : $addressId;
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
        $this->deletingId = null;
    }

    /**
     * Deletes the address waiting for confirmation.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The one-time action token.
     *
     * @return void
     */
    public function deleteAddress( string $token ): void
    {
        $customer = $this->customer();

        $this->authorizeEcommerce( 'update', $customer );

        $address = null === $this->deletingId ? null : $this->findAddress( $this->deletingId );

        $this->deletingId = null;

        if ( null === $address ) {
            return;
        }

        $deleted = $this->withActionToken( $token, 'delete-address', function () use ( $address ): bool {
            app( CustomerAddressService::class )->delete( $address, $this->actorId() );

            return true;
        }, $customer );

        if ( true !== $deleted ) {
            return;
        }

        if ( $this->editingId === (int) $address->id ) {
            $this->cancelForm();
        }

        $this->customerUpdated();
        $this->toastSuccess( __( 'Address deleted.' ) );
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $customer = $this->customer();

        return view( 'ecommerce-admin::livewire.customers.tabs.addresses', [
            'addresses'   => $this->addresses(),
            'canUpdate'   => $this->canEcommerce( 'update', $customer ),
            'deleteToken' => null === $this->deletingId ? null : $this->actionToken( 'delete-address', $customer ),
        ] );
    }

    /**
     * The form's starting state.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public static function emptyAddress(): array
    {
        return [ 'label' => '', 'is_default_shipping' => false, 'is_default_billing' => false ] + array_fill_keys( AddressForm::FIELDS, '' );
    }

    /**
     * The form's validation rules.
     *
     * @since 1.0.0
     *
     * @return array<string, array<int, mixed>>
     */
    protected static function rules(): array
    {
        return [
            'address.label'               => [ 'nullable', 'string', 'max:120' ],
            'address.is_default_shipping' => [ 'boolean' ],
            'address.is_default_billing'  => [ 'boolean' ],
            'address.first_name'          => [ 'nullable', 'string', 'max:120' ],
            'address.last_name'           => [ 'nullable', 'string', 'max:120' ],
            'address.company'             => [ 'nullable', 'string', 'max:120' ],
            'address.phone'               => [ 'nullable', 'string', 'max:50' ],
            'address.address1'            => [ 'required', 'string', 'max:255' ],
            'address.address2'            => [ 'nullable', 'string', 'max:255' ],
            'address.city'                => [ 'required', 'string', 'max:120' ],
            'address.region'              => [ 'nullable', 'string', 'max:120' ],
            'address.postal_code'         => [ 'nullable', 'string', 'max:20' ],
            'address.country_code'        => [ 'required', 'string', Rule::in( array_column( Countries::options(), 'id' ) ) ],
        ];
    }

    /**
     * Human names for the validated fields.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected static function attributes(): array
    {
        return [
            'address.label'        => __( 'label' ),
            'address.first_name'   => __( 'first name' ),
            'address.last_name'    => __( 'last name' ),
            'address.company'      => __( 'company' ),
            'address.phone'        => __( 'phone' ),
            'address.address1'     => __( 'address' ),
            'address.address2'     => __( 'apartment, suite, etc.' ),
            'address.city'         => __( 'city' ),
            'address.region'       => __( 'state / region' ),
            'address.postal_code'  => __( 'postal code' ),
            'address.country_code' => __( 'country' ),
        ];
    }

    /**
     * The form state as service attributes: blanks become null.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function attributesForSave(): array
    {
        $attributes = [];

        foreach ( $this->address as $field => $value ) {
            $attributes[ $field ] = match ( true ) {
                str_starts_with( $field, 'is_default_' ) => (bool) $value,
                'country_code' === $field                => strtoupper( trim( (string) $value ) ),
                default                                  => '' === trim( (string) $value ) ? null : trim( (string) $value ),
            };
        }

        return $attributes;
    }

    /**
     * The customer's addresses, defaults first.
     *
     * @since 1.0.0
     *
     * @return Collection<int, CustomerAddress>
     */
    protected function addresses(): Collection
    {
        return CustomerAddress::query()
            ->where( 'customer_id', $this->customerId )
            ->orderByDesc( 'is_default_shipping' )
            ->orderByDesc( 'is_default_billing' )
            ->orderBy( 'id' )
            ->get();
    }

    /**
     * One of the customer's addresses.
     *
     * @since 1.0.0
     *
     * @param  int  $addressId  The address id.
     *
     * @return CustomerAddress|null
     */
    protected function findAddress( int $addressId ): ?CustomerAddress
    {
        return CustomerAddress::query()->where( 'customer_id', $this->customerId )->find( $addressId );
    }

    /**
     * Tells the page the customer changed.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function customerUpdated(): void
    {
        $this->dispatch( CustomerTabRegistry::CUSTOMER_UPDATED_EVENT );
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
