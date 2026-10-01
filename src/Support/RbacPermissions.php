<?php

/**
 * cms-framework RBAC permissions.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Exposes the engine's abilities as cms-framework RBAC permissions.
 *
 * - `register()` creates one permission per ability (slug
 *   `ecommerce.{resource}.{action}`) and a `shop-manager` role holding them
 *   all (plan §11.1). It writes to the database, so it runs from the install
 *   and sync commands and after migrations, never on every request.
 * - `grantThroughPermissions()` adds an `ap.ecommerce.abilities.*` filter per
 *   ability that allows a user holding the matching permission. The filter
 *   only ever adds access, and it stays out of the way when the host defines
 *   the `ecommerce.{resource}.{action}` gate itself: that gate already saw the
 *   subject (e.g. an ownership check on one order), so its answer stands.
 *
 * Engine issue #151 may move this wiring into the engine.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class RbacPermissions
{
    /**
     * The role that holds every ecommerce permission.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ROLE = 'shop-manager';

    /**
     * The engine's abilities (engine spec §6.18), plus the ones engine issue
     * #148 adds for inventory, sub-statuses, settings, and reports.
     *
     * @since 1.0.0
     *
     * @var array<string, array<int, string>>
     */
    public const ABILITIES = [
        'product'              => [ 'viewAny', 'view', 'create', 'update', 'delete', 'restore' ],
        'order'                => [ 'viewAny', 'view', 'create', 'update', 'edit-fulfilled', 'cancel', 'refund' ],
        'refund'               => [ 'create', 'view' ],
        'customer'             => [ 'viewAny', 'view', 'update', 'delete' ],
        'promotion'            => [ 'viewAny', 'view', 'create', 'update', 'delete' ],
        'coupon'               => [ 'create', 'update', 'delete' ],
        'taxRate'              => [ 'viewAny', 'create', 'update', 'delete' ],
        'shippingZone'         => [ 'viewAny', 'create', 'update', 'delete' ],
        'kanbanBoard'          => [ 'viewAny', 'view', 'create', 'update', 'delete' ],
        'kanbanCard'           => [ 'move' ],
        'notificationTemplate' => [ 'viewAny', 'view', 'update' ],
        'webhookSubscription'  => [ 'viewAny', 'create', 'update', 'delete' ],
        'digitalFile'          => [ 'viewAny', 'create', 'update', 'delete' ],
        'licenseKey'           => [ 'view', 'revoke' ],
        'review'               => [ 'viewAny', 'view', 'moderate', 'delete' ],
        'inventory'            => [ 'viewAny', 'adjust' ],
        'orderSubstatus'       => [ 'viewAny', 'create', 'update', 'delete' ],
        'settings'             => [ 'view', 'update' ],
        'report'               => [ 'view' ],
    ];

    /**
     * Whether cms-framework and its RBAC helpers are available.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function available(): bool
    {
        return CmsFramework::isInstalled()
            && function_exists( 'ap_register_permission' )
            && function_exists( 'ap_register_role' )
            && function_exists( 'ap_add_permission_to_role' );
    }

    /**
     * Every ability as `{resource}.{action}`.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        $abilities = [];

        foreach ( self::ABILITIES as $resource => $actions ) {
            foreach ( $actions as $action ) {
                $abilities[] = $resource . '.' . $action;
            }
        }

        return $abilities;
    }

    /**
     * The permission slug for an ability.
     *
     * @since 1.0.0
     *
     * @param  string  $ability  A `{resource}.{action}` ability.
     *
     * @return string
     */
    public static function permissionSlug( string $ability ): string
    {
        return 'ecommerce.' . $ability;
    }

    /**
     * Creates the permissions and the `shop-manager` role.
     *
     * Idempotent: the cms-framework helpers use `firstOrCreate`.
     *
     * @since 1.0.0
     *
     * @return int The number of permissions registered.
     */
    public static function register(): int
    {
        if ( ! self::available() ) {
            return 0;
        }

        ap_register_role( self::ROLE, __( 'Shop manager' ) );

        foreach ( self::abilities() as $ability ) {
            [ $resource, $action ] = Authorization::split( $ability );

            ap_register_permission( self::permissionSlug( $ability ), __( 'Ecommerce: :action :resource', [
                'action'   => Str::lower( Str::headline( $action ) ),
                'resource' => Str::lower( Str::headline( $resource ) ),
            ] ) );

            ap_add_permission_to_role( self::ROLE, self::permissionSlug( $ability ) );
        }

        return count( self::abilities() );
    }

    /**
     * Lets RBAC permissions grant the matching engine abilities.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function grantThroughPermissions(): void
    {
        foreach ( self::abilities() as $ability ) {
            [ $resource, $action ] = Authorization::split( $ability );
            $slug                  = self::permissionSlug( $ability );

            addFilter(
                sprintf( 'ap.ecommerce.abilities.%s.%s', $resource, $action ),
                static fn ( mixed $allowed, ?Authenticatable $user = null ): bool => true === $allowed
                    || ( null !== $user && ! Gate::has( $slug ) && Gate::forUser( $user )->allows( $slug ) ),
            );
        }
    }
}
