{{--
    Address form (#86). The editable twin of Address: a design-system Fieldset
    with a two-column grid that collapses to one. Country comes first and
    binds live, because it decides how region and postal code are labelled.
    Company, Address and Apartment span both columns. Every input keeps its
    autocomplete token. See AddressForm.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<x-artisanpack-fieldset
    :title="$legend"
    :description="$description"
    :variant="null === $legend ? 'plain' : 'panel'"
    {{ $attributes->class( [ 'ec-address-form' ] ) }}
>
    <div class="ec-field-grid">
        <div class="ec-span-2" wire:key="{{ $model }}-country_code">
            <x-artisanpack-select
                :id="$model . '.country_code'"
                :label="__( 'Country' )"
                :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $countries() )"
                :placeholder="__( 'Select a country' )"
                autocomplete="country"
                required
                wire:model.live="{{ $model }}.country_code"
            />
        </div>

        @foreach ( $fields() as $field => $definition )
            <div @class( [ 'ec-span-2' => $definition['wide'] ] ) wire:key="{{ $model }}-{{ $field }}">
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
    </div>
</x-artisanpack-fieldset>
