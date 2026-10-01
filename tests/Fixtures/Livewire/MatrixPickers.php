<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithPickers;
use Livewire\Component;

/**
 * Brings the WithPickers actions into the authorization matrix.
 */
class MatrixPickers extends Component
{
    use AuthorizesEcommerce;
    use WithPickers;

    public ?int $customerId = null;

    public function mount(): void
    {
        $this->authorizeAdminAccess();
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
