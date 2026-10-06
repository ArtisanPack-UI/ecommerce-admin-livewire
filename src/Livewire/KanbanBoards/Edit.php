<?php

/**
 * Kanban board editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards;

use ArtisanPackUI\Ecommerce\Kanban\OrderConditionEvaluator;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithConfigForms;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithPickers;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithRuleBuilder;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\KanbanBoards;
use ArtisanPackUI\EcommerceAdminLivewire\Support\UnsavedChanges;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A kanban board's page (spec §7.10): its details and routing rules here,
 * with the columns and automations components below.
 *
 * - Routing rules are built with the same rule-builder rows as promotion
 *   conditions; every rule must match. A board with no rules catches every
 *   order, and the default board with no rules catches what no other board
 *   took. Rules stored with `any` / `not` nesting (through the REST API)
 *   show read-only, and stay as they are until "Replace with simple rules".
 * - The default flag cannot be switched off here: another board takes it
 *   with "Make default". The default board must stay switched on.
 * - The key is permanent.
 * - Without `kanbanBoard.update` the page is read-only.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Edit extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithConfigForms;
    use WithPickers;
    use WithRuleBuilder;

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
     * Whether the user may only look.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $readOnly = false;

    /**
     * The details form.
     *
     * @since 1.0.0
     *
     * @var array{name: string, description: string, is_active: bool, is_default: bool}
     */
    public array $form = [
        'name'        => '',
        'description' => '',
        'is_active'   => true,
        'is_default'  => false,
    ];

    /**
     * The stored routing rules, when they are too nested for the builder.
     *
     * @since 1.0.0
     *
     * @var array<int|string, mixed>|null
     */
    #[Locked]
    public ?array $complexRules = null;

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
        $this->readOnly    = ! $this->canEcommerce( 'update', $model );

        $this->fillFromBoard( $model );
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
     * Saves the details and routing rules.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function save(): void
    {
        $board = $this->board();

        $this->authorizeEcommerce( 'update', $board );

        $this->form['name']        = sanitizeText( (string) $this->form['name'] );
        $this->form['description'] = sanitizeText( (string) $this->form['description'] );

        $this->validate(
            [
                'form.name'        => [ 'required', 'string', 'max:255' ],
                'form.description' => [ 'nullable', 'string', 'max:2000' ],
                'form.is_active'   => [ 'boolean' ],
                'form.is_default'  => [ 'boolean' ],
            ],
            [],
            [
                'form.name'        => __( 'name' ),
                'form.description' => __( 'description' ),
            ],
        );

        $makeDefault = (bool) $this->form['is_default'];

        if ( ( $board->is_default || $makeDefault ) && ! (bool) $this->form['is_active'] ) {
            $this->addError( 'form.is_active', __( 'The default board must be switched on. Make another board the default first.' ) );

            return;
        }

        $routing = null === $this->complexRules ? $this->validateRules()['conditions'] : $this->complexRules;
        $error   = app( OrderConditionEvaluator::class )->validate( $routing );

        if ( null !== $error ) {
            $this->addError( 'ruleToAdd.conditions', $error );

            return;
        }

        DB::transaction( function () use ( $board, $routing, $makeDefault ): void {
            $board->fill( [
                'name'          => (string) $this->form['name'],
                'description'   => '' === (string) $this->form['description'] ? null : (string) $this->form['description'],
                'is_active'     => (bool) $this->form['is_active'],
                'routing_rules' => $routing,
            ] )->save();

            if ( $makeDefault && ! $board->is_default ) {
                KanbanBoards::makeDefault( $board );
            }

            KanbanBoards::ensureDefault();
        } );

        $this->loadedBoard = $board->fresh();
        $this->fillFromBoard( $this->loadedBoard );

        $this->dispatch( UnsavedChanges::SAVED_EVENT );
        $this->toastSuccess( __( 'Board ":name" saved.', [ 'name' => $board->name ] ) );
    }

    /**
     * Drops nested routing rules the builder cannot show, so the board can
     * be given simple rules instead. Nothing is stored until Save.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function replaceComplexRules(): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $this->complexRules = null;
        $this->ruleBuilderState( [ 'conditions' => [] ] );
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $board     = $this->board();
        $indexName = AdminNav::ROUTE_PREFIX . 'kanban-boards.index';

        return view( 'ecommerce-admin::livewire.kanban-boards.edit', [
            'board'        => $board,
            'ruleBuilder'  => $this->ruleBuilderViewData(),
            'complexJson'  => null === $this->complexRules ? null : (string) json_encode( $this->complexRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'indexUrl'     => Route::has( $indexName ) ? route( $indexName ) : null,
            'boardUrl'     => KanbanBoards::boardUrl( $board ),
            'fallbackNote' => $board->is_default && ( null !== $this->complexRules || [] !== ( $this->ruleRows['conditions'] ?? [] ) ),
        ] );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, array{registry: string, label: string, add: string, empty: string}>
     */
    protected function ruleBuilderLists(): array
    {
        return [
            'conditions' => [
                'registry' => 'promotion-condition',
                'label'    => __( 'Routing rules' ),
                'add'      => __( 'Add a rule' ),
                'empty'    => __( 'No rules: this board catches every order. Every rule you add must match.' ),
            ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeRuleBuilder(): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );
    }

    /**
     * Copies the board into the form and the rule builder.
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard  $board  The board.
     *
     * @return void
     */
    protected function fillFromBoard( KanbanBoard $board ): void
    {
        $this->form = [
            'name'        => (string) $board->name,
            'description' => (string) ( $board->description ?? '' ),
            'is_active'   => (bool) $board->is_active,
            'is_default'  => (bool) $board->is_default,
        ];

        $rows               = KanbanBoards::flattenConditions( $board->routing_rules ?? [] );
        $this->complexRules = null === $rows ? (array) $board->routing_rules : null;

        $this->ruleBuilderState( [ 'conditions' => $rows ?? [] ] );
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
