<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Index;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\CustomersQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'customer.viewAny', 'customer.view' ] );
    $this->actingAs( makeUser() );
} );

it( 'renders customers with their counters and badges', function (): void {
    Customer::factory()->create( [
        'first_name'           => 'Ada',
        'last_name'            => 'Lovelace',
        'email'                => 'ada@example.test',
        'orders_count'         => 3,
        'total_spent_amount'   => 12345,
        'total_spent_currency' => 'USD',
        'accepts_marketing'    => true,
        'user_id'              => 7,
        'last_ordered_at'      => Carbon::parse( '2026-09-01 10:00:00' ),
    ] );
    Customer::factory()->create( [ 'first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@example.test' ] );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeHtml( '<caption class="sr-only">Customers</caption>' )
        ->assertSee( [ 'Ada Lovelace', 'ada@example.test', '$123.45', 'Subscribed', 'Has an account' ] )
        ->assertSee( [ 'Grace Hopper', 'Not subscribed', 'Guest', 'Never' ] );
} );

it( 'links each name to the customer page', function (): void {
    $customer = Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Lovelace' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( route( 'artisanpack.ecommerce.admin.customers.show', [ 'customer' => $customer->id ] ) );
} );

it( 'is denied without customer.viewAny', function (): void {
    Gate::define( 'ecommerce.customer.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'searches by email, phone, and every word of the name', function (): void {
    Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'countess@example.test', 'phone' => '555-0100' ] );
    Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Byron', 'email' => 'byron@example.test' ] );

    Livewire::test( Index::class )
        ->set( 'search', 'countess' )->assertSee( 'Lovelace' )->assertDontSee( 'Byron' )
        ->set( 'search', '0100' )->assertSee( 'Lovelace' )->assertDontSee( 'Byron' )
        ->set( 'search', 'ada byr' )->assertSee( 'Byron' )->assertDontSee( 'Lovelace' );
} );

it( 'filters by account, marketing consent, order count, spend, and last order', function (): void {
    $loyal = Customer::factory()->create( [ 'email' => 'loyal@example.test', 'user_id' => 1, 'accepts_marketing' => true, 'orders_count' => 12, 'total_spent_amount' => 150000, 'last_ordered_at' => Carbon::parse( '2026-09-20' ) ] );
    $once  = Customer::factory()->create( [ 'email' => 'once@example.test', 'orders_count' => 1, 'total_spent_amount' => 2500, 'last_ordered_at' => Carbon::parse( '2026-01-05' ) ] );
    $never = Customer::factory()->create( [ 'email' => 'never@example.test' ] );

    $ids = static fn ( array $filters ): array => ( new CustomersQuery() )->build( '', $filters )->pluck( 'id' )->sort()->values()->all();

    expect( $ids( [ 'has_account' => '1' ] ) )->toBe( [ $loyal->id ] )
        ->and( $ids( [ 'has_account' => '0' ] ) )->toBe( [ $once->id, $never->id ] )
        ->and( $ids( [ 'accepts_marketing' => '1' ] ) )->toBe( [ $loyal->id ] )
        ->and( $ids( [ 'orders' => [ 'min' => '1', 'max' => '5' ] ] ) )->toBe( [ $once->id ] )
        ->and( $ids( [ 'orders' => [ 'min' => '10' ] ] ) )->toBe( [ $loyal->id ] )
        ->and( $ids( [ 'spent' => [ 'min' => '20', 'max' => '25' ] ] ) )->toBe( [ $once->id ] )
        ->and( $ids( [ 'spent' => [ 'min' => '1000' ] ] ) )->toBe( [ $loyal->id ] )
        ->and( $ids( [ 'last_order' => [ 'from' => '2026-09-01' ] ] ) )->toBe( [ $loyal->id ] )
        ->and( $ids( [ 'last_order' => [ 'to' => '2026-06-30' ] ] ) )->toBe( [ $once->id ] );
} );

it( 'applies the range filters from the screen and ignores values that are not numbers', function (): void {
    Customer::factory()->create( [ 'email' => 'big@example.test', 'orders_count' => 9 ] );
    Customer::factory()->create( [ 'email' => 'small@example.test', 'orders_count' => 1 ] );

    Livewire::test( Index::class )
        ->set( 'filters.orders.min', '5' )
        ->assertSee( 'big@example.test' )
        ->assertDontSee( 'small@example.test' )
        ->set( 'filters.orders.min', 'lots' )
        ->assertSee( [ 'big@example.test', 'small@example.test' ] );
} );

it( 'sorts by total spent', function (): void {
    Customer::factory()->create( [ 'email' => 'low@example.test', 'total_spent_amount' => 100 ] );
    Customer::factory()->create( [ 'email' => 'high@example.test', 'total_spent_amount' => 900 ] );

    Livewire::test( Index::class )
        ->call( 'sort', 'spent' )
        ->assertSeeInOrder( [ 'low@example.test', 'high@example.test' ] )
        ->call( 'sort', 'spent' )
        ->assertSeeInOrder( [ 'high@example.test', 'low@example.test' ] );
} );

it( 'exports the filtered customers as CSV', function (): void {
    Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test', 'orders_count' => 2, 'total_spent_amount' => 4200 ] );

    $component = Livewire::test( Index::class )->call( 'exportCsv' );
    $content   = base64_decode( (string) ( $component->effects['download']['content'] ?? '' ) );

    expect( $content )->toContain( 'ada@example.test' )
        ->and( $content )->toContain( 'Ada Lovelace' )
        ->and( $content )->toContain( '42.00' );
} );

it( 'shows the empty state', function (): void {
    Livewire::test( Index::class )->assertSee( 'No customers yet' );
} );

it( 'rounds spend bounds finer than the currency inward instead of failing', function (): void {
    $low  = Customer::factory()->create( [ 'email' => 'low@example.test', 'total_spent_amount' => 1012 ] );
    $high = Customer::factory()->create( [ 'email' => 'high@example.test', 'total_spent_amount' => 1013 ] );

    $ids = static fn ( array $filters ): array => ( new CustomersQuery() )->build( '', $filters )->pluck( 'id' )->sort()->values()->all();

    expect( $ids( [ 'spent' => [ 'min' => '10.125' ] ] ) )->toBe( [ $high->id ] )
        ->and( $ids( [ 'spent' => [ 'max' => '10.129' ] ] ) )->toBe( [ $low->id ] )
        ->and( $ids( [ 'spent' => [ 'min' => '1.000' ] ] ) )->toBe( [ $low->id, $high->id ] );

    Livewire::test( Index::class )
        ->set( 'filters.spent.min', '10.125' )
        ->assertOk()
        ->assertSee( 'high@example.test' )
        ->assertDontSee( 'low@example.test' );
} );

it( 'rounds spend bounds for a currency without minor units', function (): void {
    config( [ 'artisanpack.ecommerce.base_currency' => 'JPY' ] );

    $ten    = Customer::factory()->create( [ 'total_spent_amount' => 10 ] );
    $eleven = Customer::factory()->create( [ 'total_spent_amount' => 11 ] );

    expect( ( new CustomersQuery() )->build( '', [ 'spent' => [ 'min' => '10.5' ] ] )->pluck( 'id' )->all() )->toBe( [ $eleven->id ] )
        ->and( ( new CustomersQuery() )->build( '', [ 'spent' => [ 'max' => '10.5' ] ] )->pluck( 'id' )->all() )->toBe( [ $ten->id ] );
} );
