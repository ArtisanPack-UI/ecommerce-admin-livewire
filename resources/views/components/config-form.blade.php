{{--
    Config form. See ConfigForm.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div {{ $attributes->class( [ 'flex flex-col gap-3' ] ) }} data-config-form="{{ $registry }}:{{ $entry }}">
    @if ( null === $schema )
        {{-- Escaped quotes inside a component attribute break Blade's component-tag parsing, so the text is built first. --}}
        @php( $jsonNotice = __( 'Edit them as JSON. They must be a JSON object, like {"key": "value"}.' ) )
        <x-artisanpack-alert
            color="warning"
            icon="o-exclamation-triangle"
            :title="__( 'No form for these settings' )"
            :description="$jsonNotice"
            role="status"
        />
        <x-artisanpack-code
            :id="$fieldId( $model )"
            :label="__( 'Settings (JSON)' )"
            language="json"
            height="12rem"
            wire:model="{{ $model }}"
        />
        @error( $model )
            <p class="text-sm text-error" role="alert">{{ $message }}</p>
        @enderror
    @elseif ( [] === $schema )
        <p class="opacity-75" data-config-form-empty>{{ __( 'There are no settings for this.' ) }}</p>
    @else
        @foreach ( $schema as $field )
            <div wire:key="{{ $fieldId( $model . '.' . $field['name'] ) }}">
                @include( 'ecommerce-admin::components.config-form.field', [ 'field' => $field, 'path' => $model . '.' . $field['name'], 'root' => $model, 'relative' => $field['name'] ] )
            </div>
        @endforeach
    @endif
</div>
