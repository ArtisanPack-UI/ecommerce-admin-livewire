{{--
    Store settings. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Settings\Show.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Settings\Show;
@endphp
<div class="flex flex-col gap-6">
    <x-artisanpack-header :title="__( 'Settings' )" :subtitle="$tab['description']" :level="1" separator />

    <nav aria-label="{{ __( 'Settings sections' ) }}">
        <x-artisanpack-menu class="menu-horizontal flex-wrap gap-1 rounded-box bg-base-200 p-1">
            @foreach ( $tabs as $navTab )
                <x-artisanpack-menu-item
                    wire:key="settings-tab-{{ $navTab['key'] }}"
                    :title="$navTab['label']"
                    :link="route( 'artisanpack.ecommerce.admin.settings.show', [ 'group' => $navTab['key'] ] )"
                    :active="$navTab['key'] === $tab['key']"
                    :aria-current="$navTab['key'] === $tab['key'] ? 'page' : null"
                    role="link"
                    no-wire-navigate
                />
            @endforeach
        </x-artisanpack-menu>
    </nav>

    @switch ( $tab['kind'] )
        @case( 'component' )
            @livewire( $tab['component'], [], key( 'settings-component-' . $tab['key'] ) )
            @break

        @case( 'satellites' )
            <div class="overflow-x-auto" data-satellites>
                <table class="table table-sm">
                    <caption class="sr-only">{{ __( 'Satellites' ) }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __( 'Package' ) }}</th>
                            <th scope="col">{{ __( 'Version' ) }}</th>
                            <th scope="col">{{ __( 'Status' ) }}</th>
                            <th scope="col">{{ __( 'Contract verified' ) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ( $satellites as $satellite )
                            <tr wire:key="satellite-{{ $satellite['package'] }}" data-satellite="{{ $satellite['package'] }}">
                                <td>
                                    <span class="font-medium">{{ $satellite['label'] }}</span>
                                    <code class="block text-xs opacity-75">{{ $satellite['package'] }}</code>
                                </td>
                                <td>{{ $satellite['version'] }}</td>
                                <td>
                                    <x-artisanpack-badge
                                        :value="$satellite['active'] ? __( 'Active' ) : __( 'Uninstalled' )"
                                        :color="$satellite['active'] ? 'success' : 'neutral'"
                                    />
                                </td>
                                <td>
                                    @if ( $satellite['verified'] )
                                        <span class="inline-flex items-center gap-1">
                                            <x-artisanpack-icon name="o-check-circle" class="size-4" aria-hidden="true" />
                                            {{ __( 'Verified' ) }}
                                        </span>
                                    @else
                                        {{ __( 'Not verified' ) }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">{{ __( 'No satellites are registered.' ) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @break

        @default
            <form wire:submit="save" class="flex max-w-3xl flex-col gap-6" novalidate>
                @include( 'ecommerce-admin::partials.error-summary' )
                <fieldset class="flex flex-col gap-4" @disabled( ! $canUpdate )>
                    <legend class="sr-only">{{ $tab['label'] }}</legend>

                    @foreach ( $definitions as $definition )
                        @php
                            $field    = Show::field( $definition->key );
                            $path     = 'form.' . $field;
                            $id       = 'setting-' . str_replace( [ '.', '_' ], '-', $definition->key );
                            $isStored = isset( $stored[ $definition->key ] );
                        @endphp
                        <div wire:key="setting-{{ $definition->key }}" class="flex flex-col gap-1" data-setting="{{ $definition->key }}" data-setting-type="{{ $definition->type }}">
                            @switch ( $definition->type )
                                @case( 'boolean' )
                                    <x-artisanpack-toggle :id="$id" :label="$definition->label" :hint="$definition->description" wire:model="{{ $path }}" />
                                    @break

                                @case( 'integer' )
                                    <x-artisanpack-input :id="$id" :label="$definition->label" :hint="$definition->description" type="number" step="1" inputmode="numeric" wire:model="{{ $path }}" />
                                    @break

                                @case( 'email' )
                                    <x-artisanpack-input :id="$id" :label="$definition->label" :hint="$definition->description" type="email" wire:model="{{ $path }}" />
                                    @break

                                @case( 'text' )
                                    <x-artisanpack-textarea :id="$id" :label="$definition->label" :hint="$definition->description" rows="4" wire:model="{{ $path }}" />
                                    @break

                                @case( 'select' )
                                    <x-artisanpack-select
                                        :id="$id"
                                        :label="$definition->label"
                                        :hint="$definition->description"
                                        :options="collect( $definition->options() )->map( fn ( $label, $value ) => [ 'id' => (string) $value, 'name' => $label ] )->values()->all()"
                                        :placeholder="__( 'Choose…' )"
                                        placeholder-value=""
                                        wire:model="{{ $path }}"
                                    />
                                    @break

                                @case( 'multiselect' )
                                    <x-artisanpack-checkbox-group
                                        :id="$id"
                                        :label="$definition->label"
                                        :hint="$definition->description"
                                        :options="collect( $definition->options() )->map( fn ( $label, $value ) => [ 'id' => (string) $value, 'name' => $label ] )->values()->all()"
                                        wire:model="{{ $path }}"
                                    />
                                    @break

                                @case( 'list' )
                                    <x-artisanpack-textarea :id="$id" :label="$definition->label" :hint="trim( ( $definition->description ?? '' ) . ' ' . __( 'One per line.' ) )" rows="3" wire:model="{{ $path }}" />
                                    @break

                                @case( 'map' )
                                    <fieldset class="fieldset rounded-box border border-base-300 p-3">
                                        <legend class="fieldset-legend">{{ $definition->label }}</legend>
                                        @if ( null !== $definition->description )
                                            <p class="fieldset-label mb-2">{{ $definition->description }}</p>
                                        @endif

                                        @foreach ( (array) ( $form[ $field ] ?? [] ) as $index => $row )
                                            <div class="flex flex-wrap items-end gap-3" role="group" aria-label="{{ __( ':field row :number', [ 'field' => $definition->label, 'number' => $index + 1 ] ) }}" wire:key="{{ $id }}-row-{{ $index }}">
                                                <x-artisanpack-input :id="$id . '-' . $index . '-key'" :label="__( 'Key' )" class="w-32" wire:model="{{ $path }}.{{ $index }}.key" />
                                                <div class="min-w-48 flex-1">
                                                    <x-artisanpack-input :id="$id . '-' . $index . '-value'" :label="__( 'Value' )" wire:model="{{ $path }}.{{ $index }}.value" />
                                                </div>
                                                @if ( $canUpdate )
                                                    <x-artisanpack-button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="o-trash"
                                                        wire:click="removeMapRow( {{ \Illuminate\Support\Js::from( $definition->key ) }}, {{ (int) $index }} )"
                                                        :label="__( 'Remove' )"
                                                        :aria-label="__( 'Remove :field row :number', [ 'field' => $definition->label, 'number' => $index + 1 ] )"
                                                    />
                                                @endif
                                            </div>
                                        @endforeach

                                        @if ( $canUpdate )
                                            <div class="mt-2">
                                                <x-artisanpack-button size="sm" variant="outline" icon="o-plus" wire:click="addMapRow( {{ \Illuminate\Support\Js::from( $definition->key ) }} )" :label="__( 'Add row' )" />
                                            </div>
                                        @endif
                                    </fieldset>
                                    @break

                                @case( 'currency' )
                                    <x-artisanpack-input :id="$id" :label="$definition->label" :hint="$definition->description" maxlength="3" class="uppercase" autocomplete="off" wire:model="{{ $path }}" />
                                    @break

                                @case( 'timezone' )
                                    <x-artisanpack-select
                                        :id="$id"
                                        :label="$definition->label"
                                        :hint="$definition->description"
                                        :options="collect( $timezones )->map( fn ( $zone ) => [ 'id' => $zone, 'name' => $zone ] )->all()"
                                        :placeholder="__( 'Application time zone' )"
                                        placeholder-value=""
                                        wire:model="{{ $path }}"
                                    />
                                    @break

                                @default
                                    <x-artisanpack-input :id="$id" :label="$definition->label" :hint="$definition->description" wire:model="{{ $path }}" />
                            @endswitch

                            @if ( in_array( $definition->type, [ 'map', 'multiselect', 'list' ], true ) )
                                @error( $path . '.*' )
                                    <p class="text-sm text-error" role="alert">{{ $message }}</p>
                                @enderror
                            @endif
                            @if ( 'map' === $definition->type )
                                @error( $path )
                                    <p class="text-sm text-error" role="alert">{{ $message }}</p>
                                @enderror
                            @endif

                            @if ( $isStored )
                                <div class="flex flex-wrap items-center gap-2 text-sm" data-setting-stored>
                                    <span class="opacity-75">{{ __( 'Changed from the configured default.' ) }}</span>
                                    @if ( $canUpdate && \ArtisanPackUI\Ecommerce\Settings\SettingsRepository::BASE_CURRENCY_KEY !== $definition->key )
                                        <x-artisanpack-button
                                            variant="link"
                                            size="xs"
                                            wire:click="resetToDefault( {{ \Illuminate\Support\Js::from( $definition->key ) }} )"
                                            wire:confirm="{{ __( 'Reset :setting to its default? This saves immediately.', [ 'setting' => $definition->label ] ) }}"
                                            wire:loading.attr="disabled"
                                            :label="__( 'Reset to default' )"
                                            :aria-label="__( 'Reset to default: :setting', [ 'setting' => $definition->label ] )"
                                        />
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </fieldset>

                @if ( [] !== $secrets || [] !== $gateways )
                    <section class="flex flex-col gap-3" aria-labelledby="settings-credentials-heading" data-secrets>
                        <h2 id="settings-credentials-heading" class="text-lg font-semibold">{{ __( 'Credentials' ) }}</h2>
                        <p class="text-sm opacity-75">{{ __( 'Credentials are read from the environment. They are never shown or stored here; set them in your .env file.' ) }}</p>

                        @if ( [] !== $secrets )
                            <ul class="flex list-none flex-col divide-y divide-base-300 rounded-box border border-base-300">
                                @foreach ( $secrets as $secret )
                                    @php $configured = $secret->isConfigured(); @endphp
                                    <li class="flex flex-wrap items-center gap-3 p-3" wire:key="secret-{{ $secret->configKey }}" data-secret="{{ $secret->configKey }}" data-configured="{{ $configured ? 'yes' : 'no' }}">
                                        <span class="grow">
                                            {{ $secret->label }}
                                            @if ( null !== $secret->envName )
                                                <code class="ms-2 text-xs opacity-75">{{ $secret->envName }}</code>
                                            @endif
                                        </span>
                                        <x-artisanpack-badge
                                            :value="$configured ? __( 'Configured' ) : __( 'Not configured' )"
                                            :color="$configured ? 'success' : 'warning'"
                                            :icon="$configured ? 'o-check-circle' : 'o-exclamation-triangle'"
                                        />
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ( 'payments' === $tab['key'] )
                            <p class="text-sm" data-gateways>
                                @if ( [] === $gateways )
                                    {{ __( 'No payment gateway is registered. Enable Stripe above, or install a gateway satellite.' ) }}
                                @else
                                    {{ __( 'Registered gateways: :gateways. Changes to the Stripe switch apply from the next request; restart queue workers to pick them up.', [ 'gateways' => implode( ', ', array_column( $gateways, 'label' ) ) ] ) }}
                                @endif
                            </p>
                        @endif
                    </section>
                @endif

                @if ( $canUpdate )
                    <div class="flex justify-end">
                        <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" :label="__( 'Save settings' )" />
                    </div>
                @endif
            </form>

            <x-artisanpack-modal wire:model="confirmingBaseCurrency" :title="__( 'Change the base currency?' )" separator>
                <div class="flex flex-col gap-3" data-base-currency-warning>
                    <p>{{ __( 'The base currency is changing from :from to :to.', [ 'from' => $baseCurrency, 'to' => strtoupper( (string) ( $form[ Show::field( \ArtisanPackUI\Ecommerce\Settings\SettingsRepository::BASE_CURRENCY_KEY ) ] ?? '' ) ) ] ) }}</p>
                    <ul class="list-disc ps-5">
                        <li>{{ __( 'Existing orders keep the base currency and exchange rate they were placed with.' ) }}</li>
                        <li>{{ __( 'New orders use the new base currency.' ) }}</li>
                        <li>{{ __( 'Reports show every amount in the new base currency. Older orders are converted at today\'s exchange rate and flagged.' ) }}</li>
                        <li>{{ __( 'Catalog prices are not converted. Products need a price in the new base currency, because prices in other currencies are worked out from it.' ) }}</li>
                    </ul>
                </div>

                <x-slot:actions>
                    <x-artisanpack-button variant="ghost" wire:click="$set( 'confirmingBaseCurrency', false )" :label="__( 'Cancel' )" />
                    <x-artisanpack-button color="warning" wire:click="confirmBaseCurrencyChange" wire:loading.attr="disabled" :label="__( 'Change base currency' )" data-confirm-base-currency />
                </x-slot:actions>
            </x-artisanpack-modal>
    @endswitch
</div>
