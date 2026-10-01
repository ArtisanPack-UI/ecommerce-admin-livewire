<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductAttribute;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\ProductTypes\SimpleProductType;
use ArtisanPackUI\Ecommerce\Registries\ProductTypeRegistry;
use ArtisanPackUI\Ecommerce\Services\ProductService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Form;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\ProductTypePanel;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\VariablePanel;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ProductTypePanelRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\SubscriptionProductPanel;
use Tests\Fixtures\User;

beforeEach( function (): void {
    config()->set( 'auth.providers.users.model', User::class );
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    config()->set( 'artisanpack.ecommerce.currency.rates', [ 'USD' => [ 'EUR' => 92_500_000 ] ] );
    config()->set( 'artisanpack.ecommerce.features.scout', false );
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'product.update' ] );
    ProductMedia::fake( false );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    ProductMedia::fake( null );
} );

/**
 * Registers a satellite product type the engine knows nothing else about.
 */
function registerSubscriptionType(): void
{
    $registry = app( ProductTypeRegistry::class );

    if ( ! $registry->has( 'subscription' ) ) {
        $registry->register( 'subscription', new class extends SimpleProductType {
            public function key(): string
            {
                return 'subscription';
            }

            public function label(): string
            {
                return 'Subscription';
            }
        } );
    }
}

/**
 * Panel state for a T-shirt: sizes × colours.
 *
 * @param  array<int, string>  $sizes
 * @param  array<int, string>  $colours
 *
 * @return array<string, mixed>
 */
function teeAttributes( array $sizes = [ 'S', 'M', 'L', 'XL' ], array $colours = [ 'Red', 'Blue', 'Green' ] ): array
{
    $values = static fn ( string $prefix, array $labels ): array => array_map(
        static fn ( string $label, int $i ): array => [ 'uid' => "{$prefix}{$i}", 'id' => null, 'value' => null, 'label' => $label, 'swatch' => '' ],
        $labels,
        array_keys( $labels ),
    );

    return [
        [ 'uid' => 'size', 'id' => null, 'key' => '', 'label' => 'Size', 'is_variation' => true, 'values' => $values( 's', $sizes ) ],
        [ 'uid' => 'colour', 'id' => null, 'key' => '', 'label' => 'Colour', 'is_variation' => true, 'values' => $values( 'c', $colours ) ],
    ];
}

it( 'registers the core panels and resolves their classes', function (): void {
    $registry = app( ProductTypePanelRegistry::class );

    expect( $registry->has( 'simple' ) )->toBeTrue()
        ->and( $registry->panelClass( 'simple' ) )->toBeNull()
        ->and( $registry->panelClass( 'variable' ) )->toBe( VariablePanel::class )
        ->and( $registry->panelClass( 'digital' ) )->toBe( ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\DigitalPanel::class )
        ->and( $registry->panelClass( 'bundled' ) )->toBe( $registry->panelClass( 'grouped' ) )
        ->and( $registry->has( 'subscription' ) )->toBeFalse();
} );

it( 'refuses a panel that does not extend ProductTypePanel', function (): void {
    $registry = app( ProductTypePanelRegistry::class );
    $registry->register( 'broken', Tests\Fixtures\Livewire\SubscriptionPanel::class );

    expect( fn () => $registry->panelClass( 'broken' ) )->toThrow( InvalidArgumentException::class, 'must extend ' . ProductTypePanel::class );
    expect( fn () => $registry->register( ' ', null ) )->toThrow( InvalidArgumentException::class );
} );

it( 'shows a notice for a type with no registered panel instead of failing', function (): void {
    registerSubscriptionType();
    $product = Product::factory()->create( [ 'type' => 'subscription' ] );

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->assertOk()
        ->assertSeeHtml( 'data-no-panel' )
        ->assertSee( 'The "subscription" product type has no settings panel' );
} );

it( 'mounts, validates, and saves a satellite\'s panel with the product', function (): void {
    registerSubscriptionType();
    app( ProductTypePanelRegistry::class )->register( 'subscription', SubscriptionProductPanel::class );
    Livewire::component( 'subscription-product-panel', SubscriptionProductPanel::class );

    $component = Livewire::test( Form::class )
        ->set( 'name', 'Coffee Club' )
        ->set( 'type', 'subscription' )
        ->assertSet( 'panelState', [ 'interval' => 'month' ] )
        ->assertSee( 'Subscription' )
        ->set( 'panelState.interval', 'fortnight' )
        ->call( 'save' )
        ->assertHasErrors( 'panelState.interval' )
        ->assertSet( 'tab', 'panel' );

    expect( Product::query()->count() )->toBe( 0 );

    $component->set( 'panelState.interval', 'week' )->call( 'save' )->assertHasNoErrors();

    expect( Product::query()->sole()->meta['interval'] )->toBe( 'week' );
} );

it( 'renders the variable panel and is denied without the ability', function (): void {
    Livewire::test( VariablePanel::class, [ 'productId' => null ] )
        ->assertOk()
        ->assertSee( 'Attributes' )
        ->assertSee( 'Generate variants' );

    $product = Product::factory()->variable()->create();
    Gate::define( 'ecommerce.product.view', static fn (): bool => false );

    Livewire::test( VariablePanel::class, [ 'productId' => $product->id ] )->assertForbidden();
} );

it( 'edits attributes and values in the panel', function (): void {
    Livewire::test( VariablePanel::class )
        ->set( 'state', VariablePanel::initialState( null ) )
        ->call( 'addAttribute' )
        ->set( 'state.attributes.0.label', 'Size' )
        ->call( 'addValue', 0 )
        ->call( 'addValue', 0 )
        ->set( 'state.attributes.0.values.0.label', 'S' )
        ->set( 'state.attributes.0.values.1.label', 'M' )
        ->call( 'moveValue', 0, 1, -1 )
        ->assertSet( 'state.attributes.0.values.0.label', 'M' )
        ->call( 'removeValue', 0, 0 )
        ->assertSet( 'state.attributes.0.values.0.label', 'S' )
        ->call( 'addAttribute' )
        ->call( 'removeAttribute', 1 )
        ->assertCount( 'state.attributes', 1 );
} );

it( 'generates one variant per combination, named from the options', function (): void {
    $component = Livewire::test( VariablePanel::class )
        ->set( 'state', [ 'attributes' => teeAttributes(), 'variants' => [], 'stock_reason' => '' ] )
        ->call( 'generateVariants' )
        ->assertSet( 'pendingGenerate', 0 )
        ->assertCount( 'state.variants', 12 )
        ->assertSet( 'state.variants.0.name', 'S / Red' )
        ->assertSet( 'state.variants.11.name', 'XL / Green' )
        ->assertDispatched( 'ecommerce-admin-variants-generated', count: 12 );

    $component->call( 'generateVariants' )->assertCount( 'state.variants', 12 );
} );

it( 'asks before generating more than 50 variants', function (): void {
    $sizes   = array_map( static fn ( int $n ): string => "Size {$n}", range( 1, 9 ) );
    $colours = array_map( static fn ( int $n ): string => "Colour {$n}", range( 1, 6 ) );

    Livewire::test( VariablePanel::class )
        ->set( 'state', [ 'attributes' => teeAttributes( $sizes, $colours ), 'variants' => [], 'stock_reason' => '' ] )
        ->call( 'generateVariants' )
        ->assertSet( 'pendingGenerate', 54 )
        ->assertSee( 'This adds 54 variants. Continue?' )
        ->assertCount( 'state.variants', 0 )
        ->call( 'cancelGenerate' )
        ->assertSet( 'pendingGenerate', 0 )
        ->call( 'generateVariants' )
        ->call( 'confirmGenerate' )
        ->assertCount( 'state.variants', 54 );
} );

it( 'refuses to generate when an attribute has no values', function (): void {
    Livewire::test( VariablePanel::class )
        ->set( 'state', [ 'attributes' => [ [ 'uid' => 'a', 'id' => null, 'key' => '', 'label' => 'Size', 'is_variation' => true, 'values' => [] ] ], 'variants' => [], 'stock_reason' => '' ] )
        ->call( 'generateVariants' )
        ->assertHasErrors( 'state.attributes' )
        ->assertCount( 'state.variants', 0 );
} );

it( 'creates a variable product with attributes, variants, prices, and stock', function (): void {
    $panel = Livewire::test( VariablePanel::class )
        ->set( 'state', [ 'attributes' => teeAttributes( [ 'S', 'XXL' ], [ 'Red' ] ), 'variants' => [], 'stock_reason' => '' ] )
        ->call( 'generateVariants' );

    $state                                 = $panel->get( 'state' );
    $state['variants'][0]['sku']           = 'TEE-S-RED';
    $state['variants'][0]['prices']['USD'] = 2000;
    $state['variants'][0]['stock']         = 5;
    $state['variants'][1]['sku']           = 'TEE-XXL-RED';
    $state['variants'][1]['prices']['USD'] = 2400;
    $state['variants'][1]['prices']['EUR'] = 2200;

    Livewire::test( Form::class )
        ->set( 'name', 'Tee' )
        ->set( 'type', 'variable' )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasNoErrors();

    $product = Product::query()->sole();
    $xxl     = ProductVariant::query()->where( 'sku', 'TEE-XXL-RED' )->sole();

    expect( $product->type )->toBe( 'variable' )
        ->and( ProductAttribute::query()->where( 'product_id', $product->id )->orderBy( 'position' )->pluck( 'key' )->all() )->toBe( [ 'size', 'colour' ] )
        ->and( $product->variants()->orderBy( 'position' )->pluck( 'name' )->all() )->toBe( [ 'S / Red', 'XXL / Red' ] )
        ->and( $xxl->optionValues()->count() )->toBe( 2 )
        ->and( $xxl->prices()->orderBy( 'currency' )->pluck( 'price_amount', 'currency' )->all() )->toBe( [ 'EUR' => 2200, 'USD' => 2400 ] )
        ->and( app( ProductService::class )->inventoryItemFor( ProductVariant::query()->where( 'sku', 'TEE-S-RED' )->sole() )->quantity_on_hand )->toBe( 5 );
} );

it( 'loads, edits, reorders, and deletes variants of a saved product', function (): void {
    $product = app( ProductService::class )->create( [
        'type'       => 'variable',
        'name'       => 'Tee',
        'attributes' => [ [ 'label' => 'Size', 'values' => [ [ 'label' => 'S' ], [ 'label' => 'M' ], [ 'label' => 'XXL' ] ] ] ],
    ] );
    app( ProductService::class )->generateVariants( $product, [ 'prices' => [ [ 'currency' => 'USD', 'price_amount' => 1500 ] ], 'inventory' => [ 'quantity_on_hand' => 4 ] ] );

    $state = VariablePanel::initialState( $product->refresh() );

    expect( $state['variants'] )->toHaveCount( 3 )
        ->and( $state['variants'][2]['name'] )->toBe( 'XXL' )
        ->and( $state['variants'][2]['prices']['USD'] )->toBe( 1500 )
        ->and( $state['variants'][2]['stock'] )->toBe( 4 );

    $state['variants'][2]['prices']['USD'] = 1800;
    $state['variants']                     = [ $state['variants'][2], $state['variants'][0] ];

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->assertSee( 'Variants' )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $product->variants()->orderBy( 'position' )->pluck( 'name' )->all() )->toBe( [ 'XXL', 'S' ] )
        ->and( ProductVariant::query()->where( 'name', 'XXL' )->sole()->prices()->value( 'price_amount' ) )->toBe( 1800 )
        ->and( ProductVariant::query()->count() )->toBe( 2 );
} );

it( 'requires a reason when a saved variant\'s stock changes', function (): void {
    $product = app( ProductService::class )->create( [ 'type' => 'variable', 'name' => 'Tee' ] );
    $variant = app( ProductService::class )->createVariant( $product, [ 'sku' => 'TEE-1', 'inventory' => [ 'quantity_on_hand' => 4 ] ] );

    $state                         = VariablePanel::initialState( $product );
    $state['variants'][0]['stock'] = 1;

    $form = Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasErrors( 'panelState.stock_reason' )
        ->assertSet( 'tab', 'panel' );

    $state['stock_reason'] = 'Recount';

    $form->set( 'panelState', $state )->call( 'save' )->assertHasNoErrors();

    expect( app( ProductService::class )->inventoryItemFor( $variant )->quantity_on_hand )->toBe( 1 );
} );

it( 'validates variant rows and maps engine refusals to the variant', function (): void {
    Product::factory()->create( [ 'sku' => 'TAKEN' ] );

    $state = [ 'attributes' => teeAttributes( [ 'S' ], [ 'Red' ] ), 'variants' => [], 'stock_reason' => '' ];

    $panel = Livewire::test( VariablePanel::class )->set( 'state', $state )->call( 'generateVariants' );
    $state = $panel->get( 'state' );

    $duplicate                       = $state;
    $duplicate['variants'][]         = array_merge( $state['variants'][0], [ 'options' => [] ] );
    $duplicate['variants'][0]['sku'] = 'SAME';
    $duplicate['variants'][1]['sku'] = 'same';

    Livewire::test( Form::class )
        ->set( 'name', 'Tee' )
        ->set( 'type', 'variable' )
        ->set( 'panelState', $duplicate )
        ->call( 'save' )
        ->assertHasErrors( 'panelState.variants' );

    $state['variants'][0]['sku'] = 'TAKEN';

    Livewire::test( Form::class )
        ->set( 'name', 'Tee' )
        ->set( 'type', 'variable' )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasErrors( 'panelState.variants.0.sku' )
        ->assertSet( 'tab', 'panel' );

    expect( Product::query()->where( 'name', 'Tee' )->exists() )->toBeFalse();
} );

it( 'shows the form\'s errors on the panel\'s fields', function (): void {
    Livewire::test( VariablePanel::class, [ 'panelErrors' => [ 'attributes.0.label' => 'The attribute label field is required.' ] ] )
        ->set( 'state', [ 'attributes' => [ [ 'uid' => 'a', 'id' => null, 'key' => '', 'label' => '', 'is_variation' => true, 'values' => [] ] ], 'variants' => [], 'stock_reason' => '' ] )
        ->assertSee( 'The attribute label field is required.' );
} );

it( 'refuses panel changes on a read-only product', function (): void {
    $product = Product::factory()->variable()->create();
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );

    Livewire::test( VariablePanel::class, [ 'productId' => $product->id, 'readOnly' => true ] )
        ->assertDontSee( 'Generate variants' )
        ->call( 'addAttribute' )
        ->assertForbidden();
} );

it( 'lets a removed variant\'s combination and SKU be added back in the same save', function (): void {
    $product = app( ProductService::class )->create( [
        'type'       => 'variable',
        'name'       => 'Tee',
        'attributes' => [ [ 'label' => 'Size', 'values' => [ [ 'label' => 'S' ], [ 'label' => 'M' ] ] ] ],
    ] );
    app( ProductService::class )->generateVariants( $product );
    ProductVariant::query()->where( 'name', 'M' )->update( [ 'sku' => 'TEE-M' ] );

    $state = VariablePanel::initialState( $product->refresh() );
    unset( $state['variants'][1] );

    $panel = Livewire::test( VariablePanel::class, [ 'productId' => $product->id ] )
        ->set( 'state', $state )
        ->call( 'generateVariants' );

    $state                       = $panel->get( 'state' );
    $state['variants'][1]['sku'] = 'TEE-M';

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $product->variants()->pluck( 'sku', 'name' )->all() )->toBe( [ 'S' => null, 'M' => 'TEE-M' ] );
} );

it( 'leaves another product\'s variants alone when their ids are sent', function (): void {
    $other   = app( ProductService::class )->create( [ 'type' => 'variable', 'name' => 'Cap' ] );
    $foreign = app( ProductService::class )->createVariant( $other, [ 'sku' => 'CAP-1', 'name' => 'Cap' ] );
    $product = app( ProductService::class )->create( [ 'type' => 'variable', 'name' => 'Tee' ] );

    $state             = VariablePanel::initialState( $product );
    $state['variants'] = [ [ 'id' => $foreign->id, 'name' => 'Hijacked', 'sku' => 'TEE-1', 'options' => [], 'prices' => [ 'USD' => null, 'EUR' => null ], 'stock' => 0, 'stock_loaded' => 0, 'image_media_id' => null, 'image_url' => '' ] ];

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $foreign->refresh()->name )->toBe( 'Cap' )
        ->and( $foreign->product_id )->toBe( $other->id )
        ->and( $product->variants()->pluck( 'sku' )->all() )->toBe( [ 'TEE-1' ] );
} );
