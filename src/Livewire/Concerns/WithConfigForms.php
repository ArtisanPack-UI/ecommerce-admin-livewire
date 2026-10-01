<?php

/**
 * Config form concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns;

use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;
use Livewire\Attributes\Locked;
use ReflectionProperty;

/**
 * State, validation, and repeater rows for `<x-artisanpack-ec-config-form>`.
 *
 * ```php
 * public array|string $config = [];
 *
 * public function mount(): void
 * {
 *     $this->config = $this->configFormState( 'promotion-condition', $this->conditionKey, $condition->config );
 * }
 *
 * public function save(): void
 * {
 *     $config = $this->validateConfigForm( 'promotion-condition', $this->conditionKey, 'config' );
 *     // … hand $config to the engine service.
 * }
 * ```
 *
 * Uses {@see AuthorizesEcommerce}.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
trait WithConfigForms
{
    /**
     * Appends an empty row to a repeater field.
     *
     * @since 1.0.0
     *
     * @param  string  $registry  The registry name.
     * @param  string  $entry     The entry key.
     * @param  string  $property  The config's property path.
     * @param  string  $field     The repeater field name.
     *
     * @return void
     */
    public function addConfigRow( string $registry, string $entry, string $property, string $field ): void
    {
        $this->authorizeAdminAccess();

        $definition = $this->repeaterField( $registry, $entry, $property, $field );
        $rows       = array_values( (array) data_get( $this->all(), $property . '.' . $field, [] ) );

        if ( null === $definition || count( $rows ) >= ConfigFormRegistry::MAX_ROWS ) {
            return;
        }

        $rows[] = ConfigFormRegistry::emptyRow( $definition );

        data_set( $this->{$this->rootProperty( $property )}, $this->subPath( $property, $field ), $rows );
    }

    /**
     * Removes a row from a repeater field.
     *
     * @since 1.0.0
     *
     * @param  string  $registry  The registry name.
     * @param  string  $entry     The entry key.
     * @param  string  $property  The config's property path.
     * @param  string  $field     The repeater field name.
     * @param  int     $index     The row index.
     *
     * @return void
     */
    public function removeConfigRow( string $registry, string $entry, string $property, string $field, int $index ): void
    {
        $this->authorizeAdminAccess();

        if ( null === $this->repeaterField( $registry, $entry, $property, $field ) ) {
            return;
        }

        $rows = array_values( (array) data_get( $this->all(), $property . '.' . $field, [] ) );

        unset( $rows[ $index ] );

        data_set( $this->{$this->rootProperty( $property )}, $this->subPath( $property, $field ), array_values( $rows ) );
        $this->resetErrorBag( $property . '.' . $field );
    }

    /**
     * The value to bind a config form to: the stored config merged over the
     * schema's defaults, or pretty JSON for the fallback editor.
     *
     * @since 1.0.0
     *
     * @param  string                $registry  The registry name.
     * @param  string                $entry     The entry key.
     * @param  array<string, mixed>  $config    The stored config.
     *
     * @return array<string, mixed>|string
     */
    protected function configFormState( string $registry, string $entry, array $config ): array|string
    {
        $forms = app( ConfigFormRegistry::class );

        if ( ! $forms->has( $registry, $entry ) ) {
            return (string) json_encode( (object) $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        }

        return array_replace( $forms->defaults( $registry, $entry ), $config );
    }

    /**
     * Validates the config at `$property` against its schema and returns it
     * ready to store: unknown keys dropped and values cast to their types.
     * The fallback JSON is decoded.
     *
     * @since 1.0.0
     *
     * @param  string  $registry  The registry name.
     * @param  string  $entry     The entry key.
     * @param  string  $property  The config's property path.
     *
     * @return array<string, mixed>
     */
    protected function validateConfigForm( string $registry, string $entry, string $property ): array
    {
        $forms = app( ConfigFormRegistry::class );
        $rules = $forms->rules( $registry, $entry, $property );

        if ( [] !== $rules ) {
            $this->validate( $rules, [], $forms->attributes( $registry, $entry, $property ) );
        }

        $value  = data_get( $this->all(), $property );
        $schema = $forms->schema( $registry, $entry );

        if ( null === $schema ) {
            return (array) json_decode( (string) $value, true );
        }

        return ConfigFormRegistry::cast( $schema, (array) $value );
    }

    /**
     * The repeater field definition, when the request names a real repeater
     * on a writable property.
     *
     * @since 1.0.0
     *
     * @param  string  $registry  The registry name.
     * @param  string  $entry     The entry key.
     * @param  string  $property  The config's property path.
     * @param  string  $field     The repeater field name.
     *
     * @return array<string, mixed>|null
     */
    private function repeaterField( string $registry, string $entry, string $property, string $field ): ?array
    {
        if ( 1 !== preg_match( '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z0-9_]+)*$/D', $property ) || ! $this->isWritableProperty( $this->rootProperty( $property ) ) ) {
            return null;
        }

        foreach ( app( ConfigFormRegistry::class )->schema( $registry, $entry ) ?? [] as $definition ) {
            if ( $field === $definition['name'] && 'repeater' === $definition['type'] ) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Whether a property is a public, unlocked array the client may change.
     *
     * @since 1.0.0
     *
     * @param  string  $name  The property name.
     *
     * @return bool
     */
    private function isWritableProperty( string $name ): bool
    {
        if ( ! property_exists( $this, $name ) ) {
            return false;
        }

        $property = new ReflectionProperty( $this, $name );

        return $property->isPublic()
            && ! $property->isStatic()
            && [] === $property->getAttributes( Locked::class )
            && is_array( $this->{$name} );
    }

    /**
     * The first segment of a property path.
     *
     * @since 1.0.0
     *
     * @param  string  $property  The path.
     *
     * @return string
     */
    private function rootProperty( string $property ): string
    {
        return explode( '.', $property, 2 )[0];
    }

    /**
     * The path below the root property to a field.
     *
     * @since 1.0.0
     *
     * @param  string  $property  The config's property path.
     * @param  string  $field     The field name.
     *
     * @return string
     */
    private function subPath( string $property, string $field ): string
    {
        $rest = explode( '.', $property, 2 )[1] ?? '';

        return '' === $rest ? $field : $rest . '.' . $field;
    }
}
