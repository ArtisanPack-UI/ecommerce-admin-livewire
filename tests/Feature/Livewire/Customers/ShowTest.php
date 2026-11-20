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
        ->assertSeeHtml( 'data-stat="average-order-value"><span aria-hidden="true">&mdash;</span><span class="apui-sr-only">None</span>' )
        ->assertSee( 'None yet' );
} );

it( 'names a missing "customer since" date for screen readers', function (): void {
    $customer = Customer::factory()->create();
    Customer::query()->whereKey( $customer->id )->update( [ 'created_at' => null ] );

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->assertSeeHtml( '<dd><span aria-hidden="true">&mdash;</span><span class="apui-sr-only">None</span></dd>' );
} );

it( 'answers 403, not 404, for a missing customer when the user cannot list customers', function (): void {
    $url = route( 'artisanpack.ecommerce.admin.customers.show', [ 'customer' => 999 ] );

    $this->get( $url )->assertNotFound();

    Gate::define( 'ecommerce.customer.viewAny', static fn (): bool => false );

    $this->get( $url )->assertForbidden();
    Livewire::test( Show::class, [ 'customer' => 999 ] )->assertForbidden();
} );

it( 'names the customer in the page title', function (): void {
    $customer = Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Lovelace' ] );

    $this->get( route( 'artisanpack.ecommerce.admin.customers.show', [ 'customer' => $customer->id ] ) )
        ->assertOk()
        ->assertSee( '<title>Ada Lovelace &middot;', false );
} );

it( 'keeps names with angle brackets intact', function (): void {
    $customer = Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Lovelace' ] );

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->call( 'startEdit' )
        ->set( 'lastName', '  O<Brien  ' )
        ->call( 'saveDetails' )
        ->assertHasNoErrors()
        ->assertSeeHtml( 'Ada O&lt;Brien' );

    expect( $customer->fresh()->last_name )->toBe( 'O<Brien' );
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

it( 'gives each tab a value rather than the deprecated name', function (): void {
    $source = (string) file_get_contents( dirname( __DIR__, 4 ) . '/resources/views/livewire/customers/show.blade.php' );

    expect( $source )->toContain( '<x-artisanpack-tab :value=' )
        ->not->toMatch( '/<x-artisanpack-tab\s[^>]*:?name=/' );

    Livewire::test( Show::class, [ 'customer' => Customer::factory()->create()->id ] )
        ->set( 'tab', 'addresses' )
        ->assertSeeHtml( 'data-customer-tab="addresses"' );
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

it( 'tells satellites and webhooks about the edit through the engine hook', function (): void {
    $customer = Customer::factory()->create( [ 'first_name' => 'Ada', 'accepts_marketing' => false ] );
    $heard    = [];

    addAction( 'ap.ecommerce.customer.updated', static function ( Customer $updated, array $changes ) use ( &$heard ): void {
        $heard[] = [ $updated->id, $changes ];
    }, 10, 2 );

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->call( 'startEdit' )
        ->set( 'firstName', 'Augusta' )
        ->set( 'acceptsMarketing', true )
        ->call( 'saveDetails' )
        ->assertHasNoErrors();

    removeAllActions( 'ap.ecommerce.customer.updated' );

    expect( $heard )->toHaveCount( 1 )
        ->and( $heard[0][0] )->toBe( $customer->id )
        ->and( $heard[0][1] )->toContain( 'first_name', 'accepts_marketing', 'accepts_marketing_at' );
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
        ->assertRedirect( route( 'artisanpack.ecommerce.admin.customers.index' ) )
        ->assertNotDispatched( 'toast' );

    // The toast survives the redirect: it is flashed, and the list's toaster shows it.
    expect( session( ArtisanPack\LivewireUiComponents\Support\Toasts::SESSION_KEY ) )->toBe( [ [ 'title' => 'Customer deleted. Their orders were kept and anonymized.', 'color' => 'success' ] ] );

    $this->get( route( 'artisanpack.ecommerce.admin.customers.index' ) )
        ->assertOk()
        ->assertSee( 'data-apui-flashed-toasts', false )
        ->assertSee( 'Customer deleted. Their orders were kept and anonymized.' );

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

it( 'leads with the account and marketing badges', function (): void {
    $user     = makeUser();
    $customer = Customer::factory()->create( [ 'user_id' => $user->id, 'accepts_marketing' => true, 'accepts_marketing_at' => Carbon::parse( '2024-03-04' ) ] );

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->assertSeeInOrder( [ 'Has an account', 'Subscribed to marketing', 'Lifetime value' ] )
        ->assertSeeHtml( 'data-color="success"' )
        ->assertSee( [ 'Linked to user #' . $user->id, 'Recorded March 4, 2024' ] );
} );

it( 'reads as a guest who has not given consent', function (): void {
    $customer = Customer::factory()->create( [ 'user_id' => null, 'accepts_marketing' => false, 'phone' => null ] );

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->assertSee( [ 'Guest', 'Not subscribed to marketing', 'Guest, no account', 'Not given', 'None' ] )
        ->assertDontSee( 'Has an account' );
} );

it( 'builds the page from design-system parts, with no DaisyUI left', function (): void {
    $customer = Customer::factory()->create();

    $html = Livewire::test( Show::class, [ 'customer' => $customer->id ] )->html();

    expect( $html )
        ->toContain( 'apui-page-header', 'apui-stat', 'apui-tabs', 'data-variant="underline"', 'data-customer-contact' )
        ->not->toMatch( '/(?<![\w-])(rounded-box|base-content|bg-base-\d|border-error|btn-|badge-)/' )
        ->not->toContain( 'style="' );
} );

it( 'swaps the Contact card for the form in place and keeps the tabs', function (): void {
    $customer = Customer::factory()->create();

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->assertSeeHtml( 'data-customer-contact' )
        ->call( 'startEdit' )
        ->assertDontSeeHtml( 'data-customer-contact' )
        ->assertSeeHtml( 'data-customer-details-form' )
        ->assertSeeHtml( 'data-customer-tab="orders"' )
        ->assertSee( 'Turning this on records today as the date of consent.' )
        ->call( 'cancelEdit' )
        ->assertSeeHtml( 'data-customer-contact' );
} );

it( 'keeps Delete customer disabled until the email is typed back', function (): void {
    $customer = Customer::factory()->create( [ 'email' => 'ada@example.test' ] );

    $component = Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->call( 'startDelete' )
        ->assertSeeHtml( 'role="alertdialog"' );

    $submit = fn (): string => preg_match( '/<button[^>]*data-customer-delete-submit[^>]*>/', $component->html(), $matches ) ? $matches[0] : '';

    expect( $submit() )->toMatch( '/\sdisabled(=|\s|>)/' );

    $component->set( 'deleteConfirmation', 'ada@example' );
    expect( $submit() )->toMatch( '/\sdisabled(=|\s|>)/' );

    $component->set( 'deleteConfirmation', 'ada@example.test' );
    expect( $submit() )->not->toMatch( '/\sdisabled(=|\s|>)/' );
} );

it( 'clears the typed email when the dialog closes itself', function (): void {
    $customer = Customer::factory()->create( [ 'email' => 'ada@example.test' ] );

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->call( 'startDelete' )
        ->set( 'deleteConfirmation', 'ada@' )
        ->set( 'confirmingDelete', false )
        ->assertSet( 'deleteConfirmation', '' )
        ->assertDontSeeHtml( 'data-customer-delete-form' );
} );

it( 'does not open the delete dialog for a user who may not delete', function (): void {
    Gate::define( 'ecommerce.customer.delete', static fn (): bool => false );

    Livewire::test( Show::class, [ 'customer' => Customer::factory()->create()->id ] )
        ->set( 'confirmingDelete', true )
        ->assertDontSeeHtml( 'data-customer-delete-form' );
} );

it( 'hides the header Edit button while the contact form is open, so typed changes are not reset', function (): void {
    $customer = Customer::factory()->create();

    Livewire::test( Show::class, [ 'customer' => $customer->id ] )
        ->assertSeeHtml( 'data-edit-customer' )
        ->call( 'startEdit' )
        ->set( 'firstName', 'Typed' )
        ->assertDontSeeHtml( 'data-edit-customer' )
        ->assertSet( 'firstName', 'Typed' );
} );
