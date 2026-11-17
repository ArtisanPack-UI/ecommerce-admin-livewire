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
use Livewire\Component as LivewireComponent;
use Livewire\Livewire;

/**
 * `<x-artisanpack-ec-address-form model="form.shipping" :legend="__( 'Shipping address' )" />`
 *
 * Renders the address fields bound to `{model}.{field}` with the keys the
 * engine's `Address` value object and `customer_addresses` share, in a
 * design-system Fieldset. Country comes first and binds live, because it
 * decides how the region and postal code fields are labelled. Validation
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
     * @param  string|null  $description  A line under the legend.
     */
    public function __construct(
        public string $model,
        public ?string $legend = null,
        public bool $live = false,
        public bool $withName = true,
        public ?string $description = null,
    ) {
    }

    /**
     * The visible fields with their labels and autocomplete tokens.
     *
     * @since 1.0.0
     *
     * @return array<string, array{label: string, autocomplete: string, required: bool, wide: bool}>
     */
    public function fields(): array
    {
        $locality = $this->localityLabels();

        $fields = [
            'first_name'  => [ 'label' => __( 'First name' ), 'autocomplete' => 'given-name', 'required' => false, 'wide' => false ],
            'last_name'   => [ 'label' => __( 'Last name' ), 'autocomplete' => 'family-name', 'required' => false, 'wide' => false ],
            'company'     => [ 'label' => __( 'Company' ), 'autocomplete' => 'organization', 'required' => false, 'wide' => true ],
            'address1'    => [ 'label' => __( 'Address' ), 'autocomplete' => 'address-line1', 'required' => true, 'wide' => true ],
            'address2'    => [ 'label' => __( 'Apartment, suite, etc.' ), 'autocomplete' => 'address-line2', 'required' => false, 'wide' => true ],
            'city'        => [ 'label' => __( 'City' ), 'autocomplete' => 'address-level2', 'required' => true, 'wide' => false ],
            'region'      => [ 'label' => $locality['region'], 'autocomplete' => 'address-level1', 'required' => false, 'wide' => false ],
            'postal_code' => [ 'label' => $locality['postal_code'], 'autocomplete' => 'postal-code', 'required' => false, 'wide' => false ],
            'phone'       => [ 'label' => __( 'Phone' ), 'autocomplete' => 'tel', 'required' => false, 'wide' => false ],
        ];

        if ( ! $this->withName ) {
            unset( $fields['first_name'], $fields['last_name'], $fields['company'], $fields['phone'] );
        }

        return $fields;
    }

    /**
     * The country code currently bound at `{model}.country_code`.
     *
     * @since 2.0.0
     *
     * @return string|null
     */
    public function country(): ?string
    {
        $component = Livewire::current();
        $country   = $component instanceof LivewireComponent ? data_get( $component->all(), $this->model . '.country_code' ) : null;

        return is_string( $country ) && '' !== $country ? strtoupper( $country ) : null;
    }

    /**
     * The region and postal code labels for the bound country.
     *
     * @since 2.0.0
     *
     * @return array{region: string, postal_code: string}
     */
    public function localityLabels(): array
    {
        return match ( $this->country() ) {
            'US'       => [ 'region' => __( 'State' ), 'postal_code' => __( 'ZIP code' ) ],
            'CA'       => [ 'region' => __( 'Province' ), 'postal_code' => __( 'Postal code' ) ],
            'AU'       => [ 'region' => __( 'State' ), 'postal_code' => __( 'Postcode' ) ],
            'GB'       => [ 'region' => __( 'County' ), 'postal_code' => __( 'Postcode' ) ],
            'IE'       => [ 'region' => __( 'County' ), 'postal_code' => __( 'Eircode' ) ],
            default    => [ 'region' => __( 'State / region' ), 'postal_code' => __( 'Postal code' ) ],
        };
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
