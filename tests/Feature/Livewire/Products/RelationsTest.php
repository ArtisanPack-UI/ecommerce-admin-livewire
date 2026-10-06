<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductRelation;
use ArtisanPackUI\Ecommerce\Services\ProductService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Form;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Fixtures\User;

beforeEach( function (): void {
    config()->set( 'auth.providers.users.model', User::class );
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    config()->set( 'artisanpack.ecommerce.features.scout', false );
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'product.update' ] );
    ProductMedia::fake( false );
    $this->actingAs( makeUser() );

    $this->product = app( ProductService::class )->create( [ 'type' => 'simple', 'name' => 'Espresso Machine' ] );
    $this->grinder = Product::factory()->create( [ 'name' => 'Grinder' ] );
    $this->beans   = Product::factory()->create( [ 'name' => 'Beans' ] );
    $this->cups    = Product::factory()->create( [ 'name' => 'Cups' ] );
} );

afterEach( function (): void {
    ProductMedia::fake( null );
} );

/**
 * The related product ids of a type, in order.
 *
 * @return array<int, int>
 */
function relatedIds( Product $product, string $type ): array
{
    return ProductRelation::query()->where( 'product_id', $product->id )->where( 'type', $type )->orderBy( 'position' )->pluck( 'related_product_id' )->map( static fn ( $id ): int => (int) $id )->all();
}

it( 'saves all three relation types in order, and a reorder persists', function (): void {
    $component = Livewire::test( Form::class, [ 'product' => $this->product->id ] )
        ->set( 'relationPick.upsell', $this->grinder->id )
        ->set( 'relationPick.upsell', $this->beans->id )
        ->set( 'relationPick.cross_sell', $this->cups->id )
        ->set( 'relationPick.related', $this->beans->id )
        ->assertSet( 'relations.upsell', [ $this->grinder->id, $this->beans->id ] )
        ->assertSet( 'relationPick.upsell', '' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( relatedIds( $this->product, ProductRelation::UPSELL ) )->toBe( [ $this->grinder->id, $this->beans->id ] )
        ->and( relatedIds( $this->product, ProductRelation::CROSS_SELL ) )->toBe( [ $this->cups->id ] )
        ->and( relatedIds( $this->product, ProductRelation::RELATED ) )->toBe( [ $this->beans->id ] );

    $component->call( 'moveRelation', 'upsell', 1, -1 )->call( 'save' )->assertHasNoErrors();

    expect( relatedIds( $this->product, ProductRelation::UPSELL ) )->toBe( [ $this->beans->id, $this->grinder->id ] );

    $component->call( 'reorderRelations', 'upsell', [ (string) $this->grinder->id, (string) $this->beans->id ] )
        ->call( 'removeRelation', 'cross_sell', 0 )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( relatedIds( $this->product, ProductRelation::UPSELL ) )->toBe( [ $this->grinder->id, $this->beans->id ] )
        ->and( relatedIds( $this->product, ProductRelation::CROSS_SELL ) )->toBe( [] );
} );

it( 'loads an existing product\'s relations into the tab', function (): void {
    app( ProductService::class )->syncProductRelations( $this->product, ProductRelation::CROSS_SELL, [ $this->cups->id, $this->beans->id ] );

    Livewire::test( Form::class, [ 'product' => $this->product->id ] )
        ->assertSet( 'relations.cross_sell', [ $this->cups->id, $this->beans->id ] )
        ->assertSet( 'relations.upsell', [] )
        ->assertSeeHtml( 'data-relation-list="cross_sell"' )
        ->assertSeeInOrder( [ 'Cross-sells', 'Cups', 'Beans' ] );
} );

it( 'ignores the product itself, duplicates, and unknown products in the picker', function (): void {
    Livewire::test( Form::class, [ 'product' => $this->product->id ] )
        ->set( 'relationPick.upsell', $this->product->id )
        ->set( 'relationPick.upsell', $this->grinder->id )
        ->set( 'relationPick.upsell', $this->grinder->id )
        ->set( 'relationPick.upsell', 999_999 )
        ->assertSet( 'relations.upsell', [ $this->grinder->id ] );
} );

it( 'shows self, duplicate, and missing ids on the tab when they reach the save', function (): void {
    Livewire::test( Form::class, [ 'product' => $this->product->id ] )
        ->set( 'relations.upsell', [ $this->grinder->id, $this->grinder->id ] )
        ->call( 'save' )
        ->assertHasErrors( 'relations.upsell.1' )
        ->assertSet( 'tab', 'linked' );

    Livewire::test( Form::class, [ 'product' => $this->product->id ] )
        ->set( 'relations.related', [ 999_999 ] )
        ->call( 'save' )
        ->assertHasErrors( 'relations.related.0' )
        ->assertSet( 'tab', 'linked' );

    Livewire::test( Form::class, [ 'product' => $this->product->id ] )
        ->set( 'relations.cross_sell', [ $this->product->id ] )
        ->call( 'save' )
        ->assertHasErrors( 'relations.cross_sell.0' )
        ->assertSet( 'tab', 'linked' )
        ->assertSee( 'Linked products (has errors)' );

    expect( ProductRelation::query()->count() )->toBe( 0 );
} );

it( 'caps each list', function (): void {
    $ids = Product::factory()->count( Form::MAX_RELATIONS + 1 )->create()->pluck( 'id' )->map( static fn ( $id ): int => (int) $id )->all();

    Livewire::test( Form::class, [ 'product' => $this->product->id ] )
        ->set( 'relations.related', $ids )
        ->call( 'save' )
        ->assertHasErrors( [ 'relations.related' => 'max' ] );
} );

it( 'saves relations on a new product', function (): void {
    Livewire::test( Form::class )
        ->set( 'name', 'Milk Frother' )
        ->set( 'relationPick.related', $this->cups->id )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( relatedIds( Product::query()->where( 'name', 'Milk Frother' )->sole(), ProductRelation::RELATED ) )->toBe( [ $this->cups->id ] );
} );

it( 'leaves the product out of its own related-products picker', function (): void {
    $options = Livewire::test( Form::class, [ 'product' => $this->product->id ] )
        ->set( 'relationPick.upsell', $this->grinder->id )
        ->call( 'searchPicker', '', 'product', 'relationPick.upsell' )
        ->get( 'pickerOptions' )['product:relationPick.upsell'];

    expect( array_column( $options, 'id' ) )->not->toContain( $this->product->id, $this->grinder->id )
        ->toContain( $this->beans->id );
} );

it( 'shows the lists read-only to a user who can only view the product', function (): void {
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );
    app( ProductService::class )->syncProductRelations( $this->product, ProductRelation::UPSELL, [ $this->grinder->id ] );

    $component = Livewire::test( Form::class, [ 'product' => $this->product->id ] )
        ->assertSee( 'Grinder' )
        ->assertDontSeeHtml( 'id="relation-pick-upsell"' )
        ->assertDontSeeHtml( 'removeRelation' );

    $component->call( 'removeRelation', 'upsell', 0 )->assertForbidden();

    Livewire::test( Form::class, [ 'product' => $this->product->id ] )->call( 'save' )->assertForbidden();

    expect( relatedIds( $this->product, ProductRelation::UPSELL ) )->toBe( [ $this->grinder->id ] );
} );
