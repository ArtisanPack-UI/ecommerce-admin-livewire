<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Events\DigitalProductUpdated;
use ArtisanPackUI\Ecommerce\Models\DigitalDownload;
use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductChild;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Services\ProductService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Form;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\ChildrenPanel;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\DigitalPanel;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Fixtures\User;

beforeEach( function (): void {
    config()->set( 'auth.providers.users.model', User::class );
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    config()->set( 'artisanpack.ecommerce.features.scout', false );
    config()->set( 'artisanpack.ecommerce.digital.allowed_disks', [ 'local' ] );
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'product.update' ] );
    ProductMedia::fake( false );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    ProductMedia::fake( null );
} );

/**
 * A digital file row in panel state.
 *
 * @return array<string, mixed>
 */
function digitalRow( array $overrides = [] ): array
{
    return $overrides + [
        'id'                => null,
        'label'             => 'Field guide (PDF)',
        'version'           => '1.0',
        'is_streaming_only' => false,
        'source'            => 'path',
        'disk'              => 'local',
        'path'              => 'downloads/guide.pdf',
        'media_id'          => null,
    ];
}

it( 'renders the digital panel and is denied without the ability', function (): void {
    Livewire::test( DigitalPanel::class )
        ->set( 'state', DigitalPanel::initialState( null ) )
        ->assertOk()
        ->assertSee( 'Files' )
        ->assertSee( 'Downloads per purchase' )
        ->assertSee( 'Issue a license key with each purchase' );

    Gate::define( 'ecommerce.product.create', static fn (): bool => false );

    Livewire::test( DigitalPanel::class )->assertForbidden();
} );

it( 'creates a digital product with a file, 3 downloads in 30 days, and license keys', function (): void {
    Livewire::test( Form::class )
        ->set( 'name', 'Field Guide' )
        ->set( 'type', 'digital' )
        ->assertSet( 'panelState.files', [] )
        ->set( 'panelState', [
            'files'                   => [ digitalRow() ],
            'download_limit'          => '3',
            'download_expiry_days'    => '30',
            'licensing_enabled'       => true,
            'activations_limit'       => '2',
            'license_expires_in_days' => '',
        ] )
        ->call( 'save' )
        ->assertHasNoErrors();

    $product = Product::query()->sole();
    $file    = DigitalFile::query()->sole();

    expect( $product->type )->toBe( 'digital' )
        ->and( $product->meta['digital'] )->toBe( [ 'download_limit' => 3, 'download_expiry_days' => 30 ] )
        ->and( $product->meta['licensing'] )->toBe( [ 'enabled' => true, 'activations_limit' => 2 ] )
        ->and( $file->product_id )->toBe( $product->id )
        ->and( $file->label )->toBe( 'Field guide (PDF)' )
        ->and( $file->path )->toBe( 'downloads/guide.pdf' );
} );

it( 'loads, updates, and removes files, announcing a new version', function (): void {
    Event::fake( [ DigitalProductUpdated::class ] );

    $product = Product::factory()->digital()->create( [ 'meta' => [ 'digital' => [ 'download_limit' => 0 ] ] ] );
    $keep    = DigitalFile::factory()->create( [ 'product_id' => $product->id, 'label' => 'Guide', 'version' => '1.0', 'disk' => 'local', 'path' => 'a.pdf' ] );
    $drop    = DigitalFile::factory()->create( [ 'product_id' => $product->id, 'label' => 'Old', 'disk' => 'local', 'path' => 'b.pdf' ] );

    $state = DigitalPanel::initialState( $product );

    expect( $state['files'] )->toHaveCount( 2 )
        ->and( $state['download_limit'] )->toBe( '0' )
        ->and( $state['download_expiry_days'] )->toBe( '' );

    $state['files']                = [ array_merge( $state['files'][0], [ 'version' => '2.0', 'is_streaming_only' => true ] ) ];
    $state['download_expiry_days'] = '7';

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->assertSee( 'Downloads' )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( DigitalFile::query()->pluck( 'id' )->all() )->toBe( [ $keep->id ] )
        ->and( $keep->refresh()->version )->toBe( '2.0' )
        ->and( $keep->is_streaming_only )->toBeTrue()
        ->and( $product->refresh()->meta['digital'] )->toBe( [ 'download_limit' => 0, 'download_expiry_days' => 7 ] );

    Event::assertDispatched( DigitalProductUpdated::class );
} );

it( 'keeps a purchased file when it is removed, and archives it instead', function (): void {
    $product = Product::factory()->digital()->create();
    $file    = DigitalFile::factory()->create( [ 'product_id' => $product->id, 'label' => 'Guide', 'disk' => 'local', 'path' => 'a.pdf' ] );
    DigitalDownload::factory()->create( [ 'digital_file_id' => $file->id ] );

    $state          = DigitalPanel::initialState( $product );
    $state['files'] = [];

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasErrors( 'panelState.files' );

    expect( $file->refresh()->exists )->toBeTrue();

    $state = DigitalPanel::initialState( $product );

    expect( $state['files'][0]['is_archived'] )->toBeFalse();

    $state['files'][0]['is_archived'] = true;

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $file->refresh()->archived_at )->not->toBeNull()
        ->and( DigitalPanel::initialState( $product )['files'][0]['is_archived'] )->toBeTrue();
} );

it( 'validates digital files and limits', function ( array $file, array $extra, string $error ): void {
    Livewire::test( Form::class )
        ->set( 'name', 'Guide' )
        ->set( 'type', 'digital' )
        ->set( 'panelState', $extra + [
            'files'                   => [ digitalRow( $file ) ],
            'download_limit'          => '',
            'download_expiry_days'    => '',
            'licensing_enabled'       => false,
            'activations_limit'       => '',
            'license_expires_in_days' => '',
        ] )
        ->call( 'save' )
        ->assertHasErrors( $error )
        ->assertSet( 'tab', 'panel' );

    expect( Product::query()->count() )->toBe( 0 );
} )->with( [
    'missing label'      => [ [ 'label' => '' ], [], 'panelState.files.0.label' ],
    'parent traversal'   => [ [ 'path' => '../.env' ], [], 'panelState.files.0.path' ],
    'absolute path'      => [ [ 'path' => '/etc/passwd' ], [], 'panelState.files.0.path' ],
    'disallowed disk'    => [ [ 'disk' => 'public' ], [], 'panelState.files.0.disk' ],
    'media without file' => [ [ 'source' => 'media', 'media_id' => null ], [], 'panelState.files.0.media_id' ],
    'negative limit'     => [ [], [ 'download_limit' => '-1' ], 'panelState.download_limit' ],
] );

it( 'adds and removes file rows and takes media-library files', function (): void {
    ProductMedia::fake( true );

    Livewire::test( DigitalPanel::class )
        ->set( 'state', DigitalPanel::initialState( null ) )
        ->call( 'addFile' )
        ->assertSet( 'state.files.0.source', 'media' )
        ->call( 'mediaSelected', [ [ 'id' => 12, 'title' => 'Guide.pdf' ] ], DigitalPanel::MEDIA_CONTEXT . '0' )
        ->assertSet( 'state.files.0.media_id', 12 )
        ->assertSet( 'state.files.0.label', 'Guide.pdf' )
        ->call( 'removeFile', 0 )
        ->assertSet( 'state.files', [] );
} );

it( 'renders the children panel for grouped and bundled products', function (): void {
    $bundle = Product::factory()->bundled()->create();

    Livewire::test( ChildrenPanel::class, [ 'productId' => $bundle->id ] )
        ->set( 'state', ChildrenPanel::initialState( $bundle ) )
        ->assertOk()
        ->assertSee( 'No products added yet.' );

    Livewire::test( Form::class, [ 'product' => $bundle->id ] )->assertSee( 'Products included' );
} );

it( 'creates a starter-kit bundle of four products in order with quantities', function (): void {
    $items = Product::factory()->count( 4 )->create();
    $tee   = Product::factory()->variable()->create();
    $large = ProductVariant::factory()->create( [ 'product_id' => $tee->id, 'name' => 'Large' ] );

    $children   = $items->map( static fn ( Product $item, int $i ): array => [ 'product_id' => $item->id, 'variant_id' => '', 'quantity' => $i + 1 ] )->all();
    $children[] = [ 'product_id' => $tee->id, 'variant_id' => (string) $large->id, 'quantity' => 1 ];

    Livewire::test( Form::class )
        ->set( 'name', 'Starter kit' )
        ->set( 'type', 'bundled' )
        ->set( 'panelState', [ 'children' => $children ] )
        ->call( 'save' )
        ->assertHasNoErrors();

    $bundle = Product::query()->where( 'name', 'Starter kit' )->sole();

    expect( $bundle->children()->pluck( 'child_product_id' )->all() )->toBe( [ ...$items->pluck( 'id' )->all(), $tee->id ] )
        ->and( $bundle->children()->pluck( 'quantity' )->all() )->toBe( [ 1, 2, 3, 4, 1 ] )
        ->and( $bundle->children()->get()->last()->child_variant_id )->toBe( $large->id );
} );

it( 'lists a product\'s variants, clears the variant when the product changes, and reorders rows', function (): void {
    $tee   = Product::factory()->variable()->create();
    $mug   = Product::factory()->create();
    $small = ProductVariant::factory()->create( [ 'product_id' => $tee->id, 'name' => 'Small' ] );

    Livewire::test( ChildrenPanel::class )
        ->set( 'state', [ 'children' => [] ] )
        ->call( 'addChild' )
        ->call( 'addChild' )
        ->set( 'state.children.0.product_id', $tee->id )
        ->assertSee( 'Small' )
        ->set( 'state.children.0.variant_id', (string) $small->id )
        ->set( 'state.children.0.product_id', $mug->id )
        ->assertSet( 'state.children.0.variant_id', '' )
        ->set( 'state.children.1.product_id', $tee->id )
        ->call( 'moveChild', 1, -1 )
        ->assertSet( 'state.children.0.product_id', $tee->id )
        ->call( 'removeChild', 1 )
        ->assertCount( 'state.children', 1 );
} );

it( 'refuses a product that contains itself, a duplicate row, or a loop', function (): void {
    $products = app( ProductService::class );
    $inner    = $products->create( [ 'type' => 'bundled', 'name' => 'Inner kit' ] );
    $outer    = $products->create( [ 'type' => 'grouped', 'name' => 'Everything', 'children' => [ [ 'product_id' => $inner->id ] ] ] );
    $mug      = Product::factory()->create();

    $save = fn ( array $children ) => Livewire::test( Form::class, [ 'product' => $inner->id ] )
        ->set( 'panelState', [ 'children' => $children ] )
        ->call( 'save' );

    $save( [ [ 'product_id' => $inner->id, 'variant_id' => '', 'quantity' => 1 ] ] )
        ->assertHasErrors( 'panelState.children.0.product_id' )
        ->assertSet( 'tab', 'panel' );

    $save( [ [ 'product_id' => $mug->id, 'variant_id' => '', 'quantity' => 1 ], [ 'product_id' => $mug->id, 'variant_id' => '', 'quantity' => 2 ] ] )
        ->assertHasErrors( 'panelState.children' );

    $save( [ [ 'product_id' => $outer->id, 'variant_id' => '', 'quantity' => 1 ] ] )
        ->assertHasErrors( 'panelState.children.0.product_id' );

    $save( [ [ 'product_id' => $mug->id, 'variant_id' => '', 'quantity' => 0 ] ] )
        ->assertHasErrors( 'panelState.children.0.quantity' );

    expect( ProductChild::query()->where( 'parent_product_id', $inner->id )->count() )->toBe( 0 );
} );

it( 'searches products for the child picker', function (): void {
    Product::factory()->create( [ 'name' => 'Ceramic Mug' ] );

    $component = Livewire::test( ChildrenPanel::class )
        ->set( 'state', [ 'children' => [ [ 'product_id' => null, 'variant_id' => '', 'quantity' => 1 ] ] ] )
        ->call( 'searchPicker', 'ceramic', 'product', 'state.children.0.product_id' );

    expect( collect( $component->get( 'pickerOptions' )['product:state.children.0.product_id'] )->pluck( 'name' )->all() )->toBe( [ 'Ceramic Mug' ] );
} );

it( 'leaves another product\'s digital files alone when their ids are sent', function (): void {
    $other   = Product::factory()->digital()->create();
    $foreign = DigitalFile::factory()->create( [ 'product_id' => $other->id, 'label' => 'Theirs', 'disk' => 'local', 'path' => 'x.pdf' ] );
    $product = Product::factory()->digital()->create();

    $state          = DigitalPanel::initialState( $product );
    $state['files'] = [ digitalRow( [ 'id' => $foreign->id, 'label' => 'Hijacked' ] ) ];

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $foreign->refresh()->label )->toBe( 'Theirs' )
        ->and( DigitalFile::query()->where( 'product_id', $product->id )->value( 'label' ) )->toBe( 'Hijacked' );
} );
