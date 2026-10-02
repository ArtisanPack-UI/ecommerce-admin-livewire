<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Kanban\OrderConditionEvaluator;
use ArtisanPackUI\EcommerceAdminLivewire\Support\RuleSummary;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\RoutingRulesHost;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.update', 'product.viewAny' ] );
    $this->actingAs( makeUser() );
} );

it( 'works on its own with conditions only, as kanban routing rules use it', function (): void {
    $component = Livewire::test( RoutingRulesHost::class )
        ->assertOk()
        ->assertSee( [ 'Routing rules', 'No rules: this board catches every order.', 'Every order matches.' ] )
        ->assertDontSee( 'Actions' )
        ->set( 'ruleToAdd.conditions', 'min-subtotal' )->call( 'addRule', 'conditions' )
        ->set( 'ruleRows.conditions.0.config.amount', 10000 )
        ->assertSee( 'Orders match when the subtotal is at least $100.00.' )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'saved', [ [ 'type' => 'min-subtotal', 'config' => [ 'amount' => 10000 ] ] ] );

    expect( app( OrderConditionEvaluator::class )->validate( $component->get( 'saved' ) ) )->toBeNull();
} );

it( 'loads stored conditions', function (): void {
    Livewire::test( RoutingRulesHost::class, [ 'stored' => [ [ 'type' => 'customer-first-order', 'config' => [] ] ] ] )
        ->assertSee( 'Orders match when it is the customer&#039;s first order.', false );
} );

it( 'validates each row before saving', function (): void {
    Livewire::test( RoutingRulesHost::class )
        ->set( 'ruleToAdd.conditions', 'day-of-week' )->call( 'addRule', 'conditions' )
        ->call( 'save' )
        ->assertHasErrors( 'ruleRows.conditions.0.config.days' )
        ->assertSet( 'saved', null );
} );

it( 'is denied without the host\'s ability', function (): void {
    Gate::define( 'ecommerce.kanbanBoard.update', static fn (): bool => false );

    Livewire::test( RoutingRulesHost::class )->assertForbidden();
} );

it( 'ignores unknown lists', function (): void {
    Livewire::test( RoutingRulesHost::class )
        ->set( 'ruleToAdd.actions', 'free-shipping' )
        ->call( 'addRule', 'actions' )
        ->call( 'removeRule', 'actions', 0 )
        ->call( 'moveRule', 'actions', 0, 1 )
        ->call( 'reorderRules', 'actions', [] )
        ->assertSet( 'ruleRows', [ 'conditions' => [] ] );
} );

it( 'joins phrases with commas and a final conjunction', function (): void {
    expect( RuleSummary::join( [], 'and' ) )->toBe( '' )
        ->and( RuleSummary::join( [ 'a' ], 'and' ) )->toBe( 'a' )
        ->and( RuleSummary::join( [ 'a', 'b' ], 'or' ) )->toBe( 'a or b' )
        ->and( RuleSummary::join( [ 'a', 'b', 'c' ], 'and' ) )->toBe( 'a, b and c' );
} );

it( 'describes the core conditions and actions', function ( string $registry, string $type, array $config, string $expected ): void {
    expect( RuleSummary::describe( $registry, $type, $config ) )->toBe( $expected );
} )->with( [
    'min subtotal'          => [ 'promotion-condition', 'min-subtotal', [ 'amount' => 5000 ], 'the subtotal is at least $50.00' ],
    'first order'           => [ 'promotion-condition', 'customer-first-order', [], 'it is the customer\'s first order' ],
    'groups'                => [ 'promotion-condition', 'customer-in-group', [ 'groups' => [ 'vip', 'wholesale' ] ], 'the customer is in vip or wholesale' ],
    'days'                  => [ 'promotion-condition', 'day-of-week', [ 'days' => [ 6, 7 ] ], 'it is Saturday or Sunday' ],
    'product types'         => [ 'promotion-condition', 'cart-contains-product-type', [ 'types' => [ 'digital' ], 'match' => 'only' ], 'the cart contains only items of type digital product' ],
    'any product'           => [ 'promotion-condition', 'cart-contains-product', [], 'the cart contains any product' ],
    'percent off cart'      => [ 'promotion-action', 'percent-off-cart', [ 'percent' => 12.5 ], '12.5% off the cart' ],
    'fixed off cart'        => [ 'promotion-action', 'fixed-off-cart', [ 'amount' => 1000 ], '$10.00 off the cart' ],
    'free shipping'         => [ 'promotion-action', 'free-shipping', [], 'free shipping' ],
    'buy x get y, discount' => [ 'promotion-action', 'buy-x-get-y', [ 'buy_quantity' => 3, 'get_quantity' => 1, 'percent' => 50 ], 'buy 3 of any product, get 1 50% off' ],
    'tiers'                 => [ 'promotion-action', 'tiered-discount', [ 'tiers' => [ [], [] ] ], 'a tiered discount with 2 tiers' ],
    'missing values'        => [ 'promotion-action', 'percent-off-cart', [], 'a percentage off the cart' ],
] );

it( 'falls back to the registry label for keys it does not know', function (): void {
    expect( RuleSummary::describe( 'promotion-action', 'not-registered', [] ) )->toBe( '"not-registered"' )
        ->and( RuleSummary::describe( 'shipping-method', 'flat-rate', [] ) )->toStartWith( '"' );
} );

it( 'drops rows the client forges', function (): void {
    Livewire::test( RoutingRulesHost::class )
        ->set( 'ruleRows.conditions.0.config.tiers', [] )
        ->set( 'ruleRows.bogus', [ [ 'id' => 'x', 'type' => 'min-subtotal', 'config' => [] ] ] )
        ->set( 'ruleRows.conditions.1', [ 'id' => "x'); alert(1)//", 'type' => 'min-subtotal', 'config' => [] ] )
        ->assertOk()
        ->assertSet( 'ruleRows', [ 'conditions' => [] ] )
        ->call( 'save' )
        ->assertSet( 'saved', [] );
} );
