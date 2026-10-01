<?php

/**
 * Address form component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

use ArtisanPackUI\EcommerceAdminLivewire\Support\Countries;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-address-form model="form.shipping" :legend="__( 'Shipping address' )" />`
 *
 * Renders the address fields bound to `{model}.{field}` with the keys the
 * engine's `Address` value object and `customer_addresses` share. Validation
 * errors show under each field from the same keys.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class AddressForm extends Component
{
    /**
     * The fields, in order.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const FIELDS = [
        'first_name',
        'last_name',
        'company',
        'phone',
        'address1',
        'address2',
        'city',
        'region',
        'postal_code',
        'country_code',
    ];

    /**
     * @since 1.0.0
     *
     * @param  string       $model     The Livewire property path the fields bind under.
     * @param  string|null  $legend    The fieldset legend.
     * @param  bool         $live      Bind with `wire:model.live.blur` instead of deferred.
     * @param  bool         $withName  Include the name, company, and phone fields.
     */
    public function __construct(
        public string $model,
        public ?string $legend = null,
        public bool $live = false,
        public bool $withName = true,
    ) {
    }

    /**
     * The visible fields with their labels and autocomplete tokens.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, autocomplete: string, required: bool}>
     */
    public function fields(): array
    {
        $fields = [
            'first_name'  => [ 'label' => __( 'First name' ), 'autocomplete' => 'given-name', 'required' => false ],
            'last_name'   => [ 'label' => __( 'Last name' ), 'autocomplete' => 'family-name', 'required' => false ],
            'company'     => [ 'label' => __( 'Company' ), 'autocomplete' => 'organization', 'required' => false ],
            'phone'       => [ 'label' => __( 'Phone' ), 'autocomplete' => 'tel', 'required' => false ],
            'address1'    => [ 'label' => __( 'Address' ), 'autocomplete' => 'address-line1', 'required' => true ],
            'address2'    => [ 'label' => __( 'Apartment, suite, etc.' ), 'autocomplete' => 'address-line2', 'required' => false ],
            'city'        => [ 'label' => __( 'City' ), 'autocomplete' => 'address-level2', 'required' => true ],
            'region'      => [ 'label' => __( 'State / region' ), 'autocomplete' => 'address-level1', 'required' => false ],
            'postal_code' => [ 'label' => __( 'Postal code' ), 'autocomplete' => 'postal-code', 'required' => false ],
        ];

        if ( ! $this->withName ) {
            unset( $fields['first_name'], $fields['last_name'], $fields['company'], $fields['phone'] );
        }

        return $fields;
    }

    /**
     * The country select options.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function countries(): array
    {
        return Countries::options();
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        return view( 'ecommerce-admin::components.address-form' );
    }
}
