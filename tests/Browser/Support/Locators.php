<?php

declare( strict_types=1 );

namespace Tests\Browser\Support;

/**
 * Selectors for the browser suite.
 *
 * The component library prefixes generated ids, so fields are found by
 * their visible label, the way a person (or a screen reader) finds them.
 */
final class Locators
{
    /**
     * The form control labelled `$label`: the whole label text, optionally
     * followed by the required-field asterisk.
     *
     * @param  string  $label  The label text.
     *
     * @return string
     */
    public static function label( string $label ): string
    {
        return 'internal:label=/^\\s*' . preg_quote( $label, '/' ) . '\\s*\\*?\\s*$/';
    }

    /**
     * The control whose id contains `$id`.
     *
     * For component-library selects and textareas, whose label is a
     * <legend> that does not name the control (livewire-ui-components#117),
     * and for repeated rows whose labels repeat. The library prefixes the
     * id it is given, so this matches on a fragment.
     *
     * @param  string  $id  The id passed to the component.
     *
     * @return string
     */
    public static function field( string $id ): string
    {
        return '[id*="' . $id . '"]:is(input, select, textarea)';
    }

    /**
     * The button or link with the accessible name `$name` (exact match).
     *
     * @param  string  $name  The accessible name.
     * @param  string  $role  The ARIA role.
     *
     * @return string
     */
    public static function role( string $name, string $role = 'button' ): string
    {
        return 'internal:role=' . $role . '[name=' . json_encode( $name ) . 's]';
    }
}
