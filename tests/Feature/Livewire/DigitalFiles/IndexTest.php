<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Events\DigitalProductUpdated;
use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\DigitalFiles\Index;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    ProductMedia::fake( false );
    config()->set( 'artisanpack.ecommerce.digital.allowed_disks', [ 'local', 's3-private' ] );
    grantAbilities( [ 'digitalFile.viewAny', 'digitalFile.create', 'digitalFile.update', 'digitalFile.delete', 'product.viewAny' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    ProductMedia::fake( null );
} );

it( 'lists files with product, label, version, location, and streaming flag', function (): void {
    $book = Product::factory()->digital()->create( [ 'name' => 'Cookbook' ] );
    DigitalFile::factory()->create( [ 'product_id' => $book->id, 'label' => 'Cookbook PDF', 'version' => '1.2', 'disk' => 'local', 'path' => 'books/cook.pdf' ] );
    DigitalFile::factory()->streamingOnly()->create( [ 'label' => 'Video course', 'media_id' => 12, 'path' => null, 'disk' => null ] );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeHtml( '<caption class="sr-only">Digital files</caption>' )
        ->assertSee( 'Cookbook' )
        ->assertSee( 'Cookbook PDF' )
        ->assertSee( '1.2' )
        ->assertSee( 'local:books/cook.pdf' )
        ->assertSee( 'Media library file #12' );
} );

it( 'is denied without digitalFile.viewAny', function (): void {
    Gate::define( 'ecommerce.digitalFile.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'filters by product and searches by label', function (): void {
    $book = Product::factory()->digital()->create( [ 'name' => 'Cookbook' ] );
    DigitalFile::factory()->create( [ 'product_id' => $book->id, 'label' => 'Cookbook PDF' ] );
    DigitalFile::factory()->create( [ 'label' => 'Other file' ] );

    Livewire::test( Index::class )
        ->set( 'filters.product', (string) $book->id )->assertSee( 'Cookbook PDF' )->assertDontSee( 'Other file' )
        ->set( 'filters', [] )->set( 'search', 'other' )->assertSee( 'Other file' )->assertDontSee( 'Cookbook PDF' );
} );

it( 'creates a file on a disk path', function (): void {
    $book = Product::factory()->digital()->create();

    Livewire::test( Index::class )
        ->call( 'create' )
        ->assertSet( 'editing', true )
        ->set( 'form.product_id', $book->id )
        ->set( 'form.label', 'E-book' )
        ->set( 'form.version', '1.0' )
        ->set( 'form.disk', 's3-private' )
        ->set( 'form.path', 'books/ebook.epub' )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'editing', false );

    $file = DigitalFile::query()->firstOrFail();

    expect( $file )
        ->product_id->toBe( $book->id )
        ->label->toBe( 'E-book' )
        ->disk->toBe( 's3-private' )
        ->path->toBe( 'books/ebook.epub' );
} );

it( 'attaches a file to a variant', function (): void {
    $course  = Product::factory()->variable()->create();
    $variant = ProductVariant::factory()->create( [ 'product_id' => $course->id ] );

    Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.product_id', $course->id )
        ->assertSeeHtml( 'digital-file-variant' )
        ->set( 'form.product_variant_id', $variant->id )
        ->set( 'form.label', 'Workbook' )
        ->set( 'form.source', 'media' )
        ->set( 'form.media_id', 9 )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( DigitalFile::query()->firstOrFail() )
        ->product_id->toBeNull()
        ->product_variant_id->toBe( $variant->id )
        ->media_id->toBe( 9 )
        ->path->toBeNull();
} );

it( 'validates the drawer', function (): void {
    $other   = Product::factory()->variable()->create();
    $variant = ProductVariant::factory()->create( [ 'product_id' => $other->id ] );
    $book    = Product::factory()->digital()->create();

    Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.source', 'path' )
        ->set( 'form.path', '../etc/passwd' )
        ->set( 'form.disk', 'public' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.product_id' => 'required', 'form.label' => 'required', 'form.path', 'form.disk' => 'in' ] )
        ->set( 'form.product_id', $book->id )
        ->set( 'form.product_variant_id', $variant->id )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.product_variant_id' => 'exists' ] );

    expect( DigitalFile::query()->count() )->toBe( 0 );
} );

it( 'warns before a version bump and notifies customers through the service', function (): void {
    Event::fake( [ DigitalProductUpdated::class ] );
    $file = DigitalFile::factory()->create( [ 'version' => '1.0' ] );

    Livewire::test( Index::class )
        ->call( 'edit', $file->id )
        ->assertDontSeeHtml( 'data-version-warning' )
        ->set( 'form.label', 'Renamed' )
        ->assertDontSeeHtml( 'data-version-warning' )
        ->set( 'form.version', '2.0' )
        ->assertSeeHtml( 'data-version-warning' )
        ->assertSee( 'Save and notify customers' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $file->refresh() )->version->toBe( '2.0' )->label->toBe( 'Renamed' );
    Event::assertDispatched( DigitalProductUpdated::class );
} );

it( 'does not notify when the version is unchanged', function (): void {
    Event::fake( [ DigitalProductUpdated::class ] );
    $file = DigitalFile::factory()->create( [ 'version' => '1.0' ] );

    Livewire::test( Index::class )
        ->call( 'edit', $file->id )
        ->set( 'form.label', 'Renamed' )
        ->call( 'save' );

    Event::assertNotDispatched( DigitalProductUpdated::class );
} );

it( 'refuses to create or edit without the abilities', function (): void {
    Gate::define( 'ecommerce.digitalFile.create', static fn (): bool => false );
    Gate::define( 'ecommerce.digitalFile.update', static fn (): bool => false );
    $file = DigitalFile::factory()->create();

    Livewire::test( Index::class )->call( 'create' )->assertForbidden();
    Livewire::test( Index::class )->call( 'edit', $file->id )->assertForbidden();
} );

it( 'takes the file from the media library', function (): void {
    Livewire::test( Index::class )
        ->call( 'create' )
        ->call( 'mediaSelected', [ [ 'id' => 5, 'title' => 'Guide' ] ], Index::MEDIA_CONTEXT )
        ->assertSet( 'form.source', 'media' )
        ->assertSet( 'form.media_id', 5 )
        ->assertSet( 'form.label', 'Guide' );
} );

it( 'deletes a file after confirmation', function (): void {
    $file = DigitalFile::factory()->create();

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $file->id );
    $component->call( 'confirmBulkAction', $component->viewData( 'tableConfirmToken' ) );

    expect( DigitalFile::query()->find( $file->id ) )->toBeNull();
} );

it( 'does not let a saved version be cleared, which would notify customers', function (): void {
    Event::fake( [ DigitalProductUpdated::class ] );
    $file = DigitalFile::factory()->create( [ 'version' => '1.0' ] );

    Livewire::test( Index::class )
        ->call( 'edit', $file->id )
        ->set( 'form.version', '' )
        ->assertDontSeeHtml( 'data-version-warning' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.version' => 'required' ] );

    expect( $file->refresh()->version )->toBe( '1.0' );
    Event::assertNotDispatched( DigitalProductUpdated::class );
} );
