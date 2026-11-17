<?php

/**
 * Option lists for the design-system choice controls.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

/**
 * Maps the admin's option arrays onto the shape livewire-ui-components 3.0
 * reads for Select, Combobox, RadioGroup and CheckboxGroup.
 *
 * The admin, its registries and the extensions that feed them build options
 * as `[ 'id' => …, 'name' => … ]` (the 2.x default keys), sometimes with a
 * `hint`. 3.0 reads `value`, `label` and `description` only, so every list
 * passes through here: `id` becomes `value`, `name` becomes `label`, `hint`
 * becomes `description`. Keys already in the 3.0 shape win, other keys
 * (`disabled`, `icon`, `color`, `meta`) are kept, plain strings pass through,
 * and a Select group (`[ 'label' => …, 'options' => [ … ] ]`) is mapped
 * recursively.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      2.0.0
 */
final class ControlOptions
{
    /**
     * The options in the 3.0 shape.
     *
     * @since 2.0.0
     *
     * @param  iterable<mixed>|null  $options  The options.
     *
     * @return array<int, mixed>
     */
    public static function from( ?iterable $options ): array
    {
        $mapped = [];

        foreach ( $options ?? [] as $option ) {
            $mapped[] = self::option( $option );
        }

        return $mapped;
    }

    /**
     * One option (or group) in the 3.0 shape.
     *
     * @since 2.0.0
     *
     * @param  mixed  $option  The option.
     *
     * @return mixed
     */
    private static function option( mixed $option ): mixed
    {
        if ( is_object( $option ) && method_exists( $option, 'toArray' ) ) {
            $option = $option->toArray();
        }

        if ( ! is_array( $option ) ) {
            return $option;
        }

        if ( isset( $option['options'] ) && is_iterable( $option['options'] ) ) {
            return [
                'label'   => (string) ( $option['label'] ?? $option['name'] ?? '' ),
                'options' => self::from( $option['options'] ),
            ];
        }

        $value = $option['value'] ?? $option['id'] ?? null;
        $label = $option['label'] ?? $option['name'] ?? $value;

        $mapped = array_diff_key( $option, array_flip( [ 'id', 'name', 'hint' ] ) );

        $mapped['value'] = null === $value ? '' : $value;
        $mapped['label'] = (string) $label;

        $description = $option['description'] ?? $option['hint'] ?? null;

        if ( null !== $description && '' !== $description ) {
            $mapped['description'] = (string) $description;
        }

        return $mapped;
    }
}
