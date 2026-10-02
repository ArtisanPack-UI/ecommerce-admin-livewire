<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\ShippingMethod;
use ArtisanPackUI\Ecommerce\Models\ShippingZone;
use ArtisanPackUI\Ecommerce\Models\TaxClass;
use ArtisanPackUI\Ecommerce\Models\TaxRate;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Shipping\Index;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'shippingZone.viewAny', 'shippingZone.create', 'shippingZone.update', 'shippingZone.delete' ] );
    $this->actingAs( makeUser() );
} );

it( 'renders zones by priority with their methods', function (): void {
    $eu = ShippingZone::factory()->create( [ 'name' => 'Europe', 'country_codes' => [ 'DE', 'FR' ], 'priority' => 2 ] );
    $us = ShippingZone::factory()->create( [ 'name' => 'United States', 'country_codes' => [ 'US' ], 'priority' => 1, 'region_codes' => [ 'CA' ] ] );
    ShippingMethod::factory()->create( [ 'zone_id' => $us->id, 'label' => 'Flat $5', 'key' => 'flat-rate' ] );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeInOrder( [ 'United States', 'Regions: CA', 'Europe' ] )
        ->assertSee( 'Germany' )
        ->assertDontSee( 'Flat $5' )
        ->call( 'toggleZone', $us->id )
        ->assertSee( 'Flat $5' )
        ->assertSeeHtml( 'data-zone-methods="' . $us->id . '"' );

    expect( $eu->exists )->toBeTrue();
} );

it( 'shows an empty state without zones', function (): void {
    Livewire::test( Index::class )->assertSee( 'No shipping zones yet' );
} );

it( 'is denied without shippingZone.viewAny', function (): void {
    Gate::define( 'ecommerce.shippingZone.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'creates a zone', function (): void {
    Livewire::test( Index::class )
        ->call( 'createZone' )
        ->assertSet( 'editingZone', true )
        ->set( 'zoneForm.name', 'US West' )
        ->set( 'zoneForm.country_codes', [ 'us' ] )
        ->set( 'zoneForm.region_codes', 'ca, or' )
        ->set( 'zoneForm.postal_patterns', "90*\n97000...97999" )
        ->call( 'saveZone' )
        ->assertHasNoErrors()
        ->assertSet( 'editingZone', false );

    $zone = ShippingZone::query()->sole();

    expect( $zone )
        ->name->toBe( 'US West' )
        ->country_codes->toBe( [ 'US' ] )
        ->region_codes->toBe( [ 'CA', 'OR' ] )
        ->postal_patterns->toBe( [ '90*', '97000...97999' ] )
        ->is_active->toBeTrue();
} );

it( 'edits a zone', function (): void {
    $zone = ShippingZone::factory()->create( [ 'name' => 'Old', 'region_codes' => [ 'NY' ] ] );

    Livewire::test( Index::class )
        ->call( 'editZone', $zone->id )
        ->assertSet( 'zoneForm.region_codes', 'NY' )
        ->set( 'zoneForm.name', 'New' )
        ->set( 'zoneForm.region_codes', '' )
        ->set( 'zoneForm.is_active', false )
        ->call( 'saveZone' )
        ->assertHasNoErrors();

    expect( $zone->refresh() )->name->toBe( 'New' )->region_codes->toBeNull()->is_active->toBeFalse();
} );

it( 'validates the zone', function (): void {
    Livewire::test( Index::class )
        ->call( 'createZone' )
        ->set( 'zoneForm.name', '' )
        ->set( 'zoneForm.country_codes', [] )
        ->call( 'saveZone' )
        ->assertHasErrors( [ 'zoneForm.name' => 'required', 'zoneForm.country_codes' => 'required' ] );

    Livewire::test( Index::class )
        ->call( 'createZone' )
        ->set( 'zoneForm.name', 'Bad' )
        ->set( 'zoneForm.country_codes', [ 'USA' ] )
        ->call( 'saveZone' )
        ->assertHasErrors( [ 'zoneForm.country_codes.0' ] );

    Livewire::test( Index::class )
        ->call( 'createZone' )
        ->set( 'zoneForm.name', 'Long region' )
        ->set( 'zoneForm.country_codes', [ 'US' ] )
        ->set( 'zoneForm.region_codes', str_repeat( 'X', 11 ) )
        ->call( 'saveZone' )
        ->assertHasErrors( [ 'zoneForm.region_codes' ] );

    expect( ShippingZone::query()->count() )->toBe( 0 );
} );

it( 'deletes a zone and its methods after confirmation', function (): void {
    $zone = ShippingZone::factory()->create( [ 'name' => 'Gone' ] );
    ShippingMethod::factory()->count( 2 )->create( [ 'zone_id' => $zone->id ] );

    $component = Livewire::test( Index::class )
        ->call( 'confirmDeleteZone', $zone->id )
        ->assertSet( 'confirmingDelete', true )
        ->assertSee( 'its 2 methods are deleted' );

    $token = $component->viewData( 'deleteToken' );

    $component->call( 'delete', $token )->assertSet( 'confirmingDelete', false );

    expect( ShippingZone::query()->count() )->toBe( 0 )
        ->and( ShippingMethod::query()->count() )->toBe( 0 );
} );

it( 'adds a flat-rate method with its settings', function (): void {
    $zone = ShippingZone::factory()->create();
    TaxClass::factory()->create( [ 'key' => 'shipping-tax', 'label' => 'Shipping tax' ] );

    Livewire::test( Index::class )
        ->call( 'createMethod', $zone->id )
        ->assertSet( 'editingMethod', true )
        ->set( 'methodForm.key', 'flat-rate' )
        ->set( 'methodForm.label', 'Flat $5' )
        ->set( 'methodForm.tax_class_key', 'shipping-tax' )
        ->set( 'methodForm.config.amount', 500 )
        ->call( 'saveMethod' )
        ->assertHasNoErrors()
        ->assertSet( 'editingMethod', false )
        ->assertSee( 'Flat $5' );

    $method = ShippingMethod::query()->sole();

    expect( $method )
        ->zone_id->toBe( $zone->id )
        ->key->toBe( 'flat-rate' )
        ->tax_class_key->toBe( 'shipping-tax' )
        ->and( $method->config['amount'] )->toBe( 500 );
} );

it( 'edits a method', function (): void {
    $method = ShippingMethod::factory()->create( [ 'label' => 'Standard', 'config' => [ 'amount' => 500 ] ] );

    Livewire::test( Index::class )
        ->call( 'editMethod', $method->id )
        ->assertSet( 'methodForm.config.amount', 500 )
        ->set( 'methodForm.label', 'Express' )
        ->set( 'methodForm.config.amount', 1500 )
        ->set( 'methodForm.is_active', false )
        ->call( 'saveMethod' )
        ->assertHasNoErrors();

    expect( $method->refresh() )->label->toBe( 'Express' )->is_active->toBeFalse()
        ->and( $method->config['amount'] )->toBe( 1500 );
} );

it( 'validates the method', function (): void {
    $zone = ShippingZone::factory()->create();

    Livewire::test( Index::class )
        ->call( 'createMethod', $zone->id )
        ->set( 'methodForm.key', 'teleport' )
        ->set( 'methodForm.label', '' )
        ->call( 'saveMethod' )
        ->assertHasErrors( [ 'methodForm.key', 'methodForm.label' => 'required' ] );

    Livewire::test( Index::class )
        ->call( 'createMethod', $zone->id )
        ->set( 'methodForm.key', 'flat-rate' )
        ->set( 'methodForm.label', 'Flat' )
        ->set( 'methodForm.config.amount', null )
        ->call( 'saveMethod' )
        ->assertHasErrors( [ 'methodForm.config.amount' ] );

    Livewire::test( Index::class )
        ->call( 'createMethod', $zone->id )
        ->set( 'methodForm.key', 'flat-rate' )
        ->set( 'methodForm.label', 'Flat' )
        ->set( 'methodForm.tax_class_key', 'nope' )
        ->set( 'methodForm.config.amount', 100 )
        ->call( 'saveMethod' )
        ->assertHasErrors( [ 'methodForm.tax_class_key' ] );

    expect( ShippingMethod::query()->count() )->toBe( 0 );
} );

it( 'reorders methods within a zone', function (): void {
    $zone   = ShippingZone::factory()->create();
    $first  = ShippingMethod::factory()->create( [ 'zone_id' => $zone->id, 'label' => 'First', 'position' => 0 ] );
    $second = ShippingMethod::factory()->create( [ 'zone_id' => $zone->id, 'label' => 'Second', 'position' => 1 ] );

    Livewire::test( Index::class )
        ->call( 'toggleZone', $zone->id )
        ->call( 'moveMethod', $second->id, -1 )
        ->assertSeeInOrder( [ 'Second', 'First' ] )
        ->call( 'moveMethod', $second->id, -1 );

    expect( $second->refresh()->position )->toBe( 0 )
        ->and( $first->refresh()->position )->toBe( 1 );
} );

it( 'deletes a method after confirmation', function (): void {
    $method = ShippingMethod::factory()->create( [ 'label' => 'Pickup' ] );

    $component = Livewire::test( Index::class )->call( 'confirmDeleteMethod', $method->id );

    $component->call( 'delete', $component->viewData( 'deleteToken' ) );

    expect( ShippingMethod::query()->count() )->toBe( 0 )
        ->and( ShippingZone::query()->count() )->toBe( 1 );
} );

it( 'warns about countries no active zone covers', function (): void {
    ShippingZone::factory()->create( [ 'country_codes' => [ 'US' ] ] );
    ShippingZone::factory()->create( [ 'country_codes' => [ 'CA' ], 'region_codes' => [ 'ON' ] ] );
    ShippingZone::factory()->create( [ 'country_codes' => [ 'MX' ], 'is_active' => false ] );
    TaxRate::factory()->create( [ 'country_code' => 'DE' ] );
    TaxRate::factory()->create( [ 'country_code' => 'CA' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-coverage-warning' )
        ->assertSeeHtml( 'data-uncovered-countries' )
        ->assertSee( 'Germany' )
        ->assertSee( 'Mexico' )
        ->assertSeeHtml( 'data-partial-countries' )
        ->assertSee( 'Canada' );
} );

it( 'warns when no zone is active', function (): void {
    ShippingZone::factory()->create( [ 'is_active' => false ] );

    Livewire::test( Index::class )->assertSeeHtml( 'data-no-active-zones' );
} );

it( 'hides and refuses controls without the abilities', function (): void {
    Gate::define( 'ecommerce.shippingZone.create', static fn (): bool => false );
    Gate::define( 'ecommerce.shippingZone.update', static fn (): bool => false );
    Gate::define( 'ecommerce.shippingZone.delete', static fn (): bool => false );

    $zone = ShippingZone::factory()->create( [ 'name' => 'Locked' ] );

    Livewire::test( Index::class )
        ->call( 'toggleZone', $zone->id )
        ->assertDontSee( 'New zone' )
        ->assertDontSeeHtml( 'data-add-method' )
        ->assertDontSeeHtml( 'wire:click="editZone' )
        ->assertDontSeeHtml( 'wire:click="confirmDeleteZone' )
        ->call( 'createZone' )
        ->assertForbidden();

    Livewire::test( Index::class )->call( 'editZone', $zone->id )->assertForbidden();
    Livewire::test( Index::class )->call( 'confirmDeleteZone', $zone->id )->assertForbidden();
} );

it( 'does not recreate a zone deleted while it was being edited', function (): void {
    $zone = ShippingZone::factory()->create( [ 'name' => 'Europe', 'country_codes' => [ 'DE' ] ] );

    $component = Livewire::test( Index::class )->call( 'editZone', $zone->id );

    $zone->delete();

    $component->set( 'zoneForm.name', 'Europe again' )
        ->call( 'saveZone' )
        ->assertSet( 'editingZone', false );

    expect( ShippingZone::query()->count() )->toBe( 0 )
        ->and( sentToasts( $component ) )->toContain( 'This shipping zone was deleted.' );
} );

it( 'warns instead of saving a method whose zone or method was deleted', function (): void {
    $zone   = ShippingZone::factory()->create( [ 'country_codes' => [ 'US' ] ] );
    $method = ShippingMethod::factory()->create( [ 'zone_id' => $zone->id, 'key' => 'flat-rate', 'label' => 'Flat' ] );

    $component = Livewire::test( Index::class )->call( 'editMethod', $method->id );

    $method->delete();

    $component->call( 'saveMethod' )->assertSet( 'editingMethod', false );

    expect( ShippingMethod::query()->count() )->toBe( 0 )
        ->and( sentToasts( $component ) )->toContain( 'This shipping method was deleted.' );
} );

it( 'caches the countries the coverage warning checks', function (): void {
    TaxRate::factory()->create( [ 'country_code' => 'FR', 'is_active' => true ] );

    expect( Index::storeCountries() )->toBe( [ 'FR' ] );

    TaxRate::factory()->create( [ 'country_code' => 'IT', 'is_active' => true ] );

    expect( Index::storeCountries() )->toBe( [ 'FR' ] );

    Illuminate\Support\Facades\Cache::forget( Index::COUNTRIES_CACHE_KEY );

    expect( Index::storeCountries() )->toEqualCanonicalizing( [ 'FR', 'IT' ] );
} );
