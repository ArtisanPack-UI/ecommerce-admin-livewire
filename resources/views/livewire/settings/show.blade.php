{{--
    Settings (#142). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Settings\Show.

    A section list on the start side (a row of underlined links on a phone)
    with one link per settings group, satellite tab and Satellites. A group
    is a card of rows: the label and its hint on the start side, the control
    on the end side. A setting changed from its configured default carries
    an info badge and Reset to default, which asks first in an alert dialog
    because it saves immediately. Credentials only say Configured or Not
    configured, with the env variable in mono. Save stays in view at the
    bottom. Changing the base currency asks first and lists what will and
    won't change.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Settings\SettingsRepository;
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Settings\Show;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\RowKeys;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\UnsavedChanges;
    use Illuminate\Support\Js;
@endphp
<div class="ec-stack" data-settings x-data="{ {!! UnsavedChanges::members() !!} }" {!! UnsavedChanges::attributes() !!}>
    <x-artisanpack-page-header :title="__( 'Settings' )" :level="1" />

    <div class="ec-settings-layout">
        <nav class="ec-settings-nav" aria-label="{{ __( 'Settings sections' ) }}" data-settings-nav>
            <ul class="ec-settings-nav__list">
                @foreach ( $tabs as $navTab )
                    @php $isCurrent = $navTab['key'] === $tab['key']; @endphp
                    <li wire:key="settings-tab-{{ $navTab['key'] }}">
                        <a
                            class="ec-settings-nav__link"
                            href="{{ route( 'artisanpack.ecommerce.admin.settings.show', [ 'group' => $navTab['key'] ] ) }}"
                            @if ( $isCurrent ) aria-current="page" @endif
                            data-settings-tab="{{ $navTab['key'] }}"
                        >{{ $navTab['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="ec-settings-content">
            @switch ( $tab['kind'] )
                @case( 'component' )
                    @livewire( $tab['component'], [], key( 'settings-component-' . $tab['key'] ) )
                    @break

                @case( 'satellites' )
                    <section class="apui-card" aria-labelledby="settings-satellites-heading" data-satellites>
                        <div data-scope="card" data-part="header">
                            <div>
                                <h2 id="settings-satellites-heading" data-scope="card" data-part="title">{{ $tab['label'] }}</h2>
                                <p data-scope="card" data-part="description">{!! strtr( e( __( 'Packages that extend the store. Uninstall a satellite with :command.' ) ), [ ':command' => '<bdi dir="ltr" class="ec-mono">php artisan ecommerce:satellite:uninstall</bdi>' ] ) !!}</p>
                            </div>
                        </div>

                        <div data-scope="card" data-part="body" class="ec-card-body-flush">
                            @if ( [] === $satellites )
                                <x-artisanpack-ec-admin-empty-state
                                    size="sm"
                                    :heading-level="3"
                                    icon="box"
                                    :title="__( 'No satellites are registered.' )"
                                    :description="__( 'Satellites appear here once their package is installed.' )"
                                />
                            @else
                                <div class="apui-table ec-table">
                                    <div data-scope="table" data-part="scroll">
                                        <table data-scope="table" data-part="table">
                                            <caption class="apui-sr-only">{{ __( 'Satellites' ) }}</caption>
                                            <thead data-scope="table" data-part="head">
                                                <tr data-scope="table" data-part="row">
                                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Package' ) }}</th>
                                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Version' ) }}</th>
                                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Status' ) }}</th>
                                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Contract verified' ) }}</th>
                                                </tr>
                                            </thead>
                                            <tbody data-scope="table" data-part="body">
                                                @foreach ( $satellites as $satellite )
                                                    <tr data-scope="table" data-part="row" wire:key="satellite-{{ $satellite['package'] }}" data-satellite="{{ $satellite['package'] }}">
                                                        <td data-scope="table" data-part="cell">
                                                            <span class="ec-strong">{{ $satellite['label'] }}</span>
                                                            <span class="ec-cell-secondary"><bdi dir="ltr" class="ec-mono">{{ $satellite['package'] }}</bdi></span>
                                                        </td>
                                                        <td data-scope="table" data-part="cell"><bdi dir="ltr" class="ec-mono">{{ $satellite['version'] }}</bdi></td>
                                                        <td data-scope="table" data-part="cell">
                                                            <x-artisanpack-badge :color="$satellite['active'] ? 'success' : 'neutral'" dot>{{ $satellite['active'] ? __( 'Active' ) : __( 'Uninstalled' ) }}</x-artisanpack-badge>
                                                        </td>
                                                        <td data-scope="table" data-part="cell">
                                                            @if ( $satellite['verified'] )
                                                                <x-artisanpack-badge color="success" leading-icon="check">{{ __( 'Verified' ) }}</x-artisanpack-badge>
                                                            @elseif ( $satellite['active'] )
                                                                <x-artisanpack-badge color="warning" leading-icon="triangle-alert">{{ __( 'Not verified' ) }}</x-artisanpack-badge>
                                                            @else
                                                                <span class="ec-muted-text" aria-hidden="true">—</span>
                                                                <span class="apui-sr-only">{{ __( 'Not verified' ) }}</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>
                    @break

                @default
                    <form wire:submit="save" class="ec-stack" novalidate data-settings-form="{{ $tab['key'] }}">
                        @include( 'ecommerce-admin::partials.error-summary' )

                        <fieldset class="ec-settings-fieldset" @disabled( ! $canUpdate )>
                            <legend class="apui-sr-only">{{ $tab['label'] }}</legend>

                            <section class="apui-card" aria-labelledby="settings-group-heading" data-settings-group="{{ $tab['key'] }}">
                                <div data-scope="card" data-part="header">
                                    <div>
                                        <h2 id="settings-group-heading" data-scope="card" data-part="title">{{ $tab['label'] }}</h2>
                                        @if ( filled( $tab['description'] ) )
                                            <p data-scope="card" data-part="description">{{ $tab['description'] }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div data-scope="card" data-part="body" class="ec-settings-rows">
                                    @foreach ( $definitions as $definition )
                                        @php
                                            $field    = Show::field( $definition->key );
                                            $path     = 'form.' . $field;
                                            $id       = 'setting-' . str_replace( [ '.', '_' ], '-', $definition->key );
                                            $isStored = isset( $stored[ $definition->key ] );
                                            $hint     = 'list' === $definition->type ? trim( ( $definition->description ?? '' ) . ' ' . __( 'One per line.' ) ) : $definition->description;
                                            $helpId   = filled( $hint ) ? $id . '-help' : null;
                                            $isGroup  = in_array( $definition->type, [ 'multiselect', 'map' ], true );
                                        @endphp
                                        <div wire:key="setting-{{ $definition->key }}" class="ec-setting-row" data-setting="{{ $definition->key }}" data-setting-type="{{ $definition->type }}">
                                            <div class="ec-setting-row__text">
                                                @if ( $isGroup )
                                                    <span class="ec-setting-row__label" id="{{ $id }}-label">{{ $definition->label }}</span>
                                                @else
                                                    <label class="ec-setting-row__label" for="{{ $id }}" id="{{ $id }}-label">{{ $definition->label }}</label>
                                                @endif
                                                @if ( null !== $helpId )
                                                    <p class="ec-muted ec-setting-row__hint" id="{{ $helpId }}">{{ $hint }}</p>
                                                @endif

                                                @if ( $isStored )
                                                    <div class="ec-setting-row__stored" data-setting-stored>
                                                        <x-artisanpack-badge color="info" size="sm" leading-icon="info">{{ __( 'Changed from the configured default.' ) }}</x-artisanpack-badge>
                                                        @if ( $canUpdate && SettingsRepository::BASE_CURRENCY_KEY !== $definition->key )
                                                            <x-artisanpack-button
                                                                variant="ghost"
                                                                color="neutral"
                                                                size="xs"
                                                                leading-icon="rotate-ccw"
                                                                wire:click="confirmReset( {{ Js::from( $definition->key ) }} )"
                                                                wire:loading.attr="disabled"
                                                                data-focus-key="setting-reset-{{ $definition->key }}"
                                                                data-reset-setting="{{ $definition->key }}"
                                                                :aria-label="__( 'Reset to default: :setting', [ 'setting' => $definition->label ] )">{{ __( 'Reset to default' ) }}</x-artisanpack-button>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="ec-setting-row__control">
                                                @switch ( $definition->type )
                                                    @case( 'boolean' )
                                                        <x-artisanpack-toggle :id="$id" :aria-describedby="$helpId" wire:model="{{ $path }}" />
                                                        @break

                                                    @case( 'integer' )
                                                        <x-artisanpack-input :id="$id" :aria-describedby="$helpId" type="number" step="1" inputmode="numeric" wire:model="{{ $path }}" />
                                                        @break

                                                    @case( 'email' )
                                                        <x-artisanpack-input :id="$id" :aria-describedby="$helpId" type="email" dir="ltr" wire:model="{{ $path }}" />
                                                        @break

                                                    @case( 'text' )
                                                        <x-artisanpack-textarea :id="$id" :aria-describedby="$helpId" rows="4" wire:model="{{ $path }}" />
                                                        @break

                                                    @case( 'select' )
                                                        <x-artisanpack-select
                                                            :id="$id"
                                                            :aria-describedby="$helpId"
                                                            :options="ControlOptions::from( collect( $definition->options() )->map( fn ( $label, $value ) => [ 'id' => (string) $value, 'name' => $label ] )->values()->all() )"
                                                            :placeholder="__( 'Choose…' )"
                                                            wire:model="{{ $path }}"
                                                        />
                                                        @break

                                                    @case( 'multiselect' )
                                                        <x-artisanpack-checkbox-group
                                                            :id="$id"
                                                            :label="$definition->label"
                                                            :classes="[ 'label' => 'apui-sr-only' ]"
                                                            :aria-describedby="$helpId"
                                                            :options="ControlOptions::from( collect( $definition->options() )->map( fn ( $label, $value ) => [ 'id' => (string) $value, 'name' => $label ] )->values()->all() )"
                                                            wire:model="{{ $path }}"
                                                        />
                                                        @break

                                                    @case( 'list' )
                                                        <x-artisanpack-textarea :id="$id" :aria-describedby="$helpId" rows="3" dir="auto" wire:model="{{ $path }}" />
                                                        @break

                                                    @case( 'map' )
                                                        <div class="ec-setting-map" role="group" aria-labelledby="{{ $id }}-label" @if ( null !== $helpId ) aria-describedby="{{ $helpId }}" @endif>
                                                            @if ( [] !== (array) ( $form[ $field ] ?? [] ) )
                                                                <ol class="ec-setting-map__rows">
                                                                    @foreach ( (array) ( $form[ $field ] ?? [] ) as $index => $row )
                                                                        <li class="ec-setting-map__row" wire:key="{{ $id }}-row-{{ RowKeys::of( $row, $index ) }}">
                                                                            <x-artisanpack-input
                                                                                :id="$id . '-' . $index . '-key'"
                                                                                size="sm"
                                                                                class="ec-mono-input"
                                                                                dir="ltr"
                                                                                :aria-label="__( 'Key, row :number', [ 'number' => $index + 1 ] )"
                                                                                wire:model="{{ $path }}.{{ $index }}.key"
                                                                            />
                                                                            <x-artisanpack-input
                                                                                :id="$id . '-' . $index . '-value'"
                                                                                size="sm"
                                                                                :aria-label="__( 'Value, row :number', [ 'number' => $index + 1 ] )"
                                                                                wire:model="{{ $path }}.{{ $index }}.value"
                                                                            />
                                                                            @if ( $canUpdate )
                                                                                <x-artisanpack-button
                                                                                    variant="ghost"
                                                                                    color="neutral"
                                                                                    size="xs"
                                                                                    icon-only
                                                                                    leading-icon="x"
                                                                                    wire:click="removeMapRow( {{ Js::from( $definition->key ) }}, {{ (int) $index }} )" wire:loading.attr="disabled"
                                                                                    :aria-label="__( 'Remove :field row :number', [ 'field' => $definition->label, 'number' => $index + 1 ] )"
                                                                                />
                                                                            @endif
                                                                        </li>
                                                                    @endforeach
                                                                </ol>
                                                            @endif

                                                            @if ( $canUpdate )
                                                                <div>
                                                                    <x-artisanpack-button size="xs" variant="outline" color="neutral" leading-icon="plus" wire:click="addMapRow( {{ Js::from( $definition->key ) }} )">{{ __( 'Add row' ) }}</x-artisanpack-button>
                                                                </div>
                                                            @endif
                                                        </div>
                                                        @break

                                                    @case( 'currency' )
                                                        <x-artisanpack-input :id="$id" :aria-describedby="$helpId" class="ec-setting-currency" maxlength="3" autocomplete="off" dir="ltr" wire:model="{{ $path }}" />
                                                        @break

                                                    @case( 'timezone' )
                                                        <x-artisanpack-select
                                                            :id="$id"
                                                            :aria-describedby="$helpId"
                                                            :options="ControlOptions::from( collect( $timezones )->map( fn ( $zone ) => [ 'id' => $zone, 'name' => $zone ] )->all() )"
                                                            :placeholder="__( 'Application time zone' )"
                                                            wire:model="{{ $path }}"
                                                        />
                                                        @break

                                                    @default
                                                        <x-artisanpack-input :id="$id" :aria-describedby="$helpId" wire:model="{{ $path }}" />
                                                @endswitch

                                                @if ( in_array( $definition->type, [ 'map', 'multiselect', 'list' ], true ) )
                                                    @error( $path . '.*' )
                                                        <p class="ec-field-error" role="alert"><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
                                                    @enderror
                                                @endif
                                                @if ( in_array( $definition->type, [ 'map' ], true ) )
                                                    @error( $path )
                                                        <p class="ec-field-error" role="alert"><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
                                                    @enderror
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        </fieldset>

                        @if ( [] !== $secrets )
                            <section class="apui-card" aria-labelledby="settings-credentials-heading" data-secrets>
                                <div data-scope="card" data-part="header">
                                    <div>
                                        <h2 id="settings-credentials-heading" data-scope="card" data-part="title">{{ __( 'Credentials' ) }}</h2>
                                        <p data-scope="card" data-part="description">{{ __( 'Credentials are read from the environment. They are never shown or stored here; set them in your .env file.' ) }}</p>
                                    </div>
                                </div>

                                <div data-scope="card" data-part="body">
                                    <ul class="ec-settings-secrets">
                                        @foreach ( $secrets as $secret )
                                            @php $configured = $secret->isConfigured(); @endphp
                                            <li class="ec-settings-secret" wire:key="secret-{{ $secret->configKey }}" data-secret="{{ $secret->configKey }}" data-configured="{{ $configured ? 'yes' : 'no' }}">
                                                <span class="ec-settings-secret__name">
                                                    <span>{{ $secret->label }}</span>
                                                    @if ( null !== $secret->envName )
                                                        <bdi dir="ltr" class="ec-mono ec-muted-text">{{ $secret->envName }}</bdi>
                                                    @endif
                                                </span>
                                                <x-artisanpack-badge :color="$configured ? 'success' : 'warning'" size="sm" :leading-icon="$configured ? 'check' : 'triangle-alert'">{{ $configured ? __( 'Configured' ) : __( 'Not configured' ) }}</x-artisanpack-badge>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </section>
                        @endif

                        @if ( 'payments' === $tab['key'] )
                            <p class="ec-muted ec-icon-line" data-gateways>
                                <x-artisanpack-icon name="credit-card" aria-hidden="true" />
                                <span>
                                    @if ( [] === $gateways )
                                        {{ __( 'No payment gateway is registered. Enable Stripe above, or install a gateway satellite.' ) }}
                                    @else
                                        {{ __( 'Registered gateways: :gateways. Changes to the Stripe switch apply from the next request; restart queue workers to pick them up.', [ 'gateways' => implode( ', ', array_column( $gateways, 'label' ) ) ] ) }}
                                    @endif
                                </span>
                            </p>
                        @endif

                        @if ( $canUpdate )
                            <div class="ec-settings-save" data-settings-save>
                                <x-artisanpack-button type="submit" leading-icon="check" wire:loading.attr="disabled" wire:target="save">{{ __( 'Save settings' ) }}</x-artisanpack-button>
                            </div>
                        @endif
                    </form>

                    @php $newBaseCurrency = strtoupper( (string) ( $form[ Show::field( SettingsRepository::BASE_CURRENCY_KEY ) ] ?? '' ) ); @endphp
                    <x-artisanpack-dialog
                        wire:model="confirmingBaseCurrency"
                        role="alertdialog"
                        color="warning"
                        icon="triangle-alert"
                        :title="__( 'Change the base currency?' )"
                        :close-label="__( 'Close' )"
                        :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
                        data-base-currency-dialog
                    >
                        <div class="ec-stack-sm" data-base-currency-warning>
                            <p class="ec-body">{!! strtr( e( __( 'The base currency is changing from :from to :to.' ) ), [ ':from' => '<strong>' . e( $baseCurrency ) . '</strong>', ':to' => '<strong>' . e( $newBaseCurrency ) . '</strong>' ] ) !!}</p>
                            <ul class="ec-bullets">
                                <li>{{ __( 'Existing orders keep the base currency and exchange rate they were placed with.' ) }}</li>
                                <li>{{ __( 'New orders use the new base currency.' ) }}</li>
                                <li>{{ __( 'Reports show every amount in the new base currency. Older orders are converted at today\'s exchange rate and flagged.' ) }}</li>
                                <li>{{ __( 'Catalog prices are not converted. Products need a price in the new base currency, because prices in other currencies are worked out from it.' ) }}</li>
                            </ul>
                        </div>

                        <x-slot:footer>
                            <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'confirmingBaseCurrency', false )" data-autofocus>{{ __( 'Cancel' ) }}</x-artisanpack-button>
                            <x-artisanpack-button color="warning" wire:click="confirmBaseCurrencyChange" wire:loading.attr="disabled" data-confirm-base-currency>{{ __( 'Change base currency' ) }}</x-artisanpack-button>
                        </x-slot:footer>
                    </x-artisanpack-dialog>

                    @if ( null !== $resetting )
                        <x-artisanpack-dialog
                            wire:model="confirmingReset"
                            role="alertdialog"
                            size="sm"
                            color="neutral"
                            icon="rotate-ccw"
                            :title="__( 'Reset :setting to its default?', [ 'setting' => $resetting->label ] )"
                            :close-label="__( 'Close' )"
                            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
                            data-reset-dialog="{{ $resetting->key }}"
                        >
                            <p class="ec-body">
                                @if ( null !== $resetDefault )
                                    {{ __( 'It goes back to :value, the value in your config. This saves immediately.', [ 'value' => $resetDefault ] ) }}
                                @else
                                    {{ __( 'It goes back to the value in your config, which is empty. This saves immediately.' ) }}
                                @endif
                            </p>

                            <x-slot:footer>
                                <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelReset" data-autofocus data-focus-return="setting-reset-{{ $resetting->key }}">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                                <x-artisanpack-button wire:click="resetToDefault( {{ Js::from( $resetting->key ) }} )" wire:loading.attr="disabled" data-confirm-reset>{{ __( 'Reset to default' ) }}</x-artisanpack-button>
                            </x-slot:footer>
                        </x-artisanpack-dialog>
                    @endif
            @endswitch
        </div>
    </div>
</div>
