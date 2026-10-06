<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Contracts\DescribesConfig;
use ArtisanPackUI\Ecommerce\Contracts\PromotionAction;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Registries\PromotionActionRegistry;
use ArtisanPackUI\Ecommerce\Support\DiscountLedger;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;

it( 'ships a schema for every core engine registry key', function (): void {
    $forms   = app( ConfigFormRegistry::class );
    $missing = [];
    $counts  = [];

    foreach ( ConfigFormRegistry::REGISTRIES as $name => $class ) {
        foreach ( app( $class )->keys() as $key ) {
            $counts[ $name ] = ( $counts[ $name ] ?? 0 ) + 1;

            if ( ! $forms->has( $name, $key ) ) {
                $missing[] = $name . ':' . $key;
            }
        }
    }

    expect( $missing )->toBe( [] )
        ->and( $counts )->toBe( [
            'promotion-condition' => 12,
            'promotion-action'    => 8,
            'shipping-method'     => 5,
            'kanban-trigger'      => 6,
            'kanban-widget'       => 8,
        ] );
} );

it( 'resolves every core schema without errors', function (): void {
    $forms = app( ConfigFormRegistry::class );

    foreach ( ConfigFormRegistry::REGISTRIES as $registry => $class ) {
        foreach ( app( $class )->keys() as $key ) {
            expect( $forms->schema( $registry, $key ) )->toBeArray();
        }
    }
} );

it( 'rejects malformed schemas', function ( array $schema ): void {
    expect( fn () => app( ConfigFormRegistry::class )->register( 'promotion-action', 'broken', $schema ) )->toThrow( InvalidArgumentException::class );
} )->with( [
    'missing label'    => [ [ [ 'name' => 'x', 'type' => 'text' ] ] ],
    'unknown type'     => [ [ [ 'name' => 'x', 'type' => 'colour', 'label' => 'X' ] ] ],
    'bad name'         => [ [ [ 'name' => 'x-y', 'type' => 'text', 'label' => 'X' ] ] ],
    'duplicate name'   => [ [ [ 'name' => 'x', 'type' => 'text', 'label' => 'X' ], [ 'name' => 'x', 'type' => 'number', 'label' => 'Y' ] ] ],
    'nested repeater'  => [ [ [ 'name' => 'x', 'type' => 'repeater', 'label' => 'X', 'fields' => [ [ 'name' => 'y', 'type' => 'repeater', 'label' => 'Y' ] ] ] ] ],
] );

it( 'lets a satellite register a schema for its own entry', function (): void {
    app( ConfigFormRegistry::class )->register( 'promotion-action', 'loyalty-points', [
        [ 'name' => 'points', 'type' => 'number', 'label' => 'Points', 'rules' => [ 'required', 'integer', 'min:1' ] ],
    ] );

    expect( app( ConfigFormRegistry::class )->schema( 'promotion-action', 'loyalty-points' )[0]['name'] )->toBe( 'points' )
        ->and( app( ConfigFormRegistry::class )->rules( 'promotion-action', 'loyalty-points', 'config' )['config.points'] )
        ->toContain( 'required', 'integer', 'min:1', 'numeric' );
} );

it( 'prefers the schema an engine entry declares itself', function (): void {
    $entry = new class implements DescribesConfig, PromotionAction {
        public function key(): string
        {
            return 'declared-action';
        }

        public function label(): string
        {
            return 'Declared';
        }

        public function apply( Cart $cart, DiscountLedger $ledger, array $config ): void
        {
        }

        /** @return array<int, array<string, mixed>> */
        public function configSchema(): array
        {
            return [ [ 'name' => 'from_engine', 'type' => 'boolean', 'label' => 'From the engine' ] ];
        }
    };

    app( PromotionActionRegistry::class )->register( 'declared-action', $entry );
    app( ConfigFormRegistry::class )->register( 'promotion-action', 'declared-action', [ [ 'name' => 'from_admin', 'type' => 'text', 'label' => 'From the admin' ] ] );

    expect( array_column( app( ConfigFormRegistry::class )->schema( 'promotion-action', 'declared-action' ), 'name' ) )->toBe( [ 'from_engine' ] );
} );

it( 'has no schema for an unknown key, so the form falls back to JSON', function (): void {
    expect( app( ConfigFormRegistry::class )->has( 'shipping-method', 'third-party-courier' ) )->toBeFalse()
        ->and( app( ConfigFormRegistry::class )->rules( 'shipping-method', 'third-party-courier', 'config' ) )->toHaveKey( 'config' );
} );

it( 'fills defaults for a new config', function (): void {
    expect( app( ConfigFormRegistry::class )->defaults( 'promotion-condition', 'cart-contains-product' ) )->toBe( [
        'product_ids'  => [],
        'variant_ids'  => [],
        'match'        => 'any',
        'min_quantity' => 1,
    ] );
} );

it( 'casts a validated config to the types the engine reads', function (): void {
    $schema = ConfigFormRegistry::normalize( [
        [ 'name' => 'amount', 'type' => 'money', 'label' => 'A' ],
        [ 'name' => 'percent', 'type' => 'percent', 'label' => 'P' ],
        [ 'name' => 'count', 'type' => 'number', 'label' => 'C' ],
        [ 'name' => 'on', 'type' => 'boolean', 'label' => 'O' ],
        [ 'name' => 'days', 'type' => 'weekday', 'label' => 'D' ],
        [ 'name' => 'products', 'type' => 'product', 'label' => 'Pr' ],
        [ 'name' => 'product', 'type' => 'product', 'label' => 'Pr', 'multiple' => false ],
        [ 'name' => 'groups', 'type' => 'tag', 'label' => 'G' ],
        [ 'name' => 'note', 'type' => 'text', 'label' => 'N' ],
        [ 'name' => 'tiers', 'type' => 'repeater', 'label' => 'T', 'fields' => [ [ 'name' => 'amount', 'type' => 'money', 'label' => 'A' ] ] ],
    ] );

    expect( ConfigFormRegistry::cast( $schema, [
        'amount'   => '1050',
        'percent'  => '12.5',
        'count'    => '3',
        'on'       => '1',
        'days'     => [ '5', '1', '1' ],
        'products' => [ '7', '3' ],
        'product'  => '9',
        'groups'   => [ ' vip ', '', 'vip', 'wholesale' ],
        'note'     => '',
        'tiers'    => [ [ 'amount' => '500', 'junk' => 'x' ] ],
        'injected' => 'dropped',
    ] ) )->toBe( [
        'amount'   => 1050,
        'percent'  => 12.5,
        'count'    => 3,
        'on'       => true,
        'days'     => [ 1, 5 ],
        'products' => [ 7, 3 ],
        'product'  => 9,
        'groups'   => [ 'vip', 'wholesale' ],
        'tiers'    => [ [ 'amount' => 500 ] ],
    ] );
} );

it( 'adapts every engine-declared schema without throwing', function (): void {
    Illuminate\Support\Facades\Log::spy();

    config()->set( 'artisanpack.ecommerce.kanban.dispatchable_jobs', [ Illuminate\Queue\CallQueuedClosure::class ] );

    $forms = app( ConfigFormRegistry::class );

    foreach ( ConfigFormRegistry::REGISTRIES as $name => $class ) {
        foreach ( app( $class )->keys() as $key ) {
            $entry = app( $class )->get( $key );

            if ( ! $entry instanceof DescribesConfig ) {
                continue;
            }

            $schema = $forms->schema( $name, $key );

            expect( $schema )->not->toBeNull( $name . ':' . $key );

            $declared = collect( ArtisanPackUI\Ecommerce\Support\ConfigSchema::normalize( $entry->configSchema() ) )->keyBy( 'name' );

            foreach ( $schema as $field ) {
                if ( in_array( $field['type'], [ 'select', 'multiselect' ], true ) && 'timezone' !== $field['name'] ) {
                    expect( $field['options'] )->not->toBeEmpty( $name . ':' . $key . '.' . $field['name'] );
                }

                if ( $declared[ $field['name'] ]['required'] ?? false ) {
                    expect( $field['rules'] )->toContain( 'required' );
                }
            }
        }
    }

    Illuminate\Support\Facades\Log::shouldNotHaveReceived( 'error' );
} );

it( 'maps engine field types onto admin fields', function (): void {
    $forms = app( ConfigFormRegistry::class );
    $field = static fn ( string $registry, string $key, string $name ): array => collect( $forms->schema( $registry, $key ) )->firstWhere( 'name', $name );

    expect( $field( 'promotion-action', 'add-free-item', 'variant_id' ) )->toMatchArray( [ 'type' => 'product', 'source' => 'variant', 'multiple' => false ] )
        ->and( $field( 'kanban-trigger', 'webhook', 'url' ) )->type->toBe( 'text' )
        ->and( $field( 'kanban-trigger', 'webhook', 'url' )['rules'] )->toContain( 'required', 'url' )
        ->and( $field( 'kanban-trigger', 'send-email', 'to' ) )->type->toBe( 'tag' )
        ->and( $field( 'promotion-condition', 'cart-contains-tag', 'tag_ids' ) )->toMatchArray( [ 'type' => 'product-tag', 'multiple' => true ] )
        ->and( $field( 'kanban-trigger', 'update-order-field', 'value' ) )->type->toBe( 'json' )
        ->and( $field( 'promotion-condition', 'day-of-week', 'timezone' ) )->type->toBe( 'select' )
        ->and( $field( 'promotion-condition', 'min-quantity', 'product_ids' )['hint'] )->toBe( 'Count only these products. Leave empty to count everything.' )
        ->and( $field( 'shipping-method', 'weight-based', 'unit' )['options'] )->toContain( [ 'id' => 'kg', 'name' => 'Kilograms' ] );
} );

it( 'drops an empty date range instead of storing empty ends', function (): void {
    $schema = ConfigFormRegistry::normalize( [ [ 'name' => 'window', 'type' => 'daterange', 'label' => 'W' ] ] );

    expect( ConfigFormRegistry::cast( $schema, [ 'window' => [ 'start' => '', 'end' => null ] ] ) )->toBe( [] )
        ->and( ConfigFormRegistry::cast( $schema, [ 'window' => [ 'start' => '2026-01-01', 'end' => null ] ] ) )->toBe( [ 'window' => [ 'start' => '2026-01-01', 'end' => null ] ] );
} );

it( 'reports a broken engine schema once and falls back to JSON', function (): void {
    $entry = new class implements DescribesConfig, PromotionAction {
        public function key(): string
        {
            return 'broken-action';
        }

        public function label(): string
        {
            return 'Broken';
        }

        public function apply( Cart $cart, DiscountLedger $ledger, array $config ): void
        {
        }

        /** @return array<int, array<string, mixed>> */
        public function configSchema(): array
        {
            return [ [ 'name' => 'x', 'type' => 'text', 'label' => 'X' ], [ 'name' => 'x', 'type' => 'text', 'label' => 'Again' ] ];
        }
    };

    app( PromotionActionRegistry::class )->register( 'broken-action', $entry );

    $reported = 0;
    app( Illuminate\Contracts\Debug\ExceptionHandler::class )->reportable( static function ( Throwable $exception ) use ( &$reported ): void {
        $reported++;
    } );

    $forms = app( ConfigFormRegistry::class );

    expect( $forms->schema( 'promotion-action', 'broken-action' ) )->toBeNull()
        ->and( $forms->schema( 'promotion-action', 'broken-action' ) )->toBeNull()
        ->and( $reported )->toBe( 1 );
} );
