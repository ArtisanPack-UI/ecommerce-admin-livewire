<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;

it( 'shows the engine warning for a product whose type is missing', function (): void {
    $product = Product::factory()->create( [ 'type' => 'subscription' ] );

    expect( $product->typeIsMissing() )->toBeTrue();

    $html = Blade::render( '<x-artisanpack-ec-product-type-warning :product="$product" />', [ 'product' => $product ] );

    expect( $html )->toContain( 'This product is read-only' )
        ->toContain( e( $product->typeWarning() ) )
        ->toContain( 'role="status"' );
} );

it( 'renders nothing for a product whose type is installed', function (): void {
    $product = Product::factory()->create();

    expect( trim( Blade::render( '<x-artisanpack-ec-product-type-warning :product="$product" />', [ 'product' => $product ] ) ) )->toBe( '' );
} );

it( 'treats a product with a missing type as read-only even for a full admin', function (): void {
    $user = makeUser();
    grantAdmin( $user );

    $component = new class extends Component {
        use AuthorizesEcommerce;

        public function readOnly( Product $product ): bool
        {
            return $this->productIsReadOnly( $product );
        }

        public function render(): string
        {
            return '<div></div>';
        }
    };

    $this->actingAs( $user );

    expect( $component->readOnly( Product::factory()->create( [ 'type' => 'subscription' ] ) ) )->toBeTrue()
        ->and( $component->readOnly( Product::factory()->create() ) )->toBeFalse();
} );
