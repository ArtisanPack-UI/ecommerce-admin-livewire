<?php

/**
 * Kanban board automations panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards;

use ArtisanPackUI\Ecommerce\Kanban\OrderConditionEvaluator;
use ArtisanPackUI\Ecommerce\Models\KanbanAutomation;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithConfigForms;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithPickers;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithRuleBuilder;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\KanbanBoards;
use ArtisanPackUI\EcommerceAdminLivewire\Support\RuleSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A board's automations (spec §7.10): when a card moves into a column
 * (from one column, or from any), run a trigger.
 *
 * - Triggers come from the engine's `KanbanAutomationRegistry`; each
 *   trigger's config renders through the config-form renderer (spec §8.4),
 *   with a JSON editor for triggers that have no schema.
 * - Conditions use the rule-builder rows; every condition must match the
 *   order for the trigger to run. Conditions stored with `any` / `not`
 *   nesting show read-only and are kept until replaced.
 * - A stored signing `secret` is never sent back to the browser: leave the
 *   field empty to keep it, type a new one to replace it, or tick "Remove
 *   the signing secret".
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Automations extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithActionToken;
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
     * Whether the automation drawer is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $editing = false;

    /**
     * The automation being edited, or null when adding.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $automationId = null;

    /**
     * The automation form.
     *
     * @since 1.0.0
     *
     * @var array{from_column_id: int|string, to_column_id: int|string, trigger_key: string, trigger_config: array<string, mixed>|string, is_active: bool}
     */
    public array $form = [
        'from_column_id' => '',
        'to_column_id'   => '',
        'trigger_key'    => '',
        'trigger_config' => [],
        'is_active'      => true,
    ];

    /**
     * Whether the automation being edited has a stored signing secret.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $secretIsSet = false;

    /**
     * Whether to remove the stored signing secret on save.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $clearSecret = false;

    /**
     * The stored conditions, when they are too nested for the builder.
     *
     * @since 1.0.0
     *
     * @var array<int|string, mixed>|null
     */
    #[Locked]
    public ?array $complexConditions = null;

    /**
     * The automation waiting for delete confirmation.
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

        $this->ruleBuilderState( [ 'conditions' => [] ] );
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
     * Re-renders when the columns panel changes the columns.
     *
     * @since 1.0.0
     *
     * @return void
     */
    #[On( 'kanban-columns-changed' )]
    public function refreshColumns(): void
    {
        $this->authorizeEcommerce( 'view', $this->board() );
    }

    /**
     * Resets the trigger settings when the trigger changes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedFormTriggerKey(): void
    {
        $this->resetErrorBag( 'form.trigger_config' );
        $this->form['trigger_config'] = $this->triggerConfigState( (string) $this->form['trigger_key'], [] );
        $this->clearSecret            = false;

        // Switching back to the stored trigger keeps its stored secret, so
        // say so (and offer to remove it).
        $automation        = null === $this->automationId ? null : $this->automation( $this->automationId );
        $stored            = null === $automation ? null : ( ( (array) $automation->trigger_config )['secret'] ?? null );
        $this->secretIsSet = null !== $automation
            && (string) $this->form['trigger_key'] === (string) $automation->trigger_key
            && is_string( $stored ) && '' !== $stored;
    }

    /**
     * Opens the drawer for a new automation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function create(): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $this->resetErrorBag();
        $this->automationId      = null;
        $this->secretIsSet       = false;
        $this->clearSecret       = false;
        $this->complexConditions = null;
        $this->form              = [
            'from_column_id' => '',
            'to_column_id'   => '',
            'trigger_key'    => '',
            'trigger_config' => [],
            'is_active'      => true,
        ];
        $this->ruleBuilderState( [ 'conditions' => [] ] );
        $this->editing           = true;
    }

    /**
     * Opens the drawer for an automation.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Automation id.
     *
     * @return void
     */
    public function edit( int $id ): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $automation = $this->automation( $id );

        if ( null === $automation ) {
            return;
        }

        $config = (array) $automation->trigger_config;

        $this->resetErrorBag();
        $this->automationId = (int) $automation->id;
        $this->secretIsSet  = is_string( $config['secret'] ?? null ) && '' !== $config['secret'];
        $this->clearSecret  = false;

        if ( $this->secretIsSet ) {
            $config['secret'] = '';
        }

        $this->form = [
            'from_column_id' => null === $automation->from_column_id ? '' : (int) $automation->from_column_id,
            'to_column_id'   => (int) $automation->to_column_id,
            'trigger_key'    => (string) $automation->trigger_key,
            'trigger_config' => $this->triggerConfigState( (string) $automation->trigger_key, $config ),
            'is_active'      => (bool) $automation->is_active,
        ];

        $rows                    = KanbanBoards::flattenConditions( $automation->conditions ?? [] );
        $this->complexConditions = null === $rows ? (array) $automation->conditions : null;

        $this->ruleBuilderState( [ 'conditions' => $rows ?? [] ] );

        $this->editing = true;
    }

    /**
     * Drops nested conditions the builder cannot show. Nothing is stored
     * until Save.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function replaceComplexConditions(): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $this->complexConditions = null;
        $this->ruleBuilderState( [ 'conditions' => [] ] );
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

        $automation = null === $this->automationId ? null : $this->automation( $this->automationId );

        if ( null !== $this->automationId && null === $automation ) {
            $this->editing      = false;
            $this->automationId = null;
            $this->toastWarning( __( 'This automation was deleted.' ), __( 'Someone deleted it while you were editing, so your changes were not saved.' ) );

            return;
        }

        $this->form['from_column_id'] = '' === (string) $this->form['from_column_id'] ? null : $this->form['from_column_id'];

        $onBoard = Rule::exists( KanbanColumn::class, 'id' )->where( 'board_id', $board->id );

        $this->validate(
            [
                'form.from_column_id' => [ 'nullable', 'integer', $onBoard ],
                'form.to_column_id'   => [ 'required', 'integer', $onBoard ],
                'form.trigger_key'    => [ 'required', 'string', Rule::in( array_keys( KanbanBoards::triggerLabels() ) ) ],
                'form.is_active'      => [ 'boolean' ],
            ],
            [
                'form.trigger_key.in' => __( 'This trigger is no longer available. Choose another.' ),
            ],
            [
                'form.from_column_id' => __( 'from column' ),
                'form.to_column_id'   => __( 'to column' ),
                'form.trigger_key'    => __( 'trigger' ),
            ],
        );

        if ( null !== $this->form['from_column_id'] && (int) $this->form['from_column_id'] === (int) $this->form['to_column_id'] ) {
            $this->addError( 'form.from_column_id', __( 'Choose a different column from the one the card moves to.' ) );

            return;
        }

        $key = (string) $this->form['trigger_key'];

        if ( ! app( ConfigFormRegistry::class )->has( 'kanban-trigger', $key ) ) {
            $decoded = json_decode( (string) $this->form['trigger_config'], true );

            if ( ! is_array( $decoded ) || array_is_list( $decoded ) && [] !== $decoded ) {
                $this->addError( 'form.trigger_config', __( 'The settings must be a JSON object.' ) );

                return;
            }
        }

        $config     = $this->validateConfigForm( 'kanban-trigger', $key, 'form.trigger_config' );
        $conditions = null === $this->complexConditions ? $this->validateRules()['conditions'] : $this->complexConditions;
        $error      = app( OrderConditionEvaluator::class )->validate( $conditions );

        if ( null !== $error ) {
            $this->addError( 'ruleToAdd.conditions', $error );

            return;
        }

        $config = $this->withStoredSecret( $config, $automation, $key );

        $data = [
            'from_column_id' => null === $this->form['from_column_id'] ? null : (int) $this->form['from_column_id'],
            'to_column_id'   => (int) $this->form['to_column_id'],
            'trigger_key'    => $key,
            'trigger_config' => $config,
            'conditions'     => $conditions,
            'is_active'      => (bool) $this->form['is_active'],
        ];

        null === $automation
            ? $board->automations()->create( $data )
            : $automation->fill( $data )->save();

        // Never leave a typed secret in the component snapshot.
        if ( is_array( $this->form['trigger_config'] ?? null ) && array_key_exists( 'secret', $this->form['trigger_config'] ) ) {
            $this->form['trigger_config']['secret'] = '';
        }

        $this->editing = false;
        $this->toastSuccess( __( 'Automation saved.' ) );
    }

    /**
     * Switches an automation on or off.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Automation id.
     *
     * @return void
     */
    public function toggleActive( int $id ): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $automation = $this->automation( $id );

        if ( null === $automation ) {
            return;
        }

        $automation->is_active = ! $automation->is_active;
        $automation->save();

        $this->toastSuccess( $automation->is_active ? __( 'Automation switched on.' ) : __( 'Automation switched off.' ) );
    }

    /**
     * Asks to confirm deleting an automation.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Automation id.
     *
     * @return void
     */
    public function confirmDelete( int $id ): void
    {
        $this->authorizeEcommerce( 'update', $this->board() );

        $this->deletingId       = null === $this->automation( $id ) ? null : $id;
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
     * Deletes the automation waiting for confirmation, once per token.
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

        $automation = null === $this->deletingId || ! $this->confirmingDelete ? null : $this->automation( $this->deletingId );

        if ( null === $automation ) {
            $this->cancelDelete();

            return;
        }

        $deleted = $this->withActionToken( $token, 'delete-automation', static function () use ( $automation ): bool {
            $automation->delete();

            return true;
        }, $automation );

        $this->cancelDelete();

        if ( true === $deleted ) {
            $this->toastSuccess( __( 'Automation deleted.' ) );
        }
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $board       = $this->board();
        $columns     = KanbanColumn::query()->where( 'board_id', $board->id )->with( 'substatus' )->orderBy( 'position' )->orderBy( 'id' )->get();
        $labels      = $columns->mapWithKeys( static fn ( KanbanColumn $column ): array => [ (int) $column->id => $column->displayLabel() ] )->all();
        $triggers    = KanbanBoards::triggerLabels();
        $automations = KanbanAutomation::query()->where( 'board_id', $board->id )->orderBy( 'id' )->get();

        $rows = $automations->map( static function ( KanbanAutomation $automation ) use ( $labels, $triggers ): array {
            $flat = KanbanBoards::flattenConditions( $automation->conditions ?? [] );

            return [
                'id'         => (int) $automation->id,
                'from'       => null === $automation->from_column_id ? __( 'Any column' ) : ( $labels[ (int) $automation->from_column_id ] ?? __( 'Unknown column' ) ),
                'to'         => $labels[ (int) $automation->to_column_id ] ?? __( 'Unknown column' ),
                'trigger'    => $triggers[ (string) $automation->trigger_key ] ?? __( 'Unavailable (:type)', [ 'type' => $automation->trigger_key ] ),
                'conditions' => null === $flat ? __( 'Orders match nested conditions.' ) : RuleSummary::conditions( $flat ),
                'is_active'  => (bool) $automation->is_active,
            ];
        } )->all();

        $columnOptions = array_map( static fn ( int $id, string $label ): array => [ 'id' => $id, 'name' => $label ], array_keys( $labels ), $labels );
        $deleting      = null === $this->deletingId || ! $this->confirmingDelete ? null : collect( $rows )->firstWhere( 'id', $this->deletingId );

        return view( 'ecommerce-admin::livewire.kanban-boards.automations', [
            'board'          => $board,
            'automations'    => $rows,
            'hasColumns'     => [] !== $labels,
            'canUpdate'      => $this->canEcommerce( 'update', $board ),
            'columnOptions'  => $columnOptions,
            'triggerOptions' => array_map( static fn ( string $key, string $label ): array => [ 'id' => $key, 'name' => $label ], array_keys( $triggers ), $triggers ),
            'ruleBuilder'    => $this->ruleBuilderViewData(),
            'complexJson'    => null === $this->complexConditions ? null : (string) json_encode( $this->complexConditions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'deleting'       => $deleting,
            'deleteToken'    => null === $deleting ? null : $this->actionToken( 'delete-automation', $this->automation( (int) $deleting['id'] ) ),
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
                'label'    => __( 'Conditions' ),
                'add'      => __( 'Add a condition' ),
                'empty'    => __( 'No conditions: the automation runs for every card. Every condition you add must match.' ),
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
     * Keeps the stored signing secret when the field was left empty.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>   $config      The validated config.
     * @param  KanbanAutomation|null  $automation  The stored automation.
     * @param  string                 $key         The trigger key.
     *
     * @return array<string, mixed>
     */
    protected function withStoredSecret( array $config, ?KanbanAutomation $automation, string $key ): array
    {
        $stored = null === $automation ? null : ( ( (array) $automation->trigger_config )['secret'] ?? null );

        if ( $this->clearSecret ) {
            unset( $config['secret'] );

            return $config;
        }

        $typed = $config['secret'] ?? null;

        if ( ( null === $typed || '' === $typed ) && is_string( $stored ) && '' !== $stored && $key === (string) $automation?->trigger_key ) {
            $config['secret'] = $stored;
        }

        return $config;
    }

    /**
     * The config form state for a trigger.
     *
     * @since 1.0.0
     *
     * @param  string                $key     The trigger key.
     * @param  array<string, mixed>  $config  The stored config.
     *
     * @return array<string, mixed>|string
     */
    protected function triggerConfigState( string $key, array $config ): array|string
    {
        return '' === $key ? [] : $this->configFormState( 'kanban-trigger', $key, $config );
    }

    /**
     * An automation of this board.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Automation id.
     *
     * @return KanbanAutomation|null
     */
    protected function automation( int $id ): ?KanbanAutomation
    {
        return KanbanAutomation::query()->where( 'board_id', $this->boardId )->find( $id );
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
