<?php

/**
 * Picker search concern.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns;

use ArtisanPackUI\EcommerceAdminLivewire\Pickers\PickerSource;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\PickerSourceRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Livewire\Attributes\Locked;

/**
 * Server search for the `<x-artisanpack-ec-*-picker>` components.
 *
 * `x-artisanpack-choices` calls `searchPicker( term, type, field )` on the
 * component as the user types. The results land in `$pickerOptions`, keyed
 * `{type}:{field}`, together with the currently selected options so their
 * chips keep their labels. Each search is authorized against the source's
 * ability.
 *
 * Pass `:options="$this->optionsForPicker( 'product', 'productIds' )"` to the
 * picker so it renders the current options.
 *
 * Uses {@see AuthorizesEcommerce}.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
trait WithPickers
{
    /**
     * The most recent search results per picker, keyed `{type}:{field}`.
     *
     * Locked: only `searchPicker()` writes it, so a client cannot inject
     * options (or labels for selected chips).
     *
     * @since 1.0.0
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    #[Locked]
    public array $pickerOptions = [];

    /**
     * Searches a picker source.
     *
     * @since 1.0.0
     *
     * @param  string  $search  The search term.
     * @param  string  $type    The picker type.
     * @param  string  $field   The property the picker is bound to.
     *
     * @return void
     */
    public function searchPicker( string $search = '', string $type = '', string $field = '' ): void
    {
        $source = $this->authorizedPickerSource( $type );

        $this->pickerOptions[ $type . ':' . $field ] = $this->mergePickerOptions(
            $source->find( $this->pickerSelection( $field ) ),
            $source->search( mb_substr( $search, 0, 100 ), self::pickerLimit() ),
        );
    }

    /**
     * The options to render for a picker: the last search results, or the
     * selected options followed by the first page.
     *
     * Returns nothing when the user may not search the source, so a form can
     * render a picker for a user without that ability. It is public so views
     * can call it, which also makes it a Livewire action: the same check
     * keeps it from leaking options.
     *
     * @since 1.0.0
     *
     * @param  string  $type   The picker type.
     * @param  string  $field  The property the picker is bound to.
     *
     * @return array<int, array<string, mixed>>
     */
    public function optionsForPicker( string $type, string $field ): array
    {
        $source = app( PickerSourceRegistry::class )->get( $type );

        if ( null === $source || ! Authorization::allows( auth()->user(), $source->ability() ) ) {
            return [];
        }

        if ( isset( $this->pickerOptions[ $type . ':' . $field ] ) ) {
            return $this->pickerOptions[ $type . ':' . $field ];
        }

        return $this->mergePickerOptions(
            $source->find( $this->pickerSelection( $field ) ),
            $source->search( '', self::pickerLimit() ),
        );
    }

    /**
     * How many options a search returns.
     *
     * @since 1.0.0
     *
     * @return int
     */
    protected static function pickerLimit(): int
    {
        return 20;
    }

    /**
     * The registered source for a type, after authorizing it.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The picker type.
     *
     * @return PickerSource
     */
    protected function authorizedPickerSource( string $type ): PickerSource
    {
        $source = app( PickerSourceRegistry::class )->get( $type );

        if ( null === $source ) {
            $this->denyEcommerce();
        }

        $this->authorizeEcommerceAbility( $source->ability() );

        return $source;
    }

    /**
     * The selected ids of the property a picker is bound to.
     *
     * Only public component properties can be read.
     *
     * @since 1.0.0
     *
     * @param  string  $field  The property path.
     *
     * @return array<int, int|string>
     */
    protected function pickerSelection( string $field ): array
    {
        if ( '' === $field ) {
            return [];
        }

        $value = data_get( $this->all(), $field );

        return array_values( array_filter( (array) $value, static fn ( mixed $id ): bool => is_int( $id ) || is_string( $id ) ) );
    }

    /**
     * Selected options first, then results, without duplicates.
     *
     * @since 1.0.0
     *
     * @param  array<int, array<string, mixed>>  $selected  The selected options.
     * @param  array<int, array<string, mixed>>  $results   The search results.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mergePickerOptions( array $selected, array $results ): array
    {
        $merged = [];

        foreach ( [ ...$selected, ...$results ] as $option ) {
            $merged[ (string) $option['id'] ] ??= $option;
        }

        return array_values( $merged );
    }
}
