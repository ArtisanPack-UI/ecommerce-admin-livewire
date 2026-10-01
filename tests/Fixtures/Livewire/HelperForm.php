<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithPickers;
use Livewire\Component;

/**
 * A stand-in form that uses every display and input helper.
 */
class HelperForm extends Component
{
    use AuthorizesEcommerce;
    use WithPickers;

    public ?int $priceAmount = 123456;

    public string $currency = 'USD';

    public ?int $rateUbps = 83750000;

    /** @var array<int, int> */
    public array $productIds = [];

    public ?int $customerId = null;

    /** @var array<string, string|null> */
    public array $shipping = [
        'first_name'   => null,
        'last_name'    => null,
        'company'      => null,
        'phone'        => null,
        'address1'     => null,
        'address2'     => null,
        'city'         => null,
        'region'       => null,
        'postal_code'  => null,
        'country_code' => null,
    ];

    public bool $saved = false;

    public function mount(): void
    {
        $this->authorizeAdminAccess();
    }

    public function save(): void
    {
        $this->authorizeAdminAccess();

        $this->validate( [
            'priceAmount'           => [ 'required', 'integer', 'min:0' ],
            'rateUbps'              => [ 'required', 'integer', 'between:0,1000000000' ],
            'shipping.address1'     => [ 'required', 'string' ],
            'shipping.city'         => [ 'required', 'string' ],
            'shipping.country_code' => [ 'required', 'size:2' ],
        ] );

        $this->saved = true;
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-artisanpack-ec-money-input wire:model="priceAmount" :currency="$currency" :label="__( 'Price' )" />
                <x-artisanpack-ec-percent-input wire:model="rateUbps" :label="__( 'Rate' )" />
                <x-artisanpack-ec-product-picker model="productIds" :options="$this->optionsForPicker( 'product', 'productIds' )" label="Products" />
                <x-artisanpack-ec-customer-picker model="customerId" single :options="$this->optionsForPicker( 'customer', 'customerId' )" label="Customer" />
                <x-artisanpack-ec-category-picker model="productIds" label="Categories" />
                <x-artisanpack-ec-address-form model="shipping" legend="Shipping address" />
            </div>
            BLADE;
    }
}
