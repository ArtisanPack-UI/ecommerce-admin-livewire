<?php

/**
 * Order statuses screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\OrderStatuses;

use ArtisanPackUI\Ecommerce\Exceptions\OrderSubstatusWriteException;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\Ecommerce\Services\OrderSubstatusService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ColorContrast;
use ArtisanPackUI\EcommerceAdminLivewire\Support\IconChoices;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StatusPresenter;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Order sub-statuses grouped under the six system statuses (spec §7.10):
 * create, edit (label, colour, icon, terminal flag), reorder within a
 * system status, and delete.
 *
 * - Writes go through the engine's `OrderSubstatusService`, which refuses a
 *   duplicate key, a bad colour, and a delete while orders, board cards, or
 *   kanban columns use the sub-status (or when it is the last one of its
 *   system status). Field errors land on the matching form field.
 * - The key is set on create and read-only afterwards, since automations
 *   and integrations refer to it.
 * - The delete confirmation shows the in-use counts up front and disables
 *   the delete when it would be refused; the service still has the last
 *   word and its message is shown when it refuses.
 * - With `artisanpack-ui/accessibility` installed, a colour whose badge
 *   text falls below 4.5:1 shows a warning (it does not block the save).
 *   With `artisanpack-ui/icons` installed, the icon is chosen from a
 *   searchable list; otherwise it is typed.
 * - Reordering is "move up" / "move down", so it works by keyboard.
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
     * Whether the edit drawer is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $editing = false;

    /**
     * The sub-status being edited, or null when creating.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $substatusId = null;

    /**
     * The form fields.
     *
     * @since 1.0.0
     *
     * @var array{system_status: string, key: string, label: string, color: string, icon: string, is_terminal: bool}
     */
    public array $form = [
        'system_status' => '',
        'key'           => '',
        'label'         => '',
        'color'         => '',
        'icon'          => '',
        'is_terminal'   => false,
    ];

    /**
     * The sub-status waiting for delete confirmation.
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
        $this->authorizeEcommerce( 'viewAny', OrderSubstatus::class );
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
        $this->authorizeEcommerce( 'viewAny', OrderSubstatus::class );
    }

    /**
     * Opens the drawer for a new sub-status under a system status.
     *
     * @since 1.0.0
     *
     * @param  string  $systemStatus  System status.
     *
     * @return void
     */
    public function create( string $systemStatus ): void
    {
        $this->authorizeEcommerce( 'create', OrderSubstatus::class );

        if ( ! in_array( $systemStatus, OrderSubstatusService::systemStatuses(), true ) ) {
            return;
        }

        $this->resetErrorBag();
        $this->substatusId = null;
        $this->form        = [
            'system_status' => $systemStatus,
            'key'           => '',
            'label'         => '',
            'color'         => '',
            'icon'          => '',
            'is_terminal'   => in_array( $systemStatus, [ 'complete', 'cancelled', 'refunded', 'failed' ], true ),
        ];
        $this->editing     = true;
    }

    /**
     * Opens the drawer for an existing sub-status.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Sub-status id.
     *
     * @return void
     */
    public function edit( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'orderSubstatus.update' );

        $substatus = OrderSubstatus::query()->find( $id );

        if ( null === $substatus ) {
            return;
        }

        $this->resetErrorBag();
        $this->substatusId = (int) $substatus->id;
        $this->form        = [
            'system_status' => (string) $substatus->system_status,
            'key'           => (string) $substatus->key,
            'label'         => (string) $substatus->label,
            'color'         => (string) ( $substatus->color ?? '' ),
            'icon'          => (string) ( $substatus->icon ?? '' ),
            'is_terminal'   => (bool) $substatus->is_terminal,
        ];
        $this->editing     = true;
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
        $substatus = null === $this->substatusId ? null : OrderSubstatus::query()->find( $this->substatusId );

        if ( null === $substatus ) {
            $this->authorizeEcommerce( 'create', OrderSubstatus::class );

            if ( null !== $this->substatusId ) {
                // Deleted elsewhere while the drawer was open: don't recreate it.
                $this->editing     = false;
                $this->substatusId = null;
                $this->toastWarning( __( 'This sub-status was deleted.' ), __( 'Someone deleted it while you were editing, so your changes were not saved.' ) );

                return;
            }

            if ( ! $this->editing || '' === $this->form['system_status'] ) {
                return;
            }
        } else {
            $this->authorizeEcommerceAbility( 'orderSubstatus.update', $substatus );
        }

        $this->form['color'] = strtoupper( trim( (string) $this->form['color'] ) );

        $this->validate(
            [
                'form.key'         => [ 'nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/' ],
                'form.label'       => [ 'required', 'string', 'max:120' ],
                'form.color'       => [ 'nullable', 'string', 'regex:/^#[0-9A-F]{6}$/' ],
                'form.icon'        => [
                    'nullable',
                    'string',
                    'max:80',
                    ...( IconChoices::isAvailable() ? [ Rule::in( array_column( IconChoices::options(), 'id' ) ) ] : [] ),
                    static function ( string $attribute, mixed $value, Closure $fail ): void {
                        if ( is_string( $value ) && '' !== trim( $value ) && ! IconChoices::exists( trim( $value ) ) ) {
                            $fail( __( 'There is no icon with this name.' ) );
                        }
                    },
                ],
                'form.is_terminal' => [ 'boolean' ],
            ],
            [
                'form.key.regex'   => __( 'Use lowercase letters, numbers, hyphens, or underscores, like "awaiting-label".' ),
                'form.color.regex' => __( 'Enter the colour as a hex code like #3B82F6.' ),
                'form.icon.in'     => __( 'Choose an icon from the list.' ),
            ],
            [
                'form.key'         => __( 'key' ),
                'form.label'       => __( 'label' ),
                'form.color'       => __( 'colour' ),
                'form.icon'        => __( 'icon' ),
                'form.is_terminal' => __( 'terminal' ),
            ],
        );

        $data = [
            'label'       => (string) $this->form['label'],
            'color'       => '' === $this->form['color'] ? null : (string) $this->form['color'],
            'icon'        => '' === trim( (string) $this->form['icon'] ) ? null : trim( (string) $this->form['icon'] ),
            'is_terminal' => (bool) $this->form['is_terminal'],
        ];

        $service = app( OrderSubstatusService::class );

        try {
            if ( null === $substatus ) {
                $data['system_status'] = (string) $this->form['system_status'];

                if ( '' !== trim( (string) $this->form['key'] ) ) {
                    $data['key'] = trim( (string) $this->form['key'] );
                }

                $substatus = $service->create( $data );
            } else {
                $substatus = $service->update( $substatus, $data );
            }
        } catch ( OrderSubstatusWriteException $exception ) {
            foreach ( $exception->errors as $error ) {
                $field = in_array( $error['field'] ?? null, [ 'key', 'label', 'color', 'icon' ], true ) ? $error['field'] : 'label';

                $this->addError( 'form.' . $field, (string) $error['message'] );
            }

            return;
        }

        $this->editing = false;
        $this->toastSuccess( __( 'Sub-status ":name" saved.', [ 'name' => $substatus->label ] ) );
    }

    /**
     * Moves a sub-status one place up or down within its system status.
     *
     * @since 1.0.0
     *
     * @param  int  $id      Sub-status id.
     * @param  int  $offset  -1 for up, 1 for down.
     *
     * @return void
     */
    public function move( int $id, int $offset ): void
    {
        $this->authorizeEcommerceAbility( 'orderSubstatus.update' );

        $substatus = OrderSubstatus::query()->find( $id );

        if ( null === $substatus || ! in_array( $offset, [ -1, 1 ], true ) ) {
            return;
        }

        $siblings = OrderSubstatus::query()
            ->where( 'system_status', $substatus->system_status )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->pluck( 'id' )
            ->map( static fn ( mixed $sibling ): int => (int) $sibling )
            ->all();

        $from = array_search( (int) $substatus->id, $siblings, true );
        $to   = false === $from ? false : $from + $offset;

        if ( false === $to || $to < 0 || $to >= count( $siblings ) ) {
            return;
        }

        [ $siblings[ $from ], $siblings[ $to ] ] = [ $siblings[ $to ], $siblings[ $from ] ];

        app( OrderSubstatusService::class )->reorder( (string) $substatus->system_status, $siblings );
    }

    /**
     * Asks to confirm deleting a sub-status.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Sub-status id.
     *
     * @return void
     */
    public function confirmDelete( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'orderSubstatus.delete' );

        $this->deletingId       = OrderSubstatus::query()->whereKey( $id )->exists() ? $id : null;
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
     * Deletes the sub-status waiting for confirmation, once per token.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the confirmation.
     *
     * @return void
     */
    public function delete( string $token ): void
    {
        $this->authorizeEcommerceAbility( 'orderSubstatus.delete' );

        $substatus = null === $this->deletingId || ! $this->confirmingDelete ? null : OrderSubstatus::query()->find( $this->deletingId );

        if ( null === $substatus ) {
            $this->cancelDelete();

            return;
        }

        $label = (string) $substatus->label;

        try {
            $deleted = $this->withActionToken( $token, 'delete', static function () use ( $substatus ): bool {
                app( OrderSubstatusService::class )->delete( $substatus );

                return true;
            }, $substatus );
        } catch ( OrderSubstatusWriteException $exception ) {
            $this->cancelDelete();
            $this->toastError( __( 'The sub-status was not deleted.' ), (string) ( $exception->errors[0]['message'] ?? $exception->getMessage() ) );

            return;
        }

        $this->cancelDelete();

        if ( true === $deleted ) {
            $this->toastSuccess( __( 'Sub-status ":name" deleted.', [ 'name' => $label ] ) );
        }
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $substatuses = OrderSubstatus::query()->orderBy( 'position' )->orderBy( 'id' )->get();
        $orderCounts = Order::query()
            ->whereNotNull( 'substatus_id' )
            ->groupBy( 'substatus_id' )
            ->select( 'substatus_id', DB::raw( 'COUNT(*) as aggregate' ) )
            ->pluck( 'aggregate', 'substatus_id' )
            ->all();

        $systemLabels = StatusPresenter::statuses( 'system' );
        $groups       = [];

        foreach ( OrderSubstatusService::systemStatuses() as $status ) {
            $groups[] = [
                'status'      => $status,
                'label'       => $systemLabels[ $status ][0] ?? $status,
                'substatuses' => $substatuses->where( 'system_status', $status )->values(),
            ];
        }

        $deleting = null === $this->deletingId || ! $this->confirmingDelete ? null : $substatuses->firstWhere( 'id', $this->deletingId );
        $usage    = null === $deleting ? null : app( OrderSubstatusService::class )->usage( $deleting );
        $isLast   = null !== $deleting && $substatuses->where( 'system_status', $deleting->system_status )->count() <= 1;
        $editing  = null === $this->substatusId ? null : $substatuses->firstWhere( 'id', $this->substatusId );

        return view( 'ecommerce-admin::livewire.order-statuses.index', [
            'groups'           => $groups,
            'orderCounts'      => $orderCounts,
            'canCreate'        => $this->canEcommerce( 'create', OrderSubstatus::class ),
            'canUpdate'        => Authorization::allows( auth()->user(), 'orderSubstatus.update' ),
            'canDelete'        => Authorization::allows( auth()->user(), 'orderSubstatus.delete' ),
            'formStatusLabel'  => $systemLabels[ $this->form['system_status'] ][0] ?? '',
            'editingSubstatus' => $editing,
            'contrastWarning'  => ColorContrast::warning( strtoupper( trim( (string) $this->form['color'] ) ) ),
            'iconPicker'       => IconChoices::isAvailable(),
            'iconOptions'      => IconChoices::isAvailable() ? IconChoices::options( (string) $this->form['icon'] ) : [],
            'deleting'         => $deleting,
            'deletingUsage'    => $usage,
            'deletingIsLast'   => $isLast,
            'deleteBlocked'    => null !== $usage && ( array_sum( $usage ) > 0 || $isLast ),
            'deleteToken'      => null === $deleting ? null : $this->actionToken( 'delete', $deleting ),
        ] );
    }
}
