<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;
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
    'category'    => [ 'category', 'role="status"' ],
    'tag'         => [ 'tag', 'Labels' ],
    'date'        => [ 'date', 'type="date"' ],
    'daterange'   => [ 'daterange', 'wire:model="config.window.end"' ],
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

    $component->call( 'removeConfigRow', 'promotion-action', 'every-type', 'config', 'tiers', 0 )
        ->assertSet( 'config.tiers', [ [ 'min_subtotal' => 5000 ] ] );
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

it( 'says when an entry has no settings', function (): void {
    Livewire::test( ConfigFormHost::class, [ 'registry' => 'promotion-action', 'entry' => 'free-shipping' ] )
        ->assertSeeHtml( 'data-config-form-empty' )
        ->call( 'save' )
        ->assertSet( 'saved', [] );
} );

it( 'edits an entry with no schema as JSON, with a notice', function (): void {
    Livewire::test( ConfigFormHost::class, [ 'registry' => 'shipping-method', 'entry' => 'third-party-courier', 'stored' => [ 'account' => 'ACME', 'zones' => [ 1, 2 ] ] ] )
        ->assertSee( 'No form for these settings' )
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
        ->assertSee( 'must be a JSON object' );
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
