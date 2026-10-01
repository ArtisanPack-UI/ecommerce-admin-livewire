<?php

/**
 * Base picker component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

use ArtisanPackUI\EcommerceAdminLivewire\Registries\PickerSourceRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * A searchable picker on `x-artisanpack-choices`.
 *
 * Use inside a Livewire component that uses the `WithPickers` concern:
 *
 *     <x-artisanpack-ec-product-picker
 *         model="productIds"
 *         :options="$this->optionsForPicker( 'product', 'productIds' )"
 *         :label="__( 'Products' )"
 *     />
 *
 * Typing calls `searchPicker( term, type, model )` on the component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
abstract class Picker extends Component
{
    /**
     * @since 1.0.0
     *
     * @param  string                            $model    The property the picker binds to.
     * @param  array<int, array<string, mixed>>  $options  The options to show (from `optionsForPicker()`).
     * @param  string|null                       $label    The label.
     * @param  string|null                       $hint     The hint.
     * @param  bool                              $single   Pick one value instead of many.
     * @param  bool                              $live     Bind with `wire:model.live`.
     * @param  string|null                       $id       A distinct id when the same model appears twice.
     */
    public function __construct(
        public string $model,
        public array $options = [],
        public ?string $label = null,
        public ?string $hint = null,
        public bool $single = false,
        public bool $live = false,
        public ?string $id = null,
    ) {
        if ( 1 !== preg_match( '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z0-9_]+)*$/', $this->model ) ) {
            throw new InvalidArgumentException( sprintf( 'Picker model "%s" must be a property path.', $this->model ) );
        }
    }

    /**
     * The picker type in the `PickerSourceRegistry`.
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract public function type(): string;

    /**
     * The notice shown while no source is registered for the type.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function unavailableMessage(): string
    {
        return __( 'This picker is not available yet.' );
    }

    /**
     * Whether a source is registered for the type.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function available(): bool
    {
        return app( PickerSourceRegistry::class )->has( $this->type() );
    }

    /**
     * The Livewire call `x-artisanpack-choices` makes; it prepends the term.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function searchFunction(): string
    {
        return sprintf( "searchPicker('%s', '%s')", $this->type(), $this->model );
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
        return view( 'ecommerce-admin::components.picker' );
    }
}
