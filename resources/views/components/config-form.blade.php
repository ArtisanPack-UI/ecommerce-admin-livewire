{{--
    Config form (#87). Renders a registry's settings schema with one
    design-system control per type, in a field grid that collapses to one
    column. Without a schema it falls back to a JSON editor with a notice;
    with an empty schema it says there is nothing to set. See ConfigForm.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div {{ $attributes->class( [ 'ec-config-form' ] ) }} data-config-form="{{ $registry }}:{{ $entry }}">
    @if ( null === $schema )
        {{-- Escaped quotes inside a component attribute break Blade's component-tag parsing, so the text is built first. --}}
        @php( $jsonNotice = __( 'Edit them as JSON. They must be a JSON object, like {"key": "value"}.' ) )
        <div class="ec-config-json">
            <x-artisanpack-alert
                color="warning"
                :title="__( 'No form for these settings' )"
                :description="$jsonNotice"
                role="status"
            />
            <x-artisanpack-code-editor
                :id="$fieldId( $model )"
                :label="__( 'Settings (JSON)' )"
                language="json"
                :rows="10"
                dir="ltr"
                wire:model="{{ $model }}"
            />
        </div>
    @elseif ( [] === $schema )
        <x-artisanpack-empty-state
            class="ec-config-empty"
            size="sm"
            align="start"
            :description="__( 'There are no settings for this.' )"
            role="status"
            data-config-form-empty
        />
    @else
        <div class="ec-field-grid">
            @foreach ( $schema as $field )
                <div @class( [ 'ec-span-2' => $spansBothColumns( $field ) ] ) wire:key="{{ $fieldId( $model . '.' . $field['name'] ) }}">
                    @include( 'ecommerce-admin::components.config-form.field', [ 'field' => $field, 'path' => $model . '.' . $field['name'], 'root' => $model, 'relative' => $field['name'] ] )
                </div>
            @endforeach
        </div>
    @endif
</div>
