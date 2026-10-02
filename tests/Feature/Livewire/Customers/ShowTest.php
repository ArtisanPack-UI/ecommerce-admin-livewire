<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Show;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\CustomerTabRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'customer.viewAny', 'customer.view', 'customer.update', 'customer.delete', 'order.viewAny', 'order.view' ] );
    $this->actingAs( makeUser() );
} );

/**
 * The one-time token the delete form carries.
 */
function customerDeleteToken( $component ): string
{
    expect( preg_match( "/deleteCustomer\\( '([^']+)' \\)/", $component->html(), $matches ) )->toBe( 1 );

    return $matches[1];
}

it( 'renders the header stats', function (): void {
    $customer = Customer::factory()->create( [
        'first_name'           => 'Ada',
        'last_name'            => 'Lovelace',
        'email'                => 'ada@example.test',
        'orders_count'         => 4,
        'total_spent_amount'   => 40000,
        'total_spent_currency' => 'USD',
        'last_ordered_at'      => Carbon::parse( '2026-09-10' ),
    ] );
    Order::factory()->create( [ 'customer_id' => $customer->id, 'placed_at' => Carbon::parse( '2025-02-03' ) ] );
    Order::factory()->create( [ 'customer_id' => $customer->id, 'placed_at' => Carbon::parse( '2026-09-10' ) ] );

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->assertOk()
        ->assertSee( [ 'Ada Lovelace', 'ada@example.test' ] )
        ->assertSeeHtml( 'data-stat="lifetime-value">$400.00' )
        ->assertSeeHtml( 'data-stat="order-count">4' )
        ->assertSeeHtml( 'data-stat="average-order-value">$100.00' )
        ->assertSee( [ 'February 3, 2025', 'September 10, 2026' ] );
} );

it( 'shows a dash for the average when there are no orders', function (): void {
    $customer = Customer::factory()->create();

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->assertSeeHtml( 'data-stat="average-order-value">—' )
        ->assertSee( 'None yet' );
} );

it( 'is denied without customer.view', function (): void {
    Gate::define( 'ecommerce.customer.view', static fn (): bool => false );

    Livewire::test( Show::class, [ 'customer' => Customer::factory()->create()->id ] )->assertForbidden();
} );

it( 'renders the built-in tabs and opens the orders tab first', function (): void {
    $customer = Customer::factory()->create();

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->assertSet( 'tab', 'orders' )
        ->assertSeeInOrder( [ 'Orders', 'Addresses', 'Notification preferences', 'Notes', 'Activity' ] )
        ->assertSeeHtml( 'data-customer-tab="orders"' );
} );

it( 'hides the orders tab without order.viewAny', function (): void {
    Gate::define( 'ecommerce.order.viewAny', static fn (): bool => false );

    Livewire::test( Show::class, [ 'customer' => Customer::factory()->create()->id ] )
        ->assertSet( 'tab', 'addresses' )
        ->assertDontSeeHtml( 'data-customer-tab="orders"' );
} );

it( 'mounts only the open tab', function (): void {
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->create( [ 'customer_id' => $customer->id, 'address1' => '12 Analytical Row' ] );

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->assertDontSee( '12 Analytical Row' )
        ->set( 'tab', 'addresses' )
        ->assertSee( '12 Analytical Row' );
} );

it( 'adds a tab a satellite registers', function (): void {
    Livewire::component( 'test-points-tab', new class extends Component {
        public int $customerId = 0;

        public function mount( Customer $customer ): void
        {
            $this->customerId = (int) $customer->id;
        }

        public function render(): string
        {
            return '<div>Points for customer {{ $customerId }}</div>';
        }
    } );

    app( CustomerTabRegistry::class )->register( 'points', static fn (): string => 'Points', 'test-points-tab' );

    $customer = Customer::factory()->create();

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->assertSeeInOrder( [ 'Activity', 'Points' ] )
        ->set( 'tab', 'points' )
        ->assertSee( 'Points for customer ' . $customer->id );
} );

it( 'falls back to the first tab when the URL names an unknown one', function (): void {
    Livewire::withQueryParams( [ 'tab' => 'nope' ] )
        ->test( Show::class, [ 'customer' => Customer::factory()->create()->id ] )
        ->assertSet( 'tab', 'orders' );
} );

it( 'edits the name and phone', function (): void {
    $customer = Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Lovelance' ] );

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->call( 'startEdit' )
        ->assertSet( 'lastName', 'Lovelance' )
        ->set( 'lastName', 'Lovelace' )
        ->set( 'phone', '+44 20 7946 0000' )
        ->call( 'saveDetails' )
        ->assertHasNoErrors()
        ->assertSet( 'editing', false )
        ->assertSee( 'Ada Lovelace' );

    expect( $customer->fresh() )
        ->last_name->toBe( 'Lovelace' )
        ->phone->toBe( '+44 20 7946 0000' );
} );

it( 'records when marketing consent is given and clears it when withdrawn', function (): void {
    Carbon::setTestNow( '2026-10-01 12:00:00' );

    $customer = Customer::factory()->create( [ 'accepts_marketing' => false ] );

    $component = Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->call( 'startEdit' )
        ->set( 'acceptsMarketing', true )
        ->call( 'saveDetails' );

    expect( $customer->fresh() )
        ->accepts_marketing->toBeTrue()
        ->accepts_marketing_at->toDateTimeString()->toBe( '2026-10-01 12:00:00' );

    $component->call( 'startEdit' )->set( 'acceptsMarketing', false )->call( 'saveDetails' );

    expect( $customer->fresh() )
        ->accepts_marketing->toBeFalse()
        ->accepts_marketing_at->toBeNull();

    Carbon::setTestNow();
} );

it( 'keeps the consent date when consent does not change', function (): void {
    $customer = Customer::factory()->create( [ 'accepts_marketing' => true, 'accepts_marketing_at' => Carbon::parse( '2025-01-01' ) ] );

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->call( 'startEdit' )
        ->set( 'firstName', 'New' )
        ->call( 'saveDetails' );

    expect( $customer->fresh()->accepts_marketing_at->toDateString() )->toBe( '2025-01-01' );
} );

it( 'validates the details', function (): void {
    $customer = Customer::factory()->create();

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->call( 'startEdit' )
        ->set( 'firstName', str_repeat( 'a', 121 ) )
        ->set( 'phone', 'call me maybe' )
        ->call( 'saveDetails' )
        ->assertHasErrors( [ 'firstName' => 'max', 'phone' => 'regex' ] );
} );

it( 'refuses to edit without customer.update', function (): void {
    Gate::define( 'ecommerce.customer.update', static fn (): bool => false );

    Livewire::test( Show::class, [ 'customer' => Customer::factory()->create()->id ] )
        ->assertDontSeeHtml( 'data-edit-customer' )
        ->call( 'saveDetails' )
        ->assertForbidden();
} );

it( 'explains the delete and requires the email typed back', function (): void {
    $customer = Customer::factory()->create( [ 'email' => 'ada@example.test' ] );

    $component = Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->call( 'startDelete' )
        ->assertSee( [ 'Erased', 'Kept', 'Type ada@example.test to confirm' ] );

    $component->set( 'deleteConfirmation', 'someone@example.test' )
        ->call( 'deleteCustomer', customerDeleteToken( $component ) )
        ->assertHasErrors( 'deleteConfirmation' );

    $component->set( 'deleteConfirmation', '' )
        ->call( 'deleteCustomer', customerDeleteToken( $component ) )
        ->assertHasErrors( [ 'deleteConfirmation' => 'required' ] );

    expect( Customer::query()->whereKey( $customer->id )->exists() )->toBeTrue();
} );

it( 'deletes the customer, anonymizes their orders, and returns to the list', function (): void {
    $customer = Customer::factory()->create( [ 'email' => 'ada@example.test' ] );
    $order    = Order::factory()->create( [ 'customer_id' => $customer->id, 'email' => 'ada@example.test', 'total_amount' => 5000 ] );

    $component = Livewire::test( Show::class, [ 'customer' => $customer->id ] )->call( 'startDelete' );

    $component->set( 'deleteConfirmation', ' ADA@example.test ' )
        ->call( 'deleteCustomer', customerDeleteToken( $component ) )
        ->assertHasNoErrors()
        ->assertRedirect( route( 'artisanpack.ecommerce.admin.customers.index' ) );

    $order->refresh();

    expect( Customer::query()->whereKey( $customer->id )->exists() )->toBeFalse()
        ->and( $order->customer_id )->toBeNull()
        ->and( $order->email )->not->toBe( 'ada@example.test' )
        ->and( (int) $order->total_amount )->toBe( 5000 );
} );

it( 'refuses a delete token it did not issue', function (): void {
    $customer = Customer::factory()->create( [ 'email' => 'ada@example.test' ] );

    $component = Livewire::test( Show::class, [ 'customer' => $customer->id ] )->call( 'startDelete' );

    $component->set( 'deleteConfirmation', 'ada@example.test' )->call( 'deleteCustomer', 'not-the-token' );

    expect( sentToasts( $component ) )->toContain( 'This action has expired.' )
        ->and( Customer::query()->whereKey( $customer->id )->exists() )->toBeTrue();
} );

it( 'refuses to delete without customer.delete', function (): void {
    Gate::define( 'ecommerce.customer.delete', static fn (): bool => false );

    Livewire::test( Show::class, [ 'customer' => Customer::factory()->create()->id ] )
        ->assertDontSeeHtml( 'data-delete-customer' )
        ->call( 'startDelete' )
        ->assertForbidden();
} );
