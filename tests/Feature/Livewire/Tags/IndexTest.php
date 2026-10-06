<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tags\Index;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'product.viewAny', 'product.create', 'product.update', 'product.delete' ] );
    $this->actingAs( makeUser() );
} );

it( 'renders tags with product counts', function (): void {
    $sale = ProductTag::factory()->create( [ 'name' => 'Sale', 'slug' => 'sale' ] );
    ProductTag::factory()->create( [ 'name' => 'New', 'slug' => 'new' ] );
    Product::factory()->count( 3 )->create()->each( fn ( Product $product ) => $product->tags()->attach( $sale->id ) );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeHtml( '<caption class="sr-only">Tags</caption>' )
        ->assertSeeInOrder( [ 'New', 'Sale', '3' ] );
} );

it( 'is denied without product.viewAny', function (): void {
    Gate::define( 'ecommerce.product.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'searches and sorts by product count', function (): void {
    $sale = ProductTag::factory()->create( [ 'name' => 'Sale' ] );
    ProductTag::factory()->create( [ 'name' => 'Autumn' ] );
    $sale->products()->attach( Product::factory()->create()->id );

    Livewire::test( Index::class )
        ->set( 'search', 'sal' )->assertSee( 'Sale' )->assertDontSee( 'Autumn' )
        ->set( 'search', '' )
        ->call( 'sort', 'products' )->call( 'sort', 'products' )
        ->assertSeeInOrder( [ 'Sale', 'Autumn' ] );
} );

it( 'creates a tag inline', function (): void {
    Livewire::test( Index::class )
        ->set( 'newTagName', 'Gift ideas' )
        ->call( 'createTag' )
        ->assertHasNoErrors()
        ->assertSet( 'newTagName', '' )
        ->assertSee( 'Gift ideas' );

    expect( ProductTag::query()->where( 'slug', 'gift-ideas' )->exists() )->toBeTrue();
} );

it( 'validates the new tag name', function (): void {
    Livewire::test( Index::class )
        ->set( 'newTagName', '' )
        ->call( 'createTag' )
        ->assertHasErrors( [ 'newTagName' => 'required' ] );
} );

it( 'refuses to create without product.create', function (): void {
    Gate::define( 'ecommerce.product.create', static fn (): bool => false );

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'data-new-tag' )
        ->set( 'newTagName', 'Sale' )
        ->call( 'createTag' )
        ->assertForbidden();
} );

it( 'renames a tag inline', function (): void {
    $tag = ProductTag::factory()->create( [ 'name' => 'sale', 'slug' => 'sale' ] );

    Livewire::test( Index::class )
        ->call( 'startRename', $tag->id )
        ->assertSeeHtml( 'data-rename-tag="' . $tag->id . '"' )
        ->set( 'editingName', 'On sale' )
        ->set( 'editingSlug', 'on-sale' )
        ->call( 'saveRename' )
        ->assertHasNoErrors()
        ->assertSet( 'editingId', null );

    expect( $tag->refresh() )->name->toBe( 'On sale' )->slug->toBe( 'on-sale' );
} );

it( 'reports a taken slug when renaming', function (): void {
    ProductTag::factory()->create( [ 'name' => 'Sale', 'slug' => 'sale' ] );
    $other = ProductTag::factory()->create( [ 'name' => 'Deals', 'slug' => 'deals' ] );

    Livewire::test( Index::class )
        ->call( 'startRename', $other->id )
        ->set( 'editingSlug', 'sale' )
        ->call( 'saveRename' )
        ->assertHasErrors( [ 'editingSlug' ] );

    Livewire::test( Index::class )
        ->call( 'startRename', $other->id )
        ->set( 'editingName', '' )
        ->call( 'saveRename' )
        ->assertHasErrors( [ 'editingName' => 'required' ] );
} );

it( 'deletes a tag after confirmation, keeping its products', function (): void {
    $tag     = ProductTag::factory()->create( [ 'name' => 'Old' ] );
    $product = Product::factory()->create();
    $tag->products()->attach( $product->id );

    $component = Livewire::test( Index::class )
        ->call( 'confirmDelete', $tag->id )
        ->assertSet( 'confirmingBulkAction', 'delete' );

    $component->call( 'confirmBulkAction', $component->viewData( 'tableConfirmToken' ) );

    expect( ProductTag::query()->find( $tag->id ) )->toBeNull()
        ->and( Product::query()->find( $product->id ) )->not->toBeNull();
} );

it( 'merges duplicate tags into one', function (): void {
    $lower = ProductTag::factory()->create( [ 'name' => 'sale', 'slug' => 'sale' ] );
    $upper = ProductTag::factory()->create( [ 'name' => 'Sale', 'slug' => 'sale-2' ] );
    $a     = Product::factory()->create();
    $b     = Product::factory()->create();
    $lower->products()->attach( [ $a->id, $b->id ] );
    $upper->products()->attach( $b->id );

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $lower->id, (string) $upper->id ] )
        ->call( 'runBulkAction', 'merge' )
        ->assertHasErrors( [ 'mergeTargetId' => 'required' ] )
        ->set( 'mergeTargetId', (string) $upper->id )
        ->call( 'runBulkAction', 'merge' )
        ->assertHasNoErrors()
        ->assertSet( 'selected', [] );

    expect( ProductTag::query()->find( $lower->id ) )->toBeNull()
        ->and( $upper->products()->pluck( ( new Product() )->qualifyColumn( 'id' ) )->sort()->values()->all() )->toBe( [ $a->id, $b->id ] );
} );

it( 'refuses to merge without product.update', function (): void {
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );
    $tag = ProductTag::factory()->create();

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $tag->id ] )
        ->call( 'runBulkAction', 'merge' )
        ->assertForbidden();
} );
