<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Events\LicenseDeactivated;
use ArtisanPackUI\Ecommerce\Events\LicenseRevoked;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\LicenseActivation;
use ArtisanPackUI\Ecommerce\Models\LicenseKey;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\LicenseKeys\Index;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'licenseKey.view', 'licenseKey.revoke' ] );
    $this->actingAs( makeUser() );
} );

/**
 * A license key sold on an order.
 *
 * @param  array<string, mixed>  $attributes  Key attributes.
 */
function licenseKeyOn( string $orderNumber, array $attributes = [], ?Customer $customer = null ): LicenseKey
{
    $order = Order::factory()->create( [ 'order_number' => $orderNumber, 'email' => strtolower( $orderNumber ) . '@example.test', 'customer_id' => $customer?->id ] );
    $item  = OrderItem::factory()->create( [ 'order_id' => $order->id, 'product_snapshot' => [ 'name' => 'Photo Editor', 'sku' => 'PE', 'type' => 'digital', 'options' => [] ] ] );

    return LicenseKey::factory()->create( [ 'order_item_id' => $item->id ] + $attributes );
}

it( 'lists keys in full for users with licenseKey.view', function (): void {
    licenseKeyOn( 'A100', [ 'key' => 'ABCDE-FGHJK-LMNPQ-RSTUV-WXYZ2', 'activations_count' => 2, 'activations_limit' => 5 ] );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeHtml( '<caption class="sr-only">License keys</caption>' )
        ->assertSee( 'ABCDE-FGHJK-LMNPQ-RSTUV-WXYZ2' )
        ->assertSee( 'Photo Editor' )
        ->assertSee( '#A100' )
        ->assertSee( '2 of 5' )
        ->assertSeeHtml( 'data-license-status="active"' );
} );

it( 'masks keys and refuses key search for users who may only revoke', function (): void {
    Gate::define( 'ecommerce.licenseKey.view', static fn (): bool => false );
    licenseKeyOn( 'A100', [ 'key' => 'ABCDE-FGHJK-LMNPQ-RSTUV-WXYZ2' ] );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertDontSee( 'ABCDE' )
        ->assertSee( '•••••-•••••-•••••-•••••-WXYZ2' )
        ->set( 'search', 'ABCDE' )
        ->assertDontSee( 'WXYZ2' )
        ->set( 'search', 'A100' )
        ->assertSee( 'WXYZ2' );
} );

it( 'is denied without licenseKey.view or licenseKey.revoke', function (): void {
    Gate::define( 'ecommerce.licenseKey.view', static fn (): bool => false );
    Gate::define( 'ecommerce.licenseKey.revoke', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'searches by key, order, and customer', function (): void {
    $customer = Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Lovelace' ] );
    licenseKeyOn( 'A100', [ 'key' => 'AAAAA-AAAAA-AAAAA-AAAAA-AAAAA' ], $customer );
    licenseKeyOn( 'B200', [ 'key' => 'BBBBB-BBBBB-BBBBB-BBBBB-BBBBB' ] );

    Livewire::test( Index::class )
        ->set( 'search', 'BBBBB-BBBBB-BBBBB-BBBBB-BBBBB' )->assertSee( 'BBBBB-BBBBB' )->assertDontSee( 'AAAAA-AAAAA' )
        ->set( 'search', '#A100' )->assertSee( 'AAAAA-AAAAA' )->assertDontSee( 'BBBBB-BBBBB' )
        ->set( 'search', 'lovelace' )->assertSee( 'AAAAA-AAAAA' )->assertDontSee( 'BBBBB-BBBBB' )
        ->set( 'search', 'b200@example' )->assertSee( 'BBBBB-BBBBB' );
} );

it( 'matches a key only in full, in any case and with surrounding spaces', function (): void {
    licenseKeyOn( 'A100', [ 'key' => 'ABCDE-FGHJK-LMNPQ-RSTUV-WXYZ2' ] );
    licenseKeyOn( 'B200', [ 'key' => 'BBBBB-BBBBB-BBBBB-BBBBB-BBBBB' ] );

    $matches = static fn ( string $search ): array => Livewire::test( Index::class )->set( 'search', $search )->viewData( 'tableRows' )->pluck( 'key' )->all();

    expect( $matches( '  abcde-fghjk-lmnpq-rstuv-wxyz2 ' ) )->toBe( [ 'ABCDE-FGHJK-LMNPQ-RSTUV-WXYZ2' ] )
        ->and( $matches( 'ABCDE-FGHJK' ) )->toBe( [] )
        ->and( $matches( 'q2' ) )->toBe( [] );
} );

it( 'does not let a revoke-only user find a key by searching it', function (): void {
    Gate::define( 'ecommerce.licenseKey.view', static fn (): bool => false );
    licenseKeyOn( 'A100', [ 'key' => 'ABCDE-FGHJK-LMNPQ-RSTUV-WXYZ2' ] );

    Livewire::test( Index::class )
        ->set( 'search', 'ABCDE-FGHJK-LMNPQ-RSTUV-WXYZ2' )
        ->assertSee( 'Search by order or customer.' )
        ->assertDontSee( 'WXYZ2' );
} );

it( 'filters by status', function (): void {
    licenseKeyOn( 'A100', [ 'key' => 'AAAAA-AAAAA-AAAAA-AAAAA-AAAAA' ] );
    licenseKeyOn( 'B200', [ 'key' => 'BBBBB-BBBBB-BBBBB-BBBBB-BBBBB', 'is_revoked' => true, 'revoked_at' => now() ] );
    licenseKeyOn( 'C300', [ 'key' => 'CCCCC-CCCCC-CCCCC-CCCCC-CCCCC', 'expires_at' => now()->subDay() ] );

    Livewire::test( Index::class )
        ->set( 'filters.status', 'revoked' )->assertSee( 'BBBBB' )->assertDontSee( 'AAAAA' )->assertDontSee( 'CCCCC' )
        ->set( 'filters.status', 'expired' )->assertSee( 'CCCCC' )->assertDontSee( 'BBBBB' )
        ->set( 'filters.status', 'active' )->assertSee( 'AAAAA' )->assertDontSee( 'CCCCC' );
} );

it( 'lists the machines a key is active on', function (): void {
    $key = licenseKeyOn( 'A100' );
    LicenseActivation::factory()->create( [ 'license_key_id' => $key->id, 'machine_fingerprint' => 'studio-mac', 'ip_address' => '10.0.0.8' ] );

    Livewire::test( Index::class )
        ->call( 'showActivations', $key->id )
        ->assertSet( 'viewing', true )
        ->assertSeeHtml( 'data-activations="' . $key->id . '"' )
        ->assertSee( 'studio-mac' )
        ->assertSee( '10.0.0.8' );
} );

it( 'hides fingerprints and IP addresses from a revoke-only user', function (): void {
    Gate::define( 'ecommerce.licenseKey.view', static fn (): bool => false );
    $key = licenseKeyOn( 'A100' );
    LicenseActivation::factory()->create( [ 'license_key_id' => $key->id, 'machine_fingerprint' => 'studio-mac-0042', 'ip_address' => '10.0.0.8' ] );

    Livewire::test( Index::class )
        ->call( 'showActivations', $key->id )
        ->assertSee( '…0042' )
        ->assertDontSee( 'studio-mac-0042' )
        ->assertDontSee( '10.0.0.8' )
        ->assertDontSee( 'IP address' );
} );

it( 'frees an activation slot after confirmation, once per token', function (): void {
    Event::fake( [ LicenseDeactivated::class ] );
    $key    = licenseKeyOn( 'A100', [ 'activations_count' => 2, 'activations_limit' => 2 ] );
    $studio = LicenseActivation::factory()->create( [ 'license_key_id' => $key->id, 'machine_fingerprint' => 'studio-mac' ] );
    $laptop = LicenseActivation::factory()->create( [ 'license_key_id' => $key->id, 'machine_fingerprint' => 'laptop' ] );

    $component = Livewire::test( Index::class )
        ->call( 'showActivations', $key->id )
        ->assertSeeHtml( 'aria-label="Deactivate studio-mac"' )
        ->call( 'startDeactivate', $studio->id )
        ->assertSet( 'deactivating', true )
        ->assertSee( 'Free this activation slot? The software on that machine will stop validating.' );

    $token = $component->viewData( 'deactivateToken' );

    $component->call( 'deactivate', $token )
        ->assertSet( 'deactivating', false )
        ->assertSee( 'Activation slot freed.' )
        ->assertSee( '1 of 2 activations used.' )
        ->assertDontSee( 'studio-mac' )
        ->assertSee( 'laptop' );

    expect( $key->refresh()->activations_count )->toBe( 1 )
        ->and( LicenseActivation::query()->whereKey( $studio->id )->exists() )->toBeFalse();

    Event::assertDispatched( LicenseDeactivated::class, static fn ( LicenseDeactivated $event ): bool => 'studio-mac' === $event->fingerprint );

    // The same token can't free a second slot.
    $component->call( 'startDeactivate', $laptop->id )->call( 'deactivate', $token );

    expect( $key->refresh()->activations_count )->toBe( 1 )
        ->and( LicenseActivation::query()->whereKey( $laptop->id )->exists() )->toBeTrue();
} );

it( 'refuses an activation of another key', function (): void {
    $key   = licenseKeyOn( 'A100' );
    $other = LicenseActivation::factory()->create( [ 'license_key_id' => licenseKeyOn( 'B200' )->id ] );

    Livewire::test( Index::class )
        ->call( 'showActivations', $key->id )
        ->call( 'startDeactivate', $other->id )
        ->assertNotFound();
} );

it( 'refuses deactivation without licenseKey.revoke', function (): void {
    Gate::define( 'ecommerce.licenseKey.revoke', static fn (): bool => false );
    $key        = licenseKeyOn( 'A100' );
    $activation = LicenseActivation::factory()->create( [ 'license_key_id' => $key->id ] );

    Livewire::test( Index::class )
        ->call( 'showActivations', $key->id )
        ->assertDontSeeHtml( 'startDeactivate' )
        ->call( 'startDeactivate', $activation->id )
        ->assertForbidden();
} );

it( 'says when the machine was already deactivated', function (): void {
    $key        = licenseKeyOn( 'A100', [ 'activations_count' => 1 ] );
    $activation = LicenseActivation::factory()->create( [ 'license_key_id' => $key->id ] );

    $component = Livewire::test( Index::class )
        ->call( 'showActivations', $key->id )
        ->call( 'startDeactivate', $activation->id );

    $token = $component->viewData( 'deactivateToken' );
    $activation->delete();

    $component->call( 'deactivate', $token )
        ->assertSee( 'That machine was already deactivated.' );
} );

it( 'deactivates on a revoked key too', function (): void {
    $key        = licenseKeyOn( 'A100', [ 'is_revoked' => true, 'revoked_at' => now(), 'activations_count' => 1 ] );
    $activation = LicenseActivation::factory()->create( [ 'license_key_id' => $key->id ] );

    $component = Livewire::test( Index::class )->call( 'showActivations', $key->id )->call( 'startDeactivate', $activation->id );
    $component->call( 'deactivate', $component->viewData( 'deactivateToken' ) );

    expect( $key->refresh()->activations_count )->toBe( 0 );
} );

it( 'revokes a key with a reason, once per token', function (): void {
    Event::fake( [ LicenseRevoked::class ] );
    $key = licenseKeyOn( 'A100' );

    $component = Livewire::test( Index::class )
        ->call( 'startRevoke', $key->id )
        ->assertSet( 'revoking', true );

    $token = $component->viewData( 'revokeToken' );

    $component->call( 'revoke', $token )
        ->assertHasErrors( [ 'revokeReason' => 'required' ] )
        ->set( 'revokeReason', 'Chargeback' )
        ->call( 'revoke', $token )
        ->assertHasNoErrors()
        ->assertSet( 'revoking', false );

    expect( $key->refresh() )
        ->is_revoked->toBeTrue()
        ->and( $key->meta['revoked_reason'] )->toBe( 'Chargeback' );

    Event::assertDispatchedTimes( LicenseRevoked::class, 1 );

    $other = licenseKeyOn( 'B200' );
    $component->call( 'startRevoke', $other->id )->set( 'revokeReason', 'Again' )->call( 'revoke', $token );

    expect( $other->refresh()->is_revoked )->toBeFalse();
} );

it( 'refuses to revoke without licenseKey.revoke', function (): void {
    Gate::define( 'ecommerce.licenseKey.revoke', static fn (): bool => false );
    $key = licenseKeyOn( 'A100' );

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'startRevoke' )
        ->call( 'startRevoke', $key->id )
        ->assertForbidden();
} );
