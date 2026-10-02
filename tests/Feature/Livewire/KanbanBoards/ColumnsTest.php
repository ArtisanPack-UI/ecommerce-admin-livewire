<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanAutomation;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards\Columns;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ColorContrast;
use ArtisanPackUI\EcommerceAdminLivewire\Support\IconChoices;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanBoard.update' ] );
    $this->actingAs( makeUser() );

    IconChoices::fake( false );

    $this->board = KanbanBoard::factory()->create();
} );

afterEach( function (): void {
    ColorContrast::fake( null );
    IconChoices::fake( null );
} );

function substatusId( string $key ): int
{
    return (int) OrderSubstatus::query()->where( 'key', $key )->value( 'id' );
}

it( 'lists the columns in order with card counts and WIP limits', function (): void {
    KanbanColumn::factory()->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'in-progress' ), 'position' => 1, 'wip_limit' => 10 ] );
    KanbanColumn::factory()->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'awaiting-payment' ), 'position' => 0, 'label_override' => 'Unpaid' ] );
    OrderBoardAssignment::factory()->count( 2 )->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'in-progress' ) ] );

    Livewire::test( Columns::class, [ 'board' => $this->board ] )
        ->assertOk()
        ->assertSeeInOrder( [ 'Unpaid', 'In Progress' ] )
        ->assertSee( '2 of 10 cards' )
        ->assertSee( 'Card widgets: board default' );
} );

it( 'is denied without kanbanBoard.view', function (): void {
    Gate::define( 'ecommerce.kanbanBoard.view', static fn (): bool => false );

    Livewire::test( Columns::class, [ 'board' => $this->board ] )->assertForbidden();
} );

it( 'hides the controls and refuses writes without kanbanBoard.update', function (): void {
    Gate::define( 'ecommerce.kanbanBoard.update', static fn (): bool => false );

    Livewire::test( Columns::class, [ 'board' => $this->board ] )
        ->assertDontSee( 'Add column' )
        ->call( 'create' )
        ->assertForbidden();
} );

it( 'adds a column with overrides, a WIP limit, and ordered card widgets', function (): void {
    Livewire::test( Columns::class, [ 'board' => $this->board ] )
        ->call( 'create' )
        ->set( 'form.substatus_id', substatusId( 'in-progress' ) )
        ->set( 'form.label_override', 'Packing' )
        ->set( 'form.color_override', '#1e3a8a' )
        ->set( 'form.icon_override', 'o-archive-box' )
        ->set( 'form.wip_limit', '10' )
        ->set( 'widgetToAdd', 'total' )->call( 'addWidget' )
        ->set( 'widgetToAdd', 'customer' )->call( 'addWidget' )
        ->set( 'widgetToAdd', 'days-in-column' )->call( 'addWidget' )
        ->call( 'moveWidget', 2, -1 )
        ->call( 'removeWidget', 0 )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'editing', false )
        ->assertDispatched( 'kanban-columns-changed' );

    expect( KanbanColumn::query()->where( 'board_id', $this->board->id )->sole() )
        ->substatus_id->toBe( substatusId( 'in-progress' ) )
        ->label_override->toBe( 'Packing' )
        ->color_override->toBe( '#1E3A8A' )
        ->icon_override->toBe( 'o-archive-box' )
        ->wip_limit->toBe( 10 )
        ->card_widgets->toBe( [ 'days-in-column', 'customer' ] );
} );

it( 'refuses unknown and duplicate widgets', function (): void {
    Livewire::test( Columns::class, [ 'board' => $this->board ] )
        ->call( 'create' )
        ->set( 'widgetToAdd', 'nope' )->call( 'addWidget' )
        ->assertHasErrors( 'widgetToAdd' )
        ->set( 'widgetToAdd', 'total' )->call( 'addWidget' )
        ->set( 'widgetToAdd', 'total' )->call( 'addWidget' )
        ->assertHasErrors( 'widgetToAdd' )
        ->assertSet( 'form.card_widgets', [ 'total' ] );
} );

it( 'flags a stored widget that is no longer registered', function (): void {
    $column = KanbanColumn::factory()->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'in-progress' ), 'card_widgets' => [ 'gone' ] ] );

    Livewire::test( Columns::class, [ 'board' => $this->board ] )
        ->call( 'edit', $column->id )
        ->assertSee( 'Unavailable (gone)' )
        ->call( 'save' )
        ->assertHasErrors( 'form.card_widgets.0' );
} );

it( 'validates the column', function (): void {
    KanbanColumn::factory()->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'in-progress' ) ] );

    Livewire::test( Columns::class, [ 'board' => $this->board ] )
        ->call( 'create' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.substatus_id' => 'required' ] )
        ->set( 'form.substatus_id', substatusId( 'in-progress' ) )
        ->set( 'form.color_override', 'blue' )
        ->set( 'form.wip_limit', '0' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.substatus_id' => 'unique', 'form.color_override' => 'regex', 'form.wip_limit' => 'min' ] );

    expect( KanbanColumn::query()->count() )->toBe( 1 );
} );

it( 'offers each sub-status once per board', function (): void {
    KanbanColumn::factory()->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'in-progress' ) ] );

    $options = collect( Livewire::test( Columns::class, [ 'board' => $this->board ] )->call( 'create' )->viewData( 'substatusOptions' ) );

    expect( $options->pluck( 'id' )->all() )->not->toContain( substatusId( 'in-progress' ) )
        ->and( $options->pluck( 'name' )->all() )->toContain( 'Pending: Awaiting Payment' );
} );

it( 'will not change the sub-status of a column with cards', function (): void {
    $column = KanbanColumn::factory()->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'in-progress' ) ] );
    OrderBoardAssignment::factory()->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'in-progress' ) ] );

    Livewire::test( Columns::class, [ 'board' => $this->board ] )
        ->call( 'edit', $column->id )
        ->set( 'form.substatus_id', substatusId( 'completed' ) )
        ->call( 'save' )
        ->assertHasErrors( 'form.substatus_id' );

    expect( $column->fresh()->substatus_id )->toBe( substatusId( 'in-progress' ) );
} );

it( 'warns about low contrast colours', function (): void {
    ColorContrast::fake( true );

    Livewire::test( Columns::class, [ 'board' => $this->board ] )
        ->call( 'create' )
        ->set( 'form.color_override', '#3B82F6' )
        ->assertSeeHtml( 'data-contrast-warning' );
} );

it( 'reorders columns', function (): void {
    $a = KanbanColumn::factory()->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'awaiting-payment' ), 'position' => 0 ] );
    $b = KanbanColumn::factory()->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'in-progress' ), 'position' => 1 ] );

    Livewire::test( Columns::class, [ 'board' => $this->board ] )
        ->call( 'move', $b->id, -1 )
        ->assertDispatched( 'kanban-columns-changed' );

    expect( KanbanColumn::query()->orderBy( 'position' )->pluck( 'id' )->all() )->toBe( [ $b->id, $a->id ] );
} );

it( 'ignores columns of other boards', function (): void {
    $other = KanbanColumn::factory()->create( [ 'substatus_id' => substatusId( 'in-progress' ), 'label_override' => 'Elsewhere' ] );

    Livewire::test( Columns::class, [ 'board' => $this->board ] )
        ->call( 'edit', $other->id )
        ->assertSet( 'editing', false )
        ->call( 'confirmDelete', $other->id )
        ->assertSet( 'confirmingDelete', false );
} );

it( 'deletes an empty column and its automations after confirmation', function (): void {
    $from = KanbanColumn::factory()->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'awaiting-payment' ) ] );
    $to   = KanbanColumn::factory()->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'in-progress' ) ] );
    KanbanAutomation::factory()->create( [ 'board_id' => $this->board->id, 'from_column_id' => $from->id, 'to_column_id' => $to->id ] );

    $component = Livewire::test( Columns::class, [ 'board' => $this->board ] )
        ->call( 'confirmDelete', $to->id )
        ->assertSeeHtml( 'data-delete-automations' );

    $component->call( 'delete', $component->viewData( 'deleteToken' ) )
        ->assertDispatched( 'kanban-columns-changed' );

    expect( KanbanColumn::query()->whereKey( $to->id )->exists() )->toBeFalse()
        ->and( KanbanAutomation::query()->count() )->toBe( 0 );
} );

it( 'will not delete a column with cards', function (): void {
    $column = KanbanColumn::factory()->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'in-progress' ) ] );
    OrderBoardAssignment::factory()->create( [ 'board_id' => $this->board->id, 'substatus_id' => substatusId( 'in-progress' ) ] );

    $component = Livewire::test( Columns::class, [ 'board' => $this->board ] )
        ->call( 'confirmDelete', $column->id )
        ->assertSeeHtml( 'data-delete-in-use' );

    $component->call( 'delete', $component->viewData( 'deleteToken' ) );

    expect( KanbanColumn::query()->whereKey( $column->id )->exists() )->toBeTrue()
        ->and( sentToasts( $component ) )->toContain( 'Move the cards out of this column before deleting it.' );
} );
