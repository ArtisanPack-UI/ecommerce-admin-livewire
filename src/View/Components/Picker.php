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
use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Js;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * A searchable picker on the design-system Combobox.
 *
 * Single pickers show the choice as the field's value with a clear button;
 * multi pickers show choices as removable chips. With no source registered
 * the picker is an info Alert instead of a dead field.
 *
 * Use inside a Livewire component that uses the `WithPickers` concern:
 *
 *     <x-artisanpack-ec-product-picker
 *         model="productIds"
 *         :options="$this->optionsForPicker( 'product', 'productIds' )"
 *         :label="__( 'Products' )"
 *     />
 *
 * Typing calls `searchPicker( term, type, model )` on the component, 300ms
 * after the last keystroke.
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
     * @param  string|null                       $placeholder  The empty input's text. Defaults to the type's.
     */
    public function __construct(
        public string $model,
        public array $options = [],
        public ?string $label = null,
        public ?string $hint = null,
        public bool $single = false,
        public bool $live = false,
        public ?string $id = null,
        public ?string $placeholder = null,
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
     * The options in the shape the Combobox reads: value, label and the
     * sub-label as description.
     *
     * @since 2.0.0
     *
     * @return array<int, mixed>
     */
    public function comboboxOptions(): array
    {
        return ControlOptions::from( $this->options );
    }

    /**
     * The text in the empty input.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public function placeholderText(): string
    {
        return $this->placeholder ?? $this->defaultPlaceholder();
    }

    /**
     * The type's own placeholder.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public function defaultPlaceholder(): string
    {
        return __( 'Search…' );
    }

    /**
     * The Combobox `search-change` handler: calls `searchPicker()` with the
     * typed text and keeps the spinner on until the round trip ends.
     *
     * @since 2.0.0
     *
     * @return string The Alpine expression.
     */
    public function searchHandler(): string
    {
        return sprintf(
            'loading = true; Promise.resolve($wire.call(%s, $event.detail.search, %s, %s)).finally(() => loading = false)',
            Js::from( 'searchPicker' )->toHtml(),
            Js::from( $this->type() )->toHtml(),
            Js::from( $this->model )->toHtml(),
        );
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
