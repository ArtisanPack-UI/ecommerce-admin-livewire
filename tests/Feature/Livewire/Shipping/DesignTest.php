<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\ShippingMethod;
use ArtisanPackUI\Ecommerce\Models\ShippingZone;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Shipping\Index;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'shippingZone.viewAny', 'shippingZone.create', 'shippingZone.update', 'shippingZone.delete' ] );
    $this->actingAs( makeUser() );
} );

it( 'draws zones as one card of rows with a muted meta line and no DaisyUI classes', function (): void {
    $zone = ShippingZone::factory()->create( [
        'name'            => 'Canada west',
        'country_codes'   => [ 'CA' ],
        'region_codes'    => [ 'BC', 'ON' ],
        'postal_patterns' => [ 'V*', 'K0A...M9Z' ],
        'priority'        => 2,
    ] );
    ShippingMethod::factory()->create( [ 'zone_id' => $zone->id, 'label' => 'Flat', 'key' => 'flat-rate' ] );

    $component = Livewire::test( Index::class )
        ->assertSee( 'Where you ship and the methods shoppers can choose there.' )
        ->assertSeeHtml( 'aria-label="Shipping zones"' )
        ->assertSeeHtml( '<bdi dir="ltr" class="ec-mono">BC, ON</bdi>' )
        ->assertSeeHtml( '<bdi dir="ltr" class="ec-mono">V*, K0A...M9Z</bdi>' )
        ->assertSeeHtml( '<bdi dir="ltr">Priority 2</bdi>' )
        ->assertSeeHtml( 'aria-expanded="false"' )
        ->assertSeeHtml( 'aria-label="Show the methods of Canada west"' )
        ->assertSeeHtml( 'aria-label="Edit Canada west"' )
        ->assertSee( '1 method' );

    expect( $component->html() )->not->toMatch( '/class="[^"]*(?<![\w-])(btn|badge-|rounded-box|border-base-300|text-sm|text-xs|opacity-75|flex|gap-\d|font-semibold|fieldset-legend|text-error)\b/' );

    $component->call( 'toggleZone', $zone->id )
        ->assertSeeHtml( 'aria-expanded="true"' )
        ->assertSeeHtml( 'aria-controls="zone-' . $zone->id . '-methods"' )
        ->assertSeeHtml( 'aria-label="Hide the methods of Canada west"' )
        ->assertSeeHtml( 'aria-label="Move Flat up"' );
} );

it( 'shortens a long country list to three names and a count', function (): void {
    ShippingZone::factory()->create( [ 'name' => 'Europe', 'country_codes' => [ 'DE', 'FR', 'NL', 'BE', 'AT' ] ] );

    Livewire::test( Index::class )
        ->assertSee( 'Germany, France, Netherlands, +2 more' )
        ->assertSeeHtml( 'title="Germany, France, Netherlands, Belgium, Austria"' );
} );

it( 'lists four countries in full rather than "+1 more"', function (): void {
    ShippingZone::factory()->create( [ 'country_codes' => [ 'DE', 'FR', 'NL', 'BE' ] ] );

    Livewire::test( Index::class )
        ->assertSee( 'Germany, France, Netherlands, Belgium' )
        ->assertDontSee( '+1 more' );
} );

it( 'leads with an error alert when no zone is active, also before any zone exists', function (): void {
    $html = Livewire::test( Index::class )
        ->assertSee( 'No shipping zones yet' )
        ->html();

    expect( $html )->toMatch( '/<div[^>]*data-no-active-zones[^>]*role="alert"[^>]*data-color="error"/s' );
} );

it( 'warns (not errors) about uncovered countries', function (): void {
    ShippingZone::factory()->create( [ 'country_codes' => [ 'US' ] ] );
    ShippingZone::factory()->create( [ 'country_codes' => [ 'MX' ], 'is_active' => false ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )->toMatch( '/data-color="warning"[^>]*data-coverage-warning|data-coverage-warning[^>]*data-color="warning"/s' )
        ->and( $html )->not->toContain( 'data-no-active-zones' );
} );

it( 'names who loses shipping when deleting an active zone with regions', function (): void {
    $zone = ShippingZone::factory()->create( [ 'name' => 'Canada west', 'country_codes' => [ 'CA' ], 'region_codes' => [ 'BC', 'ON' ] ] );
    ShippingMethod::factory()->create( [ 'zone_id' => $zone->id ] );

    Livewire::test( Index::class )
        ->call( 'confirmDeleteZone', $zone->id )
        ->assertSeeHtml( 'role="alertdialog"' )
        ->assertSee( 'Its 1 shipping method is deleted with it.' )
        ->assertSee( 'Shoppers in BC, ON in Canada will see no shipping methods unless another zone covers them.' )
        ->assertSee( 'Keep it' )
        ->assertSee( 'Delete zone' );
} );

it( 'says an inactive zone with no methods affects no one', function (): void {
    $zone = ShippingZone::factory()->create( [ 'is_active' => false ] );

    Livewire::test( Index::class )
        ->call( 'confirmDeleteZone', $zone->id )
        ->assertSee( 'It has no shipping methods.' )
        ->assertSee( 'The zone is inactive, so no shopper uses it at the moment.' );
} );

it( 'names the zone when deleting a method', function (): void {
    $zone   = ShippingZone::factory()->create( [ 'name' => 'Domestic' ] );
    $method = ShippingMethod::factory()->create( [ 'zone_id' => $zone->id, 'label' => 'Express', 'is_active' => true ] );

    Livewire::test( Index::class )
        ->call( 'confirmDeleteMethod', $method->id )
        ->assertSee( 'Shoppers in Domestic can no longer choose Express at checkout.' )
        ->assertSee( 'Delete method' );
} );

it( 'titles the drawers for the zone and method being edited', function (): void {
    $zone   = ShippingZone::factory()->create( [ 'postal_patterns' => [ '90*', '97000...97999' ] ] );
    $method = ShippingMethod::factory()->create( [ 'zone_id' => $zone->id ] );

    Livewire::test( Index::class )
        ->call( 'editZone', $zone->id )
        ->assertSeeHtmlInOrder( [ 'data-part="title"', 'Edit zone' ] )
        ->assertSet( 'zoneForm.postal_patterns', '90*, 97000...97999' )
        ->assertSeeHtml( 'form="shipping-zone-form"' )
        ->set( 'editingZone', false )
        ->call( 'editMethod', $method->id )
        ->assertSee( 'Edit method' )
        ->assertSeeHtml( 'data-method-config' );
} );

it( 'round-trips comma-separated postal codes', function (): void {
    $zone = ShippingZone::factory()->create( [ 'postal_patterns' => [ '90*', '97000...97999' ] ] );

    Livewire::test( Index::class )
        ->call( 'editZone', $zone->id )
        ->call( 'saveZone' )
        ->assertHasNoErrors();

    expect( $zone->refresh()->postal_patterns )->toBe( [ '90*', '97000...97999' ] );
} );

it( 'switches the zone drawer back to "New zone" after editing one', function (): void {
    $zone = ShippingZone::factory()->create();

    $html = Livewire::test( Index::class )
        ->call( 'editZone', $zone->id )
        ->call( 'createZone' )
        ->assertSet( 'zoneId', null )
        ->html();

    expect( $html )->toMatch( '/data-scope="drawer" data-part="title"[^>]*>New zone</' )
        ->and( $html )->not->toMatch( '/data-scope="drawer" data-part="title"[^>]*>Edit zone</' );
} );

it( 'reads the full country list to screen readers when the row shortens it', function (): void {
    ShippingZone::factory()->create( [ 'country_codes' => [ 'DE', 'FR', 'NL', 'BE', 'AT', 'IT', 'ES' ] ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )->toMatch( '/<span aria-hidden="true"[^>]*>Germany, France, Netherlands, \\+4 more<\\/span>/' )
        ->and( $html )->toContain( '<span class="apui-sr-only">Germany, France, Netherlands, Belgium, Austria, Italy, Spain</span>' );
} );
