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
        ->toContain( '<span>London</span>' )
        ->toContain( '<span>SW1Y 4JH</span>' )
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
    expect( Blade::render( '<x-artisanpack-ec-address :address="null" />' ) )
        ->toContain( '<p class="ec-address-empty">No address</p>' )
        ->not->toContain( '<address' );
} );

it( 'sets the name apart and prefixes the phone for screen readers', function () use ( $address ): void {
    expect( Blade::render( '<x-artisanpack-ec-admin-address :address="$address" />', compact( 'address' ) ) )
        ->toContain( '<span class="ec-address-name">Ada Lovelace</span>' )
        ->toContain( '<span class="apui-sr-only">Phone:</span> <bdi dir="ltr">+44 20 7946 0000</bdi>' )
        ->toContain( '<svg' );
} );

it( 'writes the lines in the country\'s order', function ( array $fields, array $lines ): void {
    $html = Blade::render( '<x-artisanpack-ec-admin-address :address="$address" />', [ 'address' => $fields ] );

    preg_match_all( '/<span>([^<]*)<\/span>/', $html, $matches );

    expect( $matches[1] )->toBe( $lines );
} )->with( [
    'United States' => [
        [ 'address1' => '2140 Lakeshore Drive', 'city' => 'Austin', 'region' => 'TX', 'postal_code' => '78704', 'country_code' => 'US' ],
        [ '2140 Lakeshore Drive', 'Austin, TX 78704', 'United States' ],
    ],
    'France' => [
        [ 'address1' => '18 rue des Lilas', 'city' => 'Lyon', 'postal_code' => '69003', 'country_code' => 'FR' ],
        [ '18 rue des Lilas', '69003 Lyon', 'France' ],
    ],
    'United Kingdom' => [
        [ 'address1' => '12 St James Square', 'city' => 'London', 'region' => 'Greater London', 'postal_code' => 'SW1Y 4JH', 'country_code' => 'GB' ],
        [ '12 St James Square', 'London', 'Greater London', 'SW1Y 4JH', 'United Kingdom' ],
    ],
    'region code only' => [
        [ 'address1' => '1 Main St', 'city' => 'Springfield', 'region_code' => 'IL', 'postal_code' => '62701', 'country_code' => 'US' ],
        [ '1 Main St', 'Springfield, IL 62701', 'United States' ],
    ],
] );

it( 'is registered under the admin name too', function () use ( $address ): void {
    expect( Blade::render( '<x-artisanpack-ec-admin-address :address="$address" />', compact( 'address' ) ) )
        ->toContain( '<address class="ec-address">' )
        ->and( Blade::render( '<x-artisanpack-ec-admin-address-form model="billing" legend="Billing address" />' ) )
        ->toContain( 'apui-fieldset ec-address-form' );
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

        foreach ( [ 'first_name', 'last_name', 'company', 'phone', 'address1', 'address2', 'city', 'region', 'postal_code' ] as $field ) {
            expect( $html )->toContain( 'wire:model="shipping.' . $field . '"' );
        }

        expect( $html )->toContain( 'wire:model.live="shipping.country_code"' )
            ->toContain( 'Shipping address' )
            ->toContain( 'autocomplete="address-line1"' )
            ->toContain( 'value="GB"' );
    } );

    it( 'binds live when asked', function (): void {
        expect( Blade::render( '<x-artisanpack-ec-address-form model="billing" live :with-name="false" />' ) )
            ->toContain( 'wire:model.live.blur="billing.address1"' )
            ->toContain( 'wire:model.live="billing.country_code"' )
            ->not->toContain( 'billing.first_name' );
    } );

    it( 'puts country first in a fieldset and spans the long fields', function (): void {
        $html = Blade::render( '<x-artisanpack-ec-address-form model="billing" legend="Billing address" description="Where the invoice goes." />' );

        expect( $html )->toContain( 'class="apui-fieldset ec-address-form"' )
            ->toContain( 'data-variant="panel"' )
            ->toContain( 'Billing address' )
            ->toContain( 'Where the invoice goes.' )
            ->and( strpos( $html, 'billing.country_code' ) )->toBeLessThan( strpos( $html, 'billing.first_name' ) )
            ->and( $html )->toMatch( '/class="ec-span-2" wire:key="billing-address1"/' )
            ->toMatch( '/class="ec-span-2" wire:key="billing-company"/' )
            ->not->toMatch( '/class="ec-span-2" wire:key="billing-city"/' );
    } );

    it( 'is a plain fieldset without a legend', function (): void {
        expect( Blade::render( '<x-artisanpack-ec-address-form model="billing" />' ) )
            ->toContain( 'data-variant="plain"' )
            ->not->toContain( 'data-part="legend"' );
    } );

    it( 'labels the region and postal code for the chosen country', function (): void {
        Livewire::test( HelperForm::class )
            ->assertSee( 'State / region' )
            ->assertSee( 'Postal code' )
            ->set( 'shipping.country_code', 'US' )
            ->assertSee( 'ZIP code' )
            ->assertDontSee( 'State / region' )
            ->set( 'shipping.country_code', 'GB' )
            ->assertSee( 'County' )
            ->assertSee( 'Postcode' );
    } );

    it( 'marks required fields', function (): void {
        $html = Blade::render( '<x-artisanpack-ec-address-form model="billing" />' );

        expect( $html )->toMatch( '/id="billing.address1"[^>]*required/s' )
            ->toMatch( '/autocomplete="postal-code"/' );
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
