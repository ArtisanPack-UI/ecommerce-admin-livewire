{{--
    Rule builder. See RuleBuilder and WithRuleBuilder.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div {{ $attributes->class( [ 'flex flex-col gap-6' ] ) }} data-rule-builder>
    <div class="rounded-box border border-info/40 bg-info/5 p-3" role="status" aria-live="polite" data-rule-summary>
        <span class="font-semibold">{{ __( 'Summary:' ) }}</span>
        {{ $builder['summary'] }}
    </div>

    @foreach ( $builder['lists'] as $list )
        <section class="flex flex-col gap-3" aria-labelledby="{{ $prefix }}-list-{{ $list['key'] }}" data-rule-list="{{ $list['key'] }}">
            <h3 id="{{ $prefix }}-list-{{ $list['key'] }}" class="text-lg font-semibold">{{ $list['label'] }}</h3>

            @if ( [] === $list['rows'] )
                <p class="opacity-75">{{ $list['empty'] }}</p>
            @else
                <p id="{{ $prefix }}-list-{{ $list['key'] }}-help" class="text-sm opacity-75">
                    {{ __( 'Drag a row, or use Move up and Move down, to change the order.' ) }}
                </p>

                <ol
                    class="flex flex-col gap-2"
                    aria-labelledby="{{ $prefix }}-list-{{ $list['key'] }}"
                    aria-describedby="{{ $prefix }}-list-{{ $list['key'] }}-help"
                    x-data
                    x-drag-context
                    x-on:drag:end="$wire.reorderRules( {{ \Illuminate\Support\Js::from( $list['key'] ) }}, $event.detail.orderedIds )"
                >
                    @foreach ( $list['rows'] as $row )
                        @php
                            $rowError = $errors->has( 'ruleRows.' . $list['key'] . '.' . $row['index'] . '.*' ) || $errors->has( 'ruleRows.' . $list['key'] . '.' . $row['index'] . '.type' );
                        @endphp
                        <li
                            wire:key="rule-{{ $list['key'] }}-{{ $row['id'] }}"
                            x-drag-item="{{ \Illuminate\Support\Js::from( $row['id'] ) }}"
                            @class( [ 'rounded-box border p-3', 'border-error' => $rowError, 'border-base-content/10' => ! $rowError ] )
                            aria-label="{{ __( ':position. :label', [ 'position' => $row['index'] + 1, 'label' => $row['label'] ] ) }}"
                            data-rule-row="{{ $row['type'] }}"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <x-artisanpack-icon name="o-bars-3" class="h-4 w-4 cursor-grab opacity-50" aria-hidden="true" />
                                <span class="font-semibold">{{ $row['label'] }}</span>
                                <span class="text-sm opacity-75">{{ $row['description'] }}</span>
                                @if ( $rowError )
                                    <x-artisanpack-badge :value="__( 'Has errors' )" class="badge-sm" color="error" />
                                @endif

                                <div class="ms-auto flex flex-wrap gap-1">
                                    <x-artisanpack-button
                                        variant="ghost"
                                        size="xs"
                                        icon="o-arrow-up"
                                        wire:click="moveRule( {{ \Illuminate\Support\Js::from( $list['key'] ) }}, {{ $row['index'] }}, -1 )" data-reorder="up" data-reorder-list="rules-{{ $list['key'] }}" data-reorder-key="rule-{{ $row['id'] }}" data-reorder-item="{{ $row['label'] }}"
                                        :disabled="0 === $row['index']"
                                        :aria-label="__( 'Move :label up', [ 'label' => $row['label'] ] )"
                                    />
                                    <x-artisanpack-button
                                        variant="ghost"
                                        size="xs"
                                        icon="o-arrow-down"
                                        wire:click="moveRule( {{ \Illuminate\Support\Js::from( $list['key'] ) }}, {{ $row['index'] }}, 1 )" data-reorder="down" data-reorder-list="rules-{{ $list['key'] }}" data-reorder-key="rule-{{ $row['id'] }}" data-reorder-item="{{ $row['label'] }}"
                                        :disabled="$row['index'] === count( $list['rows'] ) - 1"
                                        :aria-label="__( 'Move :label down', [ 'label' => $row['label'] ] )"
                                    />
                                    @if ( $row['available'] )
                                        <x-artisanpack-button
                                            variant="ghost"
                                            size="xs"
                                            icon="o-adjustments-horizontal"
                                            wire:click="toggleRule( {{ \Illuminate\Support\Js::from( $list['key'] ) }}, {{ \Illuminate\Support\Js::from( $row['id'] ) }} )"
                                            aria-expanded="{{ $row['open'] ? 'true' : 'false' }}"
                                            aria-controls="{{ $prefix }}-settings-{{ $row['id'] }}"
                                            :label="$row['open'] ? __( 'Close settings' ) : __( 'Settings' )"
                                        />
                                    @endif
                                    <x-artisanpack-button
                                        variant="ghost"
                                        size="xs"
                                        icon="o-trash"
                                        wire:click="removeRule( {{ \Illuminate\Support\Js::from( $list['key'] ) }}, {{ $row['index'] }} )"
                                        :aria-label="__( 'Remove :label', [ 'label' => $row['label'] ] )"
                                    />
                                </div>
                            </div>

                            @error( 'ruleRows.' . $list['key'] . '.' . $row['index'] . '.type' )
                                <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>
                            @enderror

                            {{-- Inside the rule's own item, so a list of N rules reads as N items. --}}
                            @if ( $row['open'] && $row['available'] )
                                <div id="{{ $prefix }}-settings-{{ $row['id'] }}" wire:key="rule-settings-{{ $list['key'] }}-{{ $row['id'] }}" class="mt-3 rounded-box bg-base-200/50 p-3" data-rule-settings="{{ $row['type'] }}">
                                    <x-artisanpack-ec-config-form :registry="$list['registry']" :entry="$row['type']" :model="'ruleRows.' . $list['key'] . '.' . $row['index'] . '.config'" />
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ( ! $list['full'] )
                <div class="flex flex-wrap items-end gap-2">
                    <x-artisanpack-select
                        id="{{ $prefix }}-add-{{ $list['key'] }}"
                        :label="$list['add']"
                        :options="$list['options']"
                        :placeholder="__( 'Choose…' )"
                        placeholder-value=""
                        wire:model="ruleToAdd.{{ $list['key'] }}"
                    />
                    <x-artisanpack-button icon="o-plus" wire:click="addRule( {{ \Illuminate\Support\Js::from( $list['key'] ) }} )" wire:loading.attr="disabled" :label="__( 'Add' )" />
                </div>
            @endif
        </section>
    @endforeach
</div>
