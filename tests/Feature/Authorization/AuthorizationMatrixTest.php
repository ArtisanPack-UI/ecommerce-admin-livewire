<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\EcommerceAdminLivewireServiceProvider;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Authorization matrix (spec §6, §12)
|--------------------------------------------------------------------------
|
| Walks every admin route and every public action of every Livewire
| component the package registers, as a signed-in user holding no ecommerce
| abilities, and asserts each is refused. A new screen or action that forgets
| to authorize fails here.
|
| Components whose mount() needs arguments list them in MATRIX_MOUNT_PARAMS;
| a component missing from it is mounted with none.
|
*/

const MATRIX_MOUNT_PARAMS = [];

/**
 * Lifecycle hooks and render methods Livewire calls itself, which are not
 * client-callable actions.
 */
const MATRIX_NON_ACTIONS = '/^(mount|render|boot|booted|hydrate|dehydrate|updating|updated|rendering|rendered|exception|placeholder)/';

/**
 * The client-callable actions a component class declares.
 *
 * @return array<int, ReflectionMethod>
 */
function matrixActions( string $class ): array
{
    $reflection = new ReflectionClass( $class );

    return array_values( array_filter(
        $reflection->getMethods( ReflectionMethod::IS_PUBLIC ),
        static fn ( ReflectionMethod $method ): bool => ! $method->isStatic()
            && str_starts_with( $method->getDeclaringClass()->getName(), 'ArtisanPackUI\\EcommerceAdminLivewire\\' )
            && 1 !== preg_match( MATRIX_NON_ACTIONS, $method->getName() )
            && [] === $method->getAttributes( Computed::class ),
    ) );
}

/**
 * Placeholder arguments for an action, typed to match its parameters.
 *
 * @return array<int, mixed>
 */
function matrixArguments( ReflectionMethod $method ): array
{
    return array_map( static function ( ReflectionParameter $parameter ): mixed {
        if ( $parameter->isDefaultValueAvailable() ) {
            return $parameter->getDefaultValue();
        }

        return match ( (string) $parameter->getType() ) {
            'int', '?int'       => 1,
            'float', '?float'   => 1.0,
            'bool', '?bool'     => true,
            'array', '?array'   => [],
            default             => $parameter->allowsNull() ? null : 'x',
        };
    }, $method->getParameters() );
}

dataset( 'admin components', static fn (): array => array_combine(
    array_keys( EcommerceAdminLivewireServiceProvider::LIVEWIRE_COMPONENTS ),
    array_map( static fn ( string $name ): array => [ $name ], array_keys( EcommerceAdminLivewireServiceProvider::LIVEWIRE_COMPONENTS ) ),
) );

it( 'refuses every admin route', function (): void {
    $routes = collect( Route::getRoutes()->getRoutes() )
        ->filter( static fn ( RoutingRoute $route ): bool => str_starts_with( (string) $route->getName(), 'artisanpack.ecommerce.admin.' ) );

    expect( $routes )->not->toBeEmpty();

    $user = makeUser();

    $routes->each( function ( RoutingRoute $route ) use ( $user ): void {
        $parameters = array_fill_keys( $route->parameterNames(), '1' );

        $this->actingAs( $user )
            ->call( $route->methods()[0], route( $route->getName(), $parameters ) )
            ->assertForbidden();
    } );
} );

it( 'refuses to mount the component', function ( string $name ): void {
    Livewire::actingAs( makeUser() )
        ->test( $name, MATRIX_MOUNT_PARAMS[ $name ] ?? [] )
        ->assertForbidden();
} )->with( 'admin components' );

it( 'refuses every public action', function ( string $name ): void {
    $class   = EcommerceAdminLivewireServiceProvider::LIVEWIRE_COMPONENTS[ $name ];
    $actions = matrixActions( $class );

    $admin = makeUser();
    grantAdmin( $admin );
    $intruder = makeUser();

    foreach ( $actions as $action ) {
        $component = Livewire::actingAs( $admin )->test( $name, MATRIX_MOUNT_PARAMS[ $name ] ?? [] )->assertOk();

        $this->actingAs( $intruder );

        $component->call( $action->getName(), ...matrixArguments( $action ) )->assertForbidden();
    }

    expect( true )->toBeTrue();
} )->with( 'admin components' );

it( 'registers every Livewire component class under src/Livewire', function (): void {
    $classes = collect( glob( __DIR__ . '/../../../src/Livewire/*.php' ) )
        ->map( static fn ( string $path ): string => 'ArtisanPackUI\\EcommerceAdminLivewire\\Livewire\\' . basename( $path, '.php' ) )
        ->sort()
        ->values()
        ->all();

    expect( collect( EcommerceAdminLivewireServiceProvider::LIVEWIRE_COMPONENTS )->sort()->values()->all() )->toBe( $classes );
} );
