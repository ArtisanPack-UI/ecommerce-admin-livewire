<?php

/**
 * Config form component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use IntlCalendar;
use Livewire\Component as LivewireComponent;
use Livewire\Livewire;

/**
 * `<x-artisanpack-ec-config-form registry="promotion-condition" entry="min-subtotal" model="config" />`
 *
 * Renders the schema registered for a registry entry with
 * livewire-ui-components inputs, each bound to `{model}.{field}` on the
 * surrounding Livewire component. That component validates with
 * `WithConfigForms::validateConfigForm()`, which uses the same schema.
 *
 * An entry with no schema renders a JSON editor bound to `{model}` (a
 * string; see `WithConfigForms::configFormState()`), with a notice. Product
 * fields need the surrounding component to use `WithPickers`; repeater rows
 * use `WithConfigForms::addConfigRow()` / `removeConfigRow()`.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class ConfigForm extends Component
{
    /**
     * The field types that take the full width of the field grid.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const WIDE_TYPES = [ 'boolean', 'tag', 'repeater', 'template', 'textarea', 'json', 'product', 'category', 'product-tag' ];

    /**
     * The schema, or null for the JSON fallback.
     *
     * @since 1.0.0
     *
     * @var array<int, array<string, mixed>>|null
     */
    public ?array $schema;

    /**
     * @since 1.0.0
     *
     * @param  string  $registry  The registry name, e.g. `promotion-action`.
     * @param  string  $entry     The entry key, e.g. `percent-off-cart`.
     * @param  string  $model     The property path the config is bound to.
     */
    public function __construct(
        public string $registry,
        public string $entry,
        public string $model,
    ) {
        $this->schema = app( ConfigFormRegistry::class )->schema( $registry, $entry );
    }

    /**
     * A DOM id for a property path.
     *
     * @since 1.0.0
     *
     * @param  string  $path  The property path.
     *
     * @return string
     */
    public function fieldId( string $path ): string
    {
        return 'config-' . preg_replace( '/[^A-Za-z0-9_-]+/', '-', $path );
    }

    /**
     * The current value at a path on the surrounding Livewire component.
     *
     * @since 1.0.0
     *
     * @param  string  $path  The property path.
     *
     * @return mixed
     */
    public function valueAt( string $path ): mixed
    {
        $component = Livewire::current();

        return $component instanceof LivewireComponent ? data_get( $component->all(), $path ) : null;
    }

    /**
     * The picker options for a product, variant, category, or tag field, when the
     * surrounding component uses `WithPickers`.
     *
     * @since 1.0.0
     *
     * @param  string  $source  `product`, `variant`, `category`, or `tag`.
     * @param  string  $path    The property path.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pickerOptions( string $source, string $path ): array
    {
        $component = Livewire::current();

        if ( ! $component instanceof LivewireComponent || ! method_exists( $component, 'optionsForPicker' ) ) {
            return [];
        }

        return $component->optionsForPicker( $source, $path );
    }

    /**
     * Whether a field spans both columns of the field grid.
     *
     * @since 2.0.0
     *
     * @param  array<string, mixed>  $field  The schema field.
     *
     * @return bool
     */
    public function spansBothColumns( array $field ): bool
    {
        return in_array( $field['type'] ?? '', self::WIDE_TYPES, true );
    }

    /**
     * The weekdays as ISO numbers (1 = Monday) with full and short names,
     * starting on the current locale's first day of the week.
     *
     * @since 1.0.0
     * @since 2.0.0 Adds `short` and follows the locale's first day.
     *
     * @return array<int, array{id: int, name: string, short: string}>
     */
    public function weekdays(): array
    {
        $days = [
            1 => [ 'id' => 1, 'name' => __( 'Monday' ), 'short' => __( 'Mon' ) ],
            2 => [ 'id' => 2, 'name' => __( 'Tuesday' ), 'short' => __( 'Tue' ) ],
            3 => [ 'id' => 3, 'name' => __( 'Wednesday' ), 'short' => __( 'Wed' ) ],
            4 => [ 'id' => 4, 'name' => __( 'Thursday' ), 'short' => __( 'Thu' ) ],
            5 => [ 'id' => 5, 'name' => __( 'Friday' ), 'short' => __( 'Fri' ) ],
            6 => [ 'id' => 6, 'name' => __( 'Saturday' ), 'short' => __( 'Sat' ) ],
            7 => [ 'id' => 7, 'name' => __( 'Sunday' ), 'short' => __( 'Sun' ) ],
        ];

        $first = self::firstDayOfWeek();

        return [ ...array_values( array_slice( $days, $first - 1 ) ), ...array_values( array_slice( $days, 0, $first - 1 ) ) ];
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
        return view( 'ecommerce-admin::components.config-form' );
    }

    /**
     * The current locale's first day of the week as an ISO number.
     *
     * @since 2.0.0
     *
     * @return int 1 (Monday) to 7 (Sunday).
     */
    private static function firstDayOfWeek(): int
    {
        $calendar = IntlCalendar::createInstance( null, str_replace( '-', '_', app()->getLocale() ) );
        $first    = null === $calendar ? IntlCalendar::DOW_MONDAY : $calendar->getFirstDayOfWeek();

        // IntlCalendar counts from Sunday (1); ISO counts from Monday (1).
        return IntlCalendar::DOW_SUNDAY === $first ? 7 : $first - 1;
    }
}
