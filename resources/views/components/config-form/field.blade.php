{{--
    One config form field bound to $path. $root and $relative locate a
    repeater for addConfigRow() / removeConfigRow().

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
            <x-artisanpack-input :id="$id" :label="$field['label']" :hint="$field['hint']" type="number" step="any" inputmode="decimal" wire:model="{{ $path }}" />
            @break

        @case( 'money' )
            <x-artisanpack-ec-money-input :id="$id" :label="$field['label']" :hint="$field['hint']" wire:model="{{ $path }}" />
            @break

        @case( 'percent' )
            <x-artisanpack-input :id="$id" :label="$field['label']" :hint="$field['hint']" type="number" step="any" min="0" max="100" inputmode="decimal" suffix="%" wire:model="{{ $path }}" />
            @break

        @case( 'boolean' )
            <x-artisanpack-toggle :id="$id" :label="$field['label']" :hint="$field['hint']" wire:model="{{ $path }}" />
            @break

        @case( 'select' )
            <x-artisanpack-select :id="$id" :label="$field['label']" :hint="$field['hint']" :options="$field['options']" :placeholder="__( 'Choose…' )" placeholder-value="" wire:model="{{ $path }}" />
            @break

        @case( 'multiselect' )
            <x-artisanpack-checkbox-group :id="$id" :label="$field['label']" :hint="$field['hint']" :options="$field['options']" wire:model="{{ $path }}" />
            @break

        @case( 'weekday' )
            <x-artisanpack-checkbox-group :id="$id" :label="$field['label']" :hint="$field['hint']" :options="$weekdays()" horizontal wire:model="{{ $path }}" />
            @break

        @case( 'tag' )
            <x-artisanpack-tags :id="$id" :label="$field['label']" :hint="$field['hint']" :custom-tags-text="__( 'Press Enter to add' )" wire:model="{{ $path }}" />
            @break

        @case( 'date' )
            <x-artisanpack-input :id="$id" :label="$field['label']" :hint="$field['hint']" type="date" wire:model="{{ $path }}" />
            @break

        @case( 'daterange' )
            {{-- Two dates until livewire-ui-components ships a range picker (spec §10, U3). --}}
            <fieldset class="fieldset">
                <legend class="fieldset-legend">{{ $field['label'] }}</legend>
                <div class="flex flex-wrap gap-2">
                    <x-artisanpack-input :id="$id . '-start'" :label="__( 'From' )" type="date" wire:model="{{ $path }}.start" />
                    <x-artisanpack-input :id="$id . '-end'" :label="__( 'To' )" type="date" wire:model="{{ $path }}.end" />
                </div>
                @if ( null !== $field['hint'] )
                    <p class="fieldset-label">{{ $field['hint'] }}</p>
                @endif
            </fieldset>
            @break

        @case( 'template' )
            <x-artisanpack-textarea :id="$id" :label="$field['label']" :hint="$field['hint']" rows="6" class="font-mono" wire:model="{{ $path }}" />
            @break

        @case( 'textarea' )
            <x-artisanpack-textarea :id="$id" :label="$field['label']" :hint="$field['hint']" rows="4" wire:model="{{ $path }}" />
            @break

        @case( 'json' )
            <x-artisanpack-code :id="$id" :label="$field['label']" :hint="$field['hint']" language="json" height="6rem" wire:model="{{ $path }}" />
            @error( $path )
                <p class="text-sm text-error" role="alert">{{ $message }}</p>
            @enderror
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
            <fieldset class="fieldset rounded-box border border-base-300 p-3">
                <legend class="fieldset-legend">{{ $field['label'] }}</legend>
                @if ( null !== $field['hint'] )
                    <p class="fieldset-label mb-2">{{ $field['hint'] }}</p>
                @endif

                @foreach ( $rows as $index => $row )
                    <div class="flex flex-wrap items-end gap-3 border-b border-base-200 pb-3" role="group" aria-label="{{ __( ':field row :number', [ 'field' => $field['label'], 'number' => $index + 1 ] ) }}" wire:key="{{ $id }}-row-{{ $index }}">
                        @foreach ( $field['fields'] as $column )
                            <div class="min-w-40 flex-1">
                                @include( 'ecommerce-admin::components.config-form.field', [ 'field' => $column, 'path' => $path . '.' . $index . '.' . $column['name'], 'root' => $root, 'relative' => $relative ] )
                            </div>
                        @endforeach
                        <x-artisanpack-button
                            variant="ghost"
                            size="sm"
                            icon="o-trash"
                            wire:click="removeConfigRow( {{ $rowArguments }}, {{ (int) $index }} )" wire:loading.attr="disabled"
                            :label="__( 'Remove' )"
                            :aria-label="__( 'Remove :field row :number', [ 'field' => $field['label'], 'number' => $index + 1 ] )"
                        />
                    </div>
                @endforeach

                @error( $path )
                    <p class="text-sm text-error" role="alert">{{ $message }}</p>
                @enderror

                <div class="mt-2">
                    <x-artisanpack-button variant="outline" size="sm" icon="o-plus" wire:click="addConfigRow( {{ $rowArguments }} )" :label="__( 'Add a row' )" />
                </div>
            </fieldset>
            @break

        @default
            <x-artisanpack-input :id="$id" :label="$field['label']" :hint="$field['hint']" wire:model="{{ $path }}" />
    @endswitch
</div>
