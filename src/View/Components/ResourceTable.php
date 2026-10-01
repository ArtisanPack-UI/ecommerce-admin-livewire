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
 * The table body of an index screen that uses `WithResourceTable`.
 *
 * `x-artisanpack-table` (livewire-ui-components 2.1) has no caption, no
 * `aria-sort`, and sorts on a mouse-only header click, so this component
 * composes the table from daisyUI markup and library parts instead (spec
 * §11, livewire-ui-components#116). When the library table gains those,
 * this is the one file to swap.
 *
 * Sortable headers are buttons that call `sort( key )` and carry
 * `aria-sort`; row checkboxes bind to `selected`.
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
     */
    public function __construct(
        public array $columns,
        public LengthAwarePaginator $rows,
        public string $caption,
        public string $sort = '',
        public string $direction = 'asc',
        public bool $selectable = false,
        public ?Closure $rowLabel = null,
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
            return 'o-chevron-up-down';
        }

        return 'asc' === $this->direction ? 'o-chevron-up' : 'o-chevron-down';
    }

    /**
     * The text of a cell that has no view.
     *
     * @since 1.0.0
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
            return $value->format( 'Y-m-d H:i' );
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
