<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Coupon;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight;
use ArtisanPackUI\EcommerceAdminLivewire\Spotlight\NullSpotlight;
use ArtisanPackUI\EcommerceAdminLivewire\Spotlight\SpotlightProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach( function (): void {
    $this->user = makeUser();
    $this->actingAs( $this->user );
} );

afterEach( function (): void {
    removeAllFilters( AdminSpotlight::PROVIDERS_FILTER );
} );

/**
 * The palette results for a search, through the component library's route.
 *
 * @return array<int, array<string, mixed>>
 */
function spotlightResults( string $search ): array
{
    return test()->getJson( route( 'artisanpack.spotlight', [ 'search' => $search ] ) )->assertOk()->json();
}

it( 'finds orders by number and email and links to the order', function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view' ] );
    $order = Order::factory()->create( [ 'order_number' => '50123', 'email' => 'ada@example.test' ] );
    Order::factory()->create( [ 'order_number' => '70000', 'email' => 'grace@example.test' ] );

    $byNumber = spotlightResults( '#50123' );
    $byEmail  = spotlightResults( 'ada@' );

    expect( array_column( $byNumber, 'name' ) )->toContain( 'Order #50123' )->not->toContain( 'Order #70000' )
        ->and( $byNumber[0]['link'] )->toBe( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => $order->id ] ) )
        ->and( $byNumber[0]['description'] )->toContain( 'ada@example.test' )
        ->and( $byNumber[0]['icon'] )->toContain( '<svg' )
        ->and( array_column( $byEmail, 'name' ) )->toBe( [ 'Order #50123' ] );
} );

it( 'offers refund and note actions when the search is exactly an order number', function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view', 'order.refund', 'order.update' ] );
    $order = Order::factory()->create( [ 'order_number' => '50123' ] );
    Order::factory()->create( [ 'order_number' => '501234' ] );

    $names = array_column( spotlightResults( '50123' ), 'link', 'name' );

    expect( $names )->toHaveKey( 'Refund order #50123' )
        ->and( $names['Refund order #50123'] )->toBe( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => $order->id, 'action' => 'refund' ] ) )
        ->and( $names['Add note to #50123'] )->toBe( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => $order->id ] ) . '#order-notes' )
        ->and( $names )->not->toHaveKey( 'Refund order #501234' )
        ->and( array_key_first( $names ) )->toBe( 'Order #50123' );

    expect( array_column( spotlightResults( '501' ), 'name' ) )->not->toContain( 'Refund order #50123' );
} );

it( 'hides contextual order actions the user may not take', function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view' ] );
    Order::factory()->create( [ 'order_number' => '50123' ] );

    expect( array_column( spotlightResults( '50123' ), 'name' ) )->toBe( [ 'Order #50123' ] );
} );

it( 'searches products, customers, promotions, and coupons', function (): void {
    grantAbilities( [ 'product.viewAny', 'product.view', 'customer.viewAny', 'customer.view', 'promotion.viewAny', 'promotion.view', 'coupon.viewAny', 'coupon.view' ] );
    $mug       = Product::factory()->create( [ 'name' => 'Linen Mug', 'sku' => 'MUG-LIN' ] );
    $customer  = Customer::factory()->create( [ 'first_name' => 'Linus', 'last_name' => 'Pauling', 'email' => 'linus@example.test' ] );
    $promotion = Promotion::factory()->create( [ 'name' => 'Linen week' ] );
    Coupon::query()->create( [ 'promotion_id' => $promotion->id, 'code' => 'LINEN10' ] );

    $results = array_column( spotlightResults( 'lin' ), 'link', 'name' );

    expect( $results )->toMatchArray( [
        'Linen Mug'      => route( 'artisanpack.ecommerce.admin.products.edit', [ 'product' => $mug->id ] ),
        'Linus Pauling'  => route( 'artisanpack.ecommerce.admin.customers.show', [ 'customer' => $customer->id ] ),
        'Linen week'     => route( 'artisanpack.ecommerce.admin.promotions.edit', [ 'promotion' => $promotion->id ] ),
        'LINEN10'        => route( 'artisanpack.ecommerce.admin.promotions.edit', [ 'promotion' => $promotion->id, 'tab' => 'coupons' ] ),
    ] );

    expect( array_column( spotlightResults( 'MUG-LIN' ), 'name' ) )->toContain( 'Linen Mug' )
        ->and( array_column( spotlightResults( 'linus pauling' ), 'name' ) )->toContain( 'Linus Pauling' );
} );

it( 'gates each entity type by its viewAny ability', function (): void {
    grantAbilities( [ 'product.viewAny', 'product.view' ] );
    Product::factory()->create( [ 'name' => 'Linen Mug' ] );
    Customer::factory()->create( [ 'first_name' => 'Linus' ] );
    Order::factory()->create( [ 'order_number' => 'LIN-1' ] );

    $names = array_column( spotlightResults( 'lin' ), 'name' );

    expect( $names )->toContain( 'Linen Mug' )
        ->not->toContain( 'Linus' )
        ->not->toContain( 'Order #LIN-1' );
} );

it( 'caps the results per type at the configured limit', function (): void {
    grantAbilities( [ 'product.viewAny', 'product.view' ] );
    config()->set( 'artisanpack.ecommerce-admin-livewire.spotlight.limit', 2 );
    Product::factory()->count( 4 )->sequence( fn ( $sequence ) => [ 'name' => 'Mug ' . $sequence->index ] )->create();

    expect( spotlightResults( 'mug' ) )->toHaveCount( 2 );

    config()->set( 'artisanpack.ecommerce-admin-livewire.spotlight.limit', 999 );

    expect( AdminSpotlight::limit() )->toBe( AdminSpotlight::MAX_LIMIT );
} );

it( 'runs one query per provider', function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view', 'product.viewAny', 'product.view', 'customer.viewAny', 'customer.view', 'promotion.viewAny', 'promotion.view', 'coupon.viewAny', 'coupon.view' ] );
    Product::factory()->count( 3 )->create( [ 'name' => 'Mug' ] );
    Order::factory()->count( 3 )->create();

    DB::enableQueryLog();
    AdminSpotlight::search( 'mug', $this->user );
    $queries = collect( DB::getQueryLog() )->pluck( 'query' )
        ->filter( static fn ( string $sql ): bool => 1 === preg_match( '/from "(orders|products|customers|promotions|coupons)"/', $sql ) );
    DB::disableQueryLog();

    expect( $queries )->toHaveCount( 5 );
} );

it( 'offers new product, new promotion, and navigation actions', function (): void {
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'promotion.viewAny', 'promotion.view' ] );

    $results = array_column( spotlightResults( 'new' ), 'link', 'name' );

    expect( $results )->toHaveKey( 'New product' )
        ->and( $results['New product'] )->toBe( route( 'artisanpack.ecommerce.admin.products.create' ) )
        ->not->toHaveKey( 'New promotion' );

    expect( array_column( spotlightResults( 'go categ' ), 'name' ) )->toBe( [ 'Go to Categories' ] )
        ->and( array_column( spotlightResults( 'shipping' ), 'name' ) )->not->toContain( 'Go to Shipping' );
} );

it( 'returns nothing to a user who cannot open the admin, or for an empty search', function (): void {
    Product::factory()->create( [ 'name' => 'Mug' ] );

    expect( spotlightResults( 'mug' ) )->toBe( [] );

    grantAbilities( [ 'product.viewAny', 'product.view' ] );

    expect( spotlightResults( '   ' ) )->toBe( [] )
        ->and( AdminSpotlight::search( '', $this->user ) )->toBe( [] );
} );

it( 'returns nothing to guests', function (): void {
    auth()->logout();

    $this->getJson( route( 'artisanpack.spotlight', [ 'search' => 'mug' ] ) )->assertOk()->assertExactJson( [] );
} );

it( 'truncates an over-long search instead of failing', function (): void {
    grantAbilities( [ 'product.viewAny', 'product.view' ] );
    Product::factory()->create( [ 'name' => 'Mug' ] );

    expect( spotlightResults( str_repeat( 'x', 5000 ) ) )->toBe( [] );
} );

it( 'adds nothing when the palette is disabled', function (): void {
    grantAbilities( [ 'product.viewAny', 'product.view' ] );
    Product::factory()->create( [ 'name' => 'Mug' ] );
    config()->set( 'artisanpack.ecommerce-admin-livewire.spotlight.enabled', false );

    expect( spotlightResults( 'mug' ) )->toBe( [] );
} );

it( 'keeps the host spotlight results and appends its own', function (): void {
    grantAbilities( [ 'product.viewAny', 'product.view' ] );
    Product::factory()->create( [ 'name' => 'Mug' ] );

    $results = AdminSpotlight::appendTo( [ [ 'name' => 'Host result', 'link' => '/host' ] ], $this->user );

    expect( $results )->toHaveCount( 1 );

    request()->query->set( 'search', 'mug' );

    expect( array_column( AdminSpotlight::appendTo( [ [ 'name' => 'Host result', 'link' => '/host' ] ], $this->user ), 'name' ) )
        ->toBe( [ 'Host result', 'Mug' ] );
} );

it( 'binds an empty search class when the host has none', function (): void {
    expect( app( (string) config( 'artisanpack.livewire-ui-components.components.spotlight.class' ) ) )->toBeInstanceOf( NullSpotlight::class );
} );

it( 'accepts providers through the extension filter and drops invalid ones', function (): void {
    grantAbilities( [ 'product.viewAny', 'product.view' ] );

    addFilter( AdminSpotlight::PROVIDERS_FILTER, static function ( array $providers ): array {
        unset( $providers['products'] );

        $providers['subscriptions'] = new class implements SpotlightProvider {
            public function ability(): ?string
            {
                return 'product.viewAny';
            }

            public function search( string $search, Authenticatable $user, int $limit ): array
            {
                return [
                    [ 'name' => 'Subscription for ' . $search, 'link' => '/subscriptions/1', 'icon' => '<script>alert(1)</script>' ],
                    [ 'name' => 'Missing link' ],
                ];
            }
        };
        $providers['locked'] = new class implements SpotlightProvider {
            public function ability(): ?string
            {
                return 'subscription.viewAny';
            }

            public function search( string $search, Authenticatable $user, int $limit ): array
            {
                return [ [ 'name' => 'Should not show', 'link' => '/nope' ] ];
            }
        };
        $providers['junk'] = 'not-a-provider';

        return $providers;
    } );

    Product::factory()->create( [ 'name' => 'Mug' ] );

    $results = spotlightResults( 'mug' );

    expect( array_column( $results, 'name' ) )->toBe( [ 'Subscription for mug' ] )
        ->and( $results[0]['icon'] )->toBeNull()
        ->and( array_keys( AdminSpotlight::providers() ) )->toBe( [ 'orders', 'customers', 'promotions', 'coupons', 'actions', 'subscriptions', 'locked' ] );
} );

it( 'mounts the palette and its open button in the standalone layout', function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view' ] );

    $this->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->assertOk()
        ->assertSee( 'marySpotlight', false )
        ->assertSee( '@keydown.window.prevent.meta.k', false )
        ->assertSee( 'data-spotlight-open', false )
        ->assertSee( 'aria-keyshortcuts="Meta+K"', false );
} );

it( 'mounts the palette on a configured shortcut, and not at all when disabled', function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view' ] );
    config()->set( 'artisanpack.ecommerce-admin-livewire.spotlight.shortcut', 'ctrl.slash' );

    $this->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->assertSee( '@keydown.window.prevent.ctrl.slash', false );

    config()->set( 'artisanpack.ecommerce-admin-livewire.spotlight.shortcut', 'meta.k" onclick="x' );

    expect( AdminSpotlight::shortcut() )->toBe( 'meta.k' );

    config()->set( 'artisanpack.ecommerce-admin-livewire.spotlight.enabled', false );

    $this->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->assertDontSee( 'marySpotlight', false )
        ->assertDontSee( 'data-spotlight-open', false );
} );

it( 'denies the contextual refund action to a user without order.refund', function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view' ] );
    Gate::define( 'ecommerce.order.refund', static fn (): bool => false );
    Order::factory()->create( [ 'order_number' => '50123' ] );

    expect( array_column( spotlightResults( '50123' ), 'name' ) )->not->toContain( 'Refund order #50123' );
} );

it( 'mounts the palette and its button under the cms-framework layout', function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view' ] );
    ArtisanPackUI\EcommerceAdminLivewire\Support\CmsFramework::fake( true );
    Illuminate\Support\Facades\View::addNamespace( 'cms', __DIR__ . '/../../Fixtures/views/cms' );

    $html = $this->get( route( 'artisanpack.ecommerce.admin.dashboard' ) )
        ->assertOk()
        ->assertSee( 'cms-admin-fixture', false )
        ->assertSee( 'data-cms-spotlight-bar', false )
        ->assertSee( 'data-spotlight-open', false )
        ->getContent();

    expect( substr_count( $html, '@keydown.window.prevent.meta.k' ) )->toBe( 1 );
} );

it( 'drops records the user may not view, and coupons without promotion access', function (): void {
    grantAbilities( [ 'product.viewAny', 'coupon.viewAny', 'coupon.view' ] );
    Gate::define( 'ecommerce.product.view', static fn ( $user, $product = null ): bool => 'Secret Mug' !== $product?->name );
    Product::factory()->create( [ 'name' => 'Open Mug' ] );
    Product::factory()->create( [ 'name' => 'Secret Mug' ] );
    $promotion = Promotion::factory()->create();
    Coupon::query()->create( [ 'promotion_id' => $promotion->id, 'code' => 'MUG10' ] );

    expect( array_column( spotlightResults( 'mug' ), 'name' ) )->toBe( [ 'Open Mug' ] );
} );

it( 'stops answering a user who searches too often', function (): void {
    grantAbilities( [ 'product.viewAny', 'product.view' ] );
    Product::factory()->create( [ 'name' => 'Mug' ] );

    Illuminate\Support\Facades\RateLimiter::increment( 'ecommerce-admin-spotlight:' . $this->user->getAuthIdentifier(), 60, AdminSpotlight::SEARCHES_PER_MINUTE );

    expect( spotlightResults( 'mug' ) )->toBe( [] );
} );
