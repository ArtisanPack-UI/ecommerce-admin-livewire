<?php

/**
 * Config form schema registry.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Registries;

use ArtisanPackUI\Ecommerce\Contracts\DescribesConfig;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Registries\KanbanAutomationRegistry;
use ArtisanPackUI\Ecommerce\Registries\KanbanCardWidgetRegistry;
use ArtisanPackUI\Ecommerce\Registries\PromotionActionRegistry;
use ArtisanPackUI\Ecommerce\Registries\PromotionConditionRegistry;
use ArtisanPackUI\Ecommerce\Registries\ShippingMethodTypeRegistry;
use ArtisanPackUI\Ecommerce\Support\ConfigSchema;
use ArtisanPackUI\EcommerceAdminLivewire\Support\RowKeys;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StoreCurrencies;
use Closure;
use DateTimeZone;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use stdClass;
use Throwable;

/**
 * Field schemas for the free-form `config` JSON of engine registry entries
 * (spec §8.4).
 *
 * Promotion conditions and actions, shipping method types, kanban triggers,
 * and card widgets store a `config` blob whose fields the engine contracts do
 * not describe. A schema per `{registry}:{key}` lets
 * `<x-artisanpack-ec-config-form>` render a form for it:
 *
 * ```php
 * app( ConfigFormRegistry::class )->register( 'promotion-action', 'my-action', [
 *     [ 'name' => 'amount', 'type' => 'money', 'label' => __( 'Amount' ), 'rules' => [ 'required' ] ],
 * ] );
 * ```
 *
 * A field is `name`, `type`, `label`, and optionally `hint`, `rules` (extra
 * Laravel rules), `options` (`select` / `multiselect`), `default`,
 * `multiple` (`product`, `category`, and `product-tag`, default true),
 * `source` (`product` fields: `product` or `variant`), and `fields`
 * (`repeater` rows). A rule may name a sibling field as `@name`
 * (`required_without:@amount`). A schema may be a closure, resolved on use,
 * so labels and options follow the current locale.
 *
 * When an engine entry declares its own schema (it implements the engine's
 * `DescribesConfig`), that wins: its fields are adapted to this shape (see
 * {@see self::adaptEngineSchema()}), and the cast config is checked again
 * with the engine's `ConfigSchema::validate()` before it is saved (see
 * {@see self::validateDeclared()}). A key with no schema at all falls back
 * to a JSON editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class ConfigFormRegistry
{
    /**
     * The field types a schema may use.
     *
     * `tag` is a free-form list of strings; `product-tag` picks product tags
     * by id; `json` is any JSON value, edited as text; `repeater` is a list of
     * rows of other fields (for tiers).
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const TYPES = [
        'text',
        'textarea',
        'number',
        'money',
        'percent',
        'boolean',
        'select',
        'multiselect',
        'product',
        'category',
        'tag',
        'product-tag',
        'date',
        'daterange',
        'weekday',
        'template',
        'json',
        'repeater',
    ];

    /**
     * The engine registry behind each registry name.
     *
     * @since 1.0.0
     *
     * @var array<string, class-string>
     */
    public const REGISTRIES = [
        'promotion-condition' => PromotionConditionRegistry::class,
        'promotion-action'    => PromotionActionRegistry::class,
        'shipping-method'     => ShippingMethodTypeRegistry::class,
        'kanban-trigger'      => KanbanAutomationRegistry::class,
        'kanban-widget'       => KanbanCardWidgetRegistry::class,
    ];

    /**
     * The most rows a repeater accepts.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_ROWS = 50;

    /**
     * The registered schemas, keyed `{registry}:{key}`.
     *
     * @since 1.0.0
     *
     * @var array<string, array<int, array<string, mixed>>|Closure(): array<int, array<string, mixed>>>
     */
    private array $schemas = [];

    /**
     * Extra rules per `{registry}:{key}`, keyed by field path.
     *
     * @since 1.0.0
     *
     * @var array<string, array<string, array<int, mixed>>>
     */
    private array $refinements = [];

    /**
     * Adapted engine schemas, keyed `{registry}:{key}:{locale}`; `false`
     * when the entry declares none.
     *
     * @since 1.0.0
     *
     * @var array<string, array<int, array<string, mixed>>|false>
     */
    private array $declared = [];

    /**
     * `{registry}:{key}` names whose declared schema failed and was reported.
     *
     * @since 1.0.0
     *
     * @var array<string, true>
     */
    private array $reported = [];

    /**
     * Registers (or replaces) the schema for a registry entry.
     *
     * An array schema is checked now; a closure is checked when resolved.
     *
     * @since 1.0.0
     *
     * @param  string                                                $registry  The registry name, e.g. `promotion-action`.
     * @param  string                                                $key       The entry key, e.g. `percent-off-cart`.
     * @param  array<int, array<string, mixed>>|Closure(): array<int, array<string, mixed>>  $schema    The fields, or a closure returning them.
     *
     * @throws InvalidArgumentException When the schema is malformed.
     *
     * @return void
     */
    public function register( string $registry, string $key, array|Closure $schema ): void
    {
        if ( '' === trim( $registry ) || '' === trim( $key ) ) {
            throw new InvalidArgumentException( 'A config form needs a registry name and an entry key.' );
        }

        $this->schemas[ $registry . ':' . $key ] = $schema instanceof Closure ? $schema : self::normalize( $schema );
    }

    /**
     * Adds rules to fields of an entry's schema, whether the schema is
     * registered here or declared by the engine. Use it for checks the
     * engine schema can't express, such as cross-field rules:
     *
     * ```php
     * app( ConfigFormRegistry::class )->refine( 'promotion-action', 'tiered-discount', [
     *     'tiers.percent' => [ 'required_without:@amount' ],
     * ] );
     * ```
     *
     * @since 1.0.0
     *
     * @param  string                            $registry  The registry name.
     * @param  string                            $key       The entry key.
     * @param  array<string, array<int, mixed>>  $rules     Rules keyed by field path; `tiers.percent` is the `percent` column of the `tiers` repeater.
     *
     * @return void
     */
    public function refine( string $registry, string $key, array $rules ): void
    {
        $name = $registry . ':' . $key;

        foreach ( $rules as $path => $extra ) {
            $this->refinements[ $name ][ (string) $path ] = [ ...( $this->refinements[ $name ][ (string) $path ] ?? [] ), ...array_values( (array) $extra ) ];
        }

        $this->declared = [];
    }

    /**
     * Whether the entry has a schema (registered here or declared by the engine).
     *
     * @since 1.0.0
     *
     * @param  string  $registry  The registry name.
     * @param  string  $key       The entry key.
     *
     * @return bool
     */
    public function has( string $registry, string $key ): bool
    {
        return null !== $this->schema( $registry, $key );
    }

    /**
     * The normalized schema, or null when the entry has none.
     *
     * @since 1.0.0
     *
     * @param  string  $registry  The registry name.
     * @param  string  $key       The entry key.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function schema( string $registry, string $key ): ?array
    {
        $declared = $this->declaredSchema( $registry, $key );

        if ( null !== $declared ) {
            return $declared;
        }

        $schema = $this->schemas[ $registry . ':' . $key ] ?? null;

        if ( $schema instanceof Closure ) {
            $schema = self::normalize( (array) $schema() );
        }

        return null === $schema ? null : self::applyRefinements( $schema, $this->refinements[ $registry . ':' . $key ] ?? [] );
    }

    /**
     * The registered `{registry}:{key}` names.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys( $this->schemas );
    }

    /**
     * Validation rules for a config held at `$prefix`.
     *
     * With a schema: one rule set per field (and per repeater cell). Without
     * one: the value at `$prefix` must be a JSON object string.
     *
     * @since 1.0.0
     *
     * @param  string  $registry  The registry name.
     * @param  string  $key       The entry key.
     * @param  string  $prefix    The property path, e.g. `config`.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules( string $registry, string $key, string $prefix ): array
    {
        $schema = $this->schema( $registry, $key );

        if ( null === $schema ) {
            return [ $prefix => [ 'required', 'string', 'max:65535', self::jsonObjectRule() ] ];
        }

        $rules = [];

        foreach ( $schema as $field ) {
            $rules += self::fieldRules( $field, $prefix . '.' . $field['name'] );
        }

        return $rules;
    }

    /**
     * Human-readable attribute names for validation messages.
     *
     * @since 1.0.0
     *
     * @param  string  $registry  The registry name.
     * @param  string  $key       The entry key.
     * @param  string  $prefix    The property path.
     *
     * @return array<string, string>
     */
    public function attributes( string $registry, string $key, string $prefix ): array
    {
        $attributes = [];

        foreach ( $this->schema( $registry, $key ) ?? [] as $field ) {
            $path = $prefix . '.' . $field['name'];

            $attributes[ $path ] = mb_strtolower( $field['label'] );

            foreach ( $field['fields'] as $column ) {
                $attributes[ $path . '.*.' . $column['name'] ] = mb_strtolower( $column['label'] );
            }
        }

        return $attributes;
    }

    /**
     * The starting config: each field's default, or an empty value of its type.
     *
     * @since 1.0.0
     *
     * @param  string  $registry  The registry name.
     * @param  string  $key       The entry key.
     *
     * @return array<string, mixed>
     */
    public function defaults( string $registry, string $key ): array
    {
        $config = [];

        foreach ( $this->schema( $registry, $key ) ?? [] as $field ) {
            $config[ $field['name'] ] = self::emptyValue( $field );
        }

        return $config;
    }

    /**
     * A stored config as form state: the defaults, overlaid with the stored
     * values, with `json` fields encoded as text for their editor.
     *
     * @since 1.0.0
     *
     * @param  string                $registry  The registry name.
     * @param  string                $key       The entry key.
     * @param  array<string, mixed>  $config    The stored config.
     *
     * @return array<string, mixed>
     */
    public function formValues( string $registry, string $key, array $config ): array
    {
        $values = array_replace( $this->defaults( $registry, $key ), $config );

        foreach ( $this->schema( $registry, $key ) ?? [] as $field ) {
            if ( 'json' === $field['type'] && array_key_exists( $field['name'], $config ) ) {
                $values[ $field['name'] ] = (string) json_encode( $config[ $field['name'] ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            }

            if ( 'repeater' === $field['type'] && is_array( $values[ $field['name'] ] ?? null ) ) {
                $values[ $field['name'] ] = RowKeys::tagAll( array_values( $values[ $field['name'] ] ) );
            }
        }

        return $values;
    }

    /**
     * Checks a cast config against the schema the engine entry declares,
     * with the engine's own rules. The admin writes promotion, shipping, and
     * kanban rows directly, so this is the engine-side check they would
     * otherwise skip. Does nothing when the entry declares no schema.
     *
     * @since 1.0.0
     *
     * @param  string                $registry  The registry name.
     * @param  string                $key       The entry key.
     * @param  array<string, mixed>  $config    The cast config.
     * @param  string                $prefix    The property path, e.g. `config`; errors are keyed under it.
     *
     * @throws ValidationException When the engine rejects the config.
     *
     * @return void
     */
    public function validateDeclared( string $registry, string $key, array $config, string $prefix ): void
    {
        $entry = $this->engineEntry( $registry, $key );

        if ( ! $entry instanceof DescribesConfig ) {
            return;
        }

        ConfigSchema::validate( $entry, $config, $prefix . '.' );
    }

    /**
     * An empty repeater row for a field.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $field  The repeater field.
     *
     * @return array<string, mixed>
     */
    public static function emptyRow( array $field ): array
    {
        $row = [ RowKeys::KEY => RowKeys::make() ];

        foreach ( $field['fields'] as $column ) {
            $row[ $column['name'] ] = self::emptyValue( $column );
        }

        return $row;
    }

    /**
     * A validated config, ready to store: keys outside the schema dropped,
     * empty values dropped, and the rest cast to their field types.
     *
     * @since 1.0.0
     *
     * @param  array<int, array<string, mixed>>  $schema  The normalized schema.
     * @param  array<string, mixed>              $config  The validated input.
     *
     * @return array<string, mixed>
     */
    public static function cast( array $schema, array $config ): array
    {
        $result = [];

        foreach ( $schema as $field ) {
            $value = self::castValue( $field, $config[ $field['name'] ] ?? null );

            if ( null !== $value ) {
                $result[ $field['name'] ] = $value;
            }
        }

        return $result;
    }

    /**
     * Checks and fills a schema.
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $schema  The raw fields.
     * @param  bool               $nested  Whether these are repeater columns.
     *
     * @throws InvalidArgumentException When a field is malformed.
     *
     * @return array<int, array{name: string, type: string, label: string, hint: string|null, rules: array<int, mixed>, options: array<int, array{id: string, name: string}>, default: mixed, multiple: bool, source: string, fields: array<int, array<string, mixed>>}>
     */
    public static function normalize( array $schema, bool $nested = false ): array
    {
        $fields = [];
        $names  = [];

        foreach ( $schema as $field ) {
            if ( ! is_array( $field ) || ! isset( $field['name'], $field['type'], $field['label'] ) ) {
                throw new InvalidArgumentException( 'Every config form field needs a name, type, and label.' );
            }

            $name = (string) $field['name'];
            $type = (string) $field['type'];

            if ( 1 !== preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/D', $name ) ) {
                throw new InvalidArgumentException( sprintf( 'Config form field name "%s" must be letters, digits, and underscores.', $name ) );
            }

            if ( isset( $names[ $name ] ) ) {
                throw new InvalidArgumentException( sprintf( 'Config form field "%s" is declared twice.', $name ) );
            }

            if ( ! in_array( $type, self::TYPES, true ) || ( $nested && 'repeater' === $type ) ) {
                throw new InvalidArgumentException( sprintf( 'Config form field "%s" has unsupported type "%s".', $name, $type ) );
            }

            $names[ $name ] = true;

            $fields[] = [
                'name'     => $name,
                'type'     => $type,
                'label'    => (string) $field['label'],
                'hint'     => isset( $field['hint'] ) ? (string) $field['hint'] : null,
                'rules'    => array_values( is_string( $field['rules'] ?? null ) ? explode( '|', $field['rules'] ) : (array) ( $field['rules'] ?? [] ) ),
                'options'  => self::normalizeOptions( $field['options'] ?? [] ),
                'default'  => $field['default'] ?? null,
                'multiple' => (bool) ( $field['multiple'] ?? true ),
                'source'   => 'variant' === ( $field['source'] ?? 'product' ) ? 'variant' : 'product',
                'fields'   => 'repeater' === $type ? self::normalize( (array) ( $field['fields'] ?? [] ), true ) : [],
            ];
        }

        return $fields;
    }

    /**
     * The schema an engine entry declares itself, adapted to this registry's
     * field shape, or null when it declares none.
     *
     * The result is memoized per locale. A schema that can't be adapted is
     * reported once and treated as missing, so the form falls back to the
     * JSON editor instead of failing.
     *
     * @since 1.0.0
     *
     * @param  string  $registry  The registry name.
     * @param  string  $key       The entry key.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function declaredSchema( string $registry, string $key ): ?array
    {
        $name  = $registry . ':' . $key;
        $cache = $name . ':' . app()->getLocale();

        if ( array_key_exists( $cache, $this->declared ) ) {
            return false === $this->declared[ $cache ] ? null : $this->declared[ $cache ];
        }

        $schema = null;

        try {
            $entry = $this->engineEntry( $registry, $key );

            if ( $entry instanceof DescribesConfig ) {
                $schema = self::applyRefinements(
                    self::normalize( self::adaptEngineSchema( (array) $entry->configSchema() ) ),
                    $this->refinements[ $name ] ?? [],
                );
            }
        } catch ( Throwable $exception ) {
            if ( ! isset( $this->reported[ $name ] ) ) {
                $this->reported[ $name ] = true;

                report( $exception );
            }
        }

        $this->declared[ $cache ] = $schema ?? false;

        return $schema;
    }

    /**
     * Appends refinement rules to the fields they name.
     *
     * @since 1.0.0
     *
     * @param  array<int, array<string, mixed>>  $schema       The normalized schema.
     * @param  array<string, array<int, mixed>>  $refinements  Rules keyed by field path.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function applyRefinements( array $schema, array $refinements ): array
    {
        if ( [] === $refinements ) {
            return $schema;
        }

        foreach ( $schema as $index => $field ) {
            $schema[ $index ]['rules'] = [ ...$field['rules'], ...( $refinements[ $field['name'] ] ?? [] ) ];

            if ( [] !== $field['fields'] ) {
                $nested = [];

                foreach ( $refinements as $path => $rules ) {
                    if ( str_starts_with( $path, $field['name'] . '.' ) ) {
                        $nested[ substr( $path, strlen( $field['name'] ) + 1 ) ] = $rules;
                    }
                }

                $schema[ $index ]['fields'] = self::applyRefinements( $field['fields'], $nested );
            }
        }

        return $schema;
    }

    /**
     * The engine registry entry under `$key`, or null.
     *
     * @since 1.0.0
     *
     * @param  string  $registry  The registry name.
     * @param  string  $key       The entry key.
     *
     * @return object|null
     */
    private function engineEntry( string $registry, string $key ): ?object
    {
        $class = self::REGISTRIES[ $registry ] ?? null;

        if ( null === $class || ! app()->bound( $class ) ) {
            return null;
        }

        $engine = app( $class );

        if ( ! $engine->has( $key ) ) {
            return null;
        }

        $entry = $engine->get( $key );

        return is_object( $entry ) ? $entry : null;
    }

    /**
     * Adapts fields in the engine's `ConfigSchema` format to this registry's
     * shape:
     *
     * - `required: true` adds the `required` rule, and `help` becomes `hint`;
     * - `{value, label}` options become `{id, name}`;
     * - `multiple` defaults to `false`, as in the engine;
     * - `variant` is a `product` field with the `variant` source, `url` is
     *   `text` with the `url` rule, `list` is a free-form `tag` list, and the
     *   engine's `tag` (product tag ids) is `product-tag`;
     * - an unknown type is edited as `json`.
     *
     * Money fields without help get the store-currency hint, template fields
     * list their tokens, and a `timezone` text field becomes a time-zone
     * select.
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $schema  The engine fields.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function adaptEngineSchema( array $schema ): array
    {
        $fields = [];

        foreach ( $schema as $field ) {
            $field = (array) $field;
            $type  = (string) ( $field['type'] ?? 'json' );
            $rules = array_values( array_filter( (array) ( $field['rules'] ?? [] ), 'is_string' ) );

            if ( true === ( $field['required'] ?? false ) ) {
                array_unshift( $rules, 'required' );
            }

            $adapted = [
                'name'     => (string) ( $field['name'] ?? '' ),
                'type'     => $type,
                'label'    => (string) ( $field['label'] ?? '' ),
                'hint'     => isset( $field['help'] ) ? (string) $field['help'] : null,
                'rules'    => $rules,
                'default'  => $field['default'] ?? null,
                'multiple' => (bool) ( $field['multiple'] ?? false ),
            ];

            if ( isset( $field['options'] ) ) {
                $adapted['options'] = array_values( array_filter( array_map(
                    static fn ( mixed $option ): ?array => is_array( $option ) && is_scalar( $option['value'] ?? null )
                        ? [ 'id' => (string) $option['value'], 'name' => (string) ( $option['label'] ?? $option['value'] ) ]
                        : null,
                    (array) $field['options'],
                ) ) );
            }

            switch ( $type ) {
                case 'variant':
                    $adapted['type']   = 'product';
                    $adapted['source'] = 'variant';
                    break;

                case 'url':
                    $adapted['type']    = 'text';
                    $adapted['rules'][] = 'url';
                    break;

                case 'list':
                    $adapted['type'] = 'tag';
                    break;

                case 'tag':
                    $adapted['type'] = 'product-tag';
                    break;

                case 'money':
                    $adapted['hint'] ??= __( 'In the store currency (:currency); other currencies are converted.', [ 'currency' => StoreCurrencies::base() ] );
                    break;

                case 'template':
                    $tokens = array_values( array_filter( (array) ( $field['tokens'] ?? [] ), 'is_string' ) );

                    if ( null === $adapted['hint'] && [] !== $tokens ) {
                        $adapted['hint'] = __( 'Placeholders: :placeholders', [ 'placeholders' => implode( ', ', $tokens ) ] );
                    }
                    break;

                case 'text':
                    if ( in_array( 'timezone', $rules, true ) ) {
                        $zones              = DateTimeZone::listIdentifiers();
                        $adapted['type']    = 'select';
                        $adapted['options'] = array_map( static fn ( string $zone ): array => [ 'id' => $zone, 'name' => $zone ], $zones );
                        $adapted['rules']   = array_values( array_diff( $rules, [ 'timezone' ] ) );
                    }
                    break;

                case 'repeater':
                    $adapted['fields'] = self::adaptEngineSchema( (array) ( $field['fields'] ?? [] ) );
                    break;

                default:
                    if ( ! in_array( $type, self::TYPES, true ) ) {
                        $adapted['type'] = 'json';
                    }
            }

            $fields[] = $adapted;
        }

        return $fields;
    }

    /**
     * One value cast to its field type, or null when empty.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $field  The field.
     * @param  mixed                 $value  The validated value.
     *
     * @return mixed
     */
    private static function castValue( array $field, mixed $value ): mixed
    {
        if ( null === $value || ( is_string( $value ) && '' === trim( $value ) ) ) {
            return 'boolean' === $field['type'] ? false : null;
        }

        if ( 'daterange' === $field['type'] ) {
            $range = [ 'start' => ( (array) $value )['start'] ?? null, 'end' => ( (array) $value )['end'] ?? null ];

            return self::isBlank( $range['start'] ) && self::isBlank( $range['end'] ) ? null : $range;
        }

        $ids = static fn ( mixed $list ): array => array_values( array_unique( array_map( 'intval', array_filter( (array) $list, 'is_numeric' ) ) ) );

        return match ( $field['type'] ) {
            'number', 'percent'       => is_numeric( $value ) ? $value + 0 : null,
            'money'                   => is_numeric( $value ) ? (int) $value : null,
            'boolean'                 => filter_var( $value, FILTER_VALIDATE_BOOLEAN ),
            'weekday'                 => ( static function () use ( $ids, $value ): array {
                $days = $ids( $value );
                sort( $days );

                return $days;
            } )(),
            'category', 'product-tag',
            'product'                 => $field['multiple'] ? $ids( $value ) : ( is_numeric( $value ) ? (int) $value : null ),
            'json'                    => is_string( $value ) ? json_decode( $value, true ) : $value,
            'multiselect'             => array_values( array_unique( array_map( 'strval', array_filter( (array) $value, 'is_scalar' ) ) ) ),
            'tag'                     => array_values( array_unique( array_filter( array_map( static fn ( mixed $tag ): string => trim( (string) $tag ), array_filter( (array) $value, 'is_scalar' ) ), static fn ( string $tag ): bool => '' !== $tag ) ) ),
            'repeater'                => array_values( array_map( static fn ( mixed $row ): array => self::cast( $field['fields'], (array) $row ), array_filter( (array) $value, 'is_array' ) ) ),
            default                   => is_scalar( $value ) ? (string) $value : null,
        };
    }

    /**
     * The rules for one field at a path.
     *
     * Fields are optional unless their own rules say `required`.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $field  The field.
     * @param  string                $path   Its property path.
     *
     * @return array<string, array<int, mixed>>
     */
    private static function fieldRules( array $field, string $path ): array
    {
        $optionIds = array_column( $field['options'], 'id' );
        $presence  = in_array( 'required', $field['rules'], true ) ? [] : [ 'nullable' ];
        $own       = [ ...$presence, ...self::resolveSiblings( $field['rules'], $path ) ];

        $rules = match ( $field['type'] ) {
            'text'                    => [ $path => [ ...$own, 'string', 'max:1000' ] ],
            'template', 'textarea'    => [ $path => [ ...$own, 'string', 'max:10000' ] ],
            'json'                    => [ $path => [ ...$own, 'string', 'max:65535', 'json' ] ],
            'number'                  => [ $path => [ ...$own, 'numeric' ] ],
            'money'                   => [ $path => [ ...$own, 'integer', 'min:0' ] ],
            'percent'                 => [ $path => [ ...$own, 'numeric', 'min:0', 'max:100' ] ],
            'boolean'                 => [ $path => [ ...$own, 'boolean' ] ],
            'select'                  => [ $path => [ ...$own, Rule::in( $optionIds ) ] ],
            'date'                    => [ $path => [ ...$own, 'date_format:Y-m-d' ] ],
            'multiselect'             => [ $path => [ ...$own, 'array' ], $path . '.*' => [ Rule::in( $optionIds ) ] ],
            'weekday'                 => [ $path => [ ...$own, 'array' ], $path . '.*' => [ 'integer', 'between:1,7' ] ],
            'tag'                     => [ $path => [ ...$own, 'array', 'max:100' ], $path . '.*' => [ 'string', 'max:255' ] ],
            'category', 'product-tag' => $field['multiple']
                ? [ $path => [ ...$own, 'array', 'max:500' ], $path . '.*' => [ 'integer', 'min:1' ] ]
                : [ $path => [ ...$own, 'integer', 'min:1' ] ],
            'product'               => self::pickerRules( $field, $path, $own ),
            'daterange'             => [
                $path            => [ ...$own, 'array' ],
                $path . '.start' => [ 'nullable', 'date_format:Y-m-d' ],
                $path . '.end'   => [ 'nullable', 'date_format:Y-m-d', 'after_or_equal:' . $path . '.start' ],
            ],
            'repeater'              => [ $path => [ ...$own, 'array', 'max:' . self::MAX_ROWS ] ],
            default                 => [],
        };

        foreach ( $field['fields'] as $column ) {
            $rules += self::fieldRules( $column, $path . '.*.' . $column['name'] );
        }

        return $rules;
    }

    /**
     * Points `@name` in string rules at the sibling field `name`, so a
     * cross-field rule works wherever the config is bound and in every
     * repeater row (`required_without:@amount` becomes
     * `required_without:config.tiers.*.amount`; Laravel matches the `*` to
     * the same row).
     *
     * @since 1.0.0
     *
     * @param  array<int, mixed>  $rules  The field's own rules.
     * @param  string             $path   The field's property path.
     *
     * @return array<int, mixed>
     */
    private static function resolveSiblings( array $rules, string $path ): array
    {
        $parent = substr( $path, 0, (int) strrpos( $path, '.' ) );

        return array_map(
            static fn ( mixed $rule ): mixed => is_string( $rule )
                ? (string) preg_replace( '/@([A-Za-z][A-Za-z0-9_]*)/', $parent . '.$1', $rule )
                : $rule,
            $rules,
        );
    }

    /**
     * The rules for a product or variant picker.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $field  The field.
     * @param  string                $path   Its property path.
     * @param  array<int, mixed>     $own    The presence and schema rules.
     *
     * @return array<string, array<int, mixed>>
     */
    private static function pickerRules( array $field, string $path, array $own ): array
    {
        $exists = Rule::exists( 'variant' === $field['source'] ? ProductVariant::class : Product::class, 'id' );

        if ( ! $field['multiple'] ) {
            return [ $path => [ ...$own, 'integer', $exists ] ];
        }

        return [ $path => [ ...$own, 'array', 'max:500' ], $path . '.*' => [ 'integer', $exists ] ];
    }

    /**
     * The value a field starts with.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $field  The field.
     *
     * @return mixed
     */
    private static function emptyValue( array $field ): mixed
    {
        if ( null !== $field['default'] ) {
            return $field['default'];
        }

        return match ( $field['type'] ) {
            'boolean'                                                 => false,
            'multiselect', 'weekday', 'tag', 'repeater'               => [],
            'product', 'category', 'product-tag'                      => $field['multiple'] ? [] : null,
            'daterange'                                               => [ 'start' => null, 'end' => null ],
            default                                                   => null,
        };
    }

    /**
     * Whether a value is null or a blank string.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  The value.
     *
     * @return bool
     */
    private static function isBlank( mixed $value ): bool
    {
        return null === $value || ( is_string( $value ) && '' === trim( $value ) );
    }

    /**
     * Normalizes options given as `[ [ 'id' => …, 'name' => … ] ]` or `[ value => label ]`.
     *
     * @since 1.0.0
     *
     * @param  mixed  $options  The raw options.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function normalizeOptions( mixed $options ): array
    {
        $normalized = [];

        foreach ( (array) $options as $value => $option ) {
            if ( is_array( $option ) && isset( $option['id'], $option['name'] ) ) {
                $normalized[] = [ 'id' => (string) $option['id'], 'name' => (string) $option['name'] ];
            } elseif ( is_scalar( $option ) ) {
                $normalized[] = [ 'id' => (string) $value, 'name' => (string) $option ];
            }
        }

        return $normalized;
    }

    /**
     * A rule requiring a JSON object string (`{…}`), for the fallback editor.
     *
     * @since 1.0.0
     *
     * @return Closure(string, mixed, Closure): void
     */
    private static function jsonObjectRule(): Closure
    {
        return static function ( string $attribute, mixed $value, Closure $fail ): void {
            $decoded = is_string( $value ) ? json_decode( $value ) : null;

            if ( ! $decoded instanceof stdClass ) {
                $fail( __( 'The :attribute must be a JSON object, like {"key": "value"}.' ) );
            }
        };
    }
}
