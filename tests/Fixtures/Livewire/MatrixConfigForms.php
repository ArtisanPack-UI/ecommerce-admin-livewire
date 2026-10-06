<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithConfigForms;
use Livewire\Component;

/**
 * Brings the WithConfigForms actions into the authorization matrix.
 */
class MatrixConfigForms extends Component
{
    use AuthorizesEcommerce;
    use WithConfigForms;

    /** @var array<string, mixed> */
    public array $config = [ 'tiers' => [] ];

    public function mount(): void
    {
        $this->authorizeAdminAccess();
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
