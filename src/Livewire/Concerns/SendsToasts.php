<?php

/**
 * Toast concern for admin components.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns;

/**
 * Shows `<x-artisanpack-toast>` notices from an admin component.
 *
 * livewire-ui-components ships a `Toast` trait, but its methods are public,
 * which makes each of them a Livewire action any client could call. These
 * helpers are protected, so they stay server-side.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
trait SendsToasts
{
    /**
     * Shows a success toast.
     *
     * @since 1.0.0
     *
     * @param  string       $title        The title.
     * @param  string|null  $description  The description.
     *
     * @return void
     */
    protected function toastSuccess( string $title, ?string $description = null ): void
    {
        $this->sendToast( 'success', $title, $description, 'alert-success' );
    }

    /**
     * Shows a warning toast.
     *
     * @since 1.0.0
     *
     * @param  string       $title        The title.
     * @param  string|null  $description  The description.
     *
     * @return void
     */
    protected function toastWarning( string $title, ?string $description = null ): void
    {
        $this->sendToast( 'warning', $title, $description, 'alert-warning' );
    }

    /**
     * Shows an error toast.
     *
     * @since 1.0.0
     *
     * @param  string       $title        The title.
     * @param  string|null  $description  The description.
     *
     * @return void
     */
    protected function toastError( string $title, ?string $description = null ): void
    {
        $this->sendToast( 'error', $title, $description, 'alert-error' );
    }

    /**
     * Dispatches the toast to the browser.
     *
     * The payload goes through `json_encode` with the HEX flags, so titles
     * that contain quotes or markup cannot break out of the script.
     *
     * @since 1.0.0
     *
     * @param  string       $type         The toast type.
     * @param  string       $title        The title.
     * @param  string|null  $description  The description.
     * @param  string       $css          The alert class.
     *
     * @return void
     */
    private function sendToast( string $type, string $title, ?string $description, string $css ): void
    {
        $payload = json_encode(
            [
                'toast' => [
                    'type'        => $type,
                    'title'       => $title,
                    'description' => $description,
                    'icon'        => '',
                    'css'         => $css,
                ],
            ],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );

        $this->js( 'if ( typeof toast === "function" ) { toast( ' . $payload . ' ); }' );
    }
}
