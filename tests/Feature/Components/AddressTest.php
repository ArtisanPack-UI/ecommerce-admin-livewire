<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\ValueObjects\Address;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Countries;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\HelperForm;

$address = [
    'first_name'   => 'Ada',
    'last_name'    => 'Lovelace',
    'company'      => 'Analytical Engines Ltd',
    'phone'        => '+44 20 7946 0000',
    'address1'     => '12 St James Square',
    'address2'     => 'Flat 3',
    'city'         => 'London',
    'region'       => null,
    'region_code'  => null,
    'postal_code'  => 'SW1Y 4JH',
    'country_code' => 'GB',
];

it( 'renders an order address array in an address element', function () use ( $address ): void {
    $html = Blade::render( '<x-artisanpack-ec-address :address="$address" />', compact( 'address' ) );

    expect( $html )->toContain( '<address' )
        ->toContain( 'Ada Lovelace' )
        ->toContain( 'Analytical Engines Ltd' )
        ->toContain( '12 St James Square' )
        ->toContain( 'Flat 3' )
        ->toContain( 'London SW1Y 4JH' )
        ->toContain( 'United Kingdom' )
        ->toContain( '+44 20 7946 0000' );
} );

it( 'renders the engine Address value object', function () use ( $address ): void {
    $html = Blade::render( '<x-artisanpack-ec-address :address="$address" />', [ 'address' => Address::fromArray( $address ) ] );

    expect( $html )->toContain( 'Ada Lovelace' )->toContain( 'United Kingdom' );
} );

it( 'renders a customer address model', function () use ( $address ): void {
    $model = CustomerAddress::query()->create( [ 'customer_id' => Customer::factory()->create()->getKey() ] + array_diff_key( $address, [ 'region_code' => true ] ) );

    expect( Blade::render( '<x-artisanpack-ec-address :address="$address" :show-phone="false" />', [ 'address' => $model ] ) )
        ->toContain( 'Ada Lovelace' )
        ->not->toContain( '+44 20 7946 0000' );
} );

it( 'localizes the country name', function () use ( $address ): void {
    app()->setLocale( 'de' );

    expect( Blade::render( '<x-artisanpack-ec-address :address="$address" />', compact( 'address' ) ) )
        ->toContain( 'Vereinigtes Königreich' );
} );

it( 'says so when there is no address', function (): void {
    expect( Blade::render( '<x-artisanpack-ec-address :address="null" />' ) )->toContain( 'No address' );
} );

it( 'lists every ISO 3166-1 country', function (): void {
    expect( Countries::CODES )->toHaveCount( 249 )
        ->and( Countries::name( 'de', 'en' ) )->toBe( 'Germany' )
        ->and( Countries::name( 'XX' ) )->toBe( 'XX' )
        ->and( Countries::options( 'en' )[0] )->toBe( [ 'id' => 'AF', 'name' => 'Afghanistan' ] );
} );

describe( 'address form', function (): void {
    beforeEach( function (): void {
        view()->share( 'errors', new ViewErrorBag() );
        grantAbilities( [ 'order.viewAny' ] );
        $this->actingAs( makeUser() );
    } );

    it( 'binds every field under the model path', function (): void {
        $html = Livewire::test( HelperForm::class )->html();

        foreach ( [ 'first_name', 'last_name', 'company', 'phone', 'address1', 'address2', 'city', 'region', 'postal_code', 'country_code' ] as $field ) {
            expect( $html )->toContain( 'wire:model="shipping.' . $field . '"' );
        }

        expect( $html )->toContain( 'Shipping address' )
            ->toContain( 'autocomplete="address-line1"' )
            ->toContain( 'value="GB"' );
    } );

    it( 'binds live when asked', function (): void {
        expect( Blade::render( '<x-artisanpack-ec-address-form model="billing" live :with-name="false" />' ) )
            ->toContain( 'wire:model.live.blur="billing.address1"' )
            ->toContain( 'wire:model.live="billing.country_code"' )
            ->not->toContain( 'billing.first_name' );
    } );

    it( 'shows validation failures under the fields', function (): void {
        Livewire::test( HelperForm::class )
            ->call( 'save' )
            ->assertHasErrors( [ 'shipping.address1', 'shipping.city', 'shipping.country_code' ] )
            ->assertSee( 'The shipping.address1 field is required.' );
    } );

    it( 'saves a complete address', function (): void {
        Livewire::test( HelperForm::class )
            ->set( 'shipping.address1', '1 Main St' )
            ->set( 'shipping.city', 'Springfield' )
            ->set( 'shipping.country_code', 'US' )
            ->call( 'save' )
            ->assertHasNoErrors()
            ->assertSet( 'saved', true );
    } );
} );
