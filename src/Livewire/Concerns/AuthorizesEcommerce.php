<?php

/**
 * Authorization concern for admin components.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Enforces the engine's abilities inside admin Livewire components.
 *
 * The admin calls engine services in-process, so it skips the REST layer's
 * `ecommerce.can` middleware and must authorize itself: `mount()` authorizes
 * the screen and every action authorizes again, because a Livewire action can
 * be called directly with a forged request.
 *
 * Every denial ends the same way: a 403 with a translated message, whether it
 * came from `mount()` or an action.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
trait AuthorizesEcommerce
{
    /**
     * Authorizes a policy ability on a model or model class.
     *
     * Goes through the engine's policies (e.g. `ProductPolicy::update`, which
     * also refuses products whose type is missing).
     *
     * @since 1.0.0
     *
     * @param  string         $ability  The policy ability, e.g. `viewAny`, `update`, `edit-fulfilled`.
     * @param  object|string  $subject  The model, or its class for `viewAny` / `create`.
     *
     * @return void
     */
    protected function authorizeEcommerce( string $ability, object|string $subject ): void
    {
        try {
            Gate::forUser( auth()->user() )->authorize( $ability, $subject );
        } catch ( AuthorizationException ) {
            $this->denyEcommerce();
        }
    }

    /**
     * Authorizes a `{resource}.{action}` ability that has no model or policy,
     * such as `report.view` or `settings.update`.
     *
     * @since 1.0.0
     *
     * @param  string  $ability  The ability.
     * @param  mixed   $subject  The subject, when there is one.
     *
     * @return void
     */
    protected function authorizeEcommerceAbility( string $ability, mixed $subject = null ): void
    {
        if ( ! Authorization::allows( auth()->user(), $ability, $subject ) ) {
            $this->denyEcommerce();
        }
    }

    /**
     * Authorizes entry to the admin as a whole (at least one visible screen).
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeAdminAccess(): void
    {
        if ( ! AdminNav::canAccess( auth()->user() ) ) {
            $this->denyEcommerce();
        }
    }

    /**
     * Whether the user holds a policy ability, for showing or hiding controls.
     *
     * @since 1.0.0
     *
     * @param  string         $ability  The policy ability.
     * @param  object|string  $subject  The model or model class.
     *
     * @return bool
     */
    protected function canEcommerce( string $ability, object|string $subject ): bool
    {
        return Gate::forUser( auth()->user() )->allows( $ability, $subject );
    }

    /**
     * Whether a product must render read-only.
     *
     * True when its type's satellite is not installed (plan §16.6) or the user
     * may not update it.
     *
     * @since 1.0.0
     *
     * @param  Product  $product  The product.
     *
     * @return bool
     */
    protected function productIsReadOnly( Product $product ): bool
    {
        return $product->typeIsMissing() || ! $this->canEcommerce( 'update', $product );
    }

    /**
     * Ends the request with a 403.
     *
     * @since 1.0.0
     *
     * @return never
     */
    protected function denyEcommerce(): never
    {
        abort( 403, __( 'You are not allowed to do that.' ) );
    }
}
