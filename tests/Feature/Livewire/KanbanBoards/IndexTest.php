<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards\Index;
use ArtisanPackUI\EcommerceAdminLivewire\Support\KanbanBoards;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanBoard.create', 'kanbanBoard.update', 'kanbanBoard.delete' ] );
    $this->actingAs( makeUser() );
} );

function defaultBoardKeys(): array
{
    return KanbanBoard::query()->where( 'is_default', true )->pluck( 'key' )->all();
}

it( 'lists the boards in position order with their counts', function (): void {
    $production = KanbanBoard::factory()->asDefault()->create( [ 'key' => 'production', 'name' => 'Production', 'position' => 1 ] );
    KanbanBoard::factory()->inactive()->create( [ 'key' => 'returns', 'name' => 'Returns', 'position' => 0 ] );
    OrderBoardAssignment::factory()->count( 2 )->create( [ 'board_id' => $production->id ] );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeInOrder( [ 'Returns', 'Production' ] )
        ->assertSeeHtml( 'data-board="production"' )
        ->assertSeeHtml( 'data-default-board' )
        ->assertSeeHtml( 'data-inactive-board' )
        ->assertSee( '2 cards' );
} );

it( 'shows an empty state without boards', function (): void {
    Livewire::test( Index::class )->assertSee( 'No kanban boards yet' );
} );

it( 'is denied without kanbanBoard.viewAny', function (): void {
    Gate::define( 'ecommerce.kanbanBoard.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'hides the write controls without the write abilities', function (): void {
    Gate::define( 'ecommerce.kanbanBoard.create', static fn (): bool => false );
    Gate::define( 'ecommerce.kanbanBoard.update', static fn (): bool => false );
    Gate::define( 'ecommerce.kanbanBoard.delete', static fn (): bool => false );
    KanbanBoard::factory()->create( [ 'name' => 'Production' ] );

    Livewire::test( Index::class )
        ->assertDontSee( 'Add board' )
        ->assertDontSee( 'Make default' )
        ->assertDontSeeHtml( 'Delete Production' )
        ->call( 'create' )
        ->assertForbidden();
} );

it( 'creates the first board as the default and opens its page', function (): void {
    $component = Livewire::test( Index::class )
        ->call( 'create' )
        ->assertSet( 'creating', true )
        ->assertSet( 'form.is_default', true )
        ->set( 'form.name', 'Print Production' )
        ->assertSet( 'form.key', 'print-production' )
        ->set( 'form.description', 'Orders that need printing' )
        ->call( 'save' )
        ->assertHasNoErrors();

    $board = KanbanBoard::query()->where( 'key', 'print-production' )->sole();

    expect( $board )
        ->name->toBe( 'Print Production' )
        ->description->toBe( 'Orders that need printing' )
        ->is_default->toBeTrue()
        ->is_active->toBeTrue();

    $component->assertRedirect( route( 'artisanpack.ecommerce.admin.kanban-boards.edit', [ 'board' => $board->id ] ) );
} );

it( 'keeps a typed key instead of following the name', function (): void {
    Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.key', 'shop:prod' )
        ->set( 'form.name', 'Production' )
        ->assertSet( 'form.key', 'shop:prod' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( KanbanBoard::query()->where( 'key', 'shop:prod' )->exists() )->toBeTrue();
} );

it( 'creates a second board as not the default unless asked', function (): void {
    KanbanBoard::factory()->asDefault()->create( [ 'key' => 'main' ] );

    Livewire::test( Index::class )
        ->call( 'create' )
        ->assertSet( 'form.is_default', false )
        ->set( 'form.name', 'Returns' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( defaultBoardKeys() )->toBe( [ 'main' ] );

    Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.name', 'Production' )
        ->set( 'form.is_default', true )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( defaultBoardKeys() )->toBe( [ 'production' ] );
} );

it( 'validates the new board', function (): void {
    KanbanBoard::factory()->create( [ 'key' => 'taken' ] );

    Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.name', '' )
        ->set( 'form.key', 'Not A Key!' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.name' => 'required', 'form.key' => 'regex' ] )
        ->set( 'form.name', 'Taken' )
        ->set( 'form.key', 'taken' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.key' => 'unique' ] );

    expect( KanbanBoard::query()->count() )->toBe( 1 );
} );

it( 'refuses a switched-off default board', function (): void {
    Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.name', 'Production' )
        ->set( 'form.is_active', false )
        ->call( 'save' )
        ->assertHasErrors( 'form.is_active' );

    expect( KanbanBoard::query()->count() )->toBe( 0 );
} );

it( 'moves the default flag so exactly one board has it', function (): void {
    KanbanBoard::factory()->asDefault()->create( [ 'key' => 'main' ] );
    $returns = KanbanBoard::factory()->create( [ 'key' => 'returns' ] );

    Livewire::test( Index::class )->call( 'makeDefault', $returns->id )->assertOk();

    expect( defaultBoardKeys() )->toBe( [ 'returns' ] );
} );

it( 'will not make a switched-off board the default', function (): void {
    KanbanBoard::factory()->asDefault()->create( [ 'key' => 'main' ] );
    $off = KanbanBoard::factory()->inactive()->create( [ 'key' => 'off' ] );

    $component = Livewire::test( Index::class )->call( 'makeDefault', $off->id );

    expect( defaultBoardKeys() )->toBe( [ 'main' ] )
        ->and( sentToasts( $component ) )->toContain( 'the default board must be active' );
} );

it( 'reorders boards with move up and move down', function (): void {
    $a = KanbanBoard::factory()->create( [ 'key' => 'a', 'name' => 'Alpha', 'position' => 0 ] );
    $b = KanbanBoard::factory()->create( [ 'key' => 'b', 'name' => 'Bravo', 'position' => 1 ] );
    $c = KanbanBoard::factory()->create( [ 'key' => 'c', 'name' => 'Charlie', 'position' => 2 ] );

    Livewire::test( Index::class )
        ->call( 'move', $c->id, -1 )
        ->assertSeeInOrder( [ 'Alpha', 'Charlie', 'Bravo' ] )
        ->call( 'move', $a->id, -1 );

    expect( KanbanBoard::query()->orderBy( 'position' )->pluck( 'key' )->all() )->toBe( [ 'a', 'c', 'b' ] );
} );

it( 'deletes a board after confirmation and hands the default to the next board', function (): void {
    $main = KanbanBoard::factory()->asDefault()->create( [ 'key' => 'main', 'name' => 'Main', 'position' => 0 ] );
    KanbanBoard::factory()->inactive()->create( [ 'key' => 'off', 'position' => 1 ] );
    KanbanBoard::factory()->create( [ 'key' => 'returns', 'name' => 'Returns', 'position' => 2 ] );
    OrderBoardAssignment::factory()->create( [ 'board_id' => $main->id ] );

    $component = Livewire::test( Index::class )
        ->call( 'confirmDelete', $main->id )
        ->assertSet( 'confirmingDelete', true )
        ->assertSeeHtml( 'data-delete-cards' )
        ->assertSee( '"Returns" becomes the default board.' );

    $token = $component->viewData( 'deleteToken' );

    $component->call( 'delete', $token )->assertSet( 'confirmingDelete', false );

    expect( KanbanBoard::query()->whereKey( $main->id )->exists() )->toBeFalse()
        ->and( OrderBoardAssignment::query()->where( 'board_id', $main->id )->exists() )->toBeFalse()
        ->and( defaultBoardKeys() )->toBe( [ 'returns' ] );
} );

it( 'deletes once per token', function (): void {
    $board = KanbanBoard::factory()->create();
    $other = KanbanBoard::factory()->create();

    $component = Livewire::test( Index::class )->call( 'confirmDelete', $board->id );
    $token     = $component->viewData( 'deleteToken' );
    $component->call( 'delete', $token );

    $component->call( 'confirmDelete', $other->id )->call( 'delete', $token );

    expect( KanbanBoard::query()->whereKey( $other->id )->exists() )->toBeTrue();
} );

it( 'shows the open board link only when the kanban satellite is installed', function (): void {
    KanbanBoard::factory()->create( [ 'key' => 'prod' ] );

    Livewire::test( Index::class )->assertDontSeeHtml( 'data-open-board' );

    addFilter( 'ap.ecommerceAdminLivewire.kanban.boardUrl', static fn ( ?string $url, KanbanBoard $board ): string => 'https://example.test/boards/' . $board->key );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-open-board' )
        ->assertSeeHtml( 'https://example.test/boards/prod' );
} );

it( 'links to the kanban satellite route when it is registered and active', function (): void {
    $board = KanbanBoard::factory()->create();

    expect( KanbanBoards::boardUrl( $board ) )->toBeNull();

    app( ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry::class )->register( [
        'package_name' => KanbanBoards::KANBAN_PACKAGE,
        'version'      => '1.0.0',
    ] );
    Illuminate\Support\Facades\Route::get( 'kanban/{board}', static fn (): string => 'board' )->name( KanbanBoards::BOARD_ROUTE );
    Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();

    expect( KanbanBoards::boardUrl( $board ) )->toBe( route( KanbanBoards::BOARD_ROUTE, [ 'board' => $board->id ] ) );
} );

it( 'serves the page', function (): void {
    KanbanBoard::factory()->create( [ 'name' => 'Production' ] );

    $this->get( route( 'artisanpack.ecommerce.admin.kanban-boards.index' ) )
        ->assertOk()
        ->assertSee( 'Production' );
} );

it( 'makes the first board the default even when the box is unticked, so it must be switched on', function (): void {
    Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.name', 'Paused' )
        ->set( 'form.is_default', false )
        ->set( 'form.is_active', false )
        ->call( 'save' )
        ->assertHasErrors( 'form.is_active' );

    expect( KanbanBoard::query()->count() )->toBe( 0 );
} );

it( 'refuses to reorder a board the user may not update', function (): void {
    $first  = KanbanBoard::factory()->asDefault()->create( [ 'position' => 0 ] );
    $second = KanbanBoard::factory()->create( [ 'position' => 1 ] );
    Gate::define( 'ecommerce.kanbanBoard.update', static fn ( $user, $board = null ): bool => ! ( $board instanceof KanbanBoard && $board->is( $second ) ) );

    Livewire::test( Index::class )->call( 'move', $first->id, 1 )->assertForbidden();
} );

it( 'refuses to delete the default board while every other board is switched off', function (): void {
    $main = KanbanBoard::factory()->asDefault()->create( [ 'key' => 'main', 'position' => 0 ] );
    KanbanBoard::factory()->inactive()->create( [ 'key' => 'off', 'position' => 1 ] );

    $component = Livewire::test( Index::class )
        ->call( 'confirmDelete', $main->id )
        ->assertSeeHtml( 'data-delete-blocked' )
        ->assertDontSeeHtml( 'data-delete-successor' );

    $component->call( 'delete', $component->viewData( 'deleteToken' ) );

    expect( KanbanBoard::query()->whereKey( $main->id )->exists() )->toBeTrue()
        ->and( defaultBoardKeys() )->toBe( [ 'main' ] )
        ->and( sentToasts( $component ) )->toContain( 'The default board can' );
} );

it( 'lets the last board be deleted', function (): void {
    $only = KanbanBoard::factory()->asDefault()->create();

    $component = Livewire::test( Index::class )
        ->call( 'confirmDelete', $only->id )
        ->assertDontSeeHtml( 'data-delete-blocked' );

    $component->call( 'delete', $component->viewData( 'deleteToken' ) );

    expect( KanbanBoard::query()->count() )->toBe( 0 );
} );
