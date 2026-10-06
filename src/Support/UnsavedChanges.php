<?php

/**
 * The unsaved-changes warning shared by the admin's edit forms.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use Illuminate\Support\Js;

/**
 * Warns before leaving a form with unsaved changes, by closing the tab or
 * by a `wire:navigate` link.
 *
 * The form's root element merges {@see self::members()} into its `x-data`
 * object and carries {@see self::attributes()}; the component dispatches
 * {@see self::SAVED_EVENT} after a successful save:
 *
 * ```blade
 * <div x-data="{ {!! UnsavedChanges::members() !!} }" {!! UnsavedChanges::attributes() !!}>
 * ```
 *
 * Any `input` or `change` event inside the root marks the form dirty.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class UnsavedChanges
{
    /**
     * The browser event a form dispatches once its changes are saved.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SAVED_EVENT = 'ecommerce-admin-form-saved';

    /**
     * The Alpine state and methods, as members of an object literal (each
     * followed by a comma).
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function members(): string
    {
        $message = Js::from( __( 'You have unsaved changes. Leave without saving?' ) )->toHtml();

        return <<<JS
            dirty: false,
            message: {$message},
            warnBeforeUnload( event ) {
                if ( this.dirty ) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            },
            confirmNavigate( event ) {
                if ( this.dirty && ! window.confirm( this.message ) ) {
                    event.preventDefault();
                }
            },
            JS;
    }

    /**
     * The root element's listeners.
     *
     * @since 1.0.0
     *
     * @param  string  $savedEvent  The event that clears the warning.
     *
     * @return string
     */
    public static function attributes( string $savedEvent = self::SAVED_EVENT ): string
    {
        return 'data-unsaved-changes'
            . ' x-on:input="dirty = true"'
            . ' x-on:change="dirty = true"'
            . ' x-on:beforeunload.window="warnBeforeUnload( $event )"'
            . ' x-on:livewire:navigate.document="confirmNavigate( $event )"'
            . ' x-on:' . e( $savedEvent ) . '.window="dirty = false"';
    }
}
