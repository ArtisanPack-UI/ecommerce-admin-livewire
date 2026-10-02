<?php

/**
 * Rule builder component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * `<x-artisanpack-ec-rule-builder>`: the lists of a component that uses
 * `WithRuleBuilder`, each with its rows, its "add" picker, and the
 * plain-language summary (spec §7.5, §8.4).
 *
 * ```blade
 * <x-artisanpack-ec-rule-builder :builder="$ruleBuilder" />
 * ```
 *
 * `$builder` is what `WithRuleBuilder::ruleBuilderViewData()` returns.
 * Rows reorder by drag through `@artisanpack-ui/livewire-drag-and-drop`
 * when the host loads it, and always by the Move up / Move down buttons.
 * Swapping the drag for `livewire-ui-components`' reorderable list (U5) only
 * touches this component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class RuleBuilder extends Component
{
    /**
     * @since 1.0.0
     *
     * @param  array{lists: array<string, array<string, mixed>>, summary: string, openRule: string|null}  $builder  The builder's view data.
     * @param  string                                                                                    $prefix   DOM id prefix, so two builders can share a page.
     */
    public function __construct( public array $builder, public string $prefix = 'rule' )
    {
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        return view( 'ecommerce-admin::components.rule-builder' );
    }
}
