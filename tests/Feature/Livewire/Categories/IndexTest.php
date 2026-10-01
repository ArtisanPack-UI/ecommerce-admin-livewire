<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Categories\Index;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'product.viewAny', 'product.create', 'product.update', 'product.delete' ] );
    $this->actingAs( makeUser() );
} );

/**
 * The delete token the confirmation rendered.
 */
function categoryDeleteToken( $component ): string
{
    return (string) $component->viewData( 'deleteToken' );
}

it( 'renders the tree with depth and product counts', function (): void {
    $prints = ProductCategory::factory()->create( [ 'name' => 'Prints', 'position' => 0 ] );
    $canvas = ProductCategory::factory()->create( [ 'name' => 'Canvas', 'parent_id' => $prints->id ] );
    ProductCategory::factory()->create( [ 'name' => 'Mugs', 'position' => 1 ] );
    Product::factory()->count( 2 )->create()->each( fn ( Product $product ) => $product->categories()->attach( $canvas->id ) );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeInOrder( [ 'Prints', 'Canvas', 'Mugs' ] )
        ->assertSeeHtml( 'data-category="' . $canvas->id . '"' )
        ->assertSeeHtml( 'data-depth="1"' )
        ->assertSeeHtml( 'data-product-count="2"' )
        ->assertSee( '1 subcategory' );
} );

it( 'shows the empty state', function (): void {
    Livewire::test( Index::class )->assertSee( 'No categories yet' );
} );

it( 'is denied without product.viewAny', function (): void {
    Gate::define( 'ecommerce.product.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'hides write controls without the write abilities', function (): void {
    Gate::define( 'ecommerce.product.create', static fn (): bool => false );
    Gate::define( 'ecommerce.product.update', static fn (): bool => false );
    Gate::define( 'ecommerce.product.delete', static fn (): bool => false );
    ProductCategory::factory()->create( [ 'name' => 'Prints' ] );

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'wire:click="create"' )
        ->assertDontSeeHtml( 'Edit Prints' )
        ->assertDontSeeHtml( 'Delete Prints' )
        ->call( 'create' )
        ->assertForbidden();
} );

it( 'creates a category with a generated slug under a parent', function (): void {
    $prints = ProductCategory::factory()->create( [ 'name' => 'Prints' ] );

    Livewire::test( Index::class )
        ->call( 'create', $prints->id )
        ->assertSet( 'editing', true )
        ->assertSet( 'form.parent_id', $prints->id )
        ->set( 'form.name', 'Canvas' )
        ->set( 'form.description', '<p>Stretched <script>alert(1)</script></p>' )
        ->set( 'form.icon', 'o-photo' )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'editing', false );

    $canvas = ProductCategory::query()->where( 'name', 'Canvas' )->firstOrFail();

    expect( $canvas->slug )->toBe( 'canvas' )
        ->and( $canvas->parent_id )->toBe( $prints->id )
        ->and( $canvas->icon )->toBe( 'o-photo' )
        ->and( $canvas->description )->not->toContain( '<script' );
} );

it( 'edits a category and moves it under another parent', function (): void {
    $prints = ProductCategory::factory()->create( [ 'name' => 'Prints' ] );
    $canvas = ProductCategory::factory()->create( [ 'name' => 'Canvas' ] );

    Livewire::test( Index::class )
        ->call( 'edit', $canvas->id )
        ->assertSet( 'form.name', 'Canvas' )
        ->set( 'form.name', 'Canvas prints' )
        ->set( 'form.slug', 'canvas-prints' )
        ->set( 'form.parent_id', $prints->id )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $canvas->refresh() )
        ->name->toBe( 'Canvas prints' )
        ->slug->toBe( 'canvas-prints' )
        ->parent_id->toBe( $prints->id );
} );

it( 'validates the form', function (): void {
    Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.name', '' )
        ->set( 'form.parent_id', 999 )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.name' => 'required', 'form.parent_id' => 'exists' ] );
} );

it( 'reports a taken slug from the engine', function (): void {
    ProductCategory::factory()->create( [ 'name' => 'Prints', 'slug' => 'prints' ] );

    Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.name', 'More prints' )
        ->set( 'form.slug', 'prints' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.slug' ] );
} );

it( 'refuses to make a category its own descendant and leaves those out of the parent choices', function (): void {
    $prints = ProductCategory::factory()->create( [ 'name' => 'Prints' ] );
    $canvas = ProductCategory::factory()->create( [ 'name' => 'Canvas', 'parent_id' => $prints->id ] );
    $large  = ProductCategory::factory()->create( [ 'name' => 'Large canvas', 'parent_id' => $canvas->id ] );

    $component = Livewire::test( Index::class )->call( 'edit', $prints->id );

    $options = collect( $component->viewData( 'parentOptions' ) )->pluck( 'id' )->all();
    expect( $options )->not->toContain( $prints->id, $canvas->id, $large->id );

    $component->set( 'form.parent_id', $large->id )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.parent_id' ] );

    expect( $prints->refresh()->parent_id )->toBeNull();
} );

it( 'reorders categories within a parent', function (): void {
    $a = ProductCategory::factory()->create( [ 'name' => 'Alpha', 'position' => 0 ] );
    $b = ProductCategory::factory()->create( [ 'name' => 'Beta', 'position' => 1 ] );
    $c = ProductCategory::factory()->create( [ 'name' => 'Gamma', 'position' => 2 ] );

    Livewire::test( Index::class )
        ->call( 'move', $c->id, -1 )
        ->assertSeeInOrder( [ 'Alpha', 'Gamma', 'Beta' ] )
        ->call( 'move', $a->id, -1 )
        ->assertSeeInOrder( [ 'Alpha', 'Gamma', 'Beta' ] )
        ->call( 'move', $a->id, 1 )
        ->assertSeeInOrder( [ 'Gamma', 'Alpha', 'Beta' ] );

    expect( $b->refresh()->position )->toBe( 2 );
} );

it( 'deletes a leaf category and unlinks its products', function (): void {
    $mugs    = ProductCategory::factory()->create( [ 'name' => 'Mugs' ] );
    $product = Product::factory()->create();
    $product->categories()->attach( $mugs->id );

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $mugs->id );

    $component->assertSee( 'Delete "Mugs"?' )
        ->assertDontSeeHtml( 'data-children-choice' )
        ->call( 'delete', categoryDeleteToken( $component ) )
        ->assertSet( 'confirmingDelete', false );

    expect( ProductCategory::query()->find( $mugs->id ) )->toBeNull()
        ->and( Product::query()->find( $product->id ) )->not->toBeNull();
} );

it( 'moves subcategories up to the parent when deleting', function (): void {
    $root   = ProductCategory::factory()->create( [ 'name' => 'Art' ] );
    $prints = ProductCategory::factory()->create( [ 'name' => 'Prints', 'parent_id' => $root->id ] );
    $canvas = ProductCategory::factory()->create( [ 'name' => 'Canvas', 'parent_id' => $prints->id ] );

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $prints->id );

    $component->assertSeeHtml( 'data-children-choice' )
        ->assertSee( 'Up to "Art"' )
        ->call( 'delete', categoryDeleteToken( $component ) );

    expect( $canvas->refresh()->parent_id )->toBe( $root->id );
} );

it( 'moves subcategories under another category when chosen', function (): void {
    $prints = ProductCategory::factory()->create( [ 'name' => 'Prints' ] );
    $canvas = ProductCategory::factory()->create( [ 'name' => 'Canvas', 'parent_id' => $prints->id ] );
    $wall   = ProductCategory::factory()->create( [ 'name' => 'Wall art' ] );

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $prints->id );
    $token     = categoryDeleteToken( $component );

    $component->set( 'childrenTarget', 'other' )
        ->call( 'delete', $token )
        ->assertHasErrors( [ 'childrenTargetId' => 'required_if' ] )
        ->set( 'childrenTargetId', $canvas->id )
        ->call( 'delete', $token )
        ->assertHasErrors( [ 'childrenTargetId' ] )
        ->set( 'childrenTargetId', $wall->id )
        ->call( 'delete', $token )
        ->assertHasNoErrors();

    expect( ProductCategory::query()->find( $prints->id ) )->toBeNull()
        ->and( $canvas->refresh()->parent_id )->toBe( $wall->id );
} );

it( 'deletes only once per token', function (): void {
    $mugs = ProductCategory::factory()->create( [ 'name' => 'Mugs' ] );
    $cups = ProductCategory::factory()->create( [ 'name' => 'Cups' ] );

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $mugs->id );
    $token     = categoryDeleteToken( $component );
    $component->call( 'delete', $token );

    $component->call( 'confirmDelete', $cups->id )->call( 'delete', $token );

    expect( ProductCategory::query()->find( $cups->id ) )->not->toBeNull();
} );

it( 'takes the image from the media library', function (): void {
    Livewire::test( Index::class )
        ->call( 'create' )
        ->call( 'mediaSelected', [ [ 'id' => 42 ] ], 'other' )
        ->assertSet( 'form.image_media_id', null )
        ->call( 'mediaSelected', [ [ 'id' => 42 ] ], Index::MEDIA_CONTEXT )
        ->assertSet( 'form.image_media_id', 42 )
        ->call( 'clearImage' )
        ->assertSet( 'form.image_media_id', null );
} );

it( 'reports an engine refusal during delete and keeps everything in place', function (): void {
    $prints = ProductCategory::factory()->create( [ 'name' => 'Prints' ] );
    $canvas = ProductCategory::factory()->create( [ 'name' => 'Canvas', 'parent_id' => $prints->id ] );
    $wall   = ProductCategory::factory()->create( [ 'name' => 'Wall art' ] );

    app()->instance( ArtisanPackUI\Ecommerce\Services\ProductCategoryService::class, new class extends ArtisanPackUI\Ecommerce\Services\ProductCategoryService {
        public function delete( ProductCategory $category ): void
        {
            throw ArtisanPackUI\Ecommerce\Exceptions\ProductWriteException::field( 'id', 'refused', 'Refused by the engine.' );
        }
    } );

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $prints->id );

    $component->set( 'childrenTarget', 'other' )
        ->set( 'childrenTargetId', $wall->id )
        ->call( 'delete', categoryDeleteToken( $component ) )
        ->assertOk()
        ->assertSet( 'confirmingDelete', false );

    expect( sentToasts( $component ) )->toContain( 'Refused by the engine.' )
        ->and( ProductCategory::query()->find( $prints->id ) )->not->toBeNull()
        ->and( $canvas->refresh()->parent_id )->toBe( $prints->id );
} );
