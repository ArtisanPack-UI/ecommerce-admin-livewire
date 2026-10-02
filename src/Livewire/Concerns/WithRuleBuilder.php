<?php

/**
 * Rule builder concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns;

use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\RuleSummary;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * Ordered lists of rules — promotion conditions and actions, or kanban
 * routing conditions on their own — edited in the host component (spec
 * §7.5, §8.4).
 *
 * Each list is fed by an engine registry, so satellite-registered entries
 * appear automatically. Each row stores `{ type, config }` and renders its
 * config through the config-form renderer; the row whose settings are open
 * shows its form below its summary, so dragging a row never drags a text
 * field. Rows reorder by drag (`@artisanpack-ui/livewire-drag-and-drop`,
 * when the host loads it) and always by the Move up / Move down buttons.
 *
 * The host declares its lists in {@see ruleBuilderLists()}, authorizes
 * writes in {@see authorizeRuleBuilder()}, loads stored rows with
 * {@see ruleBuilderState()}, validates and reads them back with
 * {@see validateRules()}, and renders `<x-artisanpack-ec-rule-builder>`
 * with {@see ruleBuilderViewData()}. It must also use {@see WithConfigForms}
 * and {@see WithPickers} for the row forms.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
trait WithRuleBuilder
{
    /**
     * The rows: list key => list of `{ id, type, config }`.
     *
     * @since 1.0.0
     *
     * @var array<string, array<int, array{id: string, type: string, config: array<string, mixed>|string}>>
     */
    public array $ruleRows = [];

    /**
     * The entry chosen in each list's "add" picker.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public array $ruleToAdd = [];

    /**
     * The row whose settings are open, as `list:id`.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $openRule = null;

    /**
     * Adds a row of the type chosen in the list's picker and opens it.
     *
     * @since 1.0.0
     *
     * @param  string  $list  List key.
     *
     * @return void
     */
    public function addRule( string $list ): void
    {
        $this->authorizeRuleBuilder();

        $registry = $this->ruleRegistryName( $list );
        $type     = (string) ( $this->ruleToAdd[ $list ] ?? '' );

        if ( null === $registry ) {
            return;
        }

        if ( ! in_array( $type, self::ruleTypeKeys( $registry ), true ) ) {
            $this->addError( 'ruleToAdd.' . $list, __( 'Choose what to add first.' ) );

            return;
        }

        if ( count( $this->ruleRows[ $list ] ?? [] ) >= self::maxRules() ) {
            $this->addError( 'ruleToAdd.' . $list, __( 'A list can have at most :count rows.', [ 'count' => self::maxRules() ] ) );

            return;
        }

        $id = Str::lower( Str::random( 10 ) );

        $this->ruleRows[ $list ][]   = [ 'id' => $id, 'type' => $type, 'config' => $this->configFormState( $registry, $type, [] ) ];
        $this->ruleToAdd[ $list ]    = '';
        $this->openRule              = $list . ':' . $id;

        $this->resetErrorBag( 'ruleToAdd.' . $list );
    }

    /**
     * Removes a row.
     *
     * @since 1.0.0
     *
     * @param  string  $list   List key.
     * @param  int     $index  Row index.
     *
     * @return void
     */
    public function removeRule( string $list, int $index ): void
    {
        $this->authorizeRuleBuilder();

        if ( null === $this->ruleRegistryName( $list ) || ! isset( $this->ruleRows[ $list ][ $index ] ) ) {
            return;
        }

        $removed = $this->ruleRows[ $list ][ $index ];

        unset( $this->ruleRows[ $list ][ $index ] );

        $this->ruleRows[ $list ] = array_values( $this->ruleRows[ $list ] );

        if ( $this->openRule === $list . ':' . $removed['id'] ) {
            $this->openRule = null;
        }

        $this->resetErrorBag( 'ruleRows.' . $list );
    }

    /**
     * Moves a row one place up (`-1`) or down (`1`).
     *
     * @since 1.0.0
     *
     * @param  string  $list       List key.
     * @param  int     $index      Row index.
     * @param  int     $direction  `-1` or `1`.
     *
     * @return void
     */
    public function moveRule( string $list, int $index, int $direction ): void
    {
        $this->authorizeRuleBuilder();

        $target = $index + ( $direction < 0 ? -1 : 1 );

        if ( null === $this->ruleRegistryName( $list ) || ! isset( $this->ruleRows[ $list ][ $index ], $this->ruleRows[ $list ][ $target ] ) ) {
            return;
        }

        [ $this->ruleRows[ $list ][ $index ], $this->ruleRows[ $list ][ $target ] ] = [ $this->ruleRows[ $list ][ $target ], $this->ruleRows[ $list ][ $index ] ];

        $this->resetErrorBag( 'ruleRows.' . $list );
    }

    /**
     * Puts the rows in the order a drag left them in.
     *
     * Ignored unless `$ids` names every row of the list exactly once.
     *
     * @since 1.0.0
     *
     * @param  string                      $list  List key.
     * @param  array<int, mixed>           $ids   Row ids in their new order.
     *
     * @return void
     */
    public function reorderRules( string $list, array $ids ): void
    {
        $this->authorizeRuleBuilder();

        if ( null === $this->ruleRegistryName( $list ) ) {
            return;
        }

        $rows = [];

        foreach ( $this->ruleRows[ $list ] ?? [] as $row ) {
            $rows[ $row['id'] ] = $row;
        }

        $ids = array_map( 'strval', array_filter( $ids, 'is_scalar' ) );

        if ( count( $ids ) !== count( $rows ) || [] !== array_diff( array_keys( $rows ), $ids ) || count( array_unique( $ids ) ) !== count( $ids ) ) {
            return;
        }

        $this->ruleRows[ $list ] = array_map( static fn ( string $id ): array => $rows[ $id ], array_values( $ids ) );

        $this->resetErrorBag( 'ruleRows.' . $list );
    }

    /**
     * Opens a row's settings, or closes them when they are open.
     *
     * @since 1.0.0
     *
     * @param  string  $list  List key.
     * @param  string  $id    Row id.
     *
     * @return void
     */
    public function toggleRule( string $list, string $id ): void
    {
        $this->authorizeRuleBuilder();

        if ( ! in_array( $id, array_column( $this->ruleRows[ $list ] ?? [], 'id' ), true ) ) {
            return;
        }

        $this->openRule = $this->openRule === $list . ':' . $id ? null : $list . ':' . $id;
    }

    /**
     * The lists this builder edits: list key => `registry` (a
     * {@see ConfigFormRegistry::REGISTRIES} name), `label`, `add` (button
     * label), and `empty` (text when the list has no rows).
     *
     * A list keyed `actions` makes the summary describe a promotion;
     * without one it describes a condition set (kanban routing rules).
     *
     * @since 1.0.0
     *
     * @return array<string, array{registry: string, label: string, add: string, empty: string}>
     */
    abstract protected function ruleBuilderLists(): array;

    /**
     * Authorizes changing the rules; called by every rule builder action.
     *
     * @since 1.0.0
     *
     * @return void
     */
    abstract protected function authorizeRuleBuilder(): void;

    /**
     * Loads stored rules into the builder.
     *
     * @since 1.0.0
     *
     * @param  array<string, iterable<int, array{type: string, config: array<string, mixed>|null}>>  $stored  List key => stored rows.
     *
     * @return void
     */
    protected function ruleBuilderState( array $stored ): void
    {
        $this->ruleRows     = [];
        $this->ruleToAdd    = [];
        $this->openRule     = null;

        foreach ( $this->ruleBuilderLists() as $list => $definition ) {
            $this->ruleRows[ $list ]     = [];
            $this->ruleToAdd[ $list ]    = '';

            foreach ( $stored[ $list ] ?? [] as $row ) {
                $type = (string) ( $row['type'] ?? '' );

                $this->ruleRows[ $list ][] = [
                    'id'     => Str::lower( Str::random( 10 ) ),
                    'type'   => $type,
                    'config' => $this->configFormState( $definition['registry'], $type, (array) ( $row['config'] ?? [] ) ),
                ];
            }
        }
    }

    /**
     * Validates every row and returns the rules to store.
     *
     * A failure opens the first row with an error, then rethrows.
     *
     * @since 1.0.0
     *
     * @throws ValidationException When a row is invalid.
     *
     * @return array<string, array<int, array{type: string, config: array<string, mixed>}>>
     */
    protected function validateRules(): array
    {
        $this->sanitizeRuleRows();

        $forms      = app( ConfigFormRegistry::class );
        $rules      = [];
        $attributes = [];
        $messages   = [];

        foreach ( $this->ruleBuilderLists() as $list => $definition ) {
            $keys = self::ruleTypeKeys( $definition['registry'] );

            foreach ( array_values( $this->ruleRows[ $list ] ?? [] ) as $index => $row ) {
                $prefix = 'ruleRows.' . $list . '.' . $index;

                $rules[ $prefix . '.type' ]                 = [ 'required', 'string', Rule::in( $keys ) ];
                $messages[ $prefix . '.type.in' ]           = __( 'This is no longer available. Remove it.' );
                $attributes[ $prefix . '.type' ]            = __( 'type' );

                if ( in_array( $row['type'] ?? '', $keys, true ) ) {
                    $rules += $forms->rules( $definition['registry'], (string) $row['type'], $prefix . '.config' );
                    $attributes += $forms->attributes( $definition['registry'], (string) $row['type'], $prefix . '.config' );
                }
            }
        }

        try {
            if ( [] !== $rules ) {
                $this->validate( $rules, $messages, $attributes );
            }
        } catch ( ValidationException $exception ) {
            $this->openFirstInvalidRule( array_keys( $exception->errors() ) );

            throw $exception;
        }

        $result = [];

        foreach ( $this->ruleBuilderLists() as $list => $definition ) {
            $result[ $list ] = [];

            foreach ( $this->ruleRows[ $list ] ?? [] as $row ) {
                $schema = $forms->schema( $definition['registry'], (string) $row['type'] );

                $result[ $list ][] = [
                    'type'   => (string) $row['type'],
                    'config' => null === $schema
                        ? (array) json_decode( (string) $row['config'], true )
                        : ConfigFormRegistry::cast( $schema, (array) $row['config'] ),
                ];
            }
        }

        return $result;
    }

    /**
     * What `<x-artisanpack-ec-rule-builder>` renders: each list with its
     * picker options and rows, and the plain-language summary.
     *
     * @since 1.0.0
     *
     * @return array{lists: array<string, array<string, mixed>>, summary: string, openRule: string|null}
     */
    protected function ruleBuilderViewData(): array
    {
        $this->sanitizeRuleRows();

        $lists   = [];
        $current = [];

        foreach ( $this->ruleBuilderLists() as $list => $definition ) {
            $labels           = self::ruleTypeLabels( $definition['registry'] );
            $rows             = [];
            $current[ $list ] = [];

            foreach ( array_values( $this->ruleRows[ $list ] ?? [] ) as $index => $row ) {
                $type   = (string) ( $row['type'] ?? '' );
                $config = is_array( $row['config'] ?? null ) ? $row['config'] : (array) json_decode( (string) ( $row['config'] ?? '' ), true );

                $current[ $list ][] = [ 'type' => $type, 'config' => $config ];

                $rows[] = [
                    'id'          => (string) $row['id'],
                    'index'       => $index,
                    'type'        => $type,
                    'label'       => $labels[ $type ] ?? __( 'Unavailable (:type)', [ 'type' => $type ] ),
                    'available'   => isset( $labels[ $type ] ),
                    'description' => RuleSummary::describe( $definition['registry'], $type, $config ),
                    'open'        => $this->openRule === $list . ':' . $row['id'],
                ];
            }

            $lists[ $list ] = [
                'key'      => $list,
                'registry' => $definition['registry'],
                'label'    => $definition['label'],
                'add'      => $definition['add'],
                'empty'    => $definition['empty'],
                'options'  => array_map( static fn ( string $key, string $label ): array => [ 'id' => $key, 'name' => $label ], array_keys( $labels ), $labels ),
                'rows'     => $rows,
                'full'     => count( $rows ) >= self::maxRules(),
            ];
        }

        $summary = array_key_exists( 'actions', $lists )
            ? RuleSummary::promotion( $current['conditions'] ?? [], $current['actions'] )
            : RuleSummary::conditions( $current['conditions'] ?? [] );

        return [ 'lists' => $lists, 'summary' => $summary, 'openRule' => $this->openRule ];
    }

    /**
     * The most rows one list accepts.
     *
     * @since 1.0.0
     *
     * @return int
     */
    protected static function maxRules(): int
    {
        return 20;
    }

    /**
     * Registered entries of a registry: key => label, by label.
     *
     * @since 1.0.0
     *
     * @param  string  $registry  A {@see ConfigFormRegistry::REGISTRIES} name.
     *
     * @return array<string, string>
     */
    protected static function ruleTypeLabels( string $registry ): array
    {
        $class = ConfigFormRegistry::REGISTRIES[ $registry ] ?? null;

        if ( null === $class ) {
            return [];
        }

        $engine = app( $class );
        $labels = [];

        foreach ( $engine->keys() as $key ) {
            try {
                $labels[ $key ] = (string) $engine->get( $key )->label();
            } catch ( Throwable $exception ) {
                report( $exception );
            }
        }

        asort( $labels, SORT_NATURAL | SORT_FLAG_CASE );

        return $labels;
    }

    /**
     * Registered entry keys of a registry.
     *
     * @since 1.0.0
     *
     * @param  string  $registry  A {@see ConfigFormRegistry::REGISTRIES} name.
     *
     * @return array<int, string>
     */
    protected static function ruleTypeKeys( string $registry ): array
    {
        return array_keys( self::ruleTypeLabels( $registry ) );
    }

    /**
     * Drops rows that are not `{ id, type, config }` and lists the builder
     * does not declare, so state forged by the client (or a config-row path
     * pointing past the last row) can never reach the view or the save.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function sanitizeRuleRows(): void
    {
        $clean = [];

        foreach ( array_keys( $this->ruleBuilderLists() ) as $list ) {
            $clean[ $list ] = array_values( array_filter(
                (array) ( $this->ruleRows[ $list ] ?? [] ),
                static fn ( mixed $row ): bool => is_array( $row )
                    && is_string( $row['id'] ?? null ) && 1 === preg_match( '/^[a-z0-9]{1,32}$/D', $row['id'] )
                    && is_string( $row['type'] ?? null )
                    && ( is_array( $row['config'] ?? null ) || is_string( $row['config'] ?? null ) ),
            ) );
        }

        $this->ruleRows = $clean;
    }

    /**
     * The registry behind a list, or null for an unknown list.
     *
     * @since 1.0.0
     *
     * @param  string  $list  List key.
     *
     * @return string|null
     */
    private function ruleRegistryName( string $list ): ?string
    {
        return $this->ruleBuilderLists()[ $list ]['registry'] ?? null;
    }

    /**
     * Opens the first row named in a set of error keys.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $keys  Error keys.
     *
     * @return void
     */
    private function openFirstInvalidRule( array $keys ): void
    {
        foreach ( $keys as $key ) {
            if ( 1 === preg_match( '/^ruleRows\.([^.]+)\.(\d+)\./', $key, $match ) && isset( $this->ruleRows[ $match[1] ][ (int) $match[2] ] ) ) {
                $this->openRule = $match[1] . ':' . $this->ruleRows[ $match[1] ][ (int) $match[2] ]['id'];

                return;
            }
        }
    }
}
