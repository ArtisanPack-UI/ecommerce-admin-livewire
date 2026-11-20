<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;

it( 'names the missing type and what keeps working in a warning alert', function (): void {
    $product = Product::factory()->create( [ 'type' => 'subscription' ] );

    expect( $product->typeIsMissing() )->toBeTrue();

    $html = Blade::render( '<x-artisanpack-ec-product-type-warning :product="$product" />', [ 'product' => $product ] );

    expect( $html )->toContain( 'This product is read-only' )
        ->toContain( e( 'Its type, “subscription”, is provided by a plugin that is no longer installed, so it can’t be edited or bought until you reinstall it. Existing orders and stock aren’t affected.' ) )
        ->toContain( 'role="status"' )
        ->toContain( 'class="apui-alert' )
        ->toContain( 'data-color="warning"' )
        ->toContain( 'data-product-type-warning' )
        ->not->toContain( 'alert-warning' )
        ->and( substr_count( $html, 'role=' ) )->toBe( 1 );
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
