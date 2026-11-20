<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductCsv;

beforeEach( function (): void {
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    config()->set( 'artisanpack.ecommerce.features.scout', false );
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'product.update', 'inventory.adjust' ] );
    $this->actingAs( makeUser() );
} );

it( 'checks decimal cells strictly', function ( string $weight, bool $valid ): void {
    $context = [];
    $result  = ProductCsv::check( [ 'name' => 'Mug', 'sku' => 'MUG-1', 'weight' => $weight, 'weight_unit' => 'kg' ], $context );

    expect( 'error' !== $result['action'] )->toBe( $valid );
} )->with( [
    'whole'            => [ '2', true ],
    'dot'              => [ '1.5', true ],
    'comma'            => [ '1,5', true ],
    'four decimals'    => [ '0.1234', true ],
    'five decimals'    => [ '0.12345', false ],
    'exponent'         => [ '1e308', false ],
    'leading space'    => [ ' 1.5', true ],
    'negative'         => [ '-1', false ],
    'grouped'          => [ '1,000.5', false ],
    'ten digits'       => [ '1234567890', false ],
] );

it( 'stores a comma decimal as a dot decimal', function (): void {
    ProductCsv::apply( [ 'name' => 'Mug', 'sku' => 'MUG-1', 'weight' => '1,25', 'weight_unit' => 'kg' ], auth()->user() );

    expect( (float) Product::query()->where( 'sku', 'MUG-1' )->value( 'weight' ) )->toBe( 1.25 );
} );

it( 'parses prices strictly as plain dot decimals', function ( string $currency, string $amount, ?int $minor ): void {
    config()->set( 'artisanpack.ecommerce.currencies', [ 'USD', 'JPY', 'KWD' ] );
    config()->set( 'artisanpack.ecommerce.enabled_currencies', [ 'USD', 'JPY', 'KWD' ] );

    $method = new ReflectionMethod( ProductCsv::class, 'amount' );

    if ( null === $minor ) {
        expect( fn () => $method->invoke( null, [ 'price' => $amount ], 'price', $currency ) )->toThrow( InvalidArgumentException::class );

        return;
    }

    expect( $method->invoke( null, [ 'price' => $amount ], 'price', $currency ) )->toBe( $minor );
} )->with( [
    'usd cents'           => [ 'USD', '12.50', 1250 ],
    'usd one decimal'     => [ 'USD', '12.5', 1250 ],
    'usd whole'           => [ 'USD', '12', 1200 ],
    'usd three decimals'  => [ 'USD', '12.500', null ],
    'usd grouped'         => [ 'USD', '1.000', null ],
    'usd comma grouping'  => [ 'USD', '1,000.00', null ],
    'usd comma decimal'   => [ 'USD', '12,50', null ],
    'usd negative'        => [ 'USD', '-1.00', null ],
    'jpy whole'           => [ 'JPY', '1000', 1000 ],
    'jpy zero fraction'   => [ 'JPY', '1000.00', 1000 ],
    'jpy decimals'        => [ 'JPY', '10.5', null ],
    'kwd three decimals'  => [ 'KWD', '1.000', 1000 ],
] );

it( 'gives a product type without stock of its own no stock row on import', function (): void {
    ProductCsv::apply( [ 'type' => 'digital', 'name' => 'E-book', 'sku' => 'BOOK-1', 'quantity_on_hand' => '5', 'track_inventory' => 'yes' ], auth()->user() );

    $product = Product::query()->where( 'sku', 'BOOK-1' )->firstOrFail();

    expect( ArtisanPackUI\Ecommerce\Models\InventoryItem::query()->where( 'stockable_type', $product->getMorphClass() )->where( 'stockable_id', $product->id )->exists() )->toBeFalse();
} );

it( 'does not change an existing product\'s stock without inventory.adjust', function (): void {
    $product = Product::factory()->create( [ 'sku' => 'MUG-1', 'name' => 'Mug' ] );
    $item    = app( ArtisanPackUI\Ecommerce\Services\ProductService::class )->inventoryItemFor( $product );
    $item->forceFill( [ 'quantity_on_hand' => 4 ] )->save();

    Illuminate\Support\Facades\Gate::define( 'ecommerce.inventory.adjust', static fn (): bool => false );

    $warnings = [];
    ProductCsv::apply( [ 'sku' => 'MUG-1', 'quantity_on_hand' => '40', 'allow_backorder' => 'yes' ], auth()->user(), $warnings );

    expect( $item->refresh() )->quantity_on_hand->toBe( 4 )->allow_backorder->toBeFalse()
        ->and( $warnings )->toHaveKey( 'stock' );
} );

it( 'creates new tags only for a user who may create them, as on the Tags screen', function (): void {
    app( ArtisanPackUI\Ecommerce\Services\ProductTagService::class )->findOrCreate( 'Summer' );
    Product::factory()->create( [ 'sku' => 'MUG-1', 'name' => 'Mug' ] );

    Illuminate\Support\Facades\Gate::define( 'ecommerce.product.create', static fn (): bool => false );

    // An existing tag (matched by slug) is fine.
    ProductCsv::apply( [ 'sku' => 'MUG-1', 'tags' => 'summer' ], auth()->user() );

    expect( fn () => ProductCsv::apply( [ 'sku' => 'MUG-1', 'tags' => 'Summer|Winter' ], auth()->user() ) )
        ->toThrow( InvalidArgumentException::class, 'Tag "Winter" does not exist, and you may not create tags.' )
        ->and( ArtisanPackUI\Ecommerce\Models\ProductTag::query()->where( 'slug', 'winter' )->exists() )->toBeFalse();
} );

it( 'matches preloaded rows case-insensitively without scanning, on case-insensitive drivers', function (): void {
    Product::factory()->create( [ 'sku' => 'MUG-1', 'name' => 'Mug', 'slug' => 'mug' ] );

    // The slug loads the product on SQLite; MySQL would also load it by SKU.
    $rows    = [ [ 'sku' => 'mug-1', 'slug' => 'mug', 'name' => 'Mug' ] ];
    $context = [];

    ProductCsv::preload( $rows, $context );

    $lookup = new ReflectionMethod( ProductCsv::class, 'fromLookup' );

    expect( $lookup->invoke( null, $context['lookup'], 'skus', 'mug-1' ) )->toBeNull();

    $context['lookup']['caseInsensitive'] = true;

    expect( $context['lookup']['lower']['skus'] )->toHaveKey( 'mug-1' )
        ->and( $lookup->invoke( null, $context['lookup'], 'skus', 'mug-1' ) )->toBeInstanceOf( Product::class )
        ->and( ProductCsv::check( $rows[0], $context )['action'] )->toBe( 'update' );
} );
