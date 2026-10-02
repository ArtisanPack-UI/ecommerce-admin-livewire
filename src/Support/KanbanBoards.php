<?php

/**
 * Kanban board configuration helpers.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\Ecommerce\Registries\KanbanAutomationRegistry;
use ArtisanPackUI\Ecommerce\Registries\KanbanCardWidgetRegistry;
use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\Ecommerce\Services\OrderSubstatusService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Shared rules for the kanban settings screens (spec §7.10).
 *
 * - **Exactly one default board.** The default board is the routing
 *   fallback: it catches orders no other board took. Whenever boards
 *   exist, {@see self::ensureDefault()} leaves exactly one flagged, and
 *   {@see self::makeDefault()} moves the flag in one transaction.
 * - **Condition trees.** Board routing rules and automation conditions are
 *   engine condition trees. The rule builder edits a flat "every condition
 *   must match" list, so {@see self::flattenConditions()} reads a tree into
 *   rows when it is one, and returns null for trees with `any` / `not`
 *   nesting, which the screens then show read-only.
 * - **"Open board".** Links to the board in `ecommerce-kanban-livewire`
 *   when that satellite is active and its route is registered.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class KanbanBoards
{
    /**
     * The kanban satellite's package name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const KANBAN_PACKAGE = 'artisanpack-ui/ecommerce-kanban-livewire';

    /**
     * The route the kanban satellite shows a board at (parameter `board`).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const BOARD_ROUTE = 'artisanpack.ecommerce.kanban.boards.show';

    /**
     * The pattern a board key must match (the engine's REST rule).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const KEY_PATTERN = '/^(?:[a-z0-9]+(?:-[a-z0-9]+)*:)?[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Makes `$board` the only default board.
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard  $board  The new default.
     *
     * @return void
     */
    public static function makeDefault( KanbanBoard $board ): void
    {
        DB::transaction( static function () use ( $board ): void {
            KanbanBoard::query()->whereKeyNot( $board->id )->where( 'is_default', true )->update( [ 'is_default' => false ] );
            KanbanBoard::query()->whereKey( $board->id )->update( [ 'is_default' => true ] );
        } );

        $board->is_default = true;
    }

    /**
     * Leaves exactly one default board when any board exists.
     *
     * Keeps the first flagged board (by position); with none flagged, the
     * first active board — or the first board — becomes the default.
     *
     * @since 1.0.0
     *
     * @return KanbanBoard|null The default board, or null when there are no boards.
     */
    public static function ensureDefault(): ?KanbanBoard
    {
        return DB::transaction( static function (): ?KanbanBoard {
            $boards = KanbanBoard::query()->orderBy( 'position' )->orderBy( 'id' )->lockForUpdate()->get();

            if ( $boards->isEmpty() ) {
                return null;
            }

            $default = $boards->firstWhere( 'is_default', true )
                ?? $boards->firstWhere( 'is_active', true )
                ?? $boards->first();

            KanbanBoard::query()->whereKeyNot( $default->id )->where( 'is_default', true )->update( [ 'is_default' => false ] );

            if ( ! $default->is_default ) {
                KanbanBoard::query()->whereKey( $default->id )->update( [ 'is_default' => true ] );
                $default->is_default = true;
            }

            return $default;
        } );
    }

    /**
     * The board that becomes the default when `$board` is deleted.
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard  $board  The board being deleted.
     *
     * @return KanbanBoard|null
     */
    public static function successor( KanbanBoard $board ): ?KanbanBoard
    {
        $others = KanbanBoard::query()->whereKeyNot( $board->id )->orderBy( 'position' )->orderBy( 'id' )->get();

        return $others->firstWhere( 'is_active', true ) ?? $others->first();
    }

    /**
     * Writes `$ids` as positions 0, 1, 2… in one transaction.
     *
     * @since 1.0.0
     *
     * @param  class-string<Model>  $model  The model class.
     * @param  array<int, int>      $ids    Ids in their new order.
     *
     * @return void
     */
    public static function writePositions( string $model, array $ids ): void
    {
        DB::transaction( static function () use ( $model, $ids ): void {
            foreach ( array_values( $ids ) as $position => $id ) {
                $model::query()->whereKey( $id )->update( [ 'position' => $position ] );
            }
        } );
    }

    /**
     * Swaps an id with its neighbour in an ordered list.
     *
     * @since 1.0.0
     *
     * @param  array<int, int>  $ids     Ids in order.
     * @param  int              $id      The id to move.
     * @param  int              $offset  -1 for up, 1 for down.
     *
     * @return array<int, int>|null The new order, or null when the move is impossible.
     */
    public static function swap( array $ids, int $id, int $offset ): ?array
    {
        $ids  = array_values( $ids );
        $from = array_search( $id, $ids, true );
        $to   = false === $from ? false : $from + $offset;

        if ( ! in_array( $offset, [ -1, 1 ], true ) || false === $to || $to < 0 || $to >= count( $ids ) ) {
            return null;
        }

        [ $ids[ $from ], $ids[ $to ] ] = [ $ids[ $to ], $ids[ $from ] ];

        return $ids;
    }

    /**
     * Reads a condition tree as a flat list of `{ type, config }` rows.
     *
     * Accepts `{}`, `[]`, a list of leaves, and `{ "all": [ leaves ] }`.
     * Anything with `any`, `not`, or nested groups returns null.
     *
     * @since 1.0.0
     *
     * @param  mixed  $tree  The stored tree.
     *
     * @return array<int, array{type: string, config: array<string, mixed>}>|null
     */
    public static function flattenConditions( mixed $tree ): ?array
    {
        if ( ! is_array( $tree ) ) {
            return null;
        }

        if ( [] === $tree ) {
            return [];
        }

        if ( ! array_is_list( $tree ) ) {
            if ( [ 'all' ] !== array_keys( $tree ) || ! is_array( $tree['all'] ) || ! array_is_list( $tree['all'] ) ) {
                return null;
            }

            $tree = $tree['all'];
        }

        $rows = [];

        foreach ( $tree as $node ) {
            if ( ! is_array( $node ) || ! is_string( $node['type'] ?? null ) || [] !== array_diff( array_keys( $node ), [ 'type', 'config' ] ) ) {
                return null;
            }

            $rows[] = [ 'type' => $node['type'], 'config' => is_array( $node['config'] ?? null ) ? $node['config'] : [] ];
        }

        return $rows;
    }

    /**
     * Registered card widgets: key => label, by label.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public static function widgetLabels(): array
    {
        return self::labels( app( KanbanCardWidgetRegistry::class ) );
    }

    /**
     * Registered automation triggers: key => label, by label.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public static function triggerLabels(): array
    {
        return self::labels( app( KanbanAutomationRegistry::class ) );
    }

    /**
     * Sub-status options grouped under their system status, as
     * `{ id, name }` rows labelled "System status: Sub-status".
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function substatusOptions(): array
    {
        $labels  = StatusPresenter::statuses( 'system' );
        $order   = array_flip( OrderSubstatusService::systemStatuses() );
        $options = OrderSubstatus::query()->orderBy( 'position' )->orderBy( 'id' )->get()
            ->sortBy( static fn ( OrderSubstatus $substatus ): int => $order[ $substatus->system_status ] ?? PHP_INT_MAX )
            ->map( static fn ( OrderSubstatus $substatus ): array => [
                'id'   => (int) $substatus->id,
                'name' => __( ':status: :substatus', [
                    'status'    => $labels[ $substatus->system_status ][0] ?? $substatus->system_status,
                    'substatus' => $substatus->label,
                ] ),
            ] );

        return $options->values()->all();
    }

    /**
     * The "Open board" URL, or null when the kanban satellite is not
     * installed. Filterable through `ap.ecommerceAdminLivewire.kanban.boardUrl`.
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard  $board  The board.
     *
     * @return string|null
     */
    public static function boardUrl( KanbanBoard $board ): ?string
    {
        $url = self::kanbanInstalled() && Route::has( self::BOARD_ROUTE )
            ? route( self::BOARD_ROUTE, [ 'board' => $board->id ] )
            : null;

        $url = applyFilters( 'ap.ecommerceAdminLivewire.kanban.boardUrl', $url, $board );

        return is_string( $url ) && '' !== $url ? $url : null;
    }

    /**
     * Whether the kanban board satellite is installed and active.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function kanbanInstalled(): bool
    {
        try {
            $satellites = app( SatelliteRegistry::class );

            return $satellites->has( self::KANBAN_PACKAGE ) && $satellites->isActive( self::KANBAN_PACKAGE );
        } catch ( Throwable ) {
            return false;
        }
    }

    /**
     * Labels of a contract registry, by label.
     *
     * @since 1.0.0
     *
     * @param  object  $registry  An engine registry with `keys()` and `get()`.
     *
     * @return array<string, string>
     */
    private static function labels( object $registry ): array
    {
        $labels = [];

        foreach ( $registry->keys() as $key ) {
            try {
                $labels[ (string) $key ] = (string) $registry->get( $key )->label();
            } catch ( Throwable $exception ) {
                report( $exception );
            }
        }

        asort( $labels, SORT_NATURAL | SORT_FLAG_CASE );

        return $labels;
    }
}
