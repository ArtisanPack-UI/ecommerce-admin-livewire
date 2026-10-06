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

        return null === $component ? null : data_get( $component->all(), $path );
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

        if ( null === $component || ! method_exists( $component, 'optionsForPicker' ) ) {
            return [];
        }

        return $component->optionsForPicker( $source, $path );
    }

    /**
     * The ISO weekdays as checkbox options.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function weekdays(): array
    {
        return [
            [ 'id' => 1, 'name' => __( 'Monday' ) ],
            [ 'id' => 2, 'name' => __( 'Tuesday' ) ],
            [ 'id' => 3, 'name' => __( 'Wednesday' ) ],
            [ 'id' => 4, 'name' => __( 'Thursday' ) ],
            [ 'id' => 5, 'name' => __( 'Friday' ) ],
            [ 'id' => 6, 'name' => __( 'Saturday' ) ],
            [ 'id' => 7, 'name' => __( 'Sunday' ) ],
        ];
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
}
