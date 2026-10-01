<?php

/**
 * Resource table concern for index screens.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns;

use ArtisanPack\LivewireUiComponents\Traits\WithTableExport;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Stringable;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Search, filters, sort, pagination, selection, bulk actions, and CSV export
 * for an index screen (spec §8.6).
 *
 * Search, filters, sort, per-page, and page live in the URL, so reloading a
 * filtered list keeps it. Every value read from the URL or the client is
 * normalized against the screen's definitions before it reaches the query.
 *
 * A screen supplies its query class, columns, filters, and bulk actions.
 * Satellites extend them through the filters
 * `ap.ecommerceAdminLivewire.table.{screen}.columns`, `.filters`, and
 * `.bulkActions` (spec §8.5).
 *
 * Column definition: `key`, `label`, `sortable` (bool), `class`, and one of
 * `view` (a Blade view rendered with `$row` and `$column`), `value`
 * (callable returning text), or neither (the row attribute named `key`).
 * `export` (callable) overrides the CSV value; `exportable` false leaves the
 * column out of the CSV.
 *
 * Filter definition: `key`, `label`, `type` (`select`, `multiselect`, `text`,
 * `boolean`, `date-range`), `options` (`[ [ 'id' => …, 'name' => … ] ]`), and
 * `apply` (callable( Builder, mixed )) for filters the query class does not
 * handle.
 *
 * Bulk action definition: `key`, `label`, `icon`, `ability` (a
 * `{resource}.{action}` ability), `confirm` (a message; the action then asks
 * for confirmation and carries a one-time token), and `handler`
 * (callable( Builder $selection, Component $component ) returning a success
 * message, a download response, or null).
 *
 * Uses {@see AuthorizesEcommerce}, {@see SendsToasts}, {@see WithActionToken}.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
trait WithResourceTable
{
    use WithPagination;
    use WithTableExport {
        handleTableExport as protected;
        exportTableToCsv as protected;
        exportTableToXlsx as protected;
        canExportXlsx as protected;
    }

    /**
     * The search term.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( as: 'q', except: '' )]
    public string $search = '';

    /**
     * Filter values, keyed by filter key.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    #[Url( except: [] )]
    public array $filters = [];

    /**
     * The sort key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( as: 'sort', except: '' )]
    public string $sortColumn = '';

    /**
     * The sort direction.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( as: 'dir', except: '' )]
    public string $sortDirection = '';

    /**
     * Rows per page. Normalized on mount to one of `tables.per_page_values`,
     * falling back to `tables.per_page`; the URL omits the shipped default.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    #[Url( as: 'per_page', except: 25 )]
    public int|string|null $perPage = null;

    /**
     * The keys of the selected rows.
     *
     * @since 1.0.0
     *
     * @var array<int, int|string>
     */
    public array $selected = [];

    /**
     * Whether the selection is every row matching the search and filters.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $selectAllMatching = false;

    /**
     * The bulk action waiting for confirmation.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $confirmingBulkAction = null;

    /**
     * Whether the export in progress covers only the selection.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    private bool $exportingSelection = false;

    /**
     * Whether an export started by this package is in progress.
     *
     * `getTableExportData()` has to be public for livewire-ui-components'
     * export trait, which makes it a Livewire action too; it only answers
     * while this is set, so a client cannot call it for the rows as JSON.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    private bool $exporting = false;

    /**
     * Normalizes the URL state on the first render.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mountWithResourceTable(): void
    {
        $this->perPage = $this->perPageValue();
    }

    /**
     * Re-authorizes the screen on every update request, so no action,
     * pagination call, or property sync renders rows for a user who lost
     * access.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrateWithResourceTable(): void
    {
        $this->authorizeTable();
    }

    /**
     * Resets the page and the selection when the result set changes.
     *
     * @since 1.0.0
     *
     * @param  string  $property  The updated property path.
     *
     * @return void
     */
    public function updatedWithResourceTable( string $property ): void
    {
        $root = explode( '.', $property )[0];

        if ( in_array( $root, [ 'search', 'filters', 'perPage' ], true ) ) {
            $this->resetPage();
            $this->resetSelection();
        }

        if ( 'selected' === $root ) {
            $this->selectAllMatching = false;
            $this->selected          = $this->normalizedSelection();
        }
    }

    /**
     * Sorts by a column, toggling the direction when it is already sorted.
     *
     * @since 1.0.0
     *
     * @param  string  $column  The sort key.
     *
     * @return void
     */
    public function sort( string $column ): void
    {
        $this->authorizeTable();

        if ( ! $this->tableQuery()->canSortBy( $column ) ) {
            return;
        }

        [ $current, $direction ] = $this->currentSort();

        $this->sortDirection = $current === $column && 'asc' === $direction ? 'desc' : 'asc';
        $this->sortColumn    = $column;

        $this->resetPage();
    }

    /**
     * Clears the search and every filter.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function resetFilters(): void
    {
        $this->authorizeTable();

        $this->search  = '';
        $this->filters = [];

        $this->resetPage();
        $this->resetSelection();
    }

    /**
     * Selects every row matching the search and filters, across all pages.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function selectAllMatchingRows(): void
    {
        $this->authorizeTable();

        $this->selectAllMatching = true;
    }

    /**
     * Clears the selection.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function clearSelection(): void
    {
        $this->authorizeTable();

        $this->resetSelection();
    }

    /**
     * Runs a bulk action on the selection, or asks for confirmation first.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The bulk action key.
     *
     * @return mixed A download response, or null.
     */
    public function runBulkAction( string $key ): mixed
    {
        $action = $this->authorizedBulkAction( $key );

        if ( ! $this->hasSelection() ) {
            $this->toastWarning( __( 'Select at least one row first.' ) );

            return null;
        }

        if ( null !== $action['confirm'] ) {
            $this->confirmingBulkAction = $key;

            return null;
        }

        return $this->executeBulkAction( $action );
    }

    /**
     * Runs the bulk action waiting for confirmation, once per token.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the confirmation.
     *
     * @return mixed
     */
    public function confirmBulkAction( string $token ): mixed
    {
        $key = $this->confirmingBulkAction;

        if ( null === $key ) {
            return null;
        }

        $action = $this->authorizedBulkAction( $key );

        $this->confirmingBulkAction = null;

        return $this->withActionToken( $token, 'bulk.' . $key, fn (): mixed => $this->executeBulkAction( $action ) );
    }

    /**
     * Dismisses the confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelBulkAction(): void
    {
        $this->authorizeTable();

        $this->confirmingBulkAction = null;
    }

    /**
     * Exports every row matching the search and filters as CSV.
     *
     * @since 1.0.0
     *
     * @return mixed The download response.
     */
    public function exportCsv(): mixed
    {
        $this->authorizeTable();

        return $this->runExport( false );
    }

    /**
     * The export headers and rows (livewire-ui-components' `WithTableExport`).
     *
     * Covers the selection during a bulk export, else every row matching the
     * search and filters, in the current sort, capped at
     * `tables.export_max_rows`. Cell values that a spreadsheet would read as
     * a formula are prefixed with an apostrophe. Returns nothing when called
     * directly as a Livewire action.
     *
     * @since 1.0.0
     *
     * @param  string  $tableId  The table id.
     *
     * @return array{headers: array<int, array{key: string, label: string}>, rows: array<int, array<string, mixed>>, filename: string}
     */
    public function getTableExportData( string $tableId = 'default' ): array
    {
        $this->authorizeTable();

        if ( ! $this->exporting ) {
            return [];
        }

        $columns = array_values( array_filter( $this->tableColumnDefinitions(), static fn ( array $column ): bool => $column['exportable'] ) );
        $limit   = max( 1, (int) config( 'artisanpack.ecommerce-admin-livewire.tables.export_max_rows', 10_000 ) );
        $query   = $this->exportingSelection ? $this->selectionQuery() : $this->filteredQuery();
        $rows    = [];

        foreach ( $query->lazy( 500 ) as $model ) {
            if ( count( $rows ) === $limit ) {
                $this->toastWarning(
                    __( 'The export was cut short.' ),
                    trans_choice( 'Only the first :count row was exported.|Only the first :count rows were exported.', $limit, [ 'count' => $limit ] ),
                );

                break;
            }

            $row = [];

            foreach ( $columns as $column ) {
                $row[ $column['key'] ] = self::exportCell( $this->exportValue( $model, $column ) );
            }

            $rows[] = $row;
        }

        return [
            'headers'  => array_map( static fn ( array $column ): array => [ 'key' => $column['key'], 'label' => $column['label'] ], $columns ),
            'rows'     => $rows,
            'filename' => $this->tableScreen() . '-' . Carbon::now()->format( 'Y-m-d-His' ),
        ];
    }

    /**
     * The screen key used in extension filter names, e.g. `orders`.
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract protected function tableScreen(): string;

    /**
     * The screen's query class.
     *
     * @since 1.0.0
     *
     * @return ResourceQuery
     */
    abstract protected function tableQuery(): ResourceQuery;

    /**
     * Authorizes viewing the table. Called on mount and on every request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    abstract protected function authorizeTable(): void;

    /**
     * The core columns.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function tableColumns(): array;

    /**
     * The core filters.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function tableFilters(): array;

    /**
     * The core bulk actions.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function tableBulkActions(): array;

    /**
     * The table caption: what the table lists.
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract protected function tableCaption(): string;

    /**
     * The view data the resource-table partial needs.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function resourceTableData(): array
    {
        $rows             = $this->tableRows();
        [ $sort, $order ] = $this->currentSort();
        $bulkActions      = $this->visibleBulkActions();

        return [
            'tableRows'           => $rows,
            'tableColumns'        => $this->tableColumnDefinitions(),
            'tableFilters'        => $this->tableFilterDefinitions(),
            'tableBulkActions'    => $bulkActions,
            'tableCaption'        => $this->tableCaption(),
            'tableSort'           => $sort,
            'tableDirection'      => $order,
            'tablePerPageValues'  => $this->perPageValues(),
            'tableFiltersActive'  => $this->filtersActive(),
            'tableSelectionCount' => $this->selectionCount( $rows ),
            'tableAllMatching'    => $this->selectAllMatching,
            'tableSelectable'     => [] !== $bulkActions,
            'tableConfirming'     => $this->confirmingBulkAction(),
            'tableConfirmToken'   => null === $this->confirmingBulkAction ? null : $this->actionToken( 'bulk.' . $this->confirmingBulkAction ),
        ];
    }

    /**
     * The current page of rows.
     *
     * @since 1.0.0
     *
     * @return LengthAwarePaginator
     */
    protected function tableRows(): LengthAwarePaginator
    {
        return $this->filteredQuery()->paginate( $this->perPageValue() );
    }

    /**
     * The query for the search, filters, and sort.
     *
     * @since 1.0.0
     *
     * @return Builder
     */
    protected function filteredQuery(): Builder
    {
        [ $sort, $direction ] = $this->currentSort();

        return $this->tableQuery()
            ->withFilters( $this->extensionFilterCallbacks() )
            ->build( $this->search, $this->normalizedFilters(), $sort, $direction );
    }

    /**
     * The query for the selection: every matching row, or the selected keys.
     *
     * @since 1.0.0
     *
     * @return Builder
     */
    protected function selectionQuery(): Builder
    {
        if ( $this->selectAllMatching ) {
            return $this->filteredQuery();
        }

        [ $sort, $direction ] = $this->currentSort();

        $query = $this->tableQuery()->build( '', [], $sort, $direction );

        return $query->whereKey( $this->normalizedSelection() );
    }

    /**
     * Whether anything is selected.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function hasSelection(): bool
    {
        return $this->selectAllMatching || [] !== $this->normalizedSelection();
    }

    /**
     * The columns after the extension filter, normalized.
     *
     * @since 1.0.0
     *
     * @return array<int, array{key: string, label: string, sortable: bool, class: string, view: string|null, value: callable|null, export: callable|null, exportable: bool}>
     */
    protected function tableColumnDefinitions(): array
    {
        $columns = (array) applyFilters( 'ap.ecommerceAdminLivewire.table.' . $this->tableScreen() . '.columns', $this->tableColumns(), $this );
        $query   = $this->tableQuery();
        $result  = [];

        foreach ( $columns as $column ) {
            if ( ! is_array( $column ) || ! isset( $column['key'], $column['label'] ) ) {
                continue;
            }

            $key = (string) $column['key'];

            $result[ $key ] = [
                'key'        => $key,
                'label'      => (string) $column['label'],
                'sortable'   => (bool) ( $column['sortable'] ?? false ) && $query->canSortBy( $key ),
                'class'      => (string) ( $column['class'] ?? '' ),
                'view'       => isset( $column['view'] ) ? (string) $column['view'] : null,
                'value'      => isset( $column['value'] ) && is_callable( $column['value'] ) ? $column['value'] : null,
                'export'     => isset( $column['export'] ) && is_callable( $column['export'] ) ? $column['export'] : null,
                'exportable' => (bool) ( $column['exportable'] ?? true ),
            ];
        }

        return array_values( $result );
    }

    /**
     * The filters after the extension filter, normalized.
     *
     * @since 1.0.0
     *
     * @return array<int, array{key: string, label: string, type: string, options: array<int, array{id: string, name: string}>, apply: callable|null}>
     */
    protected function tableFilterDefinitions(): array
    {
        $filters = (array) applyFilters( 'ap.ecommerceAdminLivewire.table.' . $this->tableScreen() . '.filters', $this->tableFilters(), $this );
        $types   = [ 'select', 'multiselect', 'text', 'boolean', 'date-range' ];
        $result  = [];

        foreach ( $filters as $filter ) {
            if ( ! is_array( $filter ) || ! isset( $filter['key'], $filter['label'] ) || ! in_array( $filter['type'] ?? 'select', $types, true ) ) {
                continue;
            }

            $type = (string) ( $filter['type'] ?? 'select' );

            $result[ (string) $filter['key'] ] = [
                'key'     => (string) $filter['key'],
                'label'   => (string) $filter['label'],
                'type'    => $type,
                'options' => 'boolean' === $type
                    ? [ [ 'id' => '1', 'name' => __( 'Yes' ) ], [ 'id' => '0', 'name' => __( 'No' ) ] ]
                    : array_values( array_map(
                        static fn ( array $option ): array => [ 'id' => (string) $option['id'], 'name' => (string) $option['name'] ],
                        array_filter( (array) ( $filter['options'] ?? [] ), static fn ( mixed $option ): bool => is_array( $option ) && isset( $option['id'], $option['name'] ) ),
                    ) ),
                'apply'   => isset( $filter['apply'] ) && is_callable( $filter['apply'] ) ? $filter['apply'] : null,
            ];
        }

        return array_values( $result );
    }

    /**
     * The bulk actions after the extension filter, normalized and keyed.
     *
     * @since 1.0.0
     *
     * @return array<string, array{key: string, label: string, icon: string, ability: string|null, confirm: string|null, handler: callable}>
     */
    protected function tableBulkActionDefinitions(): array
    {
        $actions = (array) applyFilters( 'ap.ecommerceAdminLivewire.table.' . $this->tableScreen() . '.bulkActions', $this->tableBulkActions(), $this );
        $result  = [];

        foreach ( $actions as $action ) {
            if ( ! is_array( $action ) || ! isset( $action['key'], $action['label'], $action['handler'] ) || ! is_callable( $action['handler'] ) ) {
                continue;
            }

            $result[ (string) $action['key'] ] = [
                'key'     => (string) $action['key'],
                'label'   => (string) $action['label'],
                'icon'    => (string) ( $action['icon'] ?? 'o-bolt' ),
                'ability' => isset( $action['ability'] ) && '' !== $action['ability'] ? (string) $action['ability'] : null,
                'confirm' => isset( $action['confirm'] ) && '' !== $action['confirm'] ? (string) $action['confirm'] : null,
                'handler' => $action['handler'],
            ];
        }

        return $result;
    }

    /**
     * The bulk actions the user may run.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    protected function visibleBulkActions(): array
    {
        return array_filter(
            $this->tableBulkActionDefinitions(),
            static fn ( array $action ): bool => null === $action['ability'] || Authorization::allows( auth()->user(), $action['ability'] ),
        );
    }

    /**
     * The export action every table offers: the selection as CSV.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function exportBulkAction(): array
    {
        return [
            'key'     => 'export',
            'label'   => __( 'Export selection' ),
            'icon'    => 'o-arrow-down-tray',
            'handler' => fn (): mixed => $this->runExport( true ),
        ];
    }

    /**
     * The filter values, keeping only defined filters and valid values.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function normalizedFilters(): array
    {
        $values = [];

        foreach ( $this->tableFilterDefinitions() as $filter ) {
            $value = self::normalizeFilterValue( $filter, $this->filters[ $filter['key'] ] ?? null );

            if ( ! ResourceQuery::isEmptyValue( $value ) ) {
                $values[ $filter['key'] ] = $value;
            }
        }

        return $values;
    }

    /**
     * Whether a search or any filter is active.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function filtersActive(): bool
    {
        return '' !== trim( $this->search ) || [] !== $this->normalizedFilters();
    }

    /**
     * The sort key and direction in effect.
     *
     * @since 1.0.0
     *
     * @return array{0: string, 1: string}
     */
    protected function currentSort(): array
    {
        $query = $this->tableQuery();

        if ( ! $query->canSortBy( $this->sortColumn ) ) {
            return $query->defaultSort();
        }

        return [ $this->sortColumn, 'desc' === $this->sortDirection ? 'desc' : 'asc' ];
    }

    /**
     * The allowed per-page values.
     *
     * @since 1.0.0
     *
     * @return array<int, int>
     */
    protected function perPageValues(): array
    {
        $values = array_values( array_filter(
            array_map( 'intval', (array) config( 'artisanpack.ecommerce-admin-livewire.tables.per_page_values', [ 10, 25, 50, 100 ] ) ),
            static fn ( int $value ): bool => $value > 0,
        ) );

        return [] === $values ? [ 25 ] : $values;
    }

    /**
     * The per-page value in effect.
     *
     * @since 1.0.0
     *
     * @return int
     */
    protected function perPageValue(): int
    {
        $values  = $this->perPageValues();
        $default = (int) config( 'artisanpack.ecommerce-admin-livewire.tables.per_page', 25 );

        if ( is_numeric( $this->perPage ) && in_array( (int) $this->perPage, $values, true ) ) {
            return (int) $this->perPage;
        }

        return in_array( $default, $values, true ) ? $default : $values[0];
    }

    /**
     * The selected keys, as positive integers.
     *
     * @since 1.0.0
     *
     * @return array<int, int>
     */
    protected function normalizedSelection(): array
    {
        $keys = array_filter(
            $this->selected,
            static fn ( mixed $key ): bool => ( is_int( $key ) || ( is_string( $key ) && ctype_digit( $key ) ) ) && (int) $key > 0,
        );

        return array_values( array_unique( array_map( 'intval', $keys ) ) );
    }

    /**
     * How many rows are selected.
     *
     * @since 1.0.0
     *
     * @param  LengthAwarePaginator  $rows  The current page.
     *
     * @return int
     */
    protected function selectionCount( LengthAwarePaginator $rows ): int
    {
        return $this->selectAllMatching ? $rows->total() : count( $this->normalizedSelection() );
    }

    /**
     * The bulk action waiting for confirmation, when the user may still run it.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>|null
     */
    protected function confirmingBulkAction(): ?array
    {
        return null === $this->confirmingBulkAction ? null : ( $this->visibleBulkActions()[ $this->confirmingBulkAction ] ?? null );
    }

    /**
     * Empties the selection and dismisses any confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function resetSelection(): void
    {
        $this->selected             = [];
        $this->selectAllMatching    = false;
        $this->confirmingBulkAction = null;
    }

    /**
     * Streams the CSV through livewire-ui-components' export trait.
     *
     * @since 1.0.0
     *
     * @param  bool  $selectionOnly  Whether to export only the selection.
     *
     * @return mixed The download response.
     */
    private function runExport( bool $selectionOnly ): mixed
    {
        $this->exporting          = true;
        $this->exportingSelection = $selectionOnly;

        try {
            return $this->exportTableToCsv( $this->tableScreen() );
        } finally {
            $this->exporting = false;
        }
    }

    /**
     * A bulk action, after authorizing the screen and the action's ability.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The bulk action key.
     *
     * @return array<string, mixed>
     */
    private function authorizedBulkAction( string $key ): array
    {
        $this->authorizeTable();

        $action = $this->tableBulkActionDefinitions()[ $key ] ?? null;

        if ( null === $action ) {
            $this->denyEcommerce();
        }

        if ( null !== $action['ability'] ) {
            $this->authorizeEcommerceAbility( $action['ability'] );
        }

        return $action;
    }

    /**
     * Runs a bulk action's handler on the selection.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $action  The action.
     *
     * @return mixed
     */
    private function executeBulkAction( array $action ): mixed
    {
        $result = ( $action['handler'] )( $this->selectionQuery(), $this );

        if ( $result instanceof Response ) {
            return $result;
        }

        if ( is_string( $result ) && '' !== $result ) {
            $this->toastSuccess( $result );
        }

        $this->resetSelection();

        return null;
    }

    /**
     * The `apply` callbacks of filters the query class does not handle.
     *
     * @since 1.0.0
     *
     * @return array<string, callable>
     */
    private function extensionFilterCallbacks(): array
    {
        $callbacks = [];

        foreach ( $this->tableFilterDefinitions() as $filter ) {
            if ( null !== $filter['apply'] ) {
                $callbacks[ $filter['key'] ] = $filter['apply'];
            }
        }

        return $callbacks;
    }

    /**
     * A column's export value for a row.
     *
     * @since 1.0.0
     *
     * @param  Model                 $model   The row.
     * @param  array<string, mixed>  $column  The column.
     *
     * @return mixed
     */
    private function exportValue( Model $model, array $column ): mixed
    {
        try {
            return match ( true ) {
                null !== $column['export'] => ( $column['export'] )( $model ),
                null !== $column['value']  => ( $column['value'] )( $model ),
                default                    => data_get( $model, $column['key'] ),
            };
        } catch ( Throwable $exception ) {
            report( $exception );

            return '';
        }
    }

    /**
     * Makes a value safe for a CSV cell.
     *
     * Scalars pass through; dates become ISO 8601; text that a spreadsheet
     * would run as a formula (`=`, `+`, `-`, `@`, tab, carriage return) is
     * prefixed with an apostrophe unless it is a plain number.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  The value.
     *
     * @return float|int|string
     */
    private static function exportCell( mixed $value ): int|float|string
    {
        if ( is_int( $value ) || is_float( $value ) ) {
            return $value;
        }

        if ( is_bool( $value ) ) {
            return $value ? 1 : 0;
        }

        if ( $value instanceof DateTimeInterface ) {
            return $value->format( DATE_ATOM );
        }

        $text = is_scalar( $value ) || $value instanceof Stringable ? (string) $value : '';

        if ( '' !== $text && ! is_numeric( $text ) && in_array( $text[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
            return "'" . $text;
        }

        return $text;
    }

    /**
     * Keeps a filter value only when it fits the filter's type and options.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $filter  The filter.
     * @param  mixed                 $value   The raw value.
     *
     * @return mixed
     */
    private static function normalizeFilterValue( array $filter, mixed $value ): mixed
    {
        $optionIds = array_column( $filter['options'], 'id' );

        return match ( $filter['type'] ) {
            'select', 'boolean' => is_scalar( $value ) && in_array( (string) $value, $optionIds, true ) ? (string) $value : null,
            'multiselect'       => array_values( array_intersect( $optionIds, array_map( 'strval', array_filter( (array) $value, 'is_scalar' ) ) ) ),
            'text'              => is_string( $value ) ? trim( mb_substr( $value, 0, ResourceQuery::MAX_SEARCH_LENGTH ) ) : null,
            'date-range'        => [
                'from' => self::validDate( is_array( $value ) ? ( $value['from'] ?? null ) : null ),
                'to'   => self::validDate( is_array( $value ) ? ( $value['to'] ?? null ) : null ),
            ],
            default             => null,
        };
    }

    /**
     * A `Y-m-d` date string, or null.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  The raw value.
     *
     * @return string|null
     */
    private static function validDate( mixed $value ): ?string
    {
        if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) {
            return null;
        }

        [ $year, $month, $day ] = array_map( 'intval', explode( '-', $value ) );

        return checkdate( $month, $day, $year ) ? $value : null;
    }
}
