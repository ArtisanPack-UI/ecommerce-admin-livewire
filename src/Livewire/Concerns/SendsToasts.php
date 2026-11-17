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
 * Shows design-system toasts (`<x-artisanpack-toaster>`) from an admin component.
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
        $this->sendToast( 'success', $title, $description );
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
        $this->sendToast( 'warning', $title, $description );
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
        $this->sendToast( 'error', $title, $description );
    }

    /**
     * Dispatches the toast to the browser.
     *
     * Sends the design system's `toast` browser event, which the page's
     * `<x-artisanpack-toaster>` shows. The toaster renders the title and
     * description as text, so neither can inject markup.
     *
     * @since 1.0.0
     *
     * @param  string       $color        The toast color: success, warning, or error.
     * @param  string       $title        The title.
     * @param  string|null  $description  The description.
     *
     * @return void
     */
    private function sendToast( string $color, string $title, ?string $description ): void
    {
        $this->dispatch( 'toast', title: $title, description: $description, color: $color );
    }
}
