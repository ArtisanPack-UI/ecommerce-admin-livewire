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
        ->assertSeeHtml( '<caption class="apui-sr-only">License keys</caption>' )
        ->assertSeeHtml( 'aria-describedby="resource-table-search-hint"' )
        ->assertSeeHtml( '<span id="resource-table-search-hint" class="apui-sr-only">' )
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
        ->assertSee( '••••••••XYZ2' )
        ->assertSeeHtml( 'aria-label="Revoke ••••••••XYZ2"' )
        ->set( 'search', 'ABCDE' )
        ->assertDontSee( 'XYZ2' )
        ->set( 'search', 'A100' )
        ->assertSee( 'XYZ2' );
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
        ->assertDontSee( 'XYZ2' );
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

    $component->set( 'revokeReason', str_repeat( 'x', 501 ) )
        ->call( 'revoke', $token )
        ->assertHasErrors( [ 'revokeReason' => 'max' ] )
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

it( 'revokes a key without a reason', function (): void {
    $key = licenseKeyOn( 'A100' );

    $component = Livewire::test( Index::class )
        ->call( 'startRevoke', $key->id )
        ->assertSee( 'Reason (optional)' )
        ->assertSee( 'The key stops validating on every machine at once. This cannot be undone.' );

    $component->call( 'revoke', $component->viewData( 'revokeToken' ) )
        ->assertHasNoErrors()
        ->assertSet( 'revoking', false );

    expect( $key->refresh() )
        ->is_revoked->toBeTrue()
        ->and( $key->meta['revoked_reason'] ?? null )->toBeNull();
} );

it( 'masks a key to eight dots and its last four characters', function (): void {
    expect( Index::mask( 'KNIT-7Q4M-2HXP-9F3A' ) )->toBe( '••••••••9F3A' )
        ->and( Index::mask( 'ABCDE-FGHJK-LMNPQ-RSTUV-WXYZ2' ) )->toBe( '••••••••XYZ2' )
        ->and( Index::mask( 'ABC' ) )->toBe( '••••••••ABC' );
} );

it( 'draws used activations as text and a bar that warns when every slot is used', function (): void {
    licenseKeyOn( 'A100', [ 'key' => 'AAAAA-AAAAA-AAAAA-AAAAA-AAAAA', 'activations_count' => 2, 'activations_limit' => 3 ] );
    licenseKeyOn( 'B200', [ 'key' => 'BBBBB-BBBBB-BBBBB-BBBBB-BBBBB', 'activations_count' => 3, 'activations_limit' => 3 ] );
    licenseKeyOn( 'C300', [ 'key' => 'CCCCC-CCCCC-CCCCC-CCCCC-CCCCC', 'activations_count' => 1, 'activations_limit' => null ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )
        ->toContain( '2 of 3' )
        ->toContain( '3 of 3' )
        ->toContain( '1, no limit' )
        ->toContain( 'ec-w-70' )
        ->toContain( 'ec-w-100' )
        ->and( substr_count( $html, 'data-activations-full' ) )->toBe( 1 )
        ->and( substr_count( $html, 'role="progressbar"' ) )->toBe( 2 );
} );

it( 'rounds the activation level to a step of ten', function (): void {
    $key = static fn ( int $used, ?int $limit ): LicenseKey => new LicenseKey( [ 'activations_count' => $used, 'activations_limit' => $limit ] );

    expect( Index::activationLevel( $key( 2, 3 ) ) )->toBe( 70 )
        ->and( Index::activationLevel( $key( 0, 5 ) ) )->toBe( 0 )
        ->and( Index::activationLevel( $key( 7, 5 ) ) )->toBe( 100 )
        ->and( Index::activationLevel( $key( 1, null ) ) )->toBeNull()
        ->and( Index::activationLevel( $key( 0, 0 ) ) )->toBeNull()
        ->and( Index::activationsFull( $key( 0, 0 ) ) )->toBeTrue()
        ->and( Index::activationsFull( $key( 3, 3 ) ) )->toBeTrue()
        ->and( Index::activationsFull( $key( 2, 3 ) ) )->toBeFalse()
        ->and( Index::activationsFull( $key( 9, null ) ) )->toBeFalse();
} );

it( 'badges each status with a dot: active success, expired neutral, revoked error', function (): void {
    licenseKeyOn( 'A100' );
    licenseKeyOn( 'B200', [ 'is_revoked' => true, 'revoked_at' => now() ] );
    licenseKeyOn( 'C300', [ 'expires_at' => now()->subDay() ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )
        ->toMatch( '/data-color="success"[^>]*data-license-status="active"|data-license-status="active"[^>]*data-color="success"/' )
        ->toMatch( '/data-color="neutral"[^>]*data-license-status="expired"|data-license-status="expired"[^>]*data-color="neutral"/' )
        ->toMatch( '/data-color="error"[^>]*data-license-status="revoked"|data-license-status="revoked"[^>]*data-color="error"/' );
} );

it( 'hides Revoke on revoked keys, in the row and the drawer', function (): void {
    $key = licenseKeyOn( 'A100', [ 'is_revoked' => true, 'revoked_at' => now(), 'meta' => [ 'revoked_reason' => 'Chargeback' ] ] );

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'data-revoke-key=' )
        ->call( 'showActivations', $key->id )
        ->assertDontSeeHtml( 'data-drawer-revoke' )
        ->assertSee( 'Revoked: Chargeback' );
} );

it( 'names the key\'s product, customer and order in the drawer, and revokes from it', function (): void {
    $customer = Customer::factory()->create( [ 'first_name' => 'Maya', 'last_name' => 'Chen' ] );
    $key      = licenseKeyOn( 'A100', [ 'key' => 'KNIT-7Q4M-2HXP-9F3A', 'activations_count' => 2, 'activations_limit' => 3 ], $customer );

    Livewire::test( Index::class )
        ->call( 'showActivations', $key->id )
        ->assertSee( 'Photo Editor · Maya Chen · #A100' )
        ->assertSee( '2 of 3 activations used.' )
        ->assertSee( 'This key has not been activated on any machine.' )
        ->assertSeeHtml( 'data-drawer-revoke' )
        ->call( 'startRevoke', $key->id )
        ->assertSet( 'revoking', true )
        ->assertSet( 'viewing', true )
        ->assertSee( 'Revoke KNIT-7Q4M-2HXP-9F3A?' );
} );

it( 'keeps keys, IP addresses and order numbers left to right, with no DaisyUI or utility classes', function (): void {
    $key = licenseKeyOn( 'A100', [ 'key' => 'ABCDE-FGHJK-LMNPQ-RSTUV-WXYZ2' ] );
    LicenseActivation::factory()->create( [ 'license_key_id' => $key->id, 'machine_fingerprint' => 'studio-mac', 'ip_address' => '10.0.0.8', 'last_seen_at' => now()->subHours( 2 ) ] );

    $html = Livewire::test( Index::class )->call( 'showActivations', $key->id )->html();

    expect( $html )
        ->toContain( '<bdi dir="ltr" class="ec-mono ec-nowrap" data-license-key="' . $key->id . '">ABCDE-FGHJK-LMNPQ-RSTUV-WXYZ2</bdi>' )
        ->toContain( '<bdi dir="ltr" class="ec-mono">10.0.0.8</bdi>' )
        ->toContain( '<bdi dir="ltr" class="ec-mono">#A100</bdi>' )
        ->toContain( '2 hours ago' )
        ->not->toMatch( '/class="[^"]*\b(btn|badge-|table-sm|link-hover|font-mono|text-sm|opacity-75|flex|gap-\d|text-end|tabular-nums|overflow-x-auto|w-full|max-w-xl)\b/' );
} );

it( 'shows a key whose limit is zero as allowing no activations, not as having no limit', function (): void {
    $key = licenseKeyOn( 'Z000', [ 'key' => 'ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ-ZZZZZ', 'activations_count' => 0, 'activations_limit' => 0 ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )->toContain( '0 of 0' )
        ->not->toContain( 'no limit' )
        ->not->toContain( 'role="progressbar"' );

    Livewire::test( Index::class )
        ->call( 'showActivations', $key->id )
        ->assertSee( 'This key allows no activations.' )
        ->assertDontSeeHtml( 'data-activations-unlimited' );
} );
it( 'applies the camelCase table filters first, then the deprecated kebab-case ones', function (): void {
    licenseKeyOn( 'A100' );
    $calls = [];

    addFilter( 'ap.ecommerceAdminLivewire.table.licenseKeys.columns', static function ( array $columns ) use ( &$calls ): array {
        $calls[] = 'camel';

        return [ ...$columns, [ 'key' => 'camelColumn', 'label' => 'Camel column', 'value' => static fn (): string => 'from camel' ] ];
    } );
    addFilter( 'ap.ecommerceAdminLivewire.table.license-keys.columns', static function ( array $columns ) use ( &$calls ): array {
        $calls[] = 'kebab';

        return [ ...$columns, [ 'key' => 'kebabColumn', 'label' => 'Kebab column', 'value' => static fn (): string => 'from kebab' ] ];
    } );

    try {
        Livewire::test( Index::class )
            ->assertSee( 'Camel column' )
            ->assertSee( 'Kebab column' );

        expect( array_slice( array_unique( $calls ), 0, 2 ) )->toBe( [ 'camel', 'kebab' ] );
    } finally {
        removeAllFilters( 'ap.ecommerceAdminLivewire.table.licenseKeys.columns' );
        removeAllFilters( 'ap.ecommerceAdminLivewire.table.license-keys.columns' );
    }
} );

it( 'passes each key to the policy when showing it and its activations', function (): void {
    $open   = licenseKeyOn( 'A100', [ 'key' => 'OPENX-FGHJK-LMNPQ-RSTUV-WXYZ2' ] );
    $hidden = licenseKeyOn( 'A200', [ 'key' => 'HIDEX-FGHJK-LMNPQ-RSTUV-QQQQ7' ] );

    // A host policy that shows one key in full, and none of the other.
    Gate::define( 'ecommerce.licenseKey.view', static fn ( $user, $key = null ): bool => null === $key || $key->is( $open ) );
    Gate::define( 'ecommerce.licenseKey.revoke', static fn ( $user, $key = null ): bool => null === $key || $key->is( $open ) );

    Livewire::test( Index::class )
        ->assertSee( 'OPENX-FGHJK-LMNPQ-RSTUV-WXYZ2' )
        ->assertDontSee( 'HIDEX' )
        ->assertSee( '••••••••QQQ7' )
        ->call( 'showActivations', $open->id )
        ->assertSet( 'viewingId', $open->id );

    Livewire::test( Index::class )
        ->call( 'showActivations', $hidden->id )
        ->assertForbidden();
} );
