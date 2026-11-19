{{--
    Kanban board editor (#140). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards\Edit.

    One page, top to bottom. Under the page header (Save board is its one
    solid button; Open board jumps to the live board), a badge row shows the
    saved board's state: Default, Switched on or off, and Has routing rules.
    Then one card holds the board form: Details and Routing, each with its
    heading on the start side and its fields on the end side, like the
    promotion form (stacked below 48rem). Columns (#113) and Automations
    (#114) follow as their own cards; they save as you go.

    - The key is locked, with the reason as its hint.
    - Default can only be turned on here. On the default board the switch
      stays on and disabled, and its hint says how to move the flag.
    - A default board with routing rules gets a warning: it only takes
      matching orders, so orders no board matches go nowhere.
    - Rules stored with "any" or "not" groups can't be edited here: a warning
      says they are kept, the stored JSON sits in a disclosure, and "Replace
      with simple rules" drops them (nothing is stored until Save).
    - After a failed save an error alert leads (and takes focus), and each
      field shows its own message.
    - View only: every control is disabled, Save is hidden, an info alert
      says so, and Columns and Automations hide their add and edit actions.

    Unsaved changes: typing marks the form dirty; leaving the page asks
    first until the board is saved.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\UnsavedChanges;
@endphp
<div class="ec-stack" data-kanban-board-form x-data="{ {!! UnsavedChanges::members() !!} }" {!! UnsavedChanges::attributes() !!}>
    <x-artisanpack-page-header :title="__( 'Edit :name', [ 'name' => $board->name ] )" :level="1">
        <x-slot:actions>
            @if ( null !== $indexUrl )
                <x-artisanpack-button variant="ghost" color="neutral" size="sm" :href="$indexUrl" data-back-link>{{ __( 'All boards' ) }}</x-artisanpack-button>
            @endif
            @if ( null !== $boardUrl )
                <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="columns-3" :href="$boardUrl" data-open-board>{{ __( 'Open board' ) }}</x-artisanpack-button>
            @endif
            @unless ( $readOnly )
                <x-artisanpack-button size="sm" leading-icon="check" wire:click="save" wire:loading.attr="disabled" wire:target="save" data-save>{{ __( 'Save board' ) }}</x-artisanpack-button>
            @endunless
        </x-slot:actions>
    </x-artisanpack-page-header>

    <div class="ec-badge-row ec-board-form-badges" role="group" aria-label="{{ __( 'Board status' ) }}" data-board-badges>
        @if ( $board->is_default )
            <x-artisanpack-badge variant="soft" color="primary" data-board-is-default>{{ __( 'Default' ) }}</x-artisanpack-badge>
        @endif
        @if ( $board->is_active )
            <x-artisanpack-badge variant="soft" color="success" dot data-board-state="active">{{ __( 'Switched on' ) }}</x-artisanpack-badge>
        @else
            <x-artisanpack-badge variant="soft" color="warning" dot data-board-state="inactive">{{ __( 'Switched off' ) }}</x-artisanpack-badge>
        @endif
        @if ( $hasRoutingRules )
            <x-artisanpack-badge variant="soft" color="neutral" leading-icon="filter" data-board-has-rules>{{ __( 'Has routing rules' ) }}</x-artisanpack-badge>
        @endif
    </div>

    @if ( $readOnly )
        <x-artisanpack-alert color="info" icon="eye" :title="__( 'You can view this board but not change it.' )" data-read-only />
    @endif

    @if ( $errors->any() )
        @include( 'ecommerce-admin::partials.error-summary', [ 'failure' => __( 'The board was not saved. Fix the highlighted fields.' ) ] )
    @endif

    <x-artisanpack-card>
        <fieldset class="ec-board-form-panel" @disabled( $readOnly ) data-board-details>
            <section class="ec-board-form-section" aria-labelledby="board-details-heading">
                <div class="ec-board-form-section__head">
                    <x-artisanpack-heading :level="2" :size="6" id="board-details-heading">{{ __( 'Details' ) }}</x-artisanpack-heading>
                </div>
                <div class="ec-board-form-fields">
                    <x-artisanpack-input id="board-name" :label="__( 'Name' )" wire:model="form.name" maxlength="255" :disabled="$readOnly" required />
                    <x-artisanpack-input
                        id="board-key"
                        class="ec-mono-input"
                        dir="ltr"
                        :label="__( 'Key' )"
                        :hint="__( 'Integrations refer to the key, so it cannot be changed.' )"
                        :value="$board->key"
                        readonly
                    />
                    <x-artisanpack-textarea id="board-description" :label="__( 'Description' )" rows="3" wire:model="form.description" :disabled="$readOnly" />

                    <x-artisanpack-toggle
                        id="board-active"
                        :label="__( 'Switched on' )"
                        :description="__( 'Orders are only routed onto active boards.' )"
                        wire:model="form.is_active"
                        :disabled="$readOnly"
                    />

                    @if ( $board->is_default )
                        <x-artisanpack-toggle
                            id="board-default"
                            :label="__( 'Default board' )"
                            :description="__( 'To change the default, make another board the default from the boards list.' )"
                            :checked="true"
                            :disabled="true"
                            data-board-default-locked
                        />
                    @else
                        <x-artisanpack-toggle
                            id="board-default"
                            :label="__( 'Make this the default board' )"
                            :description="__( 'The default board catches orders no other board took. The current default stops being one.' )"
                            wire:model="form.is_default"
                            :disabled="$readOnly"
                        />
                    @endif
                </div>
            </section>

            <section class="ec-board-form-section" aria-labelledby="board-routing-heading" aria-describedby="board-routing-description" data-board-routing>
                <div class="ec-board-form-section__head">
                    <x-artisanpack-heading :level="2" :size="6" id="board-routing-heading">{{ __( 'Routing' ) }}</x-artisanpack-heading>
                    <p id="board-routing-description" class="ec-board-form-section__hint">{{ __( 'When an order is placed or edited, it goes onto this board if it matches every rule.' ) }}</p>
                </div>
                <div class="ec-board-form-fields">
                    @if ( $fallbackNote )
                        <x-artisanpack-alert
                            color="warning"
                            icon="triangle-alert"
                            :title="__( 'This is the default board, but it has routing rules, so it only takes matching orders. Orders no board matches go nowhere.' )"
                            data-fallback-note
                        />
                    @endif

                    @if ( null !== $complexJson )
                        <div class="ec-stack" data-complex-rules>
                            <x-artisanpack-alert
                                color="warning"
                                icon="triangle-alert"
                                :title="__( 'These rules use “any” or “not” groups, which this screen cannot edit.' )"
                                :description="__( 'They are kept as they are when you save.' )"
                            >
                                @unless ( $readOnly )
                                    <x-slot:actions>
                                        <x-artisanpack-button variant="outline" color="neutral" size="sm" wire:click="replaceComplexRules" data-replace-complex-rules>{{ __( 'Replace with simple rules' ) }}</x-artisanpack-button>
                                    </x-slot:actions>
                                @endunless
                            </x-artisanpack-alert>

                            <details class="ec-board-form-json">
                                <summary>{{ __( 'Show the stored rules' ) }}</summary>
                                <pre class="ec-board-form-json__code" dir="ltr"><code>{{ $complexJson }}</code></pre>
                            </details>
                        </div>
                    @else
                        <x-artisanpack-ec-rule-builder :builder="$ruleBuilder" prefix="routing-rule" />
                    @endif
                </div>
            </section>
        </fieldset>
    </x-artisanpack-card>

    @livewire( 'artisanpack-ecommerce-admin-kanban-board-columns', [ 'board' => $board ], key( 'kanban-board-columns-' . $board->id ) )

    @livewire( 'artisanpack-ecommerce-admin-kanban-board-automations', [ 'board' => $board ], key( 'kanban-board-automations-' . $board->id ) )
</div>
