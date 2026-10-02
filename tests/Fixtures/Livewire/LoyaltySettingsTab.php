<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use Livewire\Component;

/**
 * A satellite's settings tab, registered through SettingsTabRegistry.
 */
class LoyaltySettingsTab extends Component
{
    public function render(): string
    {
        return '<div data-loyalty-settings>Loyalty points settings</div>';
    }
}
