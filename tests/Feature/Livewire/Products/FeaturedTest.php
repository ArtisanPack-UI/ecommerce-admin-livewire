<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Services\ProductService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Form;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Index;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ProductsQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductCsv;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
use Livewire\Livewire;
use Tests\Fixtures\User;

beforeEach( function (): void {
    config()->set( 'auth.providers.users.model', User::class );
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    config()->set( 'artisanpack.ecommerce.features.scout', false );
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'product.update' ] );
    ProductMedia::fake( false );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    ProductMedia::fake( null );
    removeAllFilters( 'ap.ecommerce.abilities.product.update' );
} );

it( 'saves and loads the featured flag and catalog position', function (): void {
    $product = app( ProductService::class )->create( [ 'type' => 'simple', 'name' => 'Mug' ] );

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->assertSet( 'isFeatured', false )
        ->assertSet( 'catalogPosition', 0 )
        ->set( 'isFeatured', true )
        ->set( 'catalogPosition', '7' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $product->refresh() )->is_featured->toBeTrue()->position->toBe( 7 );

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->assertSet( 'isFeatured', true )
        ->assertSet( 'catalogPosition', 7 );
} );

it( 'rejects an out-of-range catalog position on the organization tab', function ( mixed $position ): void {
    $product = app( ProductService::class )->create( [ 'type' => 'simple', 'name' => 'Mug' ] );

    Livewire::test( Form::class, [ 'product' => $product->id ] )
        ->set( 'catalogPosition', $position )
        ->call( 'save' )
        ->assertHasErrors( 'catalogPosition' )
        ->assertSet( 'tab', 'organization' );
} )->with( [ 'negative' => [ -1 ], 'too big' => [ 4294967296 ], 'not a number' => [ 'first' ] ] );

it( 'filters featured products and sorts by catalog position', function (): void {
    $c = Product::factory()->create( [ 'name' => 'C', 'is_featured' => true, 'position' => 3 ] );
    $a = Product::factory()->create( [ 'name' => 'A', 'is_featured' => false, 'position' => 1 ] );
    $b = Product::factory()->create( [ 'name' => 'B', 'is_featured' => true, 'position' => 2 ] );

    expect( ( new ProductsQuery() )->build( '', [ 'featured' => '1' ], 'position', 'asc' )->pluck( 'name' )->all() )->toBe( [ 'B', 'C' ] )
        ->and( ( new ProductsQuery() )->build( '', [ 'featured' => '0' ] )->pluck( 'name' )->all() )->toBe( [ 'A' ] )
        ->and( ( new ProductsQuery() )->build( '', [], 'position', 'desc' )->pluck( 'name' )->all() )->toBe( [ 'C', 'B', 'A' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-featured' )
        ->assertSee( 'Not featured' );

    expect( [ $a, $b, $c ] )->toHaveCount( 3 );
} );

it( 'marks and unmarks featured in bulk, skipping products a policy denies', function (): void {
    $mug    = Product::factory()->create( [ 'name' => 'Mug' ] );
    $locked = Product::factory()->create( [ 'name' => 'Locked' ] );

    addFilter( 'ap.ecommerce.abilities.product.update', static fn ( bool $allowed, $user, $request, mixed $subject = null ): bool => $allowed && ! ( $subject instanceof Product && $subject->is( $locked ) ), 10, 4 );

    Livewire::test( Index::class )
        ->set( 'selected', [ $mug->id, $locked->id ] )
        ->call( 'runBulkAction', 'feature' )
        ->assertOk();

    expect( $mug->refresh()->is_featured )->toBeTrue()
        ->and( $locked->refresh()->is_featured )->toBeFalse();

    $component = Livewire::test( Index::class )
        ->set( 'selected', [ $mug->id ] )
        ->call( 'runBulkAction', 'unfeature' );

    expect( $mug->refresh()->is_featured )->toBeFalse()
        ->and( sentToasts( $component ) )->toContain( '1 product removed from featured.' );
} );

it( 'reports the products a policy denied', function (): void {
    $locked = Product::factory()->create( [ 'name' => 'Locked' ] );

    addFilter( 'ap.ecommerce.abilities.product.update', static fn ( bool $allowed, $user, $request, mixed $subject = null ): bool => $allowed && ! $subject instanceof Product, 10, 4 );

    $component = Livewire::test( Index::class )
        ->set( 'selected', [ $locked->id ] )
        ->call( 'runBulkAction', 'feature' );

    expect( sentToasts( $component ) )->toContain( '1 product skipped: you may not change it.' );
} );

it( 'keeps the featured flag and position through a CSV round trip', function (): void {
    $product = Product::factory()->create( [ 'name' => 'Mug', 'sku' => 'MUG-1', 'is_featured' => true, 'position' => 12 ] );

    $rows    = iterator_to_array( ProductCsv::exportRows( Product::query()->whereKey( $product->id ) ), false );
    $headers = $rows[0];
    $row     = array_combine( $headers, $rows[1] );

    expect( $row['is_featured'] )->toBe( '1' )->and( $row['position'] )->toBe( '12' );

    $row['is_featured'] = '0';
    $row['position']    = '4';

    $context = [];

    expect( ProductCsv::check( $row, $context )['action'] )->toBe( 'update' );

    ProductCsv::apply( $row, auth()->user() );

    expect( $product->refresh() )->is_featured->toBeFalse()->position->toBe( 4 );
} );

it( 'rejects a malformed position in the CSV dry run', function ( string $position ): void {
    $context = [];
    $result  = ProductCsv::check( [ 'name' => 'Mug', 'sku' => 'MUG-9', 'position' => $position ], $context );

    expect( $result['action'] )->toBe( 'error' )
        ->and( $result['error'] )->toContain( 'position' );
} )->with( [ '-1', '1.5', '4294967296', 'first' ] );
