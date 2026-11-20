<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Index;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'customer.viewAny', 'customer.view' ] );
    $this->actingAs( makeUser() );
} );

it( 'leads each row with a decorative avatar and the linked name', function (): void {
    $customer = Customer::factory()->create( [ 'first_name' => 'Maya', 'last_name' => 'Chen', 'email' => 'maya@example.test' ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )->toContain( 'data-customer-name="' . $customer->id . '"' )
        ->and( $html )->toMatch( '/class="apui-avatar[^"]*"[^>]*data-size="sm"[^>]*aria-hidden="true"/' )
        ->and( $html )->toContain( '>MC</span>' )
        ->and( $html )->toMatch( '/class="apui-link ec-customers-name-link"[^>]*>(?:<!--.*?-->|\s)*Maya Chen/' )
        ->and( $html )->toContain( 'Everyone who has ordered or created an account.' )
        ->and( $html )->toContain( 'Search by name, email or phone' );
} );

it( 'reads "No name" in muted italics for a customer without a name, named by email', function (): void {
    Customer::factory()->create( [ 'first_name' => null, 'last_name' => null, 'email' => 'guest-4471@example.test' ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )->toContain( 'No name' )
        ->and( $html )->toMatch( '/<a[^>]*data-tone="muted"[^>]*>/' )
        ->and( $html )->toContain( 'ec-customers-unnamed' )
        ->and( $html )->toContain( 'aria-label="No name (guest-4471@example.test)"' )
        ->and( $html )->toContain( '>?</span>' );
} );

it( 'badges marketing only when subscribed and marks accounts with a person icon', function (): void {
    Customer::factory()->create( [ 'email' => 'yes@example.test', 'accepts_marketing' => true, 'user_id' => 3 ] );
    Customer::factory()->create( [ 'email' => 'no@example.test', 'accepts_marketing' => false, 'user_id' => null ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )->toMatch( '/class="apui-badge[^"]*"[^>]*data-color="success"[^>]*data-marketing="subscribed"|data-marketing="subscribed"[^>]*data-color="success"/' )
        ->and( $html )->toContain( '<span class="ec-cell-muted ec-nowrap" data-marketing="not-subscribed">Not subscribed</span>' )
        ->and( $html )->toContain( 'data-account="registered"' )
        ->and( $html )->toContain( 'data-account="guest"' )
        ->and( substr_count( $html, 'data-marketing="not-subscribed"' ) )->toBe( 1 );
} );

it( 'shows "Never" and zero spend for a customer with no orders', function (): void {
    Customer::factory()->create( [ 'email' => 'new@example.test', 'orders_count' => 0, 'total_spent_amount' => 0, 'last_ordered_at' => null ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-last-order="never"' )
        ->assertSee( [ 'Never', '$0.00' ] );
} );

it( 'keeps email, dates and numbers left-to-right and right-aligns the numeric columns', function (): void {
    Customer::factory()->create( [ 'email' => 'ltr@example.test', 'orders_count' => 1234, 'last_ordered_at' => Carbon::parse( '2026-11-16 10:00:00' ) ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )->toContain( '<bdi dir="ltr" class="ec-cell-muted">ltr@example.test</bdi>' )
        ->and( $html )->toContain( '<bdi dir="ltr">1,234</bdi>' )
        ->and( substr_count( $html, 'data-numeric' ) )->toBeGreaterThanOrEqual( 4 )
        ->and( $html )->toContain( 'datetime="2026-11-16T10:00:00' );
} );

it( 'uses design-system icons and no DaisyUI classes', function (): void {
    Customer::factory()->create();

    $html = Livewire::test( Index::class )->html();

    expect( $html )->not->toContain( 'o-users' )
        ->and( $html )->not->toMatch( '/\b(link-hover|badge-|btn-|opacity-75)\b/' );
} );

it( 'uses the users icon in the empty state', function (): void {
    $html = Livewire::test( Index::class )
        ->assertSee( 'No customers yet' )
        ->assertDontSeeHtml( 'o-users' )
        ->html();

    // Lucide "users": the second figure's arc is unique to it.
    expect( $html )->toMatch( '/data-part="icon"[^>]*>.*?<path d="M16 3\\.13a4 4 0 0 1 0 7\\.75"\\/>/s' );
} );
