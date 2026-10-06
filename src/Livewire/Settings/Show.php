<?php

/**
 * Settings screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Settings;

use ArtisanPackUI\Ecommerce\Exceptions\SettingsWriteException;
use ArtisanPackUI\Ecommerce\Models\EcommerceSetting;
use ArtisanPackUI\Ecommerce\Models\Satellite;
use ArtisanPackUI\Ecommerce\Registries\PaymentGatewayRegistry;
use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\Ecommerce\Registries\SettingsRegistry;
use ArtisanPackUI\Ecommerce\Settings\SettingDefinition;
use ArtisanPackUI\Ecommerce\Settings\SettingsRepository;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\SettingsTabRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\RowKeys;
use ArtisanPackUI\EcommerceAdminLivewire\Support\UnsavedChanges;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The store settings, one tab per group (spec §7.9).
 *
 * - **Engine groups** (`general`, `checkout`, `tax`, …) and groups satellites
 *   add to the engine's `SettingsRegistry` get a form built from their
 *   definitions: the type picks the input, the definition's rules validate
 *   it, and the engine's `SettingsRepository` stores it. A value an admin has
 *   changed is marked and can be reset to the configured default.
 * - **Payments** also lists each gateway credential as configured or not.
 *   Credentials are read from the environment and are never shown, stored,
 *   or editable here.
 * - **Base currency** changes open a confirmation explaining plan §16.4:
 *   existing orders keep the currency and rate they were placed with, and
 *   reports convert them at today's rate and flag them.
 * - **Satellites** is a read-only list of registered satellites: package,
 *   version, active or uninstalled, and whether its contract was verified.
 *   Uninstalling stays a CLI command.
 * - **Custom tabs** from `SettingsTabRegistry` mount their own component.
 *
 * Abilities: `settings.view` for the screen and `settings.update` to save.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Show extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;

    /**
     * The key of the built-in satellites tab.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SATELLITES_TAB = 'satellites';

    /**
     * The open tab.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $group = '';

    /**
     * Form values, keyed by {@see self::field()}.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    public array $form = [];

    /**
     * Whether the base-currency confirmation is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $confirmingBaseCurrency = false;

    /**
     * Authorizes the screen and loads the group.
     *
     * @since 1.0.0
     *
     * @param  string  $group  The tab key.
     *
     * @return void
     */
    public function mount( string $group ): void
    {
        $this->authorizeEcommerce( 'view', EcommerceSetting::class );

        abort_unless( null !== $this->tab( $group ), 404 );

        $this->group = $group;

        $this->fill( [ 'form' => $this->formValues() ] );
    }

    /**
     * Re-checks the screen ability on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeEcommerce( 'view', EcommerceSetting::class );
    }

    /**
     * Validates and stores the group's values.
     *
     * @since 1.0.0
     *
     * @param  bool  $confirmBaseCurrencyChange  Set by the base-currency confirmation.
     *
     * @return void
     */
    public function save( bool $confirmBaseCurrencyChange = false ): void
    {
        $this->authorizeEcommerce( 'update', EcommerceSetting::class );

        if ( ! $this->isFormTab() ) {
            return;
        }

        $values     = $this->submittedValues();
        $repository = app( SettingsRepository::class );
        $base       = SettingsRepository::BASE_CURRENCY_KEY;

        if (
            ! $confirmBaseCurrencyChange
            && array_key_exists( $base, $values )
            && strtoupper( (string) $values[ $base ] ) !== strtoupper( (string) $repository->get( $base ) )
        ) {
            $this->confirmingBaseCurrency = true;

            return;
        }

        $this->confirmingBaseCurrency = false;

        try {
            $changes = $repository->update( $this->group, $values, $confirmBaseCurrencyChange );
        } catch ( SettingsWriteException $exception ) {
            $this->showErrors( $exception );

            return;
        }

        $this->resetErrorBag();
        $this->form = $this->formValues();

        $this->dispatch( UnsavedChanges::SAVED_EVENT );

        [] === $changes
            ? $this->toastSuccess( __( 'No changes to save.' ) )
            : $this->toastSuccess( trans_choice( ':count setting saved.|:count settings saved.', count( $changes ), [ 'count' => count( $changes ) ] ) );
    }

    /**
     * Saves after the base-currency change was confirmed.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function confirmBaseCurrencyChange(): void
    {
        $this->save( true );
    }

    /**
     * Deletes a stored value so the setting falls back to its configured
     * default. The base currency is changed through the form instead.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The setting key.
     *
     * @return void
     */
    public function resetToDefault( string $key ): void
    {
        $this->authorizeEcommerce( 'update', EcommerceSetting::class );

        if ( ! $this->isFormTab() || SettingsRepository::BASE_CURRENCY_KEY === $key ) {
            return;
        }

        try {
            app( SettingsRepository::class )->forget( $this->group, [ $key ] );
        } catch ( SettingsWriteException $exception ) {
            $this->showErrors( $exception );

            return;
        }

        // Only this field: other unsaved edits on the form stay as they are.
        $field = self::field( $key );

        $this->resetErrorBag( 'form.' . $field );
        $this->form[ $field ] = $this->formValues()[ $field ] ?? null;

        $definition = app( SettingsRegistry::class )->definition( $key );

        $this->toastSuccess( __( '":setting" is back to its default.', [ 'setting' => $definition?->label ?? $key ] ) );
    }

    /**
     * Adds a row to a key/value setting.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The setting key.
     *
     * @return void
     */
    public function addMapRow( string $key ): void
    {
        $this->authorizeEcommerce( 'update', EcommerceSetting::class );

        $field = self::field( $key );

        if ( is_array( $this->form[ $field ] ?? null ) ) {
            $this->form[ $field ][] = [ RowKeys::KEY => RowKeys::make(), 'key' => '', 'value' => '' ];
        }
    }

    /**
     * Removes a row from a key/value setting.
     *
     * @since 1.0.0
     *
     * @param  string  $key    The setting key.
     * @param  int     $index  Row index.
     *
     * @return void
     */
    public function removeMapRow( string $key, int $index ): void
    {
        $this->authorizeEcommerce( 'update', EcommerceSetting::class );

        $field = self::field( $key );

        if ( is_array( $this->form[ $field ] ?? null ) ) {
            unset( $this->form[ $field ][ $index ] );

            $this->form[ $field ] = array_values( $this->form[ $field ] );
        }
    }

    /**
     * The form field name for a setting key (dots would nest in Livewire).
     *
     * @since 1.0.0
     *
     * @param  string  $key  The setting key.
     *
     * @return string
     */
    public static function field( string $key ): string
    {
        return str_replace( '.', '__', $key );
    }

    /**
     * Renders the screen.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $tab        = $this->tab( $this->group );
        $repository = app( SettingsRepository::class );
        $registry   = app( SettingsRegistry::class );
        $isForm     = 'form' === ( $tab['kind'] ?? null );

        return view( 'ecommerce-admin::livewire.settings.show', [
            'tabs'         => $this->tabs(),
            'tab'          => $tab,
            'canUpdate'    => $this->canEcommerce( 'update', EcommerceSetting::class ),
            'definitions'  => $isForm ? array_values( $registry->definitions( $this->group ) ) : [],
            'stored'       => $isForm ? array_filter( array_map( static fn ( SettingDefinition $definition ): bool => $repository->isStored( $definition->key ), $registry->definitions( $this->group ) ) ) : [],
            'secrets'      => $isForm ? array_values( $registry->secrets( $this->group ) ) : [],
            'gateways'     => $isForm && 'payments' === $this->group ? $this->gateways() : [],
            'timezones'    => DateTimeZone::listIdentifiers(),
            'satellites'   => 'satellites' === ( $tab['kind'] ?? null ) ? $this->satellites() : [],
            'baseCurrency' => strtoupper( (string) $repository->get( SettingsRepository::BASE_CURRENCY_KEY ) ),
        ] );
    }

    /**
     * Every tab in display order: engine groups, custom tabs (which replace a
     * group with the same key), then satellites.
     *
     * @since 1.0.0
     *
     * @return array<int, array{key: string, label: string, description: string|null, kind: string, component: string|null, position: int}>
     */
    protected function tabs(): array
    {
        $tabs = [];

        foreach ( app( SettingsRegistry::class )->groups() as $group ) {
            $tabs[ $group->key ] = [
                'key'         => $group->key,
                'label'       => $group->label,
                'description' => $group->description,
                'kind'        => 'form',
                'component'   => null,
                'position'    => $group->position,
            ];
        }

        foreach ( app( SettingsTabRegistry::class )->all() as $custom ) {
            $tabs[ $custom['key'] ] = [
                'key'         => $custom['key'],
                'label'       => $custom['label'],
                'description' => null,
                'kind'        => 'component',
                'component'   => $custom['component'],
                'position'    => $custom['position'],
            ];
        }

        $tabs[ self::SATELLITES_TAB ] ??= [
            'key'         => self::SATELLITES_TAB,
            'label'       => __( 'Satellites' ),
            'description' => __( 'Packages that extend the store. Uninstall a satellite with php artisan ecommerce:satellite:uninstall.' ),
            'kind'        => 'satellites',
            'component'   => null,
            'position'    => 1000,
        ];

        $tabs = array_values( $tabs );

        usort( $tabs, static fn ( array $a, array $b ): int => [ $a['position'], $a['key'] ] <=> [ $b['position'], $b['key'] ] );

        return $tabs;
    }

    /**
     * One tab.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Tab key.
     *
     * @return array{key: string, label: string, description: string|null, kind: string, component: string|null, position: int}|null
     */
    protected function tab( string $key ): ?array
    {
        foreach ( $this->tabs() as $tab ) {
            if ( $key === $tab['key'] ) {
                return $tab;
            }
        }

        return null;
    }

    /**
     * Whether the open tab is a generated form.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function isFormTab(): bool
    {
        return 'form' === ( $this->tab( $this->group )['kind'] ?? null );
    }

    /**
     * The group's current values in form shape.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function formValues(): array
    {
        if ( ! $this->isFormTab() ) {
            return [];
        }

        $repository = app( SettingsRepository::class );
        $form       = [];

        foreach ( app( SettingsRegistry::class )->definitions( $this->group ) as $key => $definition ) {
            $value = $repository->get( $key );

            $form[ self::field( $key ) ] = match ( $definition->type ) {
                'boolean'     => (bool) $value,
                'multiselect' => array_values( array_map( 'strval', (array) ( $value ?? [] ) ) ),
                'list'        => implode( "\n", array_map( 'strval', (array) ( $value ?? [] ) ) ),
                'map'         => array_map(
                    static fn ( $mapKey, $mapValue ): array => [ RowKeys::KEY => RowKeys::make(), 'key' => (string) $mapKey, 'value' => (string) $mapValue ],
                    array_keys( (array) ( $value ?? [] ) ),
                    array_values( (array) ( $value ?? [] ) ),
                ),
                default       => null === $value ? '' : ( is_scalar( $value ) ? (string) $value : '' ),
            };
        }

        return $form;
    }

    /**
     * The form values in the shape the repository stores.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function submittedValues(): array
    {
        $values = [];

        foreach ( app( SettingsRegistry::class )->definitions( $this->group ) as $key => $definition ) {
            $field = self::field( $key );

            if ( ! array_key_exists( $field, $this->form ) ) {
                continue;
            }

            $value = $this->form[ $field ];

            $values[ $key ] = match ( $definition->type ) {
                'list'        => array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $value ) ?: [] ), static fn ( string $line ): bool => '' !== $line ) ),
                'map'         => self::mapValue( (array) $value ),
                'multiselect' => array_values( array_map( 'strval', (array) $value ) ),
                'boolean'     => (bool) $value,
                default       => is_string( $value ) && '' === trim( $value ) ? null : $value,
            };
        }

        return $values;
    }

    /**
     * Key/value rows as a map, skipping rows without a key.
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $rows  Form rows.
     *
     * @return array<string, string>
     */
    protected static function mapValue( array $rows ): array
    {
        $map = [];

        foreach ( $rows as $row ) {
            $mapKey = trim( (string) ( $row['key'] ?? '' ) );

            if ( '' !== $mapKey ) {
                $map[ $mapKey ] = (string) ( $row['value'] ?? '' );
            }
        }

        return $map;
    }

    /**
     * Puts each refusal on its field, or in a toast when it has none.
     *
     * @since 1.0.0
     *
     * @param  SettingsWriteException  $exception  The refusal.
     *
     * @return void
     */
    protected function showErrors( SettingsWriteException $exception ): void
    {
        $this->resetErrorBag();

        foreach ( $exception->errors as $error ) {
            $field = $error['field'] ?? null;

            if ( null !== $field && array_key_exists( self::field( $field ), $this->form ) ) {
                $this->addError( 'form.' . self::field( $field ), (string) $error['message'] );
            } else {
                $this->toastError( __( 'The settings were not saved.' ), (string) $error['message'] );
            }
        }
    }

    /**
     * The registered payment gateways with their labels.
     *
     * @since 1.0.0
     *
     * @return array<int, array{key: string, label: string}>
     */
    protected function gateways(): array
    {
        $registry = app( PaymentGatewayRegistry::class );

        return array_map(
            static fn ( string $key ): array => [ 'key' => $key, 'label' => (string) ( $registry->meta( $key )['label'] ?? $key ) ],
            $registry->keys(),
        );
    }

    /**
     * Registered and recorded satellites, by package name.
     *
     * @since 1.0.0
     *
     * @return array<int, array{package: string, label: string, version: string, active: bool, verified: bool}>
     */
    protected function satellites(): array
    {
        $registry = app( SatelliteRegistry::class );
        $rows     = [];

        try {
            $records = Satellite::query()->orderBy( 'package_name' )->get()->keyBy( 'package_name' );
        } catch ( Throwable ) {
            $records = collect();
        }

        foreach ( $registry->all() as $descriptor ) {
            $record                           = $records[ $descriptor->packageName ] ?? null;
            $rows[ $descriptor->packageName ] = [
                'package'  => $descriptor->packageName,
                'label'    => $descriptor->displayName(),
                'version'  => $descriptor->version,
                'active'   => $registry->isActive( $descriptor->packageName ),
                'verified' => null !== $record?->verified_report_hash,
            ];
        }

        foreach ( $records as $package => $record ) {
            $rows[ $package ] ??= [
                'package'  => (string) $package,
                'label'    => (string) ( $record->label ?? $package ),
                'version'  => (string) $record->version,
                'active'   => null === $record->uninstalled_at,
                'verified' => null !== $record->verified_report_hash,
            ];
        }

        ksort( $rows );

        return array_values( $rows );
    }
}
