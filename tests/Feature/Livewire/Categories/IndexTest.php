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

it( 'renders an ARIA tree with levels, positions and names from the row', function (): void {
    $parent = ProductCategory::query()->create( [ 'name' => 'Apparel', 'slug' => 'apparel', 'position' => 0 ] );
    $shirts = ProductCategory::query()->create( [ 'name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $parent->id, 'position' => 0 ] );
    ProductCategory::query()->create( [ 'name' => 'Hats', 'slug' => 'hats', 'parent_id' => $parent->id, 'position' => 1 ] );
    ProductCategory::query()->create( [ 'name' => 'Mugs', 'slug' => 'mugs', 'position' => 1 ] );

    Livewire::test( Index::class )
        ->assertSee( 'Group products so shoppers can browse them. Order here is the order on the storefront.' )
        ->assertSeeHtml( 'role="tree"' )
        ->assertSeeHtml( 'role="treeitem"' )
        ->assertSeeHtml( 'aria-level="2"' )
        ->assertSeeHtml( 'aria-posinset="2"' )
        ->assertSeeHtml( 'aria-setsize="2"' )
        ->assertSeeHtml( 'aria-labelledby="category-' . $parent->id . '-name category-' . $parent->id . '-children category-' . $parent->id . '-products"' )
        ->assertSeeHtml( 'aria-labelledby="category-' . $shirts->id . '-name category-' . $shirts->id . '-products"' )
        ->assertSeeHtml( 'data-subcategory-count="2"' )
        ->assertSeeHtml( 'x-bind:tabindex="isTabStop( ' . $parent->id . ', true ) ? 0 : -1"' )
        ->assertSeeHtml( 'x-bind:tabindex="isTabStop( ' . $shirts->id . ', false ) ? 0 : -1"' )
        ->assertSeeHtml( 'aria-label="Add a subcategory to Apparel"' )
        ->assertDontSee( 'Level 2' )
        ->assertDontSeeHtml( 'divide-base-300' );
} );

it( 'shows the empty state', function (): void {
    Livewire::test( Index::class )
        ->assertSee( 'No categories yet' )
        ->assertSeeHtml( 'data-empty-state' )
        ->assertDontSeeHtml( 'role="tree"' );
} );

it( 'leaves the empty state without its action when the user cannot create', function (): void {
    Gate::define( 'ecommerce.product.create', static fn (): bool => false );

    Livewire::test( Index::class )
        ->assertSee( 'No categories yet' )
        ->assertDontSeeHtml( 'wire:click="create"' );
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
        ->assertHasErrors( [ 'form.name' => 'required', 'form.parent_id' => 'exists' ] )
        ->assertSeeHtml( 'data-error-summary' )
        ->assertSee( 'Not saved. Fix the 2 highlighted fields.' );
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
        ->assertSeeInOrder( [ 'Up to the top level', 'Up to "Art"', 'Under another category' ] )
        ->assertSeeHtml( 'role="alertdialog"' )
        ->call( 'delete', categoryDeleteToken( $component ) );

    expect( $canvas->refresh()->parent_id )->toBe( $root->id );
} );

it( 'moves subcategories up to the top level when chosen for a nested category', function (): void {
    $root   = ProductCategory::factory()->create( [ 'name' => 'Art', 'position' => 0 ] );
    $prints = ProductCategory::factory()->create( [ 'name' => 'Prints', 'parent_id' => $root->id ] );
    $canvas = ProductCategory::factory()->create( [ 'name' => 'Canvas', 'parent_id' => $prints->id ] );
    ProductCategory::factory()->create( [ 'name' => 'Mugs', 'position' => 4 ] );

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $prints->id );

    $component->set( 'childrenTarget', 'top' )
        ->call( 'delete', categoryDeleteToken( $component ) )
        ->assertHasNoErrors();

    $canvas->refresh();

    expect( ProductCategory::query()->find( $prints->id ) )->toBeNull()
        ->and( $canvas->parent_id )->toBeNull()
        ->and( $canvas->position )->toBe( 5 );
} );

it( 'offers one top-level choice when the deleted category is already at the top', function (): void {
    $prints = ProductCategory::factory()->create( [ 'name' => 'Prints' ] );
    ProductCategory::factory()->create( [ 'name' => 'Canvas', 'parent_id' => $prints->id ] );

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $prints->id );

    $choices = $component->html();

    expect( substr_count( $choices, 'Up to the top level' ) )->toBe( 1 )
        ->and( $choices )->not->toContain( 'value="top"' );
} );

it( 'keeps the new parent select disabled until "Under another category" is chosen', function (): void {
    $prints = ProductCategory::factory()->create( [ 'name' => 'Prints' ] );
    ProductCategory::factory()->create( [ 'name' => 'Canvas', 'parent_id' => $prints->id ] );

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $prints->id );

    expect( $component->html() )->toMatch( '/<select[^>]*id="category-children-target-id"[^>]*disabled/s' );

    $component->set( 'childrenTarget', 'other' );

    expect( $component->html() )->not->toMatch( '/<select[^>]*id="category-children-target-id"[^>]*disabled/s' );
} );

it( 'rejects an unknown subcategory target', function (): void {
    $prints = ProductCategory::factory()->create( [ 'name' => 'Prints' ] );
    ProductCategory::factory()->create( [ 'name' => 'Canvas', 'parent_id' => $prints->id ] );

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $prints->id );

    $component->set( 'childrenTarget', 'nowhere' )
        ->call( 'delete', categoryDeleteToken( $component ) )
        ->assertHasErrors( [ 'childrenTarget' ] );

    expect( ProductCategory::query()->find( $prints->id ) )->not->toBeNull();
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

it( 'appends several subcategories moved to the top level in their order', function (): void {
    $root   = ProductCategory::factory()->create( [ 'name' => 'Art', 'position' => 0 ] );
    $prints = ProductCategory::factory()->create( [ 'name' => 'Prints', 'parent_id' => $root->id ] );
    $second = ProductCategory::factory()->create( [ 'name' => 'Posters', 'parent_id' => $prints->id, 'position' => 1 ] );
    $first  = ProductCategory::factory()->create( [ 'name' => 'Canvas', 'parent_id' => $prints->id, 'position' => 0 ] );
    ProductCategory::factory()->create( [ 'name' => 'Mugs', 'position' => 4 ] );

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $prints->id );

    $component->set( 'childrenTarget', 'top' )
        ->call( 'delete', categoryDeleteToken( $component ) )
        ->assertHasNoErrors();

    expect( [ $first->refresh()->parent_id, $first->position ] )->toBe( [ null, 5 ] )
        ->and( [ $second->refresh()->parent_id, $second->position ] )->toBe( [ null, 6 ] );
} );

it( 'marks rows with subcategories as expanded', function (): void {
    $root = ProductCategory::factory()->create( [ 'name' => 'Art' ] );
    $leaf = ProductCategory::factory()->create( [ 'name' => 'Prints', 'parent_id' => $root->id ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )->toMatch( '/data-category="' . $root->id . '"/' )
        ->and( preg_match( '/<li[^>]*aria-expanded="true"[^>]*data-category="' . $root->id . '"/s', $html ) )->toBe( 1 )
        ->and( preg_match( '/<li[^>]*aria-expanded[^>]*data-category="' . $leaf->id . '"/s', $html ) )->toBe( 0 );
} );

it( 'moves focus to a neighbour after a delete', function ( string $deleted, ?string $focused ): void {
    $art    = ProductCategory::factory()->create( [ 'name' => 'Art', 'position' => 0 ] );
    $mugs   = ProductCategory::factory()->create( [ 'name' => 'Mugs', 'position' => 1 ] );
    $prints = ProductCategory::factory()->create( [ 'name' => 'Prints', 'parent_id' => $art->id, 'position' => 0 ] );
    $ids    = [ 'Art' => $art->id, 'Mugs' => $mugs->id, 'Prints' => $prints->id ];

    if ( 'Lone' === $deleted ) {
        ProductCategory::query()->whereKey( [ $art->id, $mugs->id, $prints->id ] )->delete();
        $ids['Lone'] = ProductCategory::factory()->create( [ 'name' => 'Lone' ] )->id;
    }

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $ids[ $deleted ] );

    $component->call( 'delete', categoryDeleteToken( $component ) )
        ->assertDispatched( 'ecommerce-admin-category-deleted', focus: null === $focused ? null : $ids[ $focused ] );
} )->with( [
    'the sibling before it'         => [ 'Mugs', 'Art' ],
    'its parent, when it is first'  => [ 'Prints', 'Art' ],
    'the sibling after it'          => [ 'Art', 'Mugs' ],
    'nothing when the tree empties' => [ 'Lone', null ],
] );
