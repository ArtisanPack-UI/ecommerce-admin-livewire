<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards\Edit;
use ArtisanPackUI\EcommerceAdminLivewire\Support\KanbanBoards;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanBoard.update', 'product.viewAny' ] );
    $this->actingAs( makeUser() );
} );

it( 'renders the board details, routing rules, columns, and automations', function (): void {
    $board = KanbanBoard::factory()->create( [ 'name' => 'Production', 'key' => 'production' ] );

    Livewire::test( Edit::class, [ 'board' => $board->id ] )
        ->assertOk()
        ->assertSet( 'form.name', 'Production' )
        ->assertSee( [ 'Edit Production', 'Routing rules', 'No rules: this board catches every order.' ] )
        ->assertSeeLivewire( 'artisanpack-ecommerce-admin-kanban-board-columns' )
        ->assertSeeLivewire( 'artisanpack-ecommerce-admin-kanban-board-automations' );
} );

it( 'is denied without kanbanBoard.view', function (): void {
    Gate::define( 'ecommerce.kanbanBoard.view', static fn (): bool => false );

    Livewire::test( Edit::class, [ 'board' => KanbanBoard::factory()->create()->id ] )->assertForbidden();
} );

it( 'is read-only without kanbanBoard.update', function (): void {
    Gate::define( 'ecommerce.kanbanBoard.update', static fn (): bool => false );

    Livewire::test( Edit::class, [ 'board' => KanbanBoard::factory()->create()->id ] )
        ->assertSet( 'readOnly', true )
        ->assertSee( 'You can view this board but not change it.' )
        ->assertDontSeeHtml( 'data-save' )
        ->call( 'save' )
        ->assertForbidden();
} );

it( 'saves the details and routing rules', function (): void {
    $board = KanbanBoard::factory()->create( [ 'name' => 'Production' ] );

    Livewire::test( Edit::class, [ 'board' => $board->id ] )
        ->set( 'form.name', 'Print production' )
        ->set( 'form.description', 'Orders with printed items' )
        ->set( 'ruleToAdd.conditions', 'min-subtotal' )->call( 'addRule', 'conditions' )
        ->set( 'ruleRows.conditions.0.config.amount', 10000 )
        ->assertSee( 'Orders match when the subtotal is at least $100.00.' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $board->fresh() )
        ->name->toBe( 'Print production' )
        ->description->toBe( 'Orders with printed items' )
        ->routing_rules->toBe( [ [ 'type' => 'min-subtotal', 'config' => [ 'amount' => 10000 ] ] ] );
} );

it( 'loads stored rules, including an "all" group', function (): void {
    $board = KanbanBoard::factory()->routing( [ 'all' => [ [ 'type' => 'customer-first-order', 'config' => [] ] ] ] )->create();

    Livewire::test( Edit::class, [ 'board' => $board->id ] )
        ->assertSet( 'complexRules', null )
        ->assertSee( 'Orders match when it is the customer&#039;s first order.', false )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $board->fresh()->routing_rules )->toBe( [ [ 'type' => 'customer-first-order', 'config' => [] ] ] );
} );

it( 'keeps nested rules it cannot edit until they are replaced', function (): void {
    $tree  = [ 'any' => [ [ 'type' => 'customer-first-order', 'config' => [] ], [ 'type' => 'min-subtotal', 'config' => [ 'amount' => 500 ] ] ] ];
    $board = KanbanBoard::factory()->routing( $tree )->create();

    $component = Livewire::test( Edit::class, [ 'board' => $board->id ] )
        ->assertSet( 'complexRules', $tree )
        ->assertSeeHtml( 'data-complex-rules' )
        ->set( 'form.name', 'Renamed' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $board->fresh()->routing_rules )->toBe( $tree );

    $component->call( 'replaceComplexRules' )
        ->assertSet( 'complexRules', null )
        ->call( 'save' );

    expect( $board->fresh()->routing_rules )->toBe( [] );
} );

it( 'validates the details and each rule', function (): void {
    $board = KanbanBoard::factory()->create( [ 'name' => 'Production' ] );

    Livewire::test( Edit::class, [ 'board' => $board->id ] )
        ->set( 'form.name', '' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.name' => 'required' ] )
        ->set( 'form.name', 'Production' )
        ->set( 'ruleToAdd.conditions', 'day-of-week' )->call( 'addRule', 'conditions' )
        ->call( 'save' )
        ->assertHasErrors( 'ruleRows.conditions.0.config.days' );

    expect( $board->fresh()->routing_rules )->toBe( [] );
} );

it( 'makes the board the default and takes the flag from the old one', function (): void {
    $old   = KanbanBoard::factory()->asDefault()->create();
    $board = KanbanBoard::factory()->create();

    Livewire::test( Edit::class, [ 'board' => $board->id ] )
        ->set( 'form.is_default', true )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $board->fresh()->is_default )->toBeTrue()
        ->and( $old->fresh()->is_default )->toBeFalse();
} );

it( 'keeps the default board switched on', function (): void {
    $board = KanbanBoard::factory()->asDefault()->create();

    Livewire::test( Edit::class, [ 'board' => $board->id ] )
        ->assertSeeHtml( 'data-board-is-default' )
        ->set( 'form.is_active', false )
        ->call( 'save' )
        ->assertHasErrors( 'form.is_active' );

    expect( $board->fresh()->is_active )->toBeTrue();
} );

it( 'warns when the default board has routing rules', function (): void {
    $board = KanbanBoard::factory()->asDefault()->routing( [ [ 'type' => 'customer-first-order', 'config' => [] ] ] )->create();

    Livewire::test( Edit::class, [ 'board' => $board->id ] )->assertSeeHtml( 'data-fallback-note' );
} );

it( 'flattens only simple condition trees', function ( mixed $tree, ?array $expected ): void {
    expect( KanbanBoards::flattenConditions( $tree ) )->toBe( $expected );
} )->with( [
    'empty object'   => [ [], [] ],
    'list'           => [ [ [ 'type' => 'a' ] ], [ [ 'type' => 'a', 'config' => [] ] ] ],
    'all group'      => [ [ 'all' => [ [ 'type' => 'a', 'config' => [ 'x' => 1 ] ] ] ], [ [ 'type' => 'a', 'config' => [ 'x' => 1 ] ] ] ],
    'any group'      => [ [ 'any' => [ [ 'type' => 'a' ] ] ], null ],
    'not'            => [ [ 'not' => [ 'type' => 'a' ] ], null ],
    'nested in list' => [ [ [ 'all' => [] ] ], null ],
    'leaf at root'   => [ [ 'type' => 'a' ], null ],
    'not an array'   => [ 'nope', null ],
] );

it( 'serves the page', function (): void {
    $board = KanbanBoard::factory()->create( [ 'name' => 'Production' ] );

    $this->get( route( 'artisanpack.ecommerce.admin.kanban-boards.edit', [ 'board' => $board->id ] ) )
        ->assertOk()
        ->assertSee( 'Edit Production' );
} );

it( 'caps routing rules on save even when rows arrive through wire:model', function (): void {
    $board = KanbanBoard::factory()->create();

    $component = Livewire::test( Edit::class, [ 'board' => $board->id ] )
        ->set( 'ruleToAdd.conditions', 'min-subtotal' )->call( 'addRule', 'conditions' )
        ->set( 'ruleRows.conditions.0.config.amount', 10000 );

    $row  = $component->get( 'ruleRows.conditions.0' );
    $rows = array_map( static fn ( int $index ): array => [ ...$row, 'id' => 'row' . $index ], range( 1, 25 ) );

    $component->set( 'ruleRows.conditions', $rows )
        ->call( 'save' )
        ->assertHasErrors( 'ruleRows.conditions' );
} );
