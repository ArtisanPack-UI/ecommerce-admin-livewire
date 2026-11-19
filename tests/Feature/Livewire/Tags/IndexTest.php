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
        ->assertSeeHtml( '<caption class="apui-sr-only">Tags</caption>' )
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

    $component = Livewire::test( Index::class )
        ->set( 'selected', [ (string) $lower->id, (string) $upper->id ] )
        ->call( 'runBulkAction', 'merge' )
        ->assertHasErrors( [ 'mergeTargetId' => 'required' ] )
        ->assertSet( 'confirmingBulkAction', null )
        ->set( 'mergeTargetId', (string) $upper->id )
        ->call( 'runBulkAction', 'merge' )
        ->assertHasNoErrors()
        ->assertSet( 'confirmingBulkAction', 'merge' )
        ->assertSee( 'Move every product from the selected tags onto &quot;Sale&quot;, then delete the other selected tags?', false );

    expect( ProductTag::query()->find( $lower->id ) )->not->toBeNull();

    $component->call( 'confirmBulkAction', $component->viewData( 'tableConfirmToken' ) )
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

it( 'renders the design-system page: quick add, mono slugs, linked counts and named row buttons', function (): void {
    $sale  = ProductTag::factory()->create( [ 'name' => 'Sale', 'slug' => 'sale' ] );
    $empty = ProductTag::factory()->create( [ 'name' => 'Unused', 'slug' => 'unused' ] );
    $sale->products()->attach( Product::factory()->count( 2 )->create()->pluck( 'id' )->all() );

    $html = Livewire::test( Index::class )->html();

    expect( $html )
        ->toContain( 'Flat labels you can add to any product, for filters, collections and promotions.' )
        ->toContain( 'data-new-tag' )
        ->toContain( 'placeholder="e.g. holiday-2026"' )
        ->toContain( '<bdi dir="ltr" class="ec-mono">sale</bdi>' )
        ->toContain( 'aria-label="2 products tagged Sale"' )
        ->toContain( 'filters%5Btag%5D=' . $sale->id )
        ->toContain( 'aria-label="Rename Sale"' )
        ->toContain( 'aria-label="Delete Sale"' )
        ->toContain( 'data-focus-key="rename-' . $sale->id . '"' )
        ->not->toContain( 'data-tag-products="' . $empty->id . '"' )
        ->not->toMatch( '/\b(btn|input-bordered|select-sm|input-sm|font-semibold|mb-4|flex-wrap)\b/' );
} );

it( 'hides the quick-add card without product.create and the row buttons without their abilities', function (): void {
    Gate::define( 'ecommerce.product.create', static fn (): bool => false );
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );
    Gate::define( 'ecommerce.product.delete', static fn (): bool => false );
    ProductTag::factory()->create( [ 'name' => 'Sale' ] );

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'data-new-tag' )
        ->assertDontSeeHtml( 'aria-label="Rename Sale"' )
        ->assertDontSeeHtml( 'aria-label="Delete Sale"' );
} );

it( 'swaps the row buttons for the rename fields, Save and Cancel', function (): void {
    $tag = ProductTag::factory()->create( [ 'name' => 'Sale', 'slug' => 'sale' ] );
    $tag->products()->attach( Product::factory()->create()->id );

    Livewire::test( Index::class )
        ->call( 'startRename', $tag->id )
        ->assertSet( 'editingName', 'Sale' )
        ->assertSeeHtml( 'id="tag-' . $tag->id . '-name"' )
        ->assertSeeHtml( 'id="tag-' . $tag->id . '-slug"' )
        ->assertSeeHtml( 'data-focus-return="rename-' . $tag->id . '"' )
        ->assertSeeHtml( 'data-rename-cancel' )
        ->assertDontSeeHtml( 'aria-label="Rename Sale"' )
        ->assertDontSeeHtml( 'data-tag-products="' . $tag->id . '"' )
        ->call( 'cancelRename' )
        ->assertSet( 'editingId', null )
        ->assertSeeHtml( 'aria-label="Rename Sale"' );
} );

it( 'shows the merge select and button in the bulk bar only once rows are selected', function (): void {
    $tag = ProductTag::factory()->create( [ 'name' => 'Sale', 'slug' => 'sale' ] );

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'id="tags-merge-target"' )
        ->set( 'selected', [ (string) $tag->id ] )
        ->assertSeeHtml( 'id="tags-merge-target"' )
        ->assertSeeHtml( 'data-focus-key="bulk-merge"' )
        ->assertSee( 'Sale (sale)' );
} );

it( 'hides the merge controls without product.update', function (): void {
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );
    $tag = ProductTag::factory()->create();

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $tag->id ] )
        ->assertDontSeeHtml( 'id="tags-merge-target"' );
} );

it( 'shows the empty state with no tags', function (): void {
    Livewire::test( Index::class )
        ->assertSee( 'No tags yet' )
        ->assertSee( 'Tags are flat labels you can add to any product.' );
} );

it( 'drops a pending merge confirmation when the tag to merge into changes', function (): void {
    $lower    = ProductTag::factory()->create( [ 'name' => 'sale', 'slug' => 'sale' ] );
    $upper    = ProductTag::factory()->create( [ 'name' => 'Sale', 'slug' => 'sale-2' ] );
    $other    = ProductTag::factory()->create( [ 'name' => 'Clearance', 'slug' => 'clearance' ] );
    $selected = [ (string) $lower->id, (string) $upper->id ];

    $component = Livewire::test( Index::class )
        ->set( 'selected', $selected )
        ->set( 'mergeTargetId', (string) $upper->id )
        ->call( 'runBulkAction', 'merge' )
        ->assertSet( 'confirmingBulkAction', 'merge' );

    $token = $component->viewData( 'tableConfirmToken' );

    $component->set( 'mergeTargetId', (string) $other->id )
        ->assertSet( 'confirmingBulkAction', null )
        ->assertSeeHtml( 'wire:model.live="mergeTargetId"' )
        ->call( 'confirmBulkAction', $token );

    expect( ProductTag::query()->whereKey( [ $lower->id, $upper->id, $other->id ] )->count() )->toBe( 3 );
} );

it( 'labels the merge select visibly', function (): void {
    ProductTag::factory()->create();

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) ProductTag::query()->value( 'id' ) ] )
        ->assertSeeHtml( 'class="ec-bulk-inline-label" aria-hidden="true">Merge into</span>' );
} );
