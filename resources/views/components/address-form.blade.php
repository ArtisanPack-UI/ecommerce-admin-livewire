<fieldset {{ $attributes->class( [ 'grid', 'gap-4', 'sm:grid-cols-2' ] ) }}>
    @if ( null !== $legend )
        <legend class="mb-2 font-semibold sm:col-span-2">{{ $legend }}</legend>
    @endif

    @foreach ( $fields() as $field => $definition )
        <div @class( [ 'sm:col-span-2' => in_array( $field, [ 'company', 'address1', 'address2' ], true ) ] ) wire:key="{{ $model }}-{{ $field }}">
            @if ( $live )
                <x-artisanpack-input
                    :id="$model . '.' . $field"
                    :label="$definition['label']"
                    :autocomplete="$definition['autocomplete']"
                    :required="$definition['required']"
                    wire:model.live.blur="{{ $model }}.{{ $field }}"
                />
            @else
                <x-artisanpack-input
                    :id="$model . '.' . $field"
                    :label="$definition['label']"
                    :autocomplete="$definition['autocomplete']"
                    :required="$definition['required']"
                    wire:model="{{ $model }}.{{ $field }}"
                />
            @endif
        </div>
    @endforeach

    <div wire:key="{{ $model }}-country_code">
        @if ( $live )
            <x-artisanpack-select
                :id="$model . '.country_code'"
                :label="__( 'Country' )"
                :options="$countries()"
                :placeholder="__( 'Select a country' )"
                placeholder-value=""
                autocomplete="country"
                required
                wire:model.live="{{ $model }}.country_code"
            />
        @else
            <x-artisanpack-select
                :id="$model . '.country_code'"
                :label="__( 'Country' )"
                :options="$countries()"
                :placeholder="__( 'Select a country' )"
                placeholder-value=""
                autocomplete="country"
                required
                wire:model="{{ $model }}.country_code"
            />
        @endif
    </div>
</fieldset>
