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
use ArtisanPackUI\EcommerceAdminLivewire\Support\DigitalDisks;
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
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'product.update', 'digitalFile.create', 'digitalFile.update', 'digitalFile.delete' ] );
    ProductMedia::fake( false );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    ProductMedia::fake( null );
    DigitalDisks::fakeMediaDisks( null );
    DigitalDisks::fakeViewableMedia( null );
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
        ->assertSet( 'state.files.0.source', 'path' )
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

it( 'keeps the meta keys it doesn\'t manage, such as licensing types', function (): void {
    $product = Product::factory()->digital()->create( [ 'meta' => [
        'licensing' => [ 'enabled' => true, 'activations_limit' => 3, 'types' => [ 'personal', 'team' ] ],
        'digital'   => [ 'download_limit' => 5, 'foo' => 1 ],
        'other'     => 'kept',
    ] ] );

    $state                      = DigitalPanel::initialState( $product );
    $state['activations_limit'] = '';
    $state['download_limit']    = '9';

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $product->refresh()->meta['licensing'] )->toBe( [ 'enabled' => true, 'types' => [ 'personal', 'team' ] ] )
        ->and( $product->meta['digital'] )->toBe( [ 'download_limit' => 9, 'foo' => 1 ] )
        ->and( $product->meta['other'] )->toBe( 'kept' );
} );

it( 'does not let a saved file\'s version be cleared, which would email buyers', function (): void {
    Event::fake( [ DigitalProductUpdated::class ] );

    $product = Product::factory()->digital()->create();
    $file    = DigitalFile::factory()->create( [ 'product_id' => $product->id, 'label' => 'Guide', 'version' => '1.0', 'disk' => 'local', 'path' => 'a.pdf' ] );

    $state                          = DigitalPanel::initialState( $product );
    $state['files'][0]['version']   = '';

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasErrors( 'panelState.files.0.version' );

    expect( $file->refresh()->version )->toBe( '1.0' );
    Event::assertNotDispatched( DigitalProductUpdated::class );
} );

it( 'refuses a media-library file on a public disk in the panel', function (): void {
    DigitalDisks::fakeMediaDisks( [ 7 => 'public' ] );

    Livewire::test( Form::class )
        ->set( 'name', 'Field Guide' )
        ->set( 'type', 'digital' )
        ->set( 'panelState', [ 'files' => [ digitalRow( [ 'source' => 'media', 'media_id' => 7, 'disk' => null, 'path' => '' ] ) ], 'download_limit' => '', 'download_expiry_days' => '', 'licensing_enabled' => false, 'activations_limit' => '', 'license_expires_in_days' => '' ] )
        ->call( 'save' )
        ->assertHasErrors( 'panelState.files.0.media_id' );

    expect( DigitalFile::query()->count() )->toBe( 0 );
} );

it( 'builds the digital panel from design-system parts with no DaisyUI classes', function (): void {
    $html = Livewire::test( DigitalPanel::class )
        ->set( 'state', [ ...DigitalPanel::initialState( null ), 'files' => [ digitalRow() ], 'licensing_enabled' => true ] )
        ->html();

    expect( $html )
        ->toContain( 'class="apui-card' )
        ->toContain( 'class="ec-item ec-file"' )
        ->toContain( 'class="apui-divider' )
        ->toContain( 'Label of file 1' )
        ->toContain( 'Version of Field guide (PDF)' )
        ->toContain( 'Path of Field guide (PDF) on the disk' )
        ->toContain( 'aria-label="Remove Field guide (PDF)"' )
        ->toContain( 'Can be played but never downloaded.' )
        ->toContain( 'data-licensing-fields' )
        ->not->toContain( 'rounded-box' )
        ->not->toContain( 'border-base-300' )
        ->not->toContain( 'opacity-75' )
        ->not->toContain( 'text-error' );
} );

it( 'shows the license fields only while license keys are on', function (): void {
    Livewire::test( DigitalPanel::class )
        ->set( 'state', DigitalPanel::initialState( null ) )
        ->assertSee( 'Attach the files buyers can download or stream.' )
        ->assertDontSeeHtml( 'data-licensing-fields' )
        ->assertDontSee( 'Activations per key' )
        ->set( 'state.licensing_enabled', true )
        ->assertSeeHtml( 'data-licensing-fields' )
        ->assertSee( 'Activations per key' );
} );

it( 'shows a media-library file as a chip with a replace button', function (): void {
    ProductMedia::fake( true );

    Livewire::test( DigitalPanel::class )
        ->set( 'state', [ ...DigitalPanel::initialState( null ), 'files' => [ digitalRow( [ 'source' => 'media', 'media_id' => 418 ] ) ] ] )
        ->assertSeeHtml( 'class="ec-file-chip"' )
        ->assertSee( 'Media library file #418' )
        ->assertSee( 'Replace file' )
        ->assertSeeHtml( 'aria-label="Replace file for Field guide (PDF)"' )
        ->assertDontSee( 'Path of Field guide (PDF) on the disk' );

    Livewire::test( DigitalPanel::class )
        ->set( 'state', [ ...DigitalPanel::initialState( null ), 'files' => [ digitalRow( [ 'source' => 'media', 'media_id' => null ] ) ] ] )
        ->assertSee( 'No file chosen yet.' )
        ->assertSee( 'Choose file' );
} );

it( 'hides the digital panel actions when read-only', function (): void {
    $product = Product::factory()->create( [ 'type' => 'digital' ] );

    Livewire::test( DigitalPanel::class, [ 'productId' => $product->id, 'readOnly' => true ] )
        ->set( 'state', [ ...DigitalPanel::initialState( null ), 'files' => [ digitalRow() ] ] )
        ->assertDontSee( 'Add file' )
        ->assertDontSeeHtml( 'aria-label="Remove Field guide (PDF)"' );
} );

it( 'builds the children panel from design-system parts and names each row for its product', function (): void {
    $sweater = Product::factory()->variable()->create( [ 'name' => 'Merino crew sweater' ] );
    $socks   = Product::factory()->create( [ 'name' => 'Wool socks, 3-pack' ] );
    ProductVariant::factory()->create( [ 'product_id' => $sweater->id, 'name' => 'Navy M' ] );

    $html = Livewire::test( ChildrenPanel::class )
        ->set( 'state', [ 'children' => [
            [ 'uid' => 'a', 'product_id' => $sweater->id, 'variant_id' => '', 'quantity' => 1 ],
            [ 'uid' => 'b', 'product_id' => $socks->id, 'variant_id' => '', 'quantity' => 2 ],
        ] ] )
        ->html();

    expect( $html )
        ->toContain( 'class="apui-card' )
        ->toContain( 'aria-label="Products included, in order"' )
        ->toContain( 'class="ec-item"' )
        ->toContain( 'Product 1' )
        ->toContain( 'Product 2' )
        ->toContain( 'Shopper chooses' )
        ->toContain( 'aria-label="Variant of Merino crew sweater"' )
        ->toContain( 'aria-label="Quantity of Wool socks, 3-pack"' )
        ->toContain( 'aria-label="Move Merino crew sweater down"' )
        ->toContain( 'aria-label="Remove Wool socks, 3-pack"' )
        ->not->toContain( 'rounded-box' )
        ->not->toContain( 'border-base-300' )
        ->not->toContain( 'opacity-75' );

    // A product without variants says so instead of showing a dead select.
    expect( Illuminate\Support\Str::betweenFirst( $html, 'data-child-row="1"', '</li>' ) )->toContain( 'No variants' )->not->toContain( 'Shopper chooses' );
} );

it( 'shows the children error as an alert and hides the actions when read-only', function (): void {
    $bundle = Product::factory()->bundled()->create();
    $mug    = Product::factory()->create( [ 'name' => 'Mug' ] );

    Livewire::test( ChildrenPanel::class, [ 'productId' => $bundle->id, 'readOnly' => true, 'panelErrors' => [ 'children' => 'A bundle needs at least two products.' ] ] )
        ->set( 'state', [ 'children' => [ [ 'uid' => 'a', 'product_id' => $mug->id, 'variant_id' => '', 'quantity' => 1 ] ] ] )
        ->assertSeeHtml( 'class="ec-field-error" role="alert"' )
        ->assertSee( 'A bundle needs at least two products.' )
        ->assertDontSee( 'Add product' )
        ->assertDontSeeHtml( 'aria-label="Remove Mug"' );
} );

/**
 * Digital panel state with the given file rows.
 *
 * @return array<string, mixed>
 */
function digitalState( array $files ): array
{
    return [ 'files' => $files, 'download_limit' => '', 'download_expiry_days' => '', 'licensing_enabled' => false, 'activations_limit' => '', 'license_expires_in_days' => '' ];
}

it( 'refuses a media item the user may not view, but keeps one already saved on the file', function (): void {
    DigitalDisks::fakeMediaDisks( [ 7 => 'local', 8 => 'local' ] );
    DigitalDisks::fakeViewableMedia( [] );

    Livewire::test( Form::class )
        ->set( 'name', 'Field Guide' )
        ->set( 'type', 'digital' )
        ->set( 'panelState', digitalState( [ digitalRow( [ 'source' => 'media', 'media_id' => 7, 'disk' => null, 'path' => '' ] ) ] ) )
        ->call( 'save' )
        ->assertHasErrors( 'panelState.files.0.media_id' );

    expect( DigitalFile::query()->count() )->toBe( 0 );

    // Media attached earlier (by someone who could see it) stays valid.
    $product = Product::factory()->digital()->create();
    $file    = DigitalFile::factory()->create( [ 'product_id' => $product->id, 'label' => 'Guide', 'media_id' => 8, 'disk' => null, 'path' => null ] );
    $state   = DigitalPanel::initialState( $product );

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasNoErrors();

    // ...but its id can't vouch for another file's row.
    $state['files'][] = digitalRow( [ 'label' => 'Copy', 'source' => 'media', 'media_id' => 8, 'disk' => null, 'path' => '' ] );

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasErrors( 'panelState.files.1.media_id' );

    expect( DigitalFile::query()->where( 'product_id', $product->id )->count() )->toBe( 1 )
        ->and( $file->refresh()->media_id )->toBe( 8 );
} );

it( 'needs digitalFile abilities to add, change, or remove files in the panel', function (): void {
    Gate::define( 'ecommerce.digitalFile.create', static fn (): bool => false );

    Livewire::test( Form::class )
        ->set( 'name', 'Field Guide' )
        ->set( 'type', 'digital' )
        ->set( 'panelState', digitalState( [ digitalRow() ] ) )
        ->call( 'save' )
        ->assertHasErrors( 'panelState.files' );

    expect( Product::query()->count() )->toBe( 0 )
        ->and( DigitalFile::query()->count() )->toBe( 0 );

    $product = Product::factory()->digital()->create();
    $file    = DigitalFile::factory()->create( [ 'product_id' => $product->id, 'label' => 'Guide', 'disk' => 'local', 'path' => 'a.pdf' ] );

    Gate::define( 'ecommerce.digitalFile.update', static fn (): bool => false );
    Gate::define( 'ecommerce.digitalFile.delete', static fn (): bool => false );

    // Untouched files save without digitalFile.update.
    $state                   = DigitalPanel::initialState( $product );
    $state['download_limit'] = '4';

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasNoErrors();

    $state['files'][0]['label'] = 'Renamed';

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasErrors( 'panelState.files' );

    expect( $file->refresh()->label )->toBe( 'Guide' );

    $state['files'] = [];

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'panelState', $state )
        ->call( 'save' )
        ->assertHasErrors( 'panelState.files' );

    expect( DigitalFile::query()->whereKey( $file->id )->exists() )->toBeTrue();
} );

it( 'builds every child row\'s picker from one product page and one lookup of the selected products', function (): void {
    $items    = Product::factory()->count( 12 )->create();
    $children = $items->map( static fn ( Product $item ): array => [ 'product_id' => $item->id, 'variant_id' => '', 'quantity' => 1 ] )->all();

    $component = Livewire::test( ChildrenPanel::class )->set( 'state', [ 'children' => $children ] );

    Illuminate\Support\Facades\DB::enableQueryLog();
    Illuminate\Support\Facades\DB::flushQueryLog();

    $component->call( '$refresh' );

    $options = $component->viewData( 'childPickerOptions' );

    expect( count( Illuminate\Support\Facades\DB::getQueryLog() ) )->toBeLessThan( 8 )
        ->and( $options )->toHaveCount( 12 )
        ->and( collect( $options[11] )->pluck( 'id' )->map( 'intval' )->first() )->toBe( (int) $items[11]->id );
} );
