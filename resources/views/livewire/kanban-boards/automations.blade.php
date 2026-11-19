{{--
    Board automations (#114). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards\Automations.

    A card listing what happens when a card moves: each automation reads as
    from-column → into-column ("Any column" when the from side doesn't
    matter), then the action and its conditions. A switch turns each one on
    and off in place; a switched-off automation dims and gets a neutral
    badge, and its switch stays usable. Add automation is disabled, with the
    reason, until the board has columns.

    New and Edit open a drawer with the trigger's settings and simple
    conditions. Nested any/not groups this screen can't edit are kept, with
    a warning and a one-click replace. Delete asks in a dialog.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
@endphp
<section class="apui-card" aria-labelledby="board-automations-heading" data-board-automations>
    <div data-scope="card" data-part="header">
        <div>
            <h2 id="board-automations-heading" data-scope="card" data-part="title">{{ __( 'Automations' ) }}</h2>
            <p data-scope="card" data-part="description">{{ __( 'Automations run when a card moves into a column, like emailing the customer when an order reaches "Shipped".' ) }}</p>
        </div>
        @if ( $canUpdate )
            <div data-scope="card" data-part="actions">
                @if ( $hasColumns )
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="plus" wire:click="create">{{ __( 'Add automation' ) }}</x-artisanpack-button>
                @else
                    {{-- Focusable and named, so the reason is reachable; it calls nothing. --}}
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="plus" aria-disabled="true" aria-describedby="board-automations-needs-columns" data-add-automation-unavailable>{{ __( 'Add automation' ) }}</x-artisanpack-button>
                @endif
            </div>
        @endif
    </div>

    @if ( ! $hasColumns || [] === $automations )
        <div data-scope="card" data-part="body">
            @if ( ! $hasColumns )
                <x-artisanpack-ec-admin-empty-state
                    id="board-automations-needs-columns"
                    size="sm"
                    class="ec-empty-sunken"
                    :heading-level="3"
                    icon="layout-grid"
                    :title="__( 'Add columns first' )"
                    :description="__( 'Automations run when cards move between columns, so the board needs columns before it can have automations.' )"
                />
            @else
                <x-artisanpack-ec-admin-empty-state
                    size="sm"
                    class="ec-empty-sunken"
                    :heading-level="3"
                    icon="o-bolt"
                    :title="__( 'No automations yet' )"
                    :description="__( 'Add one to send an email, call a webhook, or create a shipment when a card moves.' )"
                />
            @endif
        </div>
    @else
        <ul class="ec-plain-list ec-settings-list" aria-label="{{ __( 'Automations' ) }}">
            @foreach ( $automations as $automation )
                @php $summary = __( ':from to :to: :trigger', [ 'from' => $automation['from'], 'to' => $automation['to'], 'trigger' => $automation['trigger'] ] ); @endphp
                <li wire:key="automation-{{ $automation['id'] }}" @class( [ 'ec-settings-row', 'ec-settings-row--off' => ! $automation['is_active'] ] ) data-automation="{{ $automation['id'] }}">
                    <x-artisanpack-icon name="o-bolt" class="ec-settings-row__icon" aria-hidden="true" />

                    <div class="ec-settings-row__main">
                        <span class="ec-settings-row__title">
                            <x-artisanpack-badge color="neutral" size="sm">{{ $automation['from'] }}</x-artisanpack-badge>
                            <x-artisanpack-icon name="arrow-right" class="ec-settings-row__arrow" aria-hidden="true" />
                            <span class="apui-sr-only">{{ __( 'to' ) }}</span>
                            <x-artisanpack-badge color="neutral" size="sm">{{ $automation['to'] }}</x-artisanpack-badge>
                        </span>
                        <span class="ec-settings-row__title">
                            <span class="ec-settings-row__name">{{ $automation['trigger'] }}</span>
                            <span class="ec-muted">{{ $automation['conditions'] }}</span>
                        </span>
                    </div>

                    @unless ( $automation['is_active'] )
                        <x-artisanpack-badge color="neutral" size="sm" data-inactive-automation>{{ __( 'Switched off' ) }}</x-artisanpack-badge>
                    @endunless

                    @if ( $canUpdate )
                        <x-artisanpack-toggle
                            id="automation-active-{{ $automation['id'] }}"
                            size="sm"
                            :checked="$automation['is_active']"
                            :aria-label="$summary"
                            wire:change="toggleActive( {{ $automation['id'] }} )"
                            wire:key="automation-toggle-{{ $automation['id'] }}-{{ $automation['is_active'] ? 'on' : 'off' }}"
                            data-automation-switch
                        />

                        <div class="ec-settings-row__actions">
                            <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="pencil" wire:click="edit( {{ $automation['id'] }} )" :aria-label="__( 'Edit :name', [ 'name' => $summary ] )" />
                            <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="confirmDelete( {{ $automation['id'] }} )" :aria-label="__( 'Delete :name', [ 'name' => $summary ] )" />
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <x-artisanpack-drawer
        wire:model="editing"
        :title="null === $automationId ? __( 'New automation' ) : __( 'Edit automation' )"
        side="end"
        size="lg"
    >
        <form id="board-automation-form" wire:submit="save" class="ec-form-stack" data-automation-form>
            @include( 'ecommerce-admin::partials.error-summary' )

            <div class="ec-field-grid">
                <x-artisanpack-select
                    id="automation-from"
                    :label="__( 'When a card moves from' )"
                    :options="ControlOptions::from( $columnOptions )"
                    :placeholder="__( 'Any column' )"
                    wire:model="form.from_column_id"
                />
                <x-artisanpack-select
                    id="automation-to"
                    :label="__( 'Into' )"
                    :options="ControlOptions::from( $columnOptions )"
                    :placeholder="__( 'Choose a column' )"
                    wire:model="form.to_column_id"
                    required
                />
            </div>

            <x-artisanpack-select
                id="automation-trigger"
                :label="__( 'Run' )"
                :options="ControlOptions::from( $triggerOptions )"
                :placeholder="__( 'Choose a trigger' )"
                wire:model.live="form.trigger_key"
                required
            />

            @if ( '' !== (string) $form['trigger_key'] )
                <fieldset class="ec-fieldset ec-sunken" data-automation-config>
                    <legend class="ec-fieldset__legend">{{ __( 'Settings' ) }}</legend>
                    <x-artisanpack-ec-config-form registry="kanban-trigger" :entry="(string) $form['trigger_key']" model="form.trigger_config" wire:key="automation-config-{{ $form['trigger_key'] }}" />

                    @if ( $secretIsSet )
                        <div class="ec-stack-sm" data-secret-set>
                            @if ( $clearSecret )
                                <p class="ec-muted ec-icon-line" role="status"><x-artisanpack-icon name="triangle-alert" aria-hidden="true" />{{ __( 'The signing secret is removed when you save.' ) }}</p>
                                <div>
                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" leading-icon="undo-2" wire:click="$set( 'clearSecret', false )" data-keep-secret>{{ __( 'Keep the signing secret' ) }}</x-artisanpack-button>
                                </div>
                            @else
                                <p class="ec-muted ec-icon-line"><x-artisanpack-icon name="lock" aria-hidden="true" />{{ __( 'A signing secret is set. Leave the field empty to keep it, or type a new one to replace it.' ) }}</p>
                                <div>
                                    <x-artisanpack-button variant="ghost" color="error" size="xs" leading-icon="trash" wire:click="$set( 'clearSecret', true )" data-clear-secret>{{ __( 'Remove the signing secret' ) }}</x-artisanpack-button>
                                </div>
                            @endif
                        </div>
                    @endif
                </fieldset>
            @endif

            @if ( null !== $complexJson )
                @php $complexTitle = __( 'These conditions use "any" or "not" groups, which this screen cannot edit.' ); @endphp
                <x-artisanpack-alert
                    color="warning"
                    data-complex-conditions
                    :title="$complexTitle"
                    :description="__( 'They are kept as they are when you save.' )"
                >
                    <x-slot:actions>
                        <x-artisanpack-button variant="outline" color="neutral" size="xs" wire:click="replaceComplexConditions">{{ __( 'Replace with simple conditions' ) }}</x-artisanpack-button>
                    </x-slot:actions>
                </x-artisanpack-alert>
            @else
                <x-artisanpack-ec-rule-builder :builder="$ruleBuilder" prefix="automation-rule" />
            @endif

            <x-artisanpack-toggle id="automation-active" :label="__( 'Switched on' )" wire:model="form.is_active" />
        </form>

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'editing', false )">{{ __( 'Cancel' ) }}</x-artisanpack-button>
            <x-artisanpack-button type="submit" form="board-automation-form" wire:loading.attr="disabled">{{ __( 'Save automation' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-drawer>

    @if ( null !== $deleting )
        <x-artisanpack-dialog wire:model="confirmingDelete" :title="__( 'Delete this automation?' )">
            <p class="ec-body" data-delete-automation="{{ $deleting['id'] }}">
                {{ __( ':from to :to: :trigger. Deleting it cannot be undone.', [ 'from' => $deleting['from'], 'to' => $deleting['to'], 'trigger' => $deleting['trigger'] ] ) }}
            </p>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelDelete">{{ __( 'Keep automation' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    color="error"
                    wire:click="delete( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )"
                    wire:loading.attr="disabled"
                    data-delete-button>{{ __( 'Delete automation' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</section>
