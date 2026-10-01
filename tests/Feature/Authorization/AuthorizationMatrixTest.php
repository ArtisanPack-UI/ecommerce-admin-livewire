<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\EcommerceAdminLivewireServiceProvider;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Authorization matrix (spec §6, §12)
|--------------------------------------------------------------------------
|
| MATRIX declares, for every Livewire component the package registers, the
| ability its mount() needs and, for every public action, the ability the
| action needs and the arguments to call it with. A null ability means
| "may enter the admin" (at least one visible screen).
|
| The suite then proves each declaration both ways:
|
| - a user who can enter the admin but lacks the ability is refused, so an
|   action that only checks admin access fails here;
| - a user holding just that ability gets through, so the refusal really
|   comes from the ability check.
|
| A component or action missing from MATRIX fails the suite, so a new screen
| cannot ship without declaring (and enforcing) its authorization.
|
*/

const MATRIX = [
    'artisanpack-ecommerce-admin-dashboard' => [
        'mount'   => [ 'params' => [], 'ability' => null ],
        'actions' => [],
    ],
    'artisanpack-ecommerce-admin-navigation' => [
        'mount'   => [ 'params' => [], 'ability' => null ],
        'actions' => [
            'refreshBadges' => [ 'args' => [], 'ability' => null ],
        ],
    ],

    'artisanpack-ecommerce-admin-orders-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'order.viewAny' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'placed' ], 'ability' => 'order.viewAny' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'order.viewAny' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'order.viewAny' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'order.viewAny' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'order.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-orders-show' => [
        'mount'   => [ 'params' => [ 'order' => '@order' ], 'ability' => 'order.view' ],
        'actions' => [
            'addToBoard'      => [ 'args' => [], 'ability' => 'kanbanCard.move' ],
            'removeFromBoard' => [ 'args' => [ 1 ], 'ability' => 'kanbanCard.move' ],
        ],
    ],
    'artisanpack-ecommerce-admin-order-status' => [
        'mount'   => [ 'params' => [ 'order' => '@order' ], 'ability' => 'order.view' ],
        'actions' => [
            'changeStatus'    => [ 'args' => [], 'ability' => 'order.update' ],
            'changeSubstatus' => [ 'args' => [], 'ability' => 'order.update' ],
            'cancelOrder'     => [ 'args' => [ 'token' ], 'ability' => 'order.cancel' ],
        ],
    ],
    'artisanpack-ecommerce-admin-order-refunds' => [
        'mount'   => [ 'params' => [ 'order' => '@order' ], 'ability' => 'order.view' ],
        'actions' => [
            'startRefund' => [ 'args' => [], 'ability' => 'order.refund' ],
            'refund'      => [ 'args' => [ 'token' ], 'ability' => 'order.refund' ],
        ],
    ],
    'artisanpack-ecommerce-admin-order-fulfillment' => [
        'mount'   => [ 'params' => [ 'order' => '@order' ], 'ability' => 'order.view' ],
        'actions' => [
            'startShipment'  => [ 'args' => [], 'ability' => 'order.update' ],
            'createShipment' => [ 'args' => [ 'token' ], 'ability' => 'order.update' ],
            'editTracking'   => [ 'args' => [ 1 ], 'ability' => 'order.update' ],
            'updateTracking' => [ 'args' => [], 'ability' => 'order.update' ],
            'buyLabel'       => [ 'args' => [ 1, 'token' ], 'ability' => 'order.update' ],
        ],
    ],
    'artisanpack-ecommerce-admin-order-edits' => [
        'mount'   => [ 'params' => [ 'order' => '@order' ], 'ability' => 'order.view' ],
        'actions' => [
            'startEdit'        => [ 'args' => [], 'ability' => 'order.update' ],
            'addItem'          => [ 'args' => [], 'ability' => 'order.update' ],
            'removeNewItem'    => [ 'args' => [ 0 ], 'ability' => 'order.update' ],
            'previewEdit'      => [ 'args' => [], 'ability' => 'order.update' ],
            'applyEdit'        => [ 'args' => [ 'token' ], 'ability' => 'order.update' ],
            'rollbackEdit'     => [ 'args' => [ 1, 'token' ], 'ability' => 'order.update' ],
            // The page re-authorizes order.view on every request; the picker source then needs product.viewAny.
            'searchPicker'     => [ 'args' => [ 'a', 'product', 'addProductId' ], 'ability' => 'product.viewAny' ],
            'optionsForPicker' => [ 'args' => [ 'product', 'addProductId' ], 'ability' => 'product.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-timeline' => [
        'mount'   => [ 'params' => [ 'subject' => '@order-model' ], 'ability' => 'order.view' ],
        'actions' => [
            'loadMore' => [ 'args' => [], 'ability' => 'order.view' ],
        ],
    ],

    'artisanpack-ecommerce-admin-notes' => [
        'mount'   => [ 'params' => [ 'subject' => '@order-model' ], 'ability' => 'order.view' ],
        'actions' => [
            'addNote'    => [ 'args' => [], 'ability' => 'order.update' ],
            // Deleting needs authorship or order.update on an existing note; a missing note only needs the view check.
            'deleteNote' => [ 'args' => [ 1 ], 'ability' => 'order.view' ],
        ],
    ],

    'artisanpack-ecommerce-admin-products-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'product.viewAny' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'name' ], 'ability' => 'product.viewAny' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'product.viewAny' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'product.viewAny' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'product.viewAny' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'product.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-products-form' => [
        'mount'   => [ 'params' => [], 'ability' => 'product.create' ],
        'actions' => [
            'addScheduledPrice'  => [ 'args' => [], 'ability' => 'product.create' ],
            'removePrice'        => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'addGalleryUrl'      => [ 'args' => [], 'ability' => 'product.create' ],
            'removeGalleryImage' => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'moveGalleryImage'   => [ 'args' => [ 0, 1 ], 'ability' => 'product.create' ],
            'clearFeaturedImage' => [ 'args' => [], 'ability' => 'product.create' ],
            'mediaSelected'      => [ 'args' => [ [], 'other' ], 'ability' => 'product.create' ],
            'save'               => [ 'args' => [], 'ability' => 'product.create' ],
            // The form re-authorizes product.create on every request; the picker source then needs product.viewAny.
            'searchPicker'       => [ 'args' => [ 'a', 'category', 'categoryIds' ], 'ability' => 'product.viewAny' ],
            'optionsForPicker'   => [ 'args' => [ 'category', 'categoryIds' ], 'ability' => 'product.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-product-variable-panel' => [
        'mount'   => [ 'params' => [ 'productId' => null ], 'ability' => 'product.create' ],
        'actions' => [
            'addAttribute'      => [ 'args' => [], 'ability' => 'product.create' ],
            'removeAttribute'   => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'moveAttribute'     => [ 'args' => [ 0, 1 ], 'ability' => 'product.create' ],
            'addValue'          => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'removeValue'       => [ 'args' => [ 0, 0 ], 'ability' => 'product.create' ],
            'moveValue'         => [ 'args' => [ 0, 0, 1 ], 'ability' => 'product.create' ],
            'generateVariants'  => [ 'args' => [], 'ability' => 'product.create' ],
            'confirmGenerate'   => [ 'args' => [], 'ability' => 'product.create' ],
            'cancelGenerate'    => [ 'args' => [], 'ability' => 'product.create' ],
            'addVariant'        => [ 'args' => [], 'ability' => 'product.create' ],
            'removeVariant'     => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'moveVariant'       => [ 'args' => [ 0, 1 ], 'ability' => 'product.create' ],
            'mediaSelected'     => [ 'args' => [ [], 'other' ], 'ability' => 'product.create' ],
            'productSaved'      => [ 'args' => [], 'ability' => 'product.create' ],
        ],
    ],
    'artisanpack-ecommerce-admin-product-digital-panel' => [
        'mount'   => [ 'params' => [ 'productId' => null ], 'ability' => 'product.create' ],
        'actions' => [
            'addFile'       => [ 'args' => [], 'ability' => 'product.create' ],
            'removeFile'    => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'mediaSelected' => [ 'args' => [ [], 'other' ], 'ability' => 'product.create' ],
            'productSaved'  => [ 'args' => [], 'ability' => 'product.create' ],
        ],
    ],
    'artisanpack-ecommerce-admin-product-children-panel' => [
        'mount'   => [ 'params' => [ 'productId' => null ], 'ability' => 'product.create' ],
        'actions' => [
            'addChild'         => [ 'args' => [], 'ability' => 'product.create' ],
            'removeChild'      => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'moveChild'        => [ 'args' => [ 0, 1 ], 'ability' => 'product.create' ],
            'productSaved'     => [ 'args' => [], 'ability' => 'product.create' ],
            // The panel re-authorizes product.create on every request; the picker source then needs product.viewAny.
            'searchPicker'     => [ 'args' => [ 'a', 'product', 'state.children.0.product_id' ], 'ability' => 'product.viewAny' ],
            'optionsForPicker' => [ 'args' => [ 'product', 'state.children.0.product_id' ], 'ability' => 'product.viewAny' ],
        ],
    ],

    // The WithPickers concern, which screens mix in.
    'matrix-pickers' => [
        'mount'   => [ 'params' => [], 'ability' => null ],
        'actions' => [
            'searchPicker'     => [ 'args' => [ 'a', 'customer', 'customerId' ], 'ability' => 'customer.viewAny' ],
            'optionsForPicker' => [ 'args' => [ 'customer', 'customerId' ], 'ability' => 'customer.viewAny', 'denied' => 'empty' ],
        ],
    ],

    // The WithConfigForms concern, which config forms mix in.
    'matrix-config-forms' => [
        'mount'   => [ 'params' => [], 'ability' => null ],
        'actions' => [
            'addConfigRow'    => [ 'args' => [ 'shipping-method', 'price-based', 'config', 'tiers' ], 'ability' => null ],
            'removeConfigRow' => [ 'args' => [ 'shipping-method', 'price-based', 'config', 'tiers', 0 ], 'ability' => null ],
        ],
    ],
];

/**
 * Test fixtures that bring shared concerns into the matrix.
 */
$GLOBALS['matrixFixtureComponents'] = [
    'matrix-pickers'      => Tests\Fixtures\Livewire\MatrixPickers::class,
    'matrix-config-forms' => Tests\Fixtures\Livewire\MatrixConfigForms::class,
];

/**
 * Resolves mount parameters: `@order` becomes the id of an order created
 * for the test, and `@order-model` that order itself.
 *
 * @param  array<string, mixed>  $params
 *
 * @return array<string, mixed>
 */
function matrixParams( array $params ): array
{
    return array_map( static function ( mixed $value ): mixed {
        if ( '@order' === $value ) {
            return $GLOBALS['matrixOrderId'] ??= ArtisanPackUI\Ecommerce\Models\Order::factory()->create()->id;
        }

        if ( '@order-model' === $value ) {
            return ArtisanPackUI\Ecommerce\Models\Order::query()->findOrFail( $GLOBALS['matrixOrderId'] ??= ArtisanPackUI\Ecommerce\Models\Order::factory()->create()->id );
        }

        return $value;
    }, $params );
}

/**
 * Livewire lifecycle hooks, which the client cannot call.
 */
const MATRIX_LIFECYCLE = '/^(render|rendering|rendered|exception|placeholder)$|^(mount|boot|booted|hydrate|dehydrate|updating|updated)([A-Z_].*)?$/';

/**
 * The client-callable actions of a component: its public instance methods
 * that Livewire's base component does not define.
 *
 * @return array<int, string>
 */
function matrixActions( string $class ): array
{
    $base = array_map(
        static fn ( ReflectionMethod $method ): string => $method->getName(),
        ( new ReflectionClass( Component::class ) )->getMethods( ReflectionMethod::IS_PUBLIC ),
    );

    $actions = array_filter(
        ( new ReflectionClass( $class ) )->getMethods( ReflectionMethod::IS_PUBLIC ),
        static fn ( ReflectionMethod $method ): bool => ! $method->isStatic()
            && ! in_array( $method->getName(), $base, true )
            && 1 !== preg_match( MATRIX_LIFECYCLE, $method->getName() )
            && [] === $method->getAttributes( Computed::class ),
    );

    $names = array_map( static fn ( ReflectionMethod $method ): string => $method->getName(), $actions );
    sort( $names );

    return $names;
}

/**
 * A user who can enter the admin but does not hold `$ability`.
 */
function matrixIntruder( ?string $ability )
{
    if ( null !== $ability ) {
        $unrelated = 'kanbanBoard.viewAny' === $ability ? 'review.viewAny' : 'kanbanBoard.viewAny';

        Illuminate\Support\Facades\Gate::define( 'ecommerce.' . $unrelated, static fn (): bool => true );
    }

    return makeUser();
}

/**
 * Grants exactly `$abilities` (null entries mean "may enter the admin").
 *
 * @param  array<int, string|null>  $abilities
 */
function matrixGrant( array $abilities ): void
{
    $abilities = array_values( array_filter( $abilities ) );

    grantAbilities( [] === $abilities ? [ 'review.viewAny' ] : $abilities );
}

/**
 * The components under test: the registered ones plus any test fixtures.
 *
 * @return array<string, class-string>
 */
function matrixComponents(): array
{
    return EcommerceAdminLivewireServiceProvider::LIVEWIRE_COMPONENTS + ( $GLOBALS['matrixFixtureComponents'] ?? [] );
}

dataset( 'admin components', static fn (): array => array_map(
    static fn ( string $name ): array => [ $name ],
    array_combine( array_keys( MATRIX ), array_keys( MATRIX ) ),
) );

dataset( 'admin actions', static function (): array {
    $rows = [];

    foreach ( MATRIX as $name => $definition ) {
        foreach ( array_keys( $definition['actions'] ) as $action ) {
            $rows[ $name . '::' . $action ] = [ $name, $action ];
        }
    }

    return [] === $rows ? [ 'none' => [ null, null ] ] : $rows;
} );

beforeEach( function (): void {
    $GLOBALS['matrixOrderId'] = null;

    foreach ( matrixComponents() as $name => $class ) {
        Livewire::component( $name, $class );
    }
} );

it( 'declares every component and every public action', function (): void {
    expect( array_keys( MATRIX ) )->toEqualCanonicalizing( array_keys( matrixComponents() ) );

    foreach ( matrixComponents() as $name => $class ) {
        $declared = array_keys( MATRIX[ $name ]['actions'] );
        sort( $declared );

        expect( $declared )->toBe( matrixActions( $class ), $name . ' has undeclared or stale actions in MATRIX.' );
    }
} );

it( 'registers every Livewire component class under src/Livewire', function (): void {
    $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../../../src/Livewire', FilesystemIterator::SKIP_DOTS ) );
    $root  = realpath( __DIR__ . '/../../../src/Livewire' );

    $classes = collect( iterator_to_array( $files ) )
        ->map( static fn ( SplFileInfo $file ): string => (string) $file->getRealPath() )
        ->filter( static fn ( string $path ): bool => str_ends_with( $path, '.php' ) && ! str_contains( $path, DIRECTORY_SEPARATOR . 'Concerns' . DIRECTORY_SEPARATOR ) )
        ->map( static fn ( string $path ): string => 'ArtisanPackUI\\EcommerceAdminLivewire\\Livewire\\' . str_replace( [ $root . DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR ], [ '', '', '\\' ], $path ) )
        ->reject( static fn ( string $class ): bool => ( new ReflectionClass( $class ) )->isAbstract() )
        ->sort()
        ->values()
        ->all();

    expect( collect( EcommerceAdminLivewireServiceProvider::LIVEWIRE_COMPONENTS )->sort()->values()->all() )->toBe( $classes );
} );

it( 'refuses every admin route to a user who may not enter the admin', function (): void {
    $routes = collect( Route::getRoutes()->getRoutes() )
        ->filter( static fn ( RoutingRoute $route ): bool => str_starts_with( (string) $route->getName(), 'artisanpack.ecommerce.admin.' ) );

    expect( $routes )->not->toBeEmpty();

    $user = makeUser();

    $routes->each( function ( RoutingRoute $route ) use ( $user ): void {
        $this->actingAs( $user )
            ->call( $route->methods()[0], route( $route->getName(), array_fill_keys( $route->parameterNames(), '1' ) ) )
            ->assertForbidden();
    } );
} );

it( 'refuses to mount without the screen ability', function ( string $name ): void {
    $mount = MATRIX[ $name ]['mount'];

    Livewire::actingAs( null === $mount['ability'] ? makeUser() : matrixIntruder( $mount['ability'] ) )
        ->test( $name, matrixParams( $mount['params'] ) )
        ->assertForbidden();
} )->with( 'admin components' );

it( 'mounts with only the screen ability', function ( string $name ): void {
    $mount = MATRIX[ $name ]['mount'];
    matrixGrant( [ $mount['ability'] ] );

    Livewire::actingAs( makeUser() )
        ->test( $name, matrixParams( $mount['params'] ) )
        ->assertOk();
} )->with( 'admin components' );

it( 'refuses the action without its ability', function ( ?string $name, ?string $action ): void {
    if ( null === $name ) {
        expect( true )->toBeTrue();

        return;
    }

    $mount      = MATRIX[ $name ]['mount'];
    $definition = MATRIX[ $name ]['actions'][ $action ];

    $admin = makeUser();
    grantAdmin( $admin );
    $intruder = matrixIntruder( $definition['ability'] );

    $component = Livewire::actingAs( $admin )->test( $name, matrixParams( $mount['params'] ) )->assertOk();

    $this->actingAs( $intruder );

    $component->call( $action, ...$definition['args'] );

    if ( 'empty' === ( $definition['denied'] ?? 'forbidden' ) ) {
        $component->assertOk()->assertReturned( [] );

        return;
    }

    $component->assertForbidden();
} )->with( 'admin actions' );

it( 'allows the action with only its ability', function ( ?string $name, ?string $action ): void {
    if ( null === $name ) {
        expect( true )->toBeTrue();

        return;
    }

    $mount      = MATRIX[ $name ]['mount'];
    $definition = MATRIX[ $name ]['actions'][ $action ];
    matrixGrant( [ $mount['ability'], $definition['ability'] ] );

    Livewire::actingAs( makeUser() )
        ->test( $name, matrixParams( $mount['params'] ) )
        ->call( $action, ...$definition['args'] )
        ->assertOk();
} )->with( 'admin actions' );
