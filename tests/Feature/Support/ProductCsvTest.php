<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductCsv;

beforeEach( function (): void {
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    config()->set( 'artisanpack.ecommerce.features.scout', false );
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'product.update' ] );
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
