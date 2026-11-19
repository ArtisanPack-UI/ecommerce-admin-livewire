<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\DigitalDownload;
use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\DigitalFiles\Index;
use ArtisanPackUI\EcommerceAdminLivewire\Support\DigitalDisks;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
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
    DigitalDisks::fakeMediaDisks( null );
} );

it( 'shows one status badge per file: offered, streaming only or archived', function (): void {
    DigitalFile::factory()->create( [ 'label' => 'Offered file' ] );
    DigitalFile::factory()->streamingOnly()->create( [ 'label' => 'Streamed file' ] );
    DigitalFile::factory()->streamingOnly()->create( [ 'label' => 'Old file', 'archived_at' => now() ] );

    $html = Livewire::test( Index::class )->html();

    expect( substr_count( $html, 'data-file-status="offered"' ) )->toBe( 1 )
        ->and( substr_count( $html, 'data-file-status="streaming"' ) )->toBe( 1 )
        ->and( substr_count( $html, 'data-file-status="archived"' ) )->toBe( 1 );
} );

it( 'draws the board: description, search hint, one status column and no DaisyUI classes', function (): void {
    DigitalFile::factory()->create( [ 'label' => 'Guide', 'version' => '2.1', 'disk' => 'local', 'path' => 'books/guide.pdf' ] );

    $component = Livewire::test( Index::class )
        ->assertSee( 'Every file attached to a digital product, with its version and where it’s stored.' )
        ->assertSeeHtml( 'placeholder="Search by label or product"' )
        ->assertSeeHtml( '<bdi dir="ltr" class="ec-mono">2.1</bdi>' )
        ->assertSeeHtml( 'title="local:books/guide.pdf" data-file-location>local:books/guide.pdf</bdi>' )
        ->assertSeeHtml( 'aria-label="Edit Guide"' )
        ->assertSeeHtml( 'aria-label="Delete Guide"' );

    $html = $component->html();

    expect( $html )->not->toMatch( '/class="[^"]*\b(btn|badge-|select-sm|input-sm|text-error|text-sm|flex|gap-\d|mb-4|max-w-xl|w-full|text-end)\b/' );
    expect( $html )->toMatch( '/<th[^>]*>\\s*(<[^>]+>\\s*)*Status\\b/' )
        ->and( $html )->not->toMatch( '/<th[^>]*>\\s*(<[^>]+>\\s*)*Streaming only\\b/' );
} );

it( 'links the product to its form, through the variant when the file is on one', function (): void {
    $book    = Product::factory()->digital()->create( [ 'name' => 'Cookbook' ] );
    $course  = Product::factory()->create( [ 'name' => 'Course' ] );
    $variant = ProductVariant::factory()->create( [ 'product_id' => $course->id, 'name' => 'Deluxe' ] );
    DigitalFile::factory()->create( [ 'product_id' => $book->id, 'label' => 'Cookbook PDF' ] );
    DigitalFile::factory()->create( [ 'product_id' => null, 'product_variant_id' => $variant->id, 'label' => 'Deluxe video' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'products/' . $book->id . '/edit' )
        ->assertSeeHtml( 'products/' . $course->id . '/edit' )
        ->assertSee( 'Course — Deluxe' );
} );

it( 'does not link products the user may not see', function (): void {
    Gate::define( 'ecommerce.product.viewAny', static fn (): bool => false );
    $book = Product::factory()->digital()->create( [ 'name' => 'Cookbook' ] );
    DigitalFile::factory()->create( [ 'product_id' => $book->id ] );

    Livewire::test( Index::class )
        ->assertSee( 'Cookbook' )
        ->assertDontSeeHtml( 'data-file-product' );
} );

it( 'offers "Archive instead" for kept files as the warning alert\'s action', function (): void {
    $file = DigitalFile::factory()->create( [ 'label' => 'Bought' ] );
    DigitalDownload::factory()->create( [ 'digital_file_id' => $file->id ] );

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $file->id );
    $component->call( 'confirmBulkAction', $component->viewData( 'tableConfirmToken' ) )
        ->assertSeeHtml( 'data-archive-kept' )
        ->assertSee( 'Archive instead' )
        ->call( 'archiveKept' )
        ->assertDontSeeHtml( 'data-kept-files' );

    expect( $file->fresh()->archived_at )->not->toBeNull();
} );

it( 'puts the drawer\'s actions in its footer and names the save for a new version', function (): void {
    $file = DigitalFile::factory()->create( [ 'label' => 'Guide', 'version' => '1.0' ] );

    Livewire::test( Index::class )
        ->call( 'edit', $file->id )
        ->assertSeeHtml( 'form="digital-file-form"' )
        ->assertSee( 'Save file' )
        ->set( 'form.version', '1.1' )
        ->assertSee( 'Save and notify customers' )
        ->assertSee( 'Customers will be notified' );
} );

it( 'describes the media choice with the private-disk hint', function (): void {
    ProductMedia::fake( true, Tests\Fixtures\Livewire\FakeMediaModal::class );

    Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.source', 'media' )
        ->assertSeeHtml( 'id="digital-file-media-hint"' )
        ->assertSeeHtml( 'aria-describedby="digital-file-media-hint"' )
        ->assertSee( 'No file chosen yet.' )
        ->assertSee( 'Choose file' );
} );
