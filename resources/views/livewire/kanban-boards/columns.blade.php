{{--
    Board columns (#113). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards\Columns.

    A card listing the board's columns left to right as they appear on it. A
    colour strip on top previews the board at a glance. Each row shows its
    position, the sub-status's icon in its colour, the label (a custom label
    keeps the real sub-status in brackets beside it), the card widgets, and
    the card count against the WIP limit. A full column turns warning and
    gets a Full badge, because moves into it are refused.

    New and Edit open a drawer; Delete asks in a dialog. Card widgets reorder
    and remove in the drawer; an empty list means the board default, which
    the hint names.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ColorContrast;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\IconChoices;
    use ArtisanPack\LivewireUiComponents\Support\IconRegistry;

    $widgetList = static fn ( array $keys ): string => implode( ', ', array_map( static fn ( string $key ): string => $widgetLabels[ $key ] ?? $key, $keys ) );
@endphp
<section class="apui-card" aria-labelledby="board-columns-heading" data-board-columns>
    <div data-scope="card" data-part="header">
        <div>
            <h2 id="board-columns-heading" data-scope="card" data-part="title">{{ __( 'Columns' ) }}</h2>
            <p data-scope="card" data-part="description">{{ __( 'Each column shows the orders on one sub-status. Columns appear on the board from left to right in this order.' ) }}</p>
        </div>
        @if ( $canUpdate )
            <div data-scope="card" data-part="actions">
                <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="plus" wire:click="create">{{ __( 'Add column' ) }}</x-artisanpack-button>
            </div>
        @endif
    </div>

    @if ( $columns->isEmpty() )
        <div data-scope="card" data-part="body">
            <x-artisanpack-ec-admin-empty-state
                size="sm"
                class="ec-empty-sunken"
                :heading-level="3"
                icon="layout-grid"
                :title="__( 'No columns yet' )"
                :description="__( 'Add a column for each sub-status you want to see on this board.' )"
            />
        </div>
    @else
        <div class="ec-board-strip" aria-hidden="true" data-column-strip>
            @foreach ( $columns as $column )
                <svg class="ec-board-strip__swatch" focusable="false" wire:key="column-strip-{{ $column->id }}"><rect width="100%" height="100%" rx="3" fill="{{ ColorContrast::safeHex( $column->color_override ?? $column->substatus?->color ) }}" /></svg>
            @endforeach
        </div>

        <ol class="ec-plain-list ec-settings-list" aria-label="{{ __( 'Columns' ) }}">
            @foreach ( $columns as $column )
                @php
                    $cards  = (int) ( $cardCounts[ $column->substatus_id ] ?? 0 );
                    $label  = $column->displayLabel();
                    $chosen = array_values( array_filter( (array) $column->card_widgets, 'is_string' ) );
                    $icon   = $column->icon_override ?? $column->substatus?->icon;
                    $isFull = null !== $column->wip_limit && $cards >= (int) $column->wip_limit;
                @endphp
                <li wire:key="column-{{ $column->id }}" class="ec-settings-row" data-column="{{ $column->substatus?->key }}">
                    <span class="ec-rule-index ec-settings-row__index" aria-hidden="true">{{ $loop->iteration }}</span>
                    <svg class="ec-column-chip" viewBox="0 0 28 28" color="{{ ColorContrast::safeHex( $column->color_override ?? $column->substatus?->color ) }}" aria-hidden="true" focusable="false">
                        <circle cx="14" cy="14" r="14" fill="currentColor" fill-opacity="0.16" />
                        @if ( IconChoices::exists( $icon ) )
                            <svg x="6" y="6" width="16" height="16">{!! IconRegistry::render( $icon ) !!}</svg>
                        @endif
                    </svg>

                    <div class="ec-settings-row__main">
                        <span class="ec-settings-row__title">
                            <span class="ec-settings-row__name">{{ $label }}</span>
                            @if ( null !== $column->substatus && null !== $column->label_override )
                                <span class="ec-muted">{{ __( '(:substatus)', [ 'substatus' => $column->substatus->label ] ) }}</span>
                            @endif
                        </span>
                        <span class="ec-muted" data-column-widgets>
                            {{ [] === $chosen ? __( 'Card widgets: board default' ) : __( 'Card widgets: :widgets', [ 'widgets' => $widgetList( $chosen ) ] ) }}
                        </span>
                    </div>

                    <span @class( [ 'ec-column-count', 'ec-column-count--full' => $isFull ] ) data-column-cards="{{ $cards }}">
                        @if ( null === $column->wip_limit )
                            {{ trans_choice( ':count card|:count cards', $cards, [ 'count' => $cards ] ) }}
                        @else
                            <bdi>{{ __( ':count of :limit cards', [ 'count' => $cards, 'limit' => $column->wip_limit ] ) }}</bdi>
                        @endif
                        @if ( $isFull )
                            <x-artisanpack-badge color="warning" size="sm" data-column-full>{{ __( 'Full' ) }}</x-artisanpack-badge>
                        @endif
                    </span>

                    @if ( $canUpdate )
                        <div class="ec-settings-row__actions">
                            <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-up" wire:click="move( {{ $column->id }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="columns" data-reorder-key="column-{{ $column->id }}" data-reorder-item="{{ $label }}" :disabled="$loop->first" :aria-label="__( 'Move :name up', [ 'name' => $label ] )" />
                            <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-down" wire:click="move( {{ $column->id }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="columns" data-reorder-key="column-{{ $column->id }}" data-reorder-item="{{ $label }}" :disabled="$loop->last" :aria-label="__( 'Move :name down', [ 'name' => $label ] )" />
                            <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="pencil" wire:click="edit( {{ $column->id }} )" :aria-label="__( 'Edit :name', [ 'name' => $label ] )" />
                            <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="confirmDelete( {{ $column->id }} )" :aria-label="__( 'Delete :name', [ 'name' => $label ] )" />
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    <x-artisanpack-drawer
        wire:model="editing"
        :title="null === $columnId ? __( 'New column' ) : __( 'Edit column' )"
        side="end"
        size="md"
    >
        <form id="board-column-form" wire:submit="save" class="ec-form-stack" data-column-form>
            @include( 'ecommerce-admin::partials.error-summary' )

            <x-artisanpack-select
                id="column-substatus"
                :label="__( 'Sub-status' )"
                :hint="__( 'Cards in this column are orders on this sub-status.' )"
                :options="ControlOptions::from( $substatusOptions )"
                :placeholder="__( 'Choose a sub-status' )"
                wire:model="form.substatus_id"
                required
            />

            <x-artisanpack-input id="column-label" :label="__( 'Label' )" :hint="__( 'Leave empty to use the sub-status label.' )" wire:model="form.label_override" maxlength="120" />

            <div class="ec-field-grid">
                <x-artisanpack-color-picker id="column-color" :label="__( 'Colour' )" :hint="__( 'A hex code like #3B82F6. Leave empty to use the sub-status colour.' )" wire:model.live.debounce.300ms="form.color_override" />

                @if ( $iconPicker )
                    <div data-icon-picker>
                        <x-artisanpack-combobox
                            id="column-icon"
                            :label="__( 'Icon' )"
                            :hint="__( 'Leave empty to use the sub-status icon.' )"
                            :options="ControlOptions::from( $iconOptions )"
                            :multiple="false"
                            clearable
                            :empty-label="__( 'No results found.' )"
                            wire:model="form.icon_override"
                        />
                    </div>
                @else
                    <x-artisanpack-input id="column-icon" :label="__( 'Icon' )" :hint="__( 'An icon name, such as o-printer. Leave empty to use the sub-status icon.' )" wire:model="form.icon_override" maxlength="80" data-icon-text />
                @endif
            </div>

            @if ( null !== $contrastWarning )
                <x-artisanpack-alert color="warning" data-contrast-warning :description="$contrastWarning" />
            @endif

            <x-artisanpack-input
                id="column-wip-limit"
                type="number"
                min="1"
                step="1"
                inputmode="numeric"
                :label="__( 'WIP limit' )"
                :hint="__( 'The most cards the column may hold. Moves into a full column are refused. Leave empty for no limit.' )"
                wire:model="form.wip_limit"
            />

            <fieldset class="ec-fieldset" data-column-card-widgets>
                <legend class="ec-fieldset__legend">{{ __( 'Card widgets' ) }}</legend>
                <p class="ec-muted">
                    {{ [] === $defaultWidgets
                        ? __( 'What each card shows, top to bottom. Leave empty to use the board default.' )
                        : __( 'What each card shows, top to bottom. Leave empty to use the board default: :widgets.', [ 'widgets' => $widgetList( $defaultWidgets ) ] ) }}
                </p>

                @if ( [] !== $form['card_widgets'] )
                    @php $widgetSeen = []; @endphp
                    <ol class="ec-plain-list ec-widget-list" aria-label="{{ __( 'Card widgets' ) }}">
                        @foreach ( array_values( $form['card_widgets'] ) as $index => $widget )
                            @php
                                $widgetLabel = $widgetLabels[ $widget ] ?? __( 'Unavailable (:type)', [ 'type' => $widget ] );

                                // Widgets are unique, so the widget is the row's key; a repeat
                                // (only in stored data) is keyed by its occurrence.
                                $widgetSeen[ $widget ] = ( $widgetSeen[ $widget ] ?? 0 ) + 1;
                            @endphp
                            <li wire:key="widget-{{ $widget }}-{{ $widgetSeen[ $widget ] }}" class="ec-widget-row" data-widget="{{ $widget }}">
                                <span class="ec-rule-index" aria-hidden="true">{{ $index + 1 }}</span>
                                <span class="ec-widget-row__label">
                                    {{ $widgetLabel }}
                                    @error( 'form.card_widgets.' . $index )
                                        <span class="ec-text-error ec-widget-row__error">{{ $message }}</span>
                                    @enderror
                                </span>
                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-up" wire:click="moveWidget( {{ $index }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="widgets" data-reorder-index="{{ $index }}" data-reorder-item="{{ $widgetLabel }}" :disabled="0 === $index" :aria-label="__( 'Move :label up', [ 'label' => $widgetLabel ] )" />
                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-down" wire:click="moveWidget( {{ $index }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="widgets" data-reorder-index="{{ $index }}" data-reorder-item="{{ $widgetLabel }}" :disabled="$loop->last" :aria-label="__( 'Move :label down', [ 'label' => $widgetLabel ] )" />
                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="x" wire:click="removeWidget( {{ $index }} )" wire:loading.attr="disabled" :aria-label="__( 'Remove :label', [ 'label' => $widgetLabel ] )" />
                            </li>
                        @endforeach
                    </ol>
                @endif

                <div class="ec-actions">
                    <div class="ec-grow">
                        <x-artisanpack-select
                            id="column-widget-to-add"
                            size="sm"
                            :label="__( 'Add a widget' )"
                            :options="ControlOptions::from( $widgetOptions )"
                            :placeholder="__( 'Choose a widget' )"
                            wire:model="widgetToAdd"
                        />
                    </div>
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="plus" wire:click="addWidget">{{ __( 'Add' ) }}</x-artisanpack-button>
                </div>
            </fieldset>
        </form>

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'editing', false )">{{ __( 'Cancel' ) }}</x-artisanpack-button>
            <x-artisanpack-button type="submit" form="board-column-form" wire:loading.attr="disabled">{{ __( 'Save column' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-drawer>

    @if ( null !== $deleting )
        @php $deleteTitle = __( 'Delete ":name"?', [ 'name' => $deleting->displayLabel() ] ); @endphp
        <x-artisanpack-dialog
            wire:model="confirmingDelete"
            role="alertdialog"
            size="sm"
            color="error"
            icon="trash"
            :title="$deleteTitle"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
            data-delete-confirm
        >
            <div class="ec-stack-sm" data-delete-column="{{ $deleting->id }}">
                @if ( $deletingCards > 0 )
                    <x-artisanpack-alert
                        color="warning"
                        data-delete-in-use
                        :description="trans_choice( ':count card is in this column. Move it to another column first.|:count cards are in this column. Move them to another column first.', $deletingCards, [ 'count' => $deletingCards ] )"
                    />
                @else
                    <p class="ec-body">{{ __( 'The sub-status itself is kept. Deleting the column cannot be undone.' ) }}</p>
                @endif
                @if ( $deletingRules > 0 )
                    <p class="ec-body" data-delete-automations>{{ trans_choice( ':count automation that starts or ends in this column is deleted too.|:count automations that start or end in this column are deleted too.', $deletingRules, [ 'count' => $deletingRules ] ) }}</p>
                @endif
            </div>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelDelete" data-autofocus>{{ __( 'Keep column' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    color="error"
                    wire:click="delete( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )"
                    wire:loading.attr="disabled"
                    :disabled="$deletingCards > 0"
                    data-delete-button>{{ __( 'Delete column' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</section>
