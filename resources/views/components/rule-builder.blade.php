{{--
    Rule builder (#88). See RuleBuilder and WithRuleBuilder.

    A plain-language summary (an info Alert, so a polite live region) above
    each ordered list of rules. Every rule is a bordered row named
    "2. Customer group": drag is the enhancement, Move up and Move down are
    the keyboard path, the settings toggle opens a Config form inside the
    row, and a row with errors shows a "Has errors" badge plus the message.
    A select and an Add button below each list append a rule.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div {{ $attributes->class( [ 'ec-rule-builder' ] ) }} data-rule-builder>
    <x-artisanpack-alert color="info" icon="info" data-rule-summary>
        <strong class="ec-rule-summary-label">{{ __( 'Summary:' ) }}</strong>
        {{ $builder['summary'] }}
    </x-artisanpack-alert>

    @foreach ( $builder['lists'] as $list )
        @php
            $listKey   = \Illuminate\Support\Js::from( $list['key'] );
            $listId    = $prefix . '-list-' . $list['key'];
            $rowCount  = count( $list['rows'] );
        @endphp
        <section class="ec-rule-list" aria-labelledby="{{ $listId }}" data-rule-list="{{ $list['key'] }}">
            <div class="ec-rule-list-head">
                <x-artisanpack-heading :level="3" :size="5" id="{{ $listId }}">{{ $list['label'] }}</x-artisanpack-heading>
                @if ( $rowCount > 0 )
                    <span class="ec-rule-count">{{ trans_choice( ':count rule|:count rules', $rowCount, [ 'count' => $rowCount ] ) }}</span>
                @endif
            </div>

            @if ( 0 === $rowCount )
                <p class="ec-rule-empty">{{ $list['empty'] }}</p>
            @else
                <p id="{{ $listId }}-help" class="ec-rule-help">
                    {{ __( 'Drag a row, or use Move up and Move down, to change the order.' ) }}
                </p>

                <ol
                    class="ec-rule-rows"
                    aria-labelledby="{{ $listId }}"
                    aria-describedby="{{ $listId }}-help"
                    x-data
                    x-drag-context
                    x-on:drag:end="$wire.reorderRules( {{ $listKey }}, $event.detail.orderedIds )"
                >
                    @foreach ( $list['rows'] as $row )
                        @php
                            $rowPath  = 'ruleRows.' . $list['key'] . '.' . $row['index'];
                            $rowError = $errors->has( $rowPath . '.*' ) || $errors->has( $rowPath . '.type' );
                            $rowId    = \Illuminate\Support\Js::from( $row['id'] );
                        @endphp
                        <li
                            wire:key="rule-{{ $list['key'] }}-{{ $row['id'] }}"
                            x-drag-item="{{ $rowId }}"
                            class="ec-rule"
                            @if ( $rowError ) data-invalid @endif
                            aria-label="{{ __( ':position. :label', [ 'position' => $row['index'] + 1, 'label' => $row['label'] ] ) }}"
                            data-rule-row="{{ $row['type'] }}"
                        >
                            <div class="ec-rule-head">
                                <span class="ec-rule-handle" aria-hidden="true"><x-artisanpack-icon name="grip-vertical" /></span>
                                <span class="ec-rule-index" aria-hidden="true">{{ $row['index'] + 1 }}.</span>
                                <span class="ec-rule-title">{{ $row['label'] }}</span>
                                <span class="ec-rule-desc">{{ $row['description'] }}</span>
                                @if ( $rowError )
                                    <x-artisanpack-badge color="error" size="sm" leading-icon="circle-alert">{{ __( 'Has errors' ) }}</x-artisanpack-badge>
                                @endif

                                <div class="ec-rule-actions">
                                    <x-artisanpack-button
                                        variant="ghost"
                                        color="neutral"
                                        size="xs"
                                        icon-only
                                        leading-icon="arrow-up"
                                        wire:click="moveRule( {{ $listKey }}, {{ $row['index'] }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="rules-{{ $list['key'] }}" data-reorder-key="rule-{{ $row['id'] }}" data-reorder-item="{{ $row['label'] }}"
                                        :disabled="0 === $row['index']"
                                        :aria-label="__( 'Move :label up', [ 'label' => $row['label'] ] )"
                                    />
                                    <x-artisanpack-button
                                        variant="ghost"
                                        color="neutral"
                                        size="xs"
                                        icon-only
                                        leading-icon="arrow-down"
                                        wire:click="moveRule( {{ $listKey }}, {{ $row['index'] }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="rules-{{ $list['key'] }}" data-reorder-key="rule-{{ $row['id'] }}" data-reorder-item="{{ $row['label'] }}"
                                        :disabled="$row['index'] === $rowCount - 1"
                                        :aria-label="__( 'Move :label down', [ 'label' => $row['label'] ] )"
                                    />
                                    @if ( $row['available'] )
                                        <x-artisanpack-button
                                            variant="ghost"
                                            color="neutral"
                                            size="xs"
                                            leading-icon="sliders-horizontal"
                                            wire:click="toggleRule( {{ $listKey }}, {{ $rowId }} )"
                                            aria-expanded="{{ $row['open'] ? 'true' : 'false' }}"
                                            aria-controls="{{ $prefix }}-settings-{{ $row['id'] }}">{{ $row['open'] ? __( 'Close settings' ) : __( 'Settings' ) }}</x-artisanpack-button>
                                    @endif
                                    <x-artisanpack-button
                                        variant="ghost"
                                        color="neutral"
                                        size="xs"
                                        icon-only
                                        leading-icon="trash"
                                        wire:click="removeRule( {{ $listKey }}, {{ $row['index'] }} )" wire:loading.attr="disabled"
                                        :aria-label="__( 'Remove :label', [ 'label' => $row['label'] ] )"
                                    />
                                </div>
                            </div>

                            @error( $rowPath . '.type' )
                                <p class="ec-rule-error" role="alert"><x-artisanpack-icon name="circle-alert" />{{ $message }}</p>
                            @enderror

                            {{-- Inside the rule's own item, so a list of N rules reads as N items. --}}
                            @if ( $row['open'] && $row['available'] )
                                <div id="{{ $prefix }}-settings-{{ $row['id'] }}" wire:key="rule-settings-{{ $list['key'] }}-{{ $row['id'] }}" class="ec-rule-body" data-rule-settings="{{ $row['type'] }}">
                                    <x-artisanpack-ec-config-form :registry="$list['registry']" :entry="$row['type']" :model="$rowPath . '.config'" />
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ( ! $list['full'] )
                <div class="ec-rule-add">
                    <x-artisanpack-select
                        id="{{ $prefix }}-add-{{ $list['key'] }}"
                        class="ec-rule-add-select"
                        :label="$list['add']"
                        :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $list['options'] )"
                        :placeholder="__( 'Choose…' )"
                        wire:model="ruleToAdd.{{ $list['key'] }}"
                    />
                    <x-artisanpack-button variant="outline" color="neutral" leading-icon="plus" wire:click="addRule( {{ $listKey }} )" wire:loading.attr="disabled">{{ __( 'Add' ) }}</x-artisanpack-button>
                </div>
            @endif
        </section>
    @endforeach
</div>
