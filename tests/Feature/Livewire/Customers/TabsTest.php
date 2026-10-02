<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\Models\CustomerNotificationPreference;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\AddressesTab;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\OrdersTab;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\PreferencesTab;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\CustomerTabRegistry;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'customer.viewAny', 'customer.view', 'customer.update', 'order.viewAny', 'order.view' ] );
    $this->actingAs( makeUser() );
} );

/**
 * The one-time token the address delete button carries.
 */
function addressDeleteToken( $component ): string
{
    expect( preg_match( "/deleteAddress\\( '([^']+)' \\)/", $component->html(), $matches ) )->toBe( 1 );

    return $matches[1];
}

describe( 'orders tab', function (): void {
    it( 'lists the customer\'s orders newest first, with links', function (): void {
        $customer = Customer::factory()->create();
        $old      = Order::factory()->create( [ 'customer_id' => $customer->id, 'order_number' => 'OLD00001', 'placed_at' => now()->subYear() ] );
        Order::factory()->create( [ 'customer_id' => $customer->id, 'order_number' => 'NEW00001', 'placed_at' => now() ] );
        Order::factory()->create( [ 'order_number' => 'OTHER001' ] );

        Livewire::test( OrdersTab::class, [ 'customer' => $customer ] )
            ->assertOk()
            ->assertSeeInOrder( [ '#NEW00001', '#OLD00001' ] )
            ->assertDontSee( 'OTHER001' )
            ->assertSeeHtml( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => $old->id ] ) );
    } );

    it( 'paginates', function (): void {
        $customer = Customer::factory()->create();
        Order::factory()->count( OrdersTab::PER_PAGE + 2 )->create( [ 'customer_id' => $customer->id ] );

        $component = Livewire::test( OrdersTab::class, [ 'customer' => $customer ] );

        expect( substr_count( $component->html(), 'wire:key="customer-order-' ) )->toBe( OrdersTab::PER_PAGE );

        $component->call( 'gotoPage', 2, 'orders-page' );

        expect( substr_count( $component->html(), 'wire:key="customer-order-' ) )->toBe( 2 );
    } );

    it( 'shows the empty state', function (): void {
        Livewire::test( OrdersTab::class, [ 'customer' => Customer::factory()->create() ] )->assertSee( 'No orders yet' );
    } );

    it( 'is denied without customer.view', function (): void {
        Gate::define( 'ecommerce.customer.view', static fn (): bool => false );

        Livewire::test( OrdersTab::class, [ 'customer' => Customer::factory()->create() ] )->assertForbidden();
    } );
} );

describe( 'addresses tab', function (): void {
    it( 'lists addresses with their default badges', function (): void {
        $customer = Customer::factory()->create();
        CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'label' => 'Home', 'address1' => '1 Home St', 'is_default_shipping' => true ] );
        CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'label' => 'Office', 'address1' => '2 Work Ave' ] );

        Livewire::test( AddressesTab::class, [ 'customer' => $customer ] )
            ->assertOk()
            ->assertSeeInOrder( [ 'Home', 'Default shipping', '1 Home St', 'Office', '2 Work Ave' ] );
    } );

    it( 'adds an address', function (): void {
        $customer = Customer::factory()->create();

        Livewire::test( AddressesTab::class, [ 'customer' => $customer ] )
            ->call( 'startAdd' )
            ->set( 'address.label', 'Phone order' )
            ->set( 'address.address1', '10 Downing St' )
            ->set( 'address.city', 'London' )
            ->set( 'address.country_code', 'GB' )
            ->set( 'address.is_default_billing', true )
            ->call( 'saveAddress' )
            ->assertHasNoErrors()
            ->assertSet( 'showForm', false )
            ->assertDispatched( CustomerTabRegistry::CUSTOMER_UPDATED_EVENT )
            ->assertSee( '10 Downing St' );

        $address = CustomerAddress::query()->where( 'customer_id', $customer->id )->sole();

        expect( $address )
            ->label->toBe( 'Phone order' )
            ->country_code->toBe( 'GB' )
            ->is_default_billing->toBeTrue()
            ->address2->toBeNull();
    } );

    it( 'validates the address', function (): void {
        Livewire::test( AddressesTab::class, [ 'customer' => Customer::factory()->create() ] )
            ->call( 'startAdd' )
            ->set( 'address.country_code', 'ZZ' )
            ->call( 'saveAddress' )
            ->assertHasErrors( [ 'address.address1' => 'required', 'address.city' => 'required', 'address.country_code' => 'in' ] );
    } );

    it( 'edits an address', function (): void {
        $customer = Customer::factory()->create();
        $address  = CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'address1' => '1 Old Rd' ] );

        Livewire::test( AddressesTab::class, [ 'customer' => $customer ] )
            ->call( 'startEdit', $address->id )
            ->assertSet( 'address.address1', '1 Old Rd' )
            ->set( 'address.address1', '2 New Rd' )
            ->call( 'saveAddress' )
            ->assertHasNoErrors();

        expect( $address->fresh()->address1 )->toBe( '2 New Rd' );
    } );

    it( 'does not edit another customer\'s address', function (): void {
        $customer = Customer::factory()->create();
        $other    = CustomerAddress::factory()->create( [ 'address1' => 'Not yours' ] );

        Livewire::test( AddressesTab::class, [ 'customer' => $customer ] )
            ->call( 'startEdit', $other->id )
            ->assertSet( 'showForm', false )
            ->assertSet( 'editingId', null );
    } );

    it( 'moves the default to another address', function (): void {
        $customer = Customer::factory()->create();
        $first    = CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'is_default_shipping' => true ] );
        $second   = CustomerAddress::factory()->create( [ 'customer_id' => $customer->id ] );

        Livewire::test( AddressesTab::class, [ 'customer' => $customer ] )
            ->call( 'makeDefault', $second->id, 'shipping' );

        expect( $second->fresh()->is_default_shipping )->toBeTrue()
            ->and( $first->fresh()->is_default_shipping )->toBeFalse();
    } );

    it( 'deletes an address after confirming', function (): void {
        $customer = Customer::factory()->create();
        $address  = CustomerAddress::factory()->create( [ 'customer_id' => $customer->id ] );

        $component = Livewire::test( AddressesTab::class, [ 'customer' => $customer ] )
            ->call( 'confirmDelete', $address->id )
            ->assertSee( 'Delete this address?' );

        $component->call( 'deleteAddress', addressDeleteToken( $component ) );

        expect( CustomerAddress::query()->whereKey( $address->id )->exists() )->toBeFalse();
    } );

    it( 'refuses changes without customer.update', function (): void {
        Gate::define( 'ecommerce.customer.update', static fn (): bool => false );

        $customer = Customer::factory()->create();
        CustomerAddress::factory()->create( [ 'customer_id' => $customer->id ] );

        Livewire::test( AddressesTab::class, [ 'customer' => $customer ] )
            ->assertDontSeeHtml( 'data-add-address' )
            ->call( 'startAdd' )
            ->assertForbidden();
    } );
} );

describe( 'notification preferences tab', function (): void {
    it( 'shows the preferences with transactional messages locked', function (): void {
        $customer = Customer::factory()->create( [ 'accepts_marketing' => false ] );

        Livewire::test( PreferencesTab::class, [ 'customer' => $customer ] )
            ->assertOk()
            ->assertSee( [ 'Order and account messages', 'Always on', 'Review requests', 'Marketing' ] )
            ->assertSet( 'preferences.mail.marketing', false )
            ->assertSet( 'preferences.mail.review-requests', true );
    } );

    it( 'saves an admin override but never writes transactional', function (): void {
        $customer = Customer::factory()->create();

        Livewire::test( PreferencesTab::class, [ 'customer' => $customer ] )
            ->set( 'preferences.mail.review-requests', false )
            ->set( 'preferences.mail.transactional', false )
            ->call( 'savePreferences' )
            ->assertDispatched( CustomerTabRegistry::CUSTOMER_UPDATED_EVENT )
            ->assertSet( 'preferences.mail.transactional', true );

        expect( CustomerNotificationPreference::query()->where( 'customer_id', $customer->id )->where( 'category', 'review-requests' )->value( 'is_enabled' ) )->toBeFalse()
            ->and( CustomerNotificationPreference::query()->where( 'customer_id', $customer->id )->where( 'category', 'transactional' )->exists() )->toBeFalse();
    } );

    it( 'refuses to save without customer.update', function (): void {
        Gate::define( 'ecommerce.customer.update', static fn (): bool => false );

        Livewire::test( PreferencesTab::class, [ 'customer' => Customer::factory()->create() ] )
            ->assertDontSee( 'Save preferences' )
            ->call( 'savePreferences' )
            ->assertForbidden();
    } );
} );
