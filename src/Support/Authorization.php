<?php

/**
 * Ability checks against the engine.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Auth\EcommerceAuthorizer;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

/**
 * Checks `{resource}.{action}` abilities through the engine's authorizer.
 *
 * The engine's policies delegate to `EcommerceAuthorizer`, which checks the
 * `ecommerce.{resource}.{action}` Gate ability, falls back to the umbrella
 * `ecommerce.admin` gate, then applies the
 * `ap.ecommerce.abilities.{resource}.{action}` filter. Default is deny.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class Authorization
{
    /**
     * Abilities the engine does not define yet, mapped to the one that
     * stands in for them until it does (spec §6, engine issue #148).
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public const INTERIM_ABILITIES = [
        'inventory.viewAny' => 'product.viewAny',
        'inventory.adjust'  => 'product.update',
    ];

    /**
     * Whether the user holds the ability.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user     The user to check.
     * @param  string                $ability  A `{resource}.{action}` ability, e.g. `product.viewAny`.
     * @param  mixed                 $subject  The model the ability applies to, when there is one.
     *
     * @return bool
     */
    public static function allows( ?Authenticatable $user, string $ability, mixed $subject = null ): bool
    {
        [ $resource, $action ] = self::split( self::INTERIM_ABILITIES[ $ability ] ?? $ability );

        return app( EcommerceAuthorizer::class )->allows( $user, $resource, $action, $subject );
    }

    /**
     * Splits a `{resource}.{action}` ability.
     *
     * @since 1.0.0
     *
     * @param  string  $ability  The ability.
     *
     * @throws InvalidArgumentException When the ability is not `{resource}.{action}`.
     *
     * @return array{0: string, 1: string}
     */
    public static function split( string $ability ): array
    {
        $parts = explode( '.', $ability );

        if ( 2 !== count( $parts ) || '' === $parts[0] || '' === $parts[1] ) {
            throw new InvalidArgumentException( sprintf( 'Ability "%s" must be "{resource}.{action}".', $ability ) );
        }

        return [ $parts[0], $parts[1] ];
    }
}
