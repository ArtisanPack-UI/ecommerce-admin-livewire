<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithConfigForms;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithPickers;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A stand-in rule-builder row that edits one registry entry's config.
 */
class ConfigFormHost extends Component
{
    use AuthorizesEcommerce;
    use WithConfigForms;
    use WithPickers;

    #[Locked]
    public string $registry = '';

    #[Locked]
    public string $entry = '';

    /** @var array<string, mixed>|string */
    public array|string $config = [];

    /** @var array<string, mixed>|null */
    public ?array $saved = null;

    /**
     * @param  array<string, mixed>  $stored  The config already stored.
     */
    public function mount( string $registry, string $entry, array $stored = [] ): void
    {
        $this->registry = $registry;
        $this->entry    = $entry;
        $this->config   = $this->configFormState( $registry, $entry, $stored );
    }

    public function save(): void
    {
        $this->authorizeAdminAccess();

        $this->saved = $this->validateConfigForm( $this->registry, $this->entry, 'config' );
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-artisanpack-ec-config-form :registry="$registry" :entry="$entry" model="config" />
            </div>
            BLADE;
    }
}
