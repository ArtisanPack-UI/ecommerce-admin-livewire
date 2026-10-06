<?php

/**
 * A conditions-only rule builder, the way kanban routing rules use it.
 */

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithConfigForms;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithPickers;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithRuleBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class RoutingRulesHost extends Component
{
    use AuthorizesEcommerce;
    use WithConfigForms;
    use WithPickers;
    use WithRuleBuilder;

    public ?array $saved = null;

    public function mount( array $stored = [] ): void
    {
        $this->authorizeEcommerceAbility( 'kanbanBoard.update' );

        $this->ruleBuilderState( [ 'conditions' => $stored ] );
    }

    public function hydrate(): void
    {
        $this->authorizeEcommerceAbility( 'kanbanBoard.update' );
    }

    public function save(): void
    {
        $this->authorizeRuleBuilder();

        $this->saved = $this->validateRules()['conditions'];
    }

    public function render(): View
    {
        return view()->file( __DIR__ . '/../views/rule-builder-host.blade.php', [ 'ruleBuilder' => $this->ruleBuilderViewData() ] );
    }

    protected function ruleBuilderLists(): array
    {
        return [
            'conditions' => [
                'registry' => 'promotion-condition',
                'label'    => 'Routing rules',
                'add'      => 'Add a rule',
                'empty'    => 'No rules: this board catches every order.',
            ],
        ];
    }

    protected function authorizeRuleBuilder(): void
    {
        $this->authorizeEcommerceAbility( 'kanbanBoard.update' );
    }
}
