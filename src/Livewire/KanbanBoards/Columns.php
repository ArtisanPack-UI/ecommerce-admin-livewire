<?php

/**
 * Kanban board columns panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards;

use ArtisanPackUI\Ecommerce\Models\KanbanAutomation;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ColorContrast;
use ArtisanPackUI\EcommerceAdminLivewire\Support\IconChoices;
use ArtisanPackUI\EcommerceAdminLivewire\Support\KanbanBoards;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A board's columns (spec §7.10): one column per order sub-status, with
 * optional label, colour, and icon overrides, a WIP limit, and the ordered
 * card widgets shown on its cards.
 *
 * - A column's sub-status cannot change, and the column cannot be deleted,
 *   while cards sit in it. Deleting a column also deletes the automations
 *   that start or end in it; the confirmation says how many.
 * - Card widgets come from the engine's `KanbanCardWidgetRegistry`. An
 *   empty list falls back to the board's, then the store's, default
 *   widgets. Widgets reorder with Move up / Move down.
 * - Columns reorder with Move up / Move down.
 * - Every write dispatches `kanban-columns-changed`, so the automations
 *   panel refreshes its column choices.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Columns extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithActionToken;

    /**
     * The most widgets one column shows.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_WIDGETS = 12;

    /**
     * The board id.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $boardId = 0;

    /**
     * Whether the column drawer is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $editing = false;

    /**
     * The column being edited, or null when adding.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $columnId = null;

    /**
     * The column form.
     *
     * @since 1.0.0
     *
     * @var array{substatus_id: int|string, label_override: string, color_override: string, icon_override: string, wip_limit: int|string|null, card_widgets: array<int, string>}
     */
    public array $form = [
        'substatus_id'   => '',
        'label_override' => '',
        'color_override' => '',
        'icon_override'  => '',
        'wip_limit'      => '',
        'card_widgets'   => [],
    ];

    /**
     * The widget chosen in the "add widget" picker.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $widgetToAdd = '';

    /**
     * The column waiting for delete confirmation.
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
     * The loaded board.
     *
     * @since 1.0.0
     *
     * @var KanbanBoard|null
     */
    protected ?KanbanBoard $loadedBoard = null;

    /**
     * Loads and authorizes the board.
     *
     * @since 1.0.0
     *
     * @param  int|KanbanBoard|string  $board  Board or id.
     *
     * @return void
     */
    public function mount( KanbanBoard|int|string $board ): void
    {
        $model = $board instanceof KanbanBoard ? $board : KanbanBoard::query()->findOrFail( (int) $board );

        $this->authorizeEcommerce( 'view', $model );

        $this->boardId     = (int) $model->id;
        $this->loadedBoard = $model;
    }

    /**
     * Re-checks access on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeEcommerce( 'view', $this->board() );
    }

    /**
     * Opens the drawer for a new column.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function create(): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $this->resetErrorBag();
        $this->columnId    = null;
        $this->widgetToAdd = '';
        $this->form        = [
            'substatus_id'   => '',
            'label_override' => '',
            'color_override' => '',
            'icon_override'  => '',
            'wip_limit'      => '',
            'card_widgets'   => [],
        ];
        $this->editing     = true;
    }

    /**
     * Opens the drawer for a column.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Column id.
     *
     * @return void
     */
    public function edit( int $id ): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $column = $this->column( $id );

        if ( null === $column ) {
            return;
        }

        $this->resetErrorBag();
        $this->columnId    = (int) $column->id;
        $this->widgetToAdd = '';
        $this->form        = [
            'substatus_id'   => (int) $column->substatus_id,
            'label_override' => (string) ( $column->label_override ?? '' ),
            'color_override' => (string) ( $column->color_override ?? '' ),
            'icon_override'  => (string) ( $column->icon_override ?? '' ),
            'wip_limit'      => null === $column->wip_limit ? '' : (int) $column->wip_limit,
            'card_widgets'   => array_values( array_map( 'strval', array_filter( (array) $column->card_widgets, 'is_string' ) ) ),
        ];
        $this->editing     = true;
    }

    /**
     * Adds the widget chosen in the picker to the end of the list.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addWidget(): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $widgets = $this->formWidgets();

        if ( ! array_key_exists( $this->widgetToAdd, KanbanBoards::widgetLabels() ) ) {
            $this->addError( 'widgetToAdd', __( 'Choose a widget first.' ) );

            return;
        }

        if ( in_array( $this->widgetToAdd, $widgets, true ) ) {
            $this->addError( 'widgetToAdd', __( 'This widget is already on the card.' ) );

            return;
        }

        if ( count( $widgets ) >= self::MAX_WIDGETS ) {
            $this->addError( 'widgetToAdd', __( 'A card can show at most :count widgets.', [ 'count' => self::MAX_WIDGETS ] ) );

            return;
        }

        $this->form['card_widgets'] = [ ...$widgets, $this->widgetToAdd ];
        $this->widgetToAdd          = '';

        $this->resetErrorBag( 'widgetToAdd' );
    }

    /**
     * Removes a widget from the list.
     *
     * @since 1.0.0
     *
     * @param  int  $index  Widget index.
     *
     * @return void
     */
    public function removeWidget( int $index ): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $widgets = $this->formWidgets();

        unset( $widgets[ $index ] );

        $this->form['card_widgets'] = array_values( $widgets );

        $this->resetErrorBag( 'form.card_widgets' );
    }

    /**
     * Moves a widget one place up or down.
     *
     * @since 1.0.0
     *
     * @param  int  $index   Widget index.
     * @param  int  $offset  -1 for up, 1 for down.
     *
     * @return void
     */
    public function moveWidget( int $index, int $offset ): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $widgets = $this->formWidgets();
        $target  = $index + $offset;

        if ( ! in_array( $offset, [ -1, 1 ], true ) || ! isset( $widgets[ $index ], $widgets[ $target ] ) ) {
            return;
        }

        [ $widgets[ $index ], $widgets[ $target ] ] = [ $widgets[ $target ], $widgets[ $index ] ];

        $this->form['card_widgets'] = $widgets;
    }

    /**
     * Saves the drawer.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function save(): void
    {
        $board = $this->board();

        $this->authorizeEcommerce( 'update', $board );

        if ( ! $this->editing ) {
            return;
        }

        $column = null === $this->columnId ? null : $this->column( $this->columnId );

        if ( null !== $this->columnId && null === $column ) {
            $this->editing  = false;
            $this->columnId = null;
            $this->toastWarning( __( 'This column was deleted.' ), __( 'Someone deleted it while you were editing, so your changes were not saved.' ) );

            return;
        }

        $this->form['color_override'] = strtoupper( trim( (string) $this->form['color_override'] ) );
        $this->form['icon_override']  = trim( (string) $this->form['icon_override'] );
        $this->form['card_widgets']   = $this->formWidgets();
        $this->form['wip_limit']      = '' === trim( (string) ( $this->form['wip_limit'] ?? '' ) ) ? null : $this->form['wip_limit'];

        $this->validate(
            [
                'form.substatus_id'   => [
                    'required',
                    'integer',
                    Rule::exists( OrderSubstatus::class, 'id' ),
                    Rule::unique( KanbanColumn::class, 'substatus_id' )->where( 'board_id', $board->id )->ignore( $column?->id ),
                ],
                'form.label_override' => [ 'nullable', 'string', 'max:120' ],
                'form.color_override' => [ 'nullable', 'string', 'regex:/^#[0-9A-F]{6}$/' ],
                'form.icon_override'  => [
                    'nullable',
                    'string',
                    'max:80',
                    ...( IconChoices::isAvailable() ? [ Rule::in( array_column( IconChoices::options(), 'id' ) ) ] : [] ),
                    static function ( string $attribute, mixed $value, Closure $fail ): void {
                        if ( is_string( $value ) && '' !== $value && ! IconChoices::exists( $value ) ) {
                            $fail( __( 'There is no icon with this name.' ) );
                        }
                    },
                ],
                'form.wip_limit'      => [ 'nullable', 'integer', 'min:1', 'max:100000' ],
                'form.card_widgets'   => [ 'array', 'max:' . self::MAX_WIDGETS ],
                'form.card_widgets.*' => [ 'string', 'distinct', Rule::in( array_keys( KanbanBoards::widgetLabels() ) ) ],
            ],
            [
                'form.substatus_id.unique'     => __( 'This board already has a column for that sub-status.' ),
                'form.color_override.regex'    => __( 'Enter the colour as a hex code like #3B82F6.' ),
                'form.icon_override.in'        => __( 'Choose an icon from the list.' ),
                'form.card_widgets.*.in'       => __( 'This widget is no longer available. Remove it.' ),
                'form.card_widgets.*.distinct' => __( 'This widget is on the card twice.' ),
            ],
            [
                'form.substatus_id'   => __( 'sub-status' ),
                'form.label_override' => __( 'label' ),
                'form.color_override' => __( 'colour' ),
                'form.icon_override'  => __( 'icon' ),
                'form.wip_limit'      => __( 'WIP limit' ),
                'form.card_widgets'   => __( 'card widgets' ),
            ],
        );

        if ( null !== $column && (int) $this->form['substatus_id'] !== (int) $column->substatus_id && $column->cardCount() > 0 ) {
            $this->addError( 'form.substatus_id', __( 'Move the cards out of this column before changing its sub-status.' ) );

            return;
        }

        $data = [
            'substatus_id'   => (int) $this->form['substatus_id'],
            'label_override' => '' === trim( (string) $this->form['label_override'] ) ? null : sanitizeText( trim( (string) $this->form['label_override'] ) ),
            'color_override' => '' === $this->form['color_override'] ? null : (string) $this->form['color_override'],
            'icon_override'  => '' === $this->form['icon_override'] ? null : (string) $this->form['icon_override'],
            'wip_limit'      => null === $this->form['wip_limit'] ? null : (int) $this->form['wip_limit'],
            'card_widgets'   => $this->form['card_widgets'],
        ];

        if ( null === $column ) {
            $data['position'] = (int) KanbanColumn::query()->where( 'board_id', $board->id )->max( 'position' ) + 1;
            $column           = $board->columns()->create( $data );
        } else {
            $column->fill( $data )->save();
        }

        $this->editing = false;
        $this->dispatch( 'kanban-columns-changed' );
        $this->toastSuccess( __( 'Column ":name" saved.', [ 'name' => $column->load( 'substatus' )->displayLabel() ] ) );
    }

    /**
     * Moves a column one place left (up) or right (down).
     *
     * @since 1.0.0
     *
     * @param  int  $id      Column id.
     * @param  int  $offset  -1 for up, 1 for down.
     *
     * @return void
     */
    public function move( int $id, int $offset ): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $ids = KanbanColumn::query()->where( 'board_id', $this->boardId )->orderBy( 'position' )->orderBy( 'id' )->pluck( 'id' )
            ->map( static fn ( mixed $columnId ): int => (int) $columnId )
            ->all();

        $order = KanbanBoards::swap( $ids, $id, $offset );

        if ( null !== $order ) {
            KanbanBoards::writePositions( KanbanColumn::class, $order );
            $this->dispatch( 'kanban-columns-changed' );
        }
    }

    /**
     * Asks to confirm deleting a column.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Column id.
     *
     * @return void
     */
    public function confirmDelete( int $id ): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $this->deletingId       = null === $this->column( $id ) ? null : $id;
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
     * Deletes the column waiting for confirmation, once per token.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the confirmation.
     *
     * @return void
     */
    public function delete( string $token ): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $column = null === $this->deletingId || ! $this->confirmingDelete ? null : $this->column( $this->deletingId );

        if ( null === $column ) {
            $this->cancelDelete();

            return;
        }

        if ( $column->cardCount() > 0 ) {
            $this->cancelDelete();
            $this->toastError( __( 'The column was not deleted.' ), __( 'Move the cards out of this column before deleting it.' ) );

            return;
        }

        $label   = $column->load( 'substatus' )->displayLabel();
        $deleted = $this->withActionToken( $token, 'delete-column', static function () use ( $column ): bool {
            $column->delete();

            return true;
        }, $column );

        $this->cancelDelete();

        if ( true === $deleted ) {
            $this->dispatch( 'kanban-columns-changed' );
            $this->toastSuccess( __( 'Column ":name" deleted.', [ 'name' => $label ] ) );
        }
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $board   = $this->board();
        $columns = KanbanColumn::query()->where( 'board_id', $board->id )->with( 'substatus' )->orderBy( 'position' )->orderBy( 'id' )->get();
        $cards   = OrderBoardAssignment::query()
            ->active()
            ->where( 'board_id', $board->id )
            ->groupBy( 'substatus_id' )
            ->select( 'substatus_id', DB::raw( 'COUNT(*) as aggregate' ) )
            ->pluck( 'aggregate', 'substatus_id' )
            ->all();

        $deleting = null === $this->deletingId || ! $this->confirmingDelete ? null : $columns->firstWhere( 'id', $this->deletingId );
        $widgets  = KanbanBoards::widgetLabels();
        $used     = $columns->pluck( 'substatus_id' )->map( static fn ( mixed $id ): int => (int) $id )->all();
        $current  = (int) ( $this->form['substatus_id'] ?? 0 );

        return view( 'ecommerce-admin::livewire.kanban-boards.columns', [
            'board'            => $board,
            'columns'          => $columns,
            'cardCounts'       => $cards,
            'canUpdate'        => $this->canEcommerce( 'update', $board ),
            'widgetLabels'     => $widgets,
            'widgetOptions'    => array_map( static fn ( string $key, string $label ): array => [ 'id' => $key, 'name' => $label ], array_keys( $widgets ), $widgets ),
            'defaultWidgets'   => array_values( array_filter( (array) ( $board->settings['card_widgets'] ?? config( 'artisanpack.ecommerce.kanban.default_card_widgets', [] ) ), 'is_string' ) ),
            'substatusOptions' => array_values( array_filter(
                KanbanBoards::substatusOptions(),
                static fn ( array $option ): bool => $option['id'] === $current || ! in_array( $option['id'], $used, true ),
            ) ),
            'contrastWarning'  => ColorContrast::warning( strtoupper( trim( (string) $this->form['color_override'] ) ) ),
            'iconPicker'       => IconChoices::isAvailable(),
            'iconOptions'      => IconChoices::isAvailable() ? IconChoices::options( (string) $this->form['icon_override'] ) : [],
            'deleting'         => $deleting,
            'deletingCards'    => null === $deleting ? 0 : (int) ( $cards[ $deleting->substatus_id ] ?? 0 ),
            'deletingRules'    => null === $deleting ? 0 : KanbanAutomation::query()
                ->where( 'board_id', $board->id )
                ->where( static fn ( $query ) => $query->where( 'from_column_id', $deleting->id )->orWhere( 'to_column_id', $deleting->id ) )
                ->count(),
            'deleteToken'      => null === $deleting ? null : $this->actionToken( 'delete-column', $deleting ),
        ] );
    }

    /**
     * The widgets in the form, as a clean list of keys.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function formWidgets(): array
    {
        return array_values( array_filter( (array) ( $this->form['card_widgets'] ?? [] ), 'is_string' ) );
    }

    /**
     * A column of this board.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Column id.
     *
     * @return KanbanColumn|null
     */
    protected function column( int $id ): ?KanbanColumn
    {
        return KanbanColumn::query()->where( 'board_id', $this->boardId )->find( $id );
    }

    /**
     * The board.
     *
     * @since 1.0.0
     *
     * @return KanbanBoard
     */
    protected function board(): KanbanBoard
    {
        return $this->loadedBoard ??= KanbanBoard::query()->findOrFail( $this->boardId );
    }
}
