{{--
    A kanban board's columns. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards\Columns.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<section class="flex flex-col gap-3" aria-labelledby="board-columns-heading" data-board-columns>
    <div class="flex flex-wrap items-center gap-2">
        <h2 id="board-columns-heading" class="grow text-lg font-semibold">{{ __( 'Columns' ) }}</h2>
        @if ( $canUpdate )
            <x-artisanpack-button size="sm" variant="outline" icon="o-plus" wire:click="create" :label="__( 'Add column' )" />
        @endif
    </div>
    <p class="text-sm opacity-75">{{ __( 'Each column shows the orders on one sub-status. Columns appear on the board from left to right in this order.' ) }}</p>

    @if ( $columns->isEmpty() )
        <x-artisanpack-ec-empty-state icon="o-view-columns" :title="__( 'No columns yet' )" :description="__( 'Add a column for each sub-status you want to see on this board.' )" />
    @else
        <ol class="flex list-none flex-col divide-y divide-base-300 rounded-box border border-base-300" aria-label="{{ __( 'Columns' ) }}">
            @foreach ( $columns as $column )
                @php
                    $cards  = (int) ( $cardCounts[ $column->substatus_id ] ?? 0 );
                    $label  = $column->displayLabel();
                    $chosen = array_values( array_filter( (array) $column->card_widgets, 'is_string' ) );
                @endphp
                <li wire:key="column-{{ $column->id }}" class="flex flex-wrap items-center gap-3 p-3" data-column="{{ $column->substatus?->key }}">
                    <span
                        class="size-5 shrink-0 rounded border border-base-300"
                        style="background-color: {{ \ArtisanPackUI\EcommerceAdminLivewire\Support\ColorContrast::safeHex( $column->color_override ?? $column->substatus?->color ) }}"
                        aria-hidden="true"
                    ></span>

                    @if ( \ArtisanPackUI\EcommerceAdminLivewire\Support\IconChoices::exists( $column->icon_override ?? $column->substatus?->icon ) )
                        <x-artisanpack-icon :name="$column->icon_override ?? $column->substatus?->icon" class="size-4" aria-hidden="true" />
                    @endif

                    <div class="min-w-0 grow">
                        <span class="font-semibold">{{ $label }}</span>
                        @if ( null !== $column->substatus && null !== $column->label_override )
                            <span class="ms-2 text-sm opacity-75">{{ __( '(:substatus)', [ 'substatus' => $column->substatus->label ] ) }}</span>
                        @endif
                        <div class="text-sm opacity-75" data-column-widgets>
                            {{ [] === $chosen
                                ? __( 'Card widgets: board default' )
                                : __( 'Card widgets: :widgets', [ 'widgets' => implode( ', ', array_map( static fn ( string $key ): string => $widgetLabels[ $key ] ?? $key, $chosen ) ) ] ) }}
                        </div>
                    </div>

                    <span class="text-sm opacity-75" data-column-cards="{{ $cards }}">
                        @if ( null === $column->wip_limit )
                            {{ trans_choice( ':count card|:count cards', $cards, [ 'count' => $cards ] ) }}
                        @else
                            {{ __( ':count of :limit cards', [ 'count' => $cards, 'limit' => $column->wip_limit ] ) }}
                        @endif
                    </span>

                    @if ( $canUpdate )
                        <div class="flex items-center gap-1">
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-arrow-up" wire:click="move( {{ $column->id }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="columns" data-reorder-key="column-{{ $column->id }}" data-reorder-item="{{ $label }}" :disabled="$loop->first" :aria-label="__( 'Move :name up', [ 'name' => $label ] )" />
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-arrow-down" wire:click="move( {{ $column->id }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="columns" data-reorder-key="column-{{ $column->id }}" data-reorder-item="{{ $label }}" :disabled="$loop->last" :aria-label="__( 'Move :name down', [ 'name' => $label ] )" />
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-pencil" wire:click="edit( {{ $column->id }} )" :aria-label="__( 'Edit :name', [ 'name' => $label ] )" />
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="confirmDelete( {{ $column->id }} )" :aria-label="__( 'Delete :name', [ 'name' => $label ] )" />
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    <x-artisanpack-drawer
        wire:model="editing"
        :title="null === $columnId ? __( 'New column' ) : __( 'Edit column' )"
        right
        separator
        with-close-button
        close-on-escape
        class="w-full max-w-lg"
    >
        <form wire:submit="save" class="flex flex-col gap-4" data-column-form>
            @include( 'ecommerce-admin::partials.error-summary' )
            <x-artisanpack-select
                id="column-substatus"
                :label="__( 'Sub-status' )"
                :hint="__( 'Cards in this column are orders on this sub-status.' )"
                :options="$substatusOptions"
                :placeholder="__( 'Choose a sub-status' )"
                placeholder-value=""
                wire:model="form.substatus_id"
                required
            />

            <x-artisanpack-input id="column-label" :label="__( 'Label' )" :hint="__( 'Leave empty to use the sub-status label.' )" wire:model="form.label_override" maxlength="120" />

            <x-artisanpack-colorpicker id="column-color" :label="__( 'Colour' )" :hint="__( 'A hex code like #3B82F6. Leave empty to use the sub-status colour.' )" wire:model.live.debounce.300ms="form.color_override" />

            @if ( null !== $contrastWarning )
                <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" role="status" data-contrast-warning>
                    {{ $contrastWarning }}
                </x-artisanpack-alert>
            @endif

            @if ( $iconPicker )
                <div data-icon-picker>
                    <x-artisanpack-choices-offline
                        id="column-icon"
                        :label="__( 'Icon' )"
                        :hint="__( 'Leave empty to use the sub-status icon.' )"
                        :options="$iconOptions"
                        single
                        searchable
                        clearable
                        :no-result-text="__( 'No results found.' )"
                        wire:model="form.icon_override"
                    />
                </div>
            @else
                <x-artisanpack-input id="column-icon" :label="__( 'Icon' )" :hint="__( 'An icon name, such as o-printer. Leave empty to use the sub-status icon.' )" wire:model="form.icon_override" maxlength="80" data-icon-text />
            @endif

            <x-artisanpack-input id="column-wip-limit" type="number" min="1" step="1" :label="__( 'WIP limit' )" :hint="__( 'The most cards the column may hold. Moves into a full column are refused. Leave empty for no limit.' )" wire:model="form.wip_limit" />

            <fieldset class="fieldset flex flex-col gap-2" data-column-card-widgets>
                <legend class="fieldset-legend">{{ __( 'Card widgets' ) }}</legend>
                <p class="text-sm opacity-75">
                    {{ [] === $defaultWidgets
                        ? __( 'What each card shows, top to bottom. Leave empty to use the board default.' )
                        : __( 'What each card shows, top to bottom. Leave empty to use the board default: :widgets.', [ 'widgets' => implode( ', ', array_map( static fn ( string $key ): string => $widgetLabels[ $key ] ?? $key, $defaultWidgets ) ) ] ) }}
                </p>

                @if ( [] !== $form['card_widgets'] )
                    <ol class="flex list-none flex-col gap-1" aria-label="{{ __( 'Card widgets' ) }}">
                        @foreach ( array_values( $form['card_widgets'] ) as $index => $widget )
                            @php $widgetLabel = $widgetLabels[ $widget ] ?? __( 'Unavailable (:type)', [ 'type' => $widget ] ); @endphp
                            <li wire:key="widget-{{ $widget }}-{{ $index }}" class="flex flex-wrap items-center gap-2 rounded-box border border-base-content/10 px-3 py-1" data-widget="{{ $widget }}">
                                <span class="grow">{{ $widgetLabel }}</span>
                                @error( 'form.card_widgets.' . $index )
                                    <span class="text-sm text-error">{{ $message }}</span>
                                @enderror
                                <x-artisanpack-button variant="ghost" size="xs" icon="o-arrow-up" wire:click="moveWidget( {{ $index }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="widgets" data-reorder-index="{{ $index }}" data-reorder-item="{{ $widgetLabel }}" :disabled="0 === $index" :aria-label="__( 'Move :label up', [ 'label' => $widgetLabel ] )" />
                                <x-artisanpack-button variant="ghost" size="xs" icon="o-arrow-down" wire:click="moveWidget( {{ $index }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="widgets" data-reorder-index="{{ $index }}" data-reorder-item="{{ $widgetLabel }}" :disabled="$loop->last" :aria-label="__( 'Move :label down', [ 'label' => $widgetLabel ] )" />
                                <x-artisanpack-button variant="ghost" size="xs" icon="o-x-mark" wire:click="removeWidget( {{ $index }} )" wire:loading.attr="disabled" :aria-label="__( 'Remove :label', [ 'label' => $widgetLabel ] )" />
                            </li>
                        @endforeach
                    </ol>
                @endif

                <div class="flex flex-wrap items-end gap-2">
                    <div class="grow">
                        <x-artisanpack-select
                            id="column-widget-to-add"
                            :label="__( 'Add a widget' )"
                            :options="$widgetOptions"
                            :placeholder="__( 'Choose a widget' )"
                            placeholder-value=""
                            wire:model="widgetToAdd"
                        />
                    </div>
                    <x-artisanpack-button variant="outline" icon="o-plus" wire:click="addWidget" :label="__( 'Add' )" />
                </div>
            </fieldset>

            <div class="flex justify-end gap-2">
                <x-artisanpack-button variant="ghost" wire:click="$set( 'editing', false )" :label="__( 'Cancel' )" />
                <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" :label="__( 'Save column' )" />
            </div>
        </form>
    </x-artisanpack-drawer>

    @if ( null !== $deleting )
        @php $deleteTitle = __( 'Delete ":name"?', [ 'name' => $deleting->displayLabel() ] ); @endphp
        <x-artisanpack-modal wire:model="confirmingDelete" :title="$deleteTitle" separator>
            <div class="flex flex-col gap-3" data-delete-column="{{ $deleting->id }}">
                @if ( $deletingCards > 0 )
                    <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" role="status" data-delete-in-use>
                        {{ trans_choice( ':count card is in this column. Move it to another column first.|:count cards are in this column. Move them to another column first.', $deletingCards, [ 'count' => $deletingCards ] ) }}
                    </x-artisanpack-alert>
                @else
                    <p>{{ __( 'The sub-status itself is kept. Deleting the column cannot be undone.' ) }}</p>
                @endif
                @if ( $deletingRules > 0 )
                    <p data-delete-automations>{{ trans_choice( ':count automation that starts or ends in this column is deleted too.|:count automations that start or end in this column are deleted too.', $deletingRules, [ 'count' => $deletingRules ] ) }}</p>
                @endif
            </div>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="cancelDelete" :label="__( 'Keep column' )" />
                <x-artisanpack-button
                    color="error"
                    wire:click="delete( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )"
                    wire:loading.attr="disabled"
                    :disabled="$deletingCards > 0"
                    :label="__( 'Delete column' )"
                    data-delete-button
                />
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif
</section>
