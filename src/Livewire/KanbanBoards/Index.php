<?php

/**
 * Kanban boards screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards;

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\KanbanBoards;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The kanban boards list (spec §7.10): add, reorder, choose the default,
 * and delete boards. Columns, automations, and routing rules are edited on
 * each board's own page.
 *
 * - Exactly one board is the default. The first board is the default; a
 *   board becomes the default with "Make default", which takes the flag
 *   from the old one; deleting the default hands the flag to the next
 *   board (shown in the confirmation).
 * - Reordering is "move up" / "move down", so it works by keyboard. The
 *   order is the order routing walks the boards in.
 * - A new board's key is filled from its name until typed, and cannot be
 *   changed after the board is created.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Index extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithActionToken;

    /**
     * Whether the new-board drawer is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $creating = false;

    /**
     * The new board's fields.
     *
     * @since 1.0.0
     *
     * @var array{name: string, key: string, description: string, is_active: bool, is_default: bool}
     */
    public array $form = [
        'name'        => '',
        'key'         => '',
        'description' => '',
        'is_active'   => true,
        'is_default'  => false,
    ];

    /**
     * Whether the key was typed (so it stops following the name).
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $keyEdited = false;

    /**
     * The board waiting for delete confirmation.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $deletingId = null;

    /**
     * Whether the delete confirmation is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $confirmingDelete = false;

    /**
     * Authorizes the screen.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeEcommerce( 'viewAny', KanbanBoard::class );
    }

    /**
     * Re-authorizes the screen on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeEcommerce( 'viewAny', KanbanBoard::class );
    }

    /**
     * Fills the key from the name until the key is typed.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedFormName(): void
    {
        if ( ! $this->keyEdited ) {
            $this->form['key'] = Str::slug( (string) $this->form['name'] );
        }
    }

    /**
     * Stops the key following the name once it is typed.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedFormKey(): void
    {
        $this->keyEdited = true;
    }

    /**
     * Opens the new-board drawer.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function create(): void
    {
        $this->authorizeEcommerce( 'create', KanbanBoard::class );

        $this->resetErrorBag();
        $this->keyEdited = false;
        $this->form      = [
            'name'        => '',
            'key'         => '',
            'description' => '',
            'is_active'   => true,
            'is_default'  => ! KanbanBoard::query()->exists(),
        ];
        $this->creating  = true;
    }

    /**
     * Creates the board and opens its page.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function save(): void
    {
        $this->authorizeEcommerce( 'create', KanbanBoard::class );

        if ( ! $this->creating ) {
            return;
        }

        $this->form['name']        = sanitizeText( (string) $this->form['name'] );
        $this->form['description'] = sanitizeText( (string) $this->form['description'] );
        $this->form['key']         = Str::lower( trim( (string) $this->form['key'] ) );

        $this->validate(
            [
                'form.name'        => [ 'required', 'string', 'max:255' ],
                'form.key'         => [ 'required', 'string', 'max:120', 'regex:' . KanbanBoards::KEY_PATTERN, Rule::unique( KanbanBoard::class, 'key' ) ],
                'form.description' => [ 'nullable', 'string', 'max:2000' ],
                'form.is_active'   => [ 'boolean' ],
                'form.is_default'  => [ 'boolean' ],
            ],
            [
                'form.key.regex' => __( 'Use lowercase letters, numbers, and hyphens, like "production".' ),
            ],
            [
                'form.name'        => __( 'name' ),
                'form.key'         => __( 'key' ),
                'form.description' => __( 'description' ),
            ],
        );

        // The first board is always the default.
        if ( ! KanbanBoard::query()->exists() ) {
            $this->form['is_default'] = true;
        }

        if ( (bool) $this->form['is_default'] && ! (bool) $this->form['is_active'] ) {
            $this->addError( 'form.is_active', __( 'The default board must be switched on.' ) );

            return;
        }

        $board = DB::transaction( function (): KanbanBoard {
            $board = KanbanBoard::query()->create( [
                'name'          => (string) $this->form['name'],
                'key'           => (string) $this->form['key'],
                'description'   => '' === (string) $this->form['description'] ? null : (string) $this->form['description'],
                'routing_rules' => [],
                'is_active'     => (bool) $this->form['is_active'],
                'is_default'    => false,
                'position'      => (int) KanbanBoard::query()->max( 'position' ) + 1,
                'settings'      => [],
            ] );

            if ( (bool) $this->form['is_default'] ) {
                KanbanBoards::makeDefault( $board );
            }

            KanbanBoards::ensureDefault();

            return $board;
        } );

        $this->creating = false;
        $this->toastSuccess( __( 'Board ":name" created. Add its columns next.', [ 'name' => $board->name ] ) );

        $editRoute = AdminNav::ROUTE_PREFIX . 'kanban-boards.edit';

        if ( Route::has( $editRoute ) ) {
            $this->redirectRoute( $editRoute, [ 'board' => $board->id ] );
        }
    }

    /**
     * Makes a board the default.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Board id.
     *
     * @return void
     */
    public function makeDefault( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'kanbanBoard.update' );

        $board = KanbanBoard::query()->find( $id );

        if ( null === $board ) {
            return;
        }

        $this->authorizeEcommerce( 'update', $board );

        if ( ! $board->is_active ) {
            $this->toastError( __( 'The board was not made the default.' ), __( 'Switch the board on first: the default board must be active.' ) );

            return;
        }

        KanbanBoards::makeDefault( $board );

        $this->toastSuccess( __( '":name" is now the default board.', [ 'name' => $board->name ] ) );
    }

    /**
     * Moves a board one place up or down.
     *
     * @since 1.0.0
     *
     * @param  int  $id      Board id.
     * @param  int  $offset  -1 for up, 1 for down.
     *
     * @return void
     */
    public function move( int $id, int $offset ): void
    {
        $this->authorizeEcommerceAbility( 'kanbanBoard.update' );

        $ids = KanbanBoard::query()->orderBy( 'position' )->orderBy( 'id' )->pluck( 'id' )
            ->map( static fn ( mixed $boardId ): int => (int) $boardId )
            ->all();

        $order = KanbanBoards::swap( $ids, $id, $offset );

        if ( null === $order ) {
            return;
        }

        // Both boards that change places must be updatable.
        $index = (int) array_search( $id, $ids, true );

        foreach ( KanbanBoard::query()->whereKey( [ $id, $ids[ $index + $offset ] ?? $id ] )->get() as $board ) {
            $this->authorizeEcommerce( 'update', $board );
        }

        KanbanBoards::writePositions( KanbanBoard::class, $order );
    }

    /**
     * Asks to confirm deleting a board.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Board id.
     *
     * @return void
     */
    public function confirmDelete( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'kanbanBoard.delete' );

        $this->deletingId       = KanbanBoard::query()->whereKey( $id )->exists() ? $id : null;
        $this->confirmingDelete = null !== $this->deletingId;
    }

    /**
     * Dismisses the delete confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelDelete(): void
    {
        $this->deletingId       = null;
        $this->confirmingDelete = false;
    }

    /**
     * Deletes the board waiting for confirmation, once per token. Its
     * columns, automations, and cards go with it; the orders stay.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the confirmation.
     *
     * @return void
     */
    public function delete( string $token ): void
    {
        $this->authorizeEcommerceAbility( 'kanbanBoard.delete' );

        $board = null === $this->deletingId || ! $this->confirmingDelete ? null : KanbanBoard::query()->find( $this->deletingId );

        if ( null === $board ) {
            $this->cancelDelete();

            return;
        }

        $this->authorizeEcommerce( 'delete', $board );

        if ( KanbanBoards::deleteLeavesNoActiveDefault( $board ) ) {
            $this->cancelDelete();
            $this->toastError( __( 'The default board can\'t be deleted yet.' ), __( 'Switch on another board first, so it can become the default.' ) );

            return;
        }

        $name    = (string) $board->name;
        $deleted = $this->withActionToken( $token, 'delete', static fn (): bool => DB::transaction( static function () use ( $board ): bool {
            $board->delete();
            KanbanBoards::ensureDefault();

            return true;
        } ), $board );

        $this->cancelDelete();

        if ( true === $deleted ) {
            $this->toastSuccess( __( 'Board ":name" deleted.', [ 'name' => $name ] ) );
        }
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $boards = KanbanBoard::query()
            ->withCount( 'columns' )
            ->withCount( 'automations' )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get();

        $cards = OrderBoardAssignment::query()
            ->active()
            ->groupBy( 'board_id' )
            ->select( 'board_id', DB::raw( 'COUNT(*) as aggregate' ) )
            ->pluck( 'aggregate', 'board_id' )
            ->all();

        $deleting = null === $this->deletingId || ! $this->confirmingDelete ? null : $boards->firstWhere( 'id', $this->deletingId );
        $editable = Route::has( AdminNav::ROUTE_PREFIX . 'kanban-boards.edit' );

        return view( 'ecommerce-admin::livewire.kanban-boards.index', [
            'boards'        => $boards,
            'cardCounts'    => $cards,
            'editUrls'      => $editable ? $boards->mapWithKeys( static fn ( KanbanBoard $board ): array => [ $board->id => route( AdminNav::ROUTE_PREFIX . 'kanban-boards.edit', [ 'board' => $board->id ] ) ] )->all() : [],
            'boardUrls'     => $boards->mapWithKeys( static fn ( KanbanBoard $board ): array => [ $board->id => KanbanBoards::boardUrl( $board ) ] )->all(),
            'canCreate'     => $this->canEcommerce( 'create', KanbanBoard::class ),
            'canUpdate'     => Authorization::allows( auth()->user(), 'kanbanBoard.update' ),
            'canDelete'     => Authorization::allows( auth()->user(), 'kanbanBoard.delete' ),
            'deleting'      => $deleting,
            'deletingCards' => null === $deleting ? 0 : (int) ( $cards[ $deleting->id ] ?? 0 ),
            'successor'     => null !== $deleting && $deleting->is_default ? KanbanBoards::successor( $deleting ) : null,
            'deleteBlocked' => null !== $deleting && KanbanBoards::deleteLeavesNoActiveDefault( $deleting ),
            'deleteToken'   => null === $deleting ? null : $this->actionToken( 'delete', $deleting ),
        ] );
    }
}
