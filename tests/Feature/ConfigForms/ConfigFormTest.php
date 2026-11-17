<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\RowKeys;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\ConfigFormHost;

beforeEach( function (): void {
    view()->share( 'errors', new Illuminate\Support\ViewErrorBag() );
    grantAbilities( [ 'product.viewAny', 'promotion.viewAny' ] );
    $this->actingAs( makeUser() );

    app( ConfigFormRegistry::class )->register( 'promotion-action', 'every-type', [
        [ 'name' => 'title', 'type' => 'text', 'label' => 'Title' ],
        [ 'name' => 'count', 'type' => 'number', 'label' => 'Count' ],
        [ 'name' => 'amount', 'type' => 'money', 'label' => 'Amount' ],
        [ 'name' => 'percent', 'type' => 'percent', 'label' => 'Percent' ],
        [ 'name' => 'enabled', 'type' => 'boolean', 'label' => 'Enabled' ],
        [ 'name' => 'mode', 'type' => 'select', 'label' => 'Mode', 'options' => [ 'fast' => 'Fast', 'slow' => 'Slow' ] ],
        [ 'name' => 'channels', 'type' => 'multiselect', 'label' => 'Channels', 'options' => [ 'web' => 'Web', 'pos' => 'Point of sale' ] ],
        [ 'name' => 'products', 'type' => 'product', 'label' => 'Products' ],
        [ 'name' => 'categories', 'type' => 'category', 'label' => 'Categories' ],
        [ 'name' => 'labels', 'type' => 'tag', 'label' => 'Labels' ],
        [ 'name' => 'starts', 'type' => 'date', 'label' => 'Starts' ],
        [ 'name' => 'window', 'type' => 'daterange', 'label' => 'Window' ],
        [ 'name' => 'days', 'type' => 'weekday', 'label' => 'Days' ],
        [ 'name' => 'message', 'type' => 'template', 'label' => 'Message' ],
        [ 'name' => 'tiers', 'type' => 'repeater', 'label' => 'Tiers', 'fields' => [
            [ 'name' => 'min_subtotal', 'type' => 'money', 'label' => 'From subtotal', 'rules' => [ 'required' ] ],
        ] ],
    ] );
} );

/**
 * The host component editing the every-type schema.
 */
function everyTypeForm( array $stored = [] )
{
    return Livewire::test( ConfigFormHost::class, [ 'registry' => 'promotion-action', 'entry' => 'every-type', 'stored' => $stored ] );
}

it( 'renders each field type with a library input', function ( string $type, string $marker ): void {
    expect( everyTypeForm()->html() )->toMatch( '/data-config-field="' . $type . '".{0,20000}?' . preg_quote( $marker, '/' ) . '/s' );
} )->with( [
    'text'        => [ 'text', 'wire:model="config.title"' ],
    'number'      => [ 'number', 'type="number"' ],
    'money'       => [ 'money', 'data-scale="2"' ],
    'percent'     => [ 'percent', '%' ],
    'boolean'     => [ 'boolean', 'toggle' ],
    'select'      => [ 'select', 'Slow' ],
    'multiselect' => [ 'multiselect', 'Point of sale' ],
    'product'     => [ 'product', 'searchPicker' ],
    'category'    => [ 'category', 'searchPicker' ],
    'tag'         => [ 'tag', 'Labels' ],
    'date'        => [ 'date', 'apui-date-picker' ],
    'daterange'   => [ 'daterange', '<fieldset class="ec-range"' ],
    'weekday'     => [ 'weekday', 'Sunday' ],
    'template'    => [ 'template', '<textarea' ],
    'repeater'    => [ 'repeater', 'Add a row' ],
] );

it( 'validates each field type with the schema rules', function ( string $field, mixed $value, ?string $errorKey = null ): void {
    everyTypeForm()
        ->set( 'config.' . $field, $value )
        ->call( 'save' )
        ->assertHasErrors( [ 'config.' . ( $errorKey ?? $field ) ] )
        ->assertSet( 'saved', null );
} )->with( [
    'text too long'       => [ 'title', str_repeat( 'x', 1001 ) ],
    'number'              => [ 'count', 'many' ],
    'money not integer'   => [ 'amount', '10.5' ],
    'money negative'      => [ 'amount', -1 ],
    'percent over 100'    => [ 'percent', 150 ],
    'boolean'             => [ 'enabled', 'maybe' ],
    'select'              => [ 'mode', 'warp' ],
    'multiselect'         => [ 'channels', [ 'web', 'carrier-pigeon' ], 'channels.1' ],
    'product unknown'     => [ 'products', [ 999 ], 'products.0' ],
    'category not ids'    => [ 'categories', 'shoes' ],
    'tag not a list'      => [ 'labels', 'vip' ],
    'date'                => [ 'starts', '2026-13-01' ],
    'weekday'             => [ 'days', [ 8 ], 'days.0' ],
] );

it( 'checks that a date range ends after it starts', function (): void {
    everyTypeForm()
        ->set( 'config.window', [ 'start' => '2026-10-10', 'end' => '2026-10-01' ] )
        ->call( 'save' )
        ->assertHasErrors( [ 'config.window.end' ] );
} );

it( 'validates repeater cells', function (): void {
    everyTypeForm()
        ->call( 'addConfigRow', 'promotion-action', 'every-type', 'config', 'tiers' )
        ->call( 'save' )
        ->assertHasErrors( [ 'config.tiers.0.min_subtotal' => 'required' ] );
} );

it( 'saves a valid config cast to its types', function (): void {
    $product = Product::factory()->create();

    everyTypeForm()
        ->set( 'config.title', 'Spring' )
        ->set( 'config.amount', 1050 )
        ->set( 'config.percent', '12.5' )
        ->set( 'config.mode', 'fast' )
        ->set( 'config.products', [ (string) $product->id ] )
        ->set( 'config.days', [ '6', '7' ] )
        ->set( 'config.window', [ 'start' => '2026-10-01', 'end' => '2026-10-31' ] )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'saved', [
            'title'      => 'Spring',
            'amount'     => 1050,
            'percent'    => 12.5,
            'enabled'    => false,
            'mode'       => 'fast',
            'channels'   => [],
            'products'   => [ (int) $product->id ],
            'categories' => [],
            'labels'     => [],
            'window'     => [ 'start' => '2026-10-01', 'end' => '2026-10-31' ],
            'days'       => [ 6, 7 ],
            'tiers'      => [],
        ] );
} );

it( 'adds and removes repeater rows', function (): void {
    $component = everyTypeForm()
        ->call( 'addConfigRow', 'promotion-action', 'every-type', 'config', 'tiers' )
        ->call( 'addConfigRow', 'promotion-action', 'every-type', 'config', 'tiers' )
        ->set( 'config.tiers.0.min_subtotal', 1000 )
        ->set( 'config.tiers.1.min_subtotal', 5000 )
        ->assertSee( 'Tiers row 2' );

    $kept = $component->get( 'config.tiers.1' )[ RowKeys::KEY ];

    $component->call( 'removeConfigRow', 'promotion-action', 'every-type', 'config', 'tiers', 0 )
        ->assertSet( 'config.tiers', [ [ RowKeys::KEY => $kept, 'min_subtotal' => 5000 ] ] )
        ->assertSeeHtml( 'wire:key="config-config-tiers-row-' . $kept . '"' );

    expect( ConfigFormRegistry::cast( app( ConfigFormRegistry::class )->schema( 'promotion-action', 'every-type' ), $component->get( 'config' ) )['tiers'] )
        ->toBe( [ [ 'min_subtotal' => 5000 ] ] );
} );

it( 'ignores row actions aimed at something that is not a repeater', function (): void {
    everyTypeForm()
        ->call( 'addConfigRow', 'promotion-action', 'every-type', 'config', 'title' )
        ->assertSet( 'config.title', null )
        ->call( 'addConfigRow', 'promotion-action', 'every-type', 'registry', 'tiers' )
        ->assertSet( 'registry', 'promotion-action' )
        ->call( 'addConfigRow', 'promotion-action', 'every-type', 'saved', 'tiers' )
        ->assertSet( 'saved', null );
} );

it( 'caps repeater rows', function (): void {
    $component = everyTypeForm();

    foreach ( range( 1, ConfigFormRegistry::MAX_ROWS + 2 ) as $attempt ) {
        $component->call( 'addConfigRow', 'promotion-action', 'every-type', 'config', 'tiers' );
    }

    expect( $component->get( 'config.tiers' ) )->toHaveCount( ConfigFormRegistry::MAX_ROWS );
} );

it( 'requires a percent or an amount in each tiered-discount tier', function (): void {
    $tiers = static fn ( array $tier ) => Livewire::test( ConfigFormHost::class, [ 'registry' => 'promotion-action', 'entry' => 'tiered-discount' ] )
        ->call( 'addConfigRow', 'promotion-action', 'tiered-discount', 'config', 'tiers' )
        ->set( 'config.tiers.0', [ 'min_subtotal' => 5000, ...$tier ] )
        ->call( 'save' );

    $tiers( [] )->assertHasErrors( [ 'config.tiers.0.percent', 'config.tiers.0.amount' ] );
    $tiers( [ 'percent' => 10 ] )->assertHasNoErrors()->assertSet( 'saved', [ 'tiers' => [ [ 'min_subtotal' => 5000, 'percent' => 10 ] ] ] );
    $tiers( [ 'amount' => 500 ] )->assertHasNoErrors()->assertSet( 'saved', [ 'tiers' => [ [ 'min_subtotal' => 5000, 'amount' => 500 ] ] ] );
} );

it( 'resolves sibling references against wherever the config is bound', function (): void {
    expect( app( ConfigFormRegistry::class )->rules( 'promotion-action', 'tiered-discount', 'conditions.2.config' )['conditions.2.config.tiers.*.percent'] )
        ->toContain( 'required_without:conditions.2.config.tiers.*.amount' );
} );

it( 'shows a money field for a minimum-subtotal condition', function (): void {
    Livewire::test( ConfigFormHost::class, [ 'registry' => 'promotion-condition', 'entry' => 'min-subtotal' ] )
        ->assertSee( 'Minimum subtotal' )
        ->assertSeeHtml( 'data-config-field="money"' )
        ->call( 'save' )
        ->assertHasErrors( [ 'config.amount' => 'required' ] )
        ->set( 'config.amount', 5000 )
        ->call( 'save' )
        ->assertSet( 'saved', [ 'amount' => 5000 ] );
} );

it( 'lays the fields out in a grid and spans the wide ones', function (): void {
    $html = everyTypeForm()->html();

    expect( $html )->toContain( '<div class="ec-field-grid">' )
        ->toMatch( '/class="ec-span-2" wire:key="config-config-enabled"/' )
        ->toMatch( '/class="ec-span-2" wire:key="config-config-tiers"/' )
        ->toMatch( '/class="ec-span-2" wire:key="config-config-labels"/' )
        ->not->toMatch( '/class="ec-span-2" wire:key="config-config-count"/' )
        ->not->toContain( 'fieldset-legend' )
        ->not->toContain( 'rounded-box' );
} );

it( 'renders weekdays as toggle chips in a labelled group', function (): void {
    $html = everyTypeForm( [ 'days' => [ 1, 5 ] ] )->html();

    expect( $html )->toContain( 'role="group" aria-labelledby="config-config-days-label"' )
        ->toMatch( '/aria-label="Monday"\s+aria-pressed="true"/' )
        ->toMatch( '/aria-label="Friday"\s+aria-pressed="true"/' )
        ->toMatch( '/aria-label="Tuesday"\s+aria-pressed="false"/' )
        ->toContain( '>Mon</button>' );
} );

it( 'binds the weekday chips by a path read on use and shows their error', function (): void {
    $html = everyTypeForm( [ 'days' => [ 9 ] ] )->call( 'save' )->html();

    expect( $html )->toContain( 'data-config-path="config.days"' )
        ->toContain( '$wire.$set( this.$root.dataset.configPath' )
        ->not->toContain( '$wire.entangle( &#039;config.days' )
        ->toContain( 'aria-describedby="config-config-days-error"' )
        ->toContain( 'id="config-config-days-error" role="alert"' );
} );

it( 'starts the week on the locale\'s first day', function ( string $locale, string $first ): void {
    app()->setLocale( $locale );

    $days = ( new ArtisanPackUI\EcommerceAdminLivewire\View\Components\ConfigForm( 'promotion-action', 'every-type', 'config' ) )->weekdays();

    expect( $days )->toHaveCount( 7 )
        ->and( $days[0]['name'] )->toBe( $first )
        ->and( collect( $days )->pluck( 'id' )->sort()->values()->all() )->toBe( [ 1, 2, 3, 4, 5, 6, 7 ] );
} )->with( [
    'English (US week)' => [ 'en', 'Sunday' ],
    'German'            => [ 'de', 'Montag' ],
] );

it( 'puts repeater rows in a fieldset with named groups and remove buttons', function (): void {
    $html = everyTypeForm( [ 'tiers' => [ [ 'min_subtotal' => 1000 ], [ 'min_subtotal' => 2000 ] ] ] )->html();

    expect( $html )->toContain( 'apui-fieldset ec-repeater' )
        ->toContain( 'role="group" aria-label="Tiers row 2"' )
        ->toContain( 'aria-label="Remove Tiers row 2"' )
        ->toContain( 'data-icon-only' );
} );

it( 'uses date pickers for dates and both ends of a range', function (): void {
    $html = everyTypeForm()->html();

    expect( substr_count( $html, 'class="apui-date-picker' ) )->toBe( 3 )
        ->and( $html )->toContain( '<fieldset class="ec-range"' )
        ->toContain( '<legend>Window</legend>' )
        ->toContain( 'config.window.end' );
} );

it( 'says when an entry has no settings', function (): void {
    Livewire::test( ConfigFormHost::class, [ 'registry' => 'promotion-action', 'entry' => 'free-shipping' ] )
        ->assertSeeHtml( 'data-config-form-empty' )
        ->assertSeeHtml( 'apui-empty-state' )
        ->assertSee( 'There are no settings for this.' )
        ->call( 'save' )
        ->assertSet( 'saved', [] );
} );

it( 'edits an entry with no schema as JSON, with a notice', function (): void {
    Livewire::test( ConfigFormHost::class, [ 'registry' => 'shipping-method', 'entry' => 'third-party-courier', 'stored' => [ 'account' => 'ACME', 'zones' => [ 1, 2 ] ] ] )
        ->assertSee( 'No form for these settings' )
        ->assertDontSeeHtml( '<x-artisanpack-alert' )
        ->assertSee( 'Settings (JSON)' )
        ->assertSet( 'config', "{\n    \"account\": \"ACME\",\n    \"zones\": [\n        1,\n        2\n    ]\n}" )
        ->set( 'config', '{"account": "ACME", "express": true}' )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'saved', [ 'account' => 'ACME', 'express' => true ] );
} );

it( 'refuses JSON that is not an object', function ( string $json ): void {
    Livewire::test( ConfigFormHost::class, [ 'registry' => 'shipping-method', 'entry' => 'third-party-courier' ] )
        ->set( 'config', $json )
        ->call( 'save' )
        ->assertHasErrors( [ 'config' ] )
        ->assertSee( 'must be a JSON object' )
        ->assertDontSeeHtml( 'text-error' );
} )->with( [
    'invalid JSON' => [ '{ not json' ],
    'array'        => [ '[1, 2, 3]' ],
    'scalar'       => [ '42' ],
    'empty'        => [ '' ],
] );

it( 'starts an empty JSON config as an empty object', function (): void {
    Livewire::test( ConfigFormHost::class, [ 'registry' => 'shipping-method', 'entry' => 'third-party-courier' ] )
        ->assertSet( 'config', '{}' );
} );
