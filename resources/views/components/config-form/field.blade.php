{{--
    One config form field bound to $path (#87): each schema type maps to one
    design-system control. $root and $relative locate a repeater for
    addConfigRow() / removeConfigRow(). A text field named `secret` (or
    flagged `sensitive`) is a password input, so a signing secret isn't
    shown on screen or offered to autofill.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    $id = $fieldId( $path );
@endphp
<div data-config-field="{{ $field['type'] }}">
    @switch ( $field['type'] )
        @case( 'number' )
            <x-artisanpack-input :id="$id" :label="$field['label']" :hint="$field['hint']" type="number" step="any" inputmode="decimal" class="ec-numeric" wire:model="{{ $path }}" />
            @break

        @case( 'money' )
            <x-artisanpack-ec-money-input :id="$id" :label="$field['label']" :hint="$field['hint']" wire:model="{{ $path }}" />
            @break

        @case( 'percent' )
            <x-artisanpack-input :id="$id" :label="$field['label']" :hint="$field['hint']" type="number" step="any" min="0" max="100" inputmode="decimal" suffix="%" class="ec-numeric" wire:model="{{ $path }}" />
            @break

        @case( 'boolean' )
            <x-artisanpack-toggle :id="$id" :label="$field['label']" :description="$field['hint']" wire:model="{{ $path }}" />
            @break

        @case( 'select' )
            <x-artisanpack-select :id="$id" :label="$field['label']" :hint="$field['hint']" :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $field['options'] )" :placeholder="__( 'Choose…' )" wire:model="{{ $path }}" />
            @break

        @case( 'multiselect' )
            <x-artisanpack-checkbox-group :id="$id" :label="$field['label']" :hint="$field['hint']" :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $field['options'] )" orientation="horizontal" wire:model="{{ $path }}" />
            @break

        @case( 'weekday' )
            {{-- Selectable chips: toggle buttons in a labelled group, in the locale's week order. --}}
            @php
                $selectedDays = array_map( 'intval', array_values( (array) $valueAt( $path ) ) );
                $daysError    = isset( $errors ) && $errors instanceof \Illuminate\Support\ViewErrorBag ? ( $errors->first( $path ) ?: $errors->first( $path . '.*' ) ) : '';
            @endphp
            {{-- The path is read from data-config-path on each use, so a repeater row that morphs to a new index binds the new one. --}}
            <div
                class="ec-weekdays"
                data-config-path="{{ $path }}"
                x-data="{
                    days() {
                        const value = $wire.$get( this.$root.dataset.configPath );
                        return Array.isArray( value ) ? value.map( Number ) : [];
                    },
                    isOn( day ) {
                        return this.days().includes( day );
                    },
                    toggle( day ) {
                        const days = this.days();
                        $wire.$set( this.$root.dataset.configPath, this.isOn( day ) ? days.filter( ( entry ) => entry !== day ) : [ ...days, day ].sort( ( a, b ) => a - b ), false );
                    },
                }"
            >
                <span class="ec-field-label" id="{{ $id }}-label">{{ $field['label'] }}</span>
                <div class="ec-chip-row" role="group" aria-labelledby="{{ $id }}-label" @if ( null !== $field['hint'] || '' !== $daysError ) aria-describedby="{{ implode( ' ', array_filter( [ null !== $field['hint'] ? $id . '-hint' : null, '' !== $daysError ? $id . '-error' : null ] ) ) }}" @endif>
                    @foreach ( $weekdays() as $day )
                        <button
                            type="button"
                            class="ec-chip-toggle"
                            aria-label="{{ $day['name'] }}"
                            aria-pressed="{{ in_array( $day['id'], $selectedDays, true ) ? 'true' : 'false' }}"
                            x-bind:aria-pressed="isOn( {{ $day['id'] }} ) ? 'true' : 'false'"
                            x-on:click="toggle( {{ $day['id'] }} )"
                            wire:key="{{ $id }}-day-{{ $day['id'] }}"
                        >{{ $day['short'] }}</button>
                    @endforeach
                </div>
                @if ( null !== $field['hint'] )
                    <p class="ec-field-hint" id="{{ $id }}-hint">{{ $field['hint'] }}</p>
                @endif
                @if ( '' !== $daysError )
                    <p class="ec-field-error" id="{{ $id }}-error" role="alert"><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $daysError }}</p>
                @endif
            </div>
            @break

        @case( 'tag' )
            <x-artisanpack-combobox multiple allow-create :id="$id" :label="$field['label']" :hint="$field['hint']" :placeholder="__( 'Type and press Enter to add' )" :create-label="__( 'Add “:value”', [ 'value' => '{value}' ] )" wire:model="{{ $path }}" />
            @break

        @case( 'date' )
            <x-artisanpack-date-picker :id="$id" :label="$field['label']" :hint="$field['hint']" wire:model="{{ $path }}" />
            @break

        @case( 'daterange' )
            {{-- Two DatePickers under one legend; the range's start and end keep their own error keys. --}}
            <fieldset class="ec-range" @if ( null !== $field['hint'] ) aria-describedby="{{ $id }}-hint" @endif>
                <legend>{{ $field['label'] }}</legend>
                <div class="ec-range-fields">
                    <x-artisanpack-date-picker :id="$id . '-start'" :aria-label="__( 'From' )" :placeholder="__( 'From' )" wire:model="{{ $path }}.start" />
                    <span aria-hidden="true">&ndash;</span>
                    <x-artisanpack-date-picker :id="$id . '-end'" :aria-label="__( 'To' )" :placeholder="__( 'To' )" wire:model="{{ $path }}.end" />
                </div>
                @if ( null !== $field['hint'] )
                    <p class="ec-field-hint" id="{{ $id }}-hint">{{ $field['hint'] }}</p>
                @endif
            </fieldset>
            @break

        @case( 'template' )
            <x-artisanpack-textarea :id="$id" :label="$field['label']" :hint="$field['hint']" rows="6" class="ec-mono" dir="ltr" wire:model="{{ $path }}" />
            @break

        @case( 'textarea' )
            <x-artisanpack-textarea :id="$id" :label="$field['label']" :hint="$field['hint']" rows="4" wire:model="{{ $path }}" />
            @break

        @case( 'json' )
            <x-artisanpack-code-editor :id="$id" :label="$field['label']" :hint="$field['hint']" language="json" :rows="5" dir="ltr" wire:model="{{ $path }}" />
            @break

        @case( 'product' )
            @if ( 'variant' === $field['source'] )
                <x-artisanpack-ec-variant-picker :id="$id" :model="$path" :label="$field['label']" :hint="$field['hint']" :single="! $field['multiple']" :options="$pickerOptions( 'variant', $path )" />
            @else
                <x-artisanpack-ec-product-picker :id="$id" :model="$path" :label="$field['label']" :hint="$field['hint']" :single="! $field['multiple']" :options="$pickerOptions( 'product', $path )" />
            @endif
            @break

        @case( 'category' )
            <x-artisanpack-ec-category-picker :id="$id" :model="$path" :label="$field['label']" :hint="$field['hint']" :single="! $field['multiple']" :options="$pickerOptions( 'category', $path )" />
            @break

        @case( 'product-tag' )
            <x-artisanpack-ec-tag-picker :id="$id" :model="$path" :label="$field['label']" :hint="$field['hint']" :single="! $field['multiple']" :options="$pickerOptions( 'tag', $path )" />
            @break

        @case( 'repeater' )
            @php
                $rows = array_values( (array) $valueAt( $path ) );

                // `@js` is not compiled inside component attributes, so the
                // action arguments are encoded here.
                $rowArguments = implode( ', ', array_map(
                    static fn ( string $argument ): string => \Illuminate\Support\Js::from( $argument )->toHtml(),
                    [ $registry, $entry, $root, $relative ],
                ) );
            @endphp
            <x-artisanpack-fieldset :title="$field['label']" :description="$field['hint']" class="ec-repeater">
                @foreach ( $rows as $index => $row )
                    <div class="ec-repeat-row" role="group" aria-label="{{ __( ':field row :number', [ 'field' => $field['label'], 'number' => $index + 1 ] ) }}" wire:key="{{ $id }}-row-{{ \ArtisanPackUI\EcommerceAdminLivewire\Support\RowKeys::of( $row, $index ) }}">
                        <div class="ec-repeat-cells">
                            @foreach ( $field['fields'] as $column )
                                <div>
                                    @include( 'ecommerce-admin::components.config-form.field', [ 'field' => $column, 'path' => $path . '.' . $index . '.' . $column['name'], 'root' => $root, 'relative' => $relative ] )
                                </div>
                            @endforeach
                        </div>
                        <x-artisanpack-button
                            variant="ghost"
                            color="neutral"
                            icon-only
                            leading-icon="trash"
                            wire:click="removeConfigRow( {{ $rowArguments }}, {{ (int) $index }} )" wire:loading.attr="disabled"
                            :aria-label="__( 'Remove :field row :number', [ 'field' => $field['label'], 'number' => $index + 1 ] )" />
                    </div>
                @endforeach

                @error( $path )
                    <p class="ec-field-error" role="alert"><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
                @enderror

                <div>
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="plus" wire:click="addConfigRow( {{ $rowArguments }} )">{{ __( 'Add a row' ) }}</x-artisanpack-button>
                </div>
            </x-artisanpack-fieldset>
            @break

        @default
            @if ( 'secret' === $field['name'] || true === ( $field['sensitive'] ?? false ) )
                <x-artisanpack-input :id="$id" :label="$field['label']" :hint="$field['hint']" type="password" autocomplete="new-password" spellcheck="false" wire:model="{{ $path }}" data-config-secret />
            @else
                <x-artisanpack-input :id="$id" :label="$field['label']" :hint="$field['hint']" wire:model="{{ $path }}" />
            @endif
    @endswitch
</div>
