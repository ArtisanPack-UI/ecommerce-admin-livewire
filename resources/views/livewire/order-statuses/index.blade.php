{{--
    Order sub-statuses (#138). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\OrderStatuses\Index.

    One card per order status, in the fixed system order, with a count of its
    sub-statuses. Each sub-status is a reorderable row: its icon in a soft
    circle of its own colour, the label (and a neutral Terminal badge with a
    check when orders on it are done), the key in mono, and the badge exactly
    as orders show it.

    New and Edit open a drawer with a live badge preview; the key is locked
    after creation. Delete asks in an alert dialog that says what still uses
    the sub-status and only offers to keep it while anything does, or while
    it is the last sub-status of its order status.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ColorContrast;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\IconChoices;
    use ArtisanPack\LivewireUiComponents\Support\IconRegistry;
    use Illuminate\Support\Js;
@endphp
<div class="ec-stack" data-order-statuses>
    <x-artisanpack-page-header :title="__( 'Order statuses' )" :description="__( 'Sub-statuses refine each order status. Order screens and kanban columns use them.' )" :level="1" />

    @foreach ( $groups as $group )
        <section wire:key="system-status-{{ $group['status'] }}" class="apui-card" aria-labelledby="system-status-{{ $group['status'] }}-heading" data-system-status="{{ $group['status'] }}">
            <div data-scope="card" data-part="header">
                <div class="ec-card-title-row">
                    <h2 id="system-status-{{ $group['status'] }}-heading" data-scope="card" data-part="title">{{ $group['label'] }}</h2>
                    <span class="ec-muted" data-substatus-count="{{ $group['substatuses']->count() }}">
                        <span aria-hidden="true"><bdi dir="ltr">{{ $group['substatuses']->count() }}</bdi></span>
                        <span class="apui-sr-only">{{ trans_choice( ':count sub-status|:count sub-statuses', $group['substatuses']->count(), [ 'count' => $group['substatuses']->count() ] ) }}</span>
                    </span>
                </div>
                @if ( $canCreate )
                    <div data-scope="card" data-part="actions">
                        <x-artisanpack-button
                            variant="ghost"
                            color="neutral"
                            size="xs"
                            leading-icon="plus"
                            wire:click="create( {{ Js::from( $group['status'] ) }} )"
                            data-focus-key="substatus-add-{{ $group['status'] }}"
                            :aria-label="__( 'Add sub-status to :status', [ 'status' => $group['label'] ] )">{{ __( 'Add sub-status' ) }}</x-artisanpack-button>
                    </div>
                @endif
            </div>

            @if ( $group['substatuses']->isNotEmpty() )
                <ol class="ec-plain-list ec-settings-list" aria-label="{{ __( ':status sub-statuses', [ 'status' => $group['label'] ] ) }}">
                    @foreach ( $group['substatuses'] as $substatus )
                        <li wire:key="substatus-{{ $substatus->id }}" class="ec-settings-row" data-substatus="{{ $substatus->key }}">
                            <svg class="ec-column-chip" viewBox="0 0 28 28" color="{{ ColorContrast::safeHex( $substatus->color ) }}" aria-hidden="true" focusable="false">
                                <circle cx="14" cy="14" r="14" fill="currentColor" fill-opacity="0.16" />
                                @if ( IconChoices::exists( $substatus->icon ) )
                                    <svg x="6" y="6" width="16" height="16">{!! IconRegistry::render( $substatus->icon ) !!}</svg>
                                @endif
                            </svg>

                            <div class="ec-settings-row__main">
                                <span class="ec-settings-row__title">
                                    <span class="ec-settings-row__name">{{ $substatus->label }}</span>
                                    @if ( $substatus->is_terminal )
                                        <x-artisanpack-badge color="neutral" size="sm" leading-icon="circle-check" data-terminal>{{ __( 'Terminal' ) }}</x-artisanpack-badge>
                                    @endif
                                </span>
                                <bdi dir="ltr" class="ec-mono ec-muted">{{ $substatus->key }}</bdi>
                            </div>

                            <x-artisanpack-ec-status-badge :substatus="$substatus" size="sm" />

                            @if ( $canUpdate || $canDelete )
                                <div class="ec-settings-row__actions">
                                    @if ( $canUpdate )
                                        <x-artisanpack-button
                                            variant="ghost"
                                            color="neutral"
                                            size="xs"
                                            icon-only
                                            leading-icon="arrow-up"
                                            wire:click="move( {{ $substatus->id }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="substatuses-{{ $group['status'] }}" data-reorder-key="substatus-{{ $substatus->id }}" data-reorder-item="{{ $substatus->label }}"
                                            :disabled="$loop->first"
                                            :aria-label="__( 'Move :name up', [ 'name' => $substatus->label ] )"
                                        />
                                        <x-artisanpack-button
                                            variant="ghost"
                                            color="neutral"
                                            size="xs"
                                            icon-only
                                            leading-icon="arrow-down"
                                            wire:click="move( {{ $substatus->id }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="substatuses-{{ $group['status'] }}" data-reorder-key="substatus-{{ $substatus->id }}" data-reorder-item="{{ $substatus->label }}"
                                            :disabled="$loop->last"
                                            :aria-label="__( 'Move :name down', [ 'name' => $substatus->label ] )"
                                        />
                                        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="pencil" wire:click="edit( {{ $substatus->id }} )" data-focus-key="substatus-edit-{{ $substatus->id }}" :aria-label="__( 'Edit :name', [ 'name' => $substatus->label ] )" />
                                    @endif
                                    @if ( $canDelete )
                                        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="confirmDelete( {{ $substatus->id }} )" data-focus-key="substatus-delete-{{ $substatus->id }}" :aria-label="__( 'Delete :name', [ 'name' => $substatus->label ] )" />
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endforeach

    <x-artisanpack-drawer
        wire:model="editing"
        :title="null === $substatusId ? __( 'New sub-status' ) : __( 'Edit sub-status' )"
        side="end"
        size="md"
        :close-label="__( 'Close' )"
    >
        <form id="substatus-form" wire:submit="save" class="ec-form-stack ec-stack-lg" data-substatus-form>
            @include( 'ecommerce-admin::partials.error-summary' )

            <x-artisanpack-alert color="info" icon="info" :title="__( 'Order status: :status', [ 'status' => $formStatusLabel ] )" data-substatus-status />

            <x-artisanpack-input id="substatus-label" :label="__( 'Label' )" wire:model.live.debounce.300ms="form.label" maxlength="120" required />

            @if ( null === $substatusId )
                <x-artisanpack-input id="substatus-key" class="ec-mono-input" dir="ltr" :label="__( 'Key' )" :hint="__( 'Lowercase, like awaiting-label. Leave empty to make one from the label. It cannot be changed later.' )" wire:model="form.key" maxlength="80" />
            @else
                <x-artisanpack-input id="substatus-key" class="ec-mono-input" dir="ltr" :label="__( 'Key' )" :hint="__( 'Automations and integrations refer to the key, so it cannot be changed.' )" :value="$form['key']" readonly data-key-locked />
            @endif

            <x-artisanpack-color-picker id="substatus-color" :label="__( 'Colour' )" :hint="__( 'A hex code like #3B82F6. Leave empty for a neutral badge.' )" wire:model.live.debounce.300ms="form.color" />

            @if ( null !== $contrastWarning )
                <x-artisanpack-alert color="warning" icon="triangle-alert" :description="$contrastWarning" data-contrast-warning />
            @endif

            @if ( $iconPicker )
                <div data-icon-picker>
                    <x-artisanpack-combobox
                        id="substatus-icon"
                        :label="__( 'Icon' )"
                        :options="ControlOptions::from( $iconOptions )"
                        :multiple="false"
                        clearable
                        :empty-label="__( 'No results found.' )"
                        wire:model="form.icon"
                    />
                </div>
            @else
                <x-artisanpack-input id="substatus-icon" class="ec-mono-input" dir="ltr" :label="__( 'Icon' )" :hint="__( 'An icon name, such as o-printer. Install artisanpack-ui/icons to pick from a list.' )" wire:model="form.icon" maxlength="80" data-icon-text />
            @endif

            <x-artisanpack-toggle id="substatus-terminal" :label="__( 'Terminal' )" :description="__( 'Orders on a terminal sub-status are finished. Kanban boards treat them as done.' )" wire:model="form.is_terminal" />

            <div class="ec-sunken" data-substatus-preview>
                <div class="ec-card-title-row">
                    <span class="ec-muted">{{ __( 'Preview:' ) }}</span>
                    @if ( '' !== trim( (string) $form['label'] ) )
                        <x-artisanpack-ec-status-badge :substatus="new OrderSubstatus( [ 'label' => $form['label'], 'color' => $previewColor ] )" />
                    @else
                        <span class="ec-muted">{{ __( 'Add a label to see the badge.' ) }}</span>
                    @endif
                </div>
            </div>
        </form>

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'editing', false )" data-focus-return="{{ null === $substatusId ? 'substatus-add-' . $form['system_status'] : 'substatus-edit-' . $substatusId }}">{{ __( 'Cancel' ) }}</x-artisanpack-button>
            <x-artisanpack-button type="submit" form="substatus-form" wire:loading.attr="disabled" data-save-substatus>{{ __( 'Save sub-status' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-drawer>

    @if ( null !== $deleting )
        @php $deleteTitle = __( 'Delete ":name"?', [ 'name' => $deleting->label ] ); @endphp
        <x-artisanpack-dialog
            wire:model="confirmingDelete"
            role="alertdialog"
            size="sm"
            :color="$deleteBlocked ? 'warning' : 'error'"
            icon="trash"
            :title="$deleteTitle"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
            data-delete-substatus-dialog
        >
            <div data-delete-substatus="{{ $deleting->key }}">
                @if ( $deletingIsLast )
                    <x-artisanpack-alert
                        color="warning"
                        icon="triangle-alert"
                        :title="__( 'Every order status needs at least one sub-status, so the last one can\'t be deleted.' )"
                        :description="__( 'Add another sub-status to :status first.', [ 'status' => $deletingStatusLabel ] )"
                        data-delete-last
                    />
                @elseif ( array_sum( $deletingUsage ) > 0 )
                    <x-artisanpack-alert
                        color="warning"
                        icon="triangle-alert"
                        :title="__( 'It is still in use by :orders, :cards, and :columns.', [
                            'orders'  => trans_choice( ':count order|:count orders', $deletingUsage['orders'], [ 'count' => $deletingUsage['orders'] ] ),
                            'cards'   => trans_choice( ':count card|:count cards', $deletingUsage['assignments'], [ 'count' => $deletingUsage['assignments'] ] ),
                            'columns' => trans_choice( ':count column|:count columns', $deletingUsage['columns'], [ 'count' => $deletingUsage['columns'] ] ),
                        ] )"
                        :description="__( 'Move them to another sub-status first.' )"
                        data-delete-in-use
                    />
                @else
                    <p class="ec-body">{{ __( 'Nothing uses this sub-status. Deleting it cannot be undone.' ) }}</p>
                @endif
            </div>

            <x-slot:footer>
                @if ( $deleteBlocked )
                    <x-artisanpack-button color="neutral" wire:click="cancelDelete" data-autofocus data-focus-return="substatus-delete-{{ $deleting->id }}">{{ __( 'Keep sub-status' ) }}</x-artisanpack-button>
                @else
                    <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelDelete" data-autofocus data-focus-return="substatus-delete-{{ $deleting->id }}">{{ __( 'Keep sub-status' ) }}</x-artisanpack-button>
                    <x-artisanpack-button
                        color="error"
                        wire:click="delete( {{ Js::from( $deleteToken ) }} )"
                        wire:loading.attr="disabled"
                        data-delete-button>{{ __( 'Delete sub-status' ) }}</x-artisanpack-button>
                @endif
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
