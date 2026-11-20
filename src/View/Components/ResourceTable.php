<?php

/**
 * Resource table component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;
use Stringable;
use Throwable;

/**
 * `<x-artisanpack-ec-resource-table :columns="$tableColumns" :rows="$tableRows" … />`
 *
 * The grid of an index screen that uses `WithResourceTable` (#79): the
 * scroll part of a design-system Table, with a visually hidden caption,
 * sortable headers and row selection.
 *
 * It renders the design system's Table markup itself rather than through
 * `x-artisanpack-table`: that component sorts and selects in Alpine, while
 * the admin's tables sort, filter, select and page on the server
 * (`WithResourceTable`), so a selection survives paging and "select all
 * matching" can reach rows that aren't on the page.
 *
 * Sortable headers are buttons inside `th[aria-sort]` that call
 * `sort( key )`. Row checkboxes bind to `selected` and name their row
 * ("Select order #1048"); the header box selects the page and goes mixed
 * while part of it is selected.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class ResourceTable extends Component
{
    /**
     * @since 1.0.0
     *
     * @param  array<int, array<string, mixed>>  $columns     The normalized column definitions.
     * @param  LengthAwarePaginator              $rows        The current page.
     * @param  string                            $caption     What the table lists.
     * @param  string                            $sort        The sort key in effect.
     * @param  string                            $direction   `asc` or `desc`.
     * @param  bool                              $selectable  Whether rows can be selected.
     * @param  Closure|null                      $rowLabel    Names a row for its checkbox, e.g. the order number.
     * @param  array<string, mixed>              $context     Screen state passed to cell views as `$context`.
     * @param  array<int, int|string>            $selected    The selected row keys, for the checkboxes' first render.
     */
    public function __construct(
        public array $columns,
        public LengthAwarePaginator $rows,
        public string $caption,
        public string $sort = '',
        public string $direction = 'asc',
        public bool $selectable = false,
        public ?Closure $rowLabel = null,
        public array $context = [],
        public array $selected = [],
    ) {
    }

    /**
     * The `aria-sort` value for a column.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $column  The column.
     *
     * @return string
     */
    public function ariaSort( array $column ): string
    {
        if ( $column['key'] !== $this->sort ) {
            return 'none';
        }

        return 'asc' === $this->direction ? 'ascending' : 'descending';
    }

    /**
     * The sort indicator icon for a column.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $column  The column.
     *
     * @return string
     */
    public function sortIcon( array $column ): string
    {
        if ( $column['key'] !== $this->sort ) {
            return 'chevrons-up-down';
        }

        return 'asc' === $this->direction ? 'arrow-up' : 'arrow-down';
    }

    /**
     * Whether a row is selected.
     *
     * @since 2.0.0
     *
     * @param  Model  $row  The row.
     *
     * @return bool
     */
    public function isSelected( Model $row ): bool
    {
        return in_array( (string) $row->getKey(), array_map( 'strval', $this->selected ), true );
    }

    /**
     * The page's select-all state: `checked` when every row on the page is
     * selected, `indeterminate` when some are, else `unchecked`.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public function pageSelectionState(): string
    {
        $keys = $this->pageKeys();

        if ( [] === $keys ) {
            return 'unchecked';
        }

        $selected = count( array_intersect( $keys, array_map( 'strval', $this->selected ) ) );

        return match ( true ) {
            0 === $selected              => 'unchecked',
            count( $keys ) === $selected => 'checked',
            default                      => 'indeterminate',
        };
    }

    /**
     * The text of a cell that has no view. Dates show in the store time zone
     * and the app locale.
     *
     * @since 1.0.0
     * @since 2.0.0 Dates are localized through the engine's `LocalizedDate`.
     *
     * @param  Model                 $row     The row.
     * @param  array<string, mixed>  $column  The column.
     *
     * @return string
     */
    public function cellText( Model $row, array $column ): string
    {
        try {
            $value = null !== $column['value'] ? ( $column['value'] )( $row ) : data_get( $row, $column['key'] );
        } catch ( Throwable $exception ) {
            report( $exception );

            return '';
        }

        if ( $value instanceof DateTimeInterface ) {
            return LocalizedDate::format( $value, __( 'M j, Y g:i A' ) );
        }

        return is_scalar( $value ) || $value instanceof Stringable ? (string) $value : '';
    }

    /**
     * The accessible name of a row's checkbox.
     *
     * @since 1.0.0
     *
     * @param  Model  $row  The row.
     *
     * @return string
     */
    public function checkboxLabel( Model $row ): string
    {
        $label = null !== $this->rowLabel ? (string) ( $this->rowLabel )( $row ) : (string) $row->getKey();

        return __( 'Select :row', [ 'row' => $label ] );
    }

    /**
     * The keys of the rows on this page.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public function pageKeys(): array
    {
        return array_map( static fn ( Model $row ): string => (string) $row->getKey(), $this->rows->items() );
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        return view( 'ecommerce-admin::components.resource-table' );
    }
}
