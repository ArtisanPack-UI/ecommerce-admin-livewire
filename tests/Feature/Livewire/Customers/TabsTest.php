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
        Livewire::test( OrdersTab::class, [ 'customer' => Customer::factory()->create() ] )
            ->assertSee( 'No orders yet' )
            ->assertSeeHtml( 'ec-empty-sunken' )
            ->assertDontSeeHtml( '<table' );
    } );

    it( 'builds the orders table from design-system parts with no DaisyUI classes', function (): void {
        $customer = Customer::factory()->create();
        Order::factory()->create( [ 'customer_id' => $customer->id, 'order_number' => 'TAB00001', 'placed_at' => now() ] );

        $html = Livewire::test( OrdersTab::class, [ 'customer' => $customer ] )->html();

        expect( $html )
            ->toContain( 'class="apui-table ec-table"' )
            ->toContain( 'data-part="head-cell"' )
            ->toContain( '<bdi dir="ltr" class="ec-mono">#TAB00001</bdi>' )
            ->toContain( 'class="apui-link' )
            ->toContain( '<time datetime=' )
            ->toContain( 'data-part="footer"' )
            ->not->toContain( 'class="table' )
            ->not->toContain( 'link-hover' )
            ->not->toContain( 'overflow-x-auto' )
            ->not->toContain( 'opacity-75' );

        // The three status badges and the total sit in their own cells.
        expect( substr_count( $html, 'class="apui-badge' ) )->toBeGreaterThanOrEqual( 3 )
            ->and( $html )->toContain( 'data-money' );
    } );

    it( 'shows a lock and a sentence instead of a table without order.viewAny', function (): void {
        Gate::define( 'ecommerce.order.viewAny', static fn (): bool => false );

        $customer = Customer::factory()->create();
        Order::factory()->create( [ 'customer_id' => $customer->id, 'order_number' => 'HIDDEN01' ] );

        $html = Livewire::test( OrdersTab::class, [ 'customer' => $customer ] )->html();

        expect( Illuminate\Support\Str::betweenFirst( $html, 'data-orders-locked', '</p>' ) )
            ->toContain( 'You are not allowed to see orders.' )
            ->toContain( 'aria-hidden="true"' )
            ->and( $html )->toContain( 'role="status"' )
            ->not->toContain( 'HIDDEN01' )
            ->not->toContain( '<table' );
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
            ->assertDontSeeHtml( 'data-delete-address' )
            ->assertDontSee( 'Use for shipping' )
            ->call( 'startAdd' )
            ->assertForbidden();
    } );

    it( 'builds address cards from design-system parts with no DaisyUI classes', function (): void {
        $customer = Customer::factory()->create();
        CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'label' => 'Home', 'is_default_shipping' => true, 'is_default_billing' => true ] );
        CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'label' => 'Office' ] );

        $html = Livewire::test( AddressesTab::class, [ 'customer' => $customer ] )->html();

        expect( $html )
            ->toContain( 'Saved addresses' )
            ->toContain( 'class="ec-address-grid"' )
            ->toContain( 'class="apui-card ec-address-card"' )
            ->toContain( 'aria-label="Delete Home"' )
            ->toContain( 'aria-label="Delete Office"' )
            ->not->toContain( 'rounded-box' )
            ->not->toContain( 'border-base-content' )
            ->not->toContain( 'text-error' );

        $home   = Illuminate\Support\Str::betweenFirst( $html, '>Home</h4>', '</section>' );
        $office = Illuminate\Support\Str::betweenFirst( $html, '>Office</h4>', '</section>' );

        // Default badges carry an icon, and "Use for …" only appears where the address isn't already that default.
        expect( $home )
            ->toContain( 'Default shipping' )
            ->toContain( 'Default billing' )
            ->toContain( 'data-color="primary"' )
            ->not->toContain( 'Use for shipping' )
            ->not->toContain( 'Use for billing' )
            ->and( $office )
            ->toContain( 'Use for shipping' )
            ->toContain( 'Use for billing' )
            ->not->toContain( 'Default shipping' );
    } );

    it( 'titles an unlabelled address by its first line', function (): void {
        $customer = Customer::factory()->create();
        CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'label' => null, 'address1' => '88 Harbour Road' ] );

        Livewire::test( AddressesTab::class, [ 'customer' => $customer ] )
            ->assertSeeHtml( '>88 Harbour Road</h4>' )
            ->assertSeeHtml( 'aria-label="Delete 88 Harbour Road"' );
    } );

    it( 'asks in the card footer before deleting', function (): void {
        $customer = Customer::factory()->create();
        $address  = CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'label' => 'Parents' ] );

        $html = Livewire::test( AddressesTab::class, [ 'customer' => $customer ] )
            ->call( 'confirmDelete', $address->id )
            ->html();

        $card = Illuminate\Support\Str::betweenFirst( $html, '>Parents</h4>', '</section>' );

        expect( $card )
            ->toContain( 'class="ec-confirm ec-confirm-card"' )
            ->toContain( 'role="alertdialog"' )
            ->toContain( 'Orders that used it keep their own copy.' )
            ->toContain( 'data-focus-return="address-delete-' . $address->id . '"' )
            ->not->toContain( 'data-delete-address' );
    } );

    it( 'opens the address form in place of the grid', function (): void {
        $customer = Customer::factory()->create();
        CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'label' => 'Home' ] );

        $html = Livewire::test( AddressesTab::class, [ 'customer' => $customer ] )
            ->call( 'startAdd' )
            ->html();

        expect( $html )
            ->toContain( 'data-address-form' )
            ->toContain( 'New address' )
            ->toContain( 'Save address' )
            ->not->toContain( 'class="ec-address-grid"' )
            ->not->toContain( 'data-add-address' );
    } );

    it( 'moves focus into the address form and back to the control that opened it', function (): void {
        $customer = Customer::factory()->create();
        $address  = CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'label' => 'Home' ] );

        $component = Livewire::test( AddressesTab::class, [ 'customer' => $customer ] );

        expect( $component->html() )
            ->toContain( 'data-focus-key="address-add"' )
            ->toContain( 'data-focus-key="address-edit-' . $address->id . '"' );

        // The first field takes focus when the form opens.
        expect( Illuminate\Support\Str::betweenFirst( $component->call( 'startAdd' )->html(), '<form', 'data-address-form' ) )
            ->toContain( 'x-init="$nextTick( () => $el.querySelector( \'input, select\' )?.focus() )"' );

        // Cancel and Save send focus back to Add address after adding…
        expect( substr_count( $component->html(), 'data-focus-return="address-add"' ) )->toBe( 2 );

        // …and to the address's Edit button after editing, even though cancelForm clears editingId.
        $html = $component->call( 'cancelForm' )->call( 'startEdit', $address->id )->html();

        expect( substr_count( $html, 'data-focus-return="address-edit-' . $address->id . '"' ) )->toBe( 2 );
    } );

    it( 'shows the empty state without addresses', function (): void {
        Livewire::test( AddressesTab::class, [ 'customer' => Customer::factory()->create() ] )
            ->assertSee( [ 'Saved addresses', 'No saved addresses' ] )
            ->assertSeeHtml( 'ec-empty-sunken' )
            ->assertSeeHtml( 'data-add-address' );
    } );
} );

describe( 'notification preferences tab', function (): void {
    it( 'shows the preferences with transactional messages locked', function (): void {
        $customer = Customer::factory()->create( [ 'accepts_marketing' => false ] );

        Livewire::test( PreferencesTab::class, [ 'customer' => $customer ] )
            ->assertOk()
            ->assertSee( [ 'Order and account messages', 'Always on', 'Review requests', 'Marketing' ] )
            ->assertSee( 'Change them only when the customer asks you to; transactional messages can\'t be turned off.' )
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
            ->assertDontSee( 'Change them only when' )
            ->call( 'savePreferences' )
            ->assertForbidden();
    } );

    it( 'builds the preferences grid from design-system parts with no DaisyUI classes', function (): void {
        $html = Livewire::test( PreferencesTab::class, [ 'customer' => Customer::factory()->create() ] )->html();

        expect( $html )
            ->toContain( 'class="apui-table ec-table"' )
            ->toContain( 'class="apui-toggle' )
            ->toContain( 'aria-label="Shipping updates by Email"' )
            ->toContain( 'role="switch"' )
            ->not->toContain( 'class="table' )
            ->not->toContain( 'opacity-75' );

        // "Always on" is a badge with a lock, not a disabled switch, so it isn't read as an option.
        $locked = Illuminate\Support\Str::betweenFirst( $html, 'Order and account messages', '</tr>' );

        expect( $locked )
            ->toContain( 'data-always-on' )
            ->toContain( 'data-color="neutral"' )
            ->toContain( '<svg' )
            ->not->toContain( 'role="switch"' );

        // The switches are the small size.
        expect( Illuminate\Support\Str::betweenFirst( $html, 'class="apui-toggle', '>' ) )->toContain( 'data-size="sm"' );
    } );
} );
