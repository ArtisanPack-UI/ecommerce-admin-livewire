<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use Livewire\Component;

/**
 * A stand-in screen that shows whichever toast a test asks for.
 */
class ToastScreen extends Component
{
    use SendsToasts;

    public function notify( string $type, string $title, ?string $description = null ): void
    {
        match ( $type ) {
            'warning' => $this->toastWarning( $title, $description ),
            'error'   => $this->toastError( $title, $description ),
            default   => $this->toastSuccess( $title, $description ),
        };
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
