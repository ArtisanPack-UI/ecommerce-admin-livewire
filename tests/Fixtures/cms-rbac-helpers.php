<?php

/**
 * Stand-ins for cms-framework's RBAC helpers, which record every call.
 */

declare( strict_types=1 );

if ( ! function_exists( 'ap_register_permission' ) ) {
    $GLOBALS['fakeRbac'] = [ 'roles' => [], 'roleCalls' => [], 'permissions' => [], 'grants' => [] ];

    function ap_register_role( string $slug, string $name ): string
    {
        $GLOBALS['fakeRbac']['roles'][ $slug ] = $name;
        $GLOBALS['fakeRbac']['roleCalls'][]    = $slug;

        return $slug;
    }

    function ap_register_permission( string $slug, string $name ): string
    {
        $GLOBALS['fakeRbac']['permissions'][ $slug ] = $name;

        return $slug;
    }

    function ap_add_permission_to_role( string $roleSlug, string $permissionSlug ): void
    {
        $GLOBALS['fakeRbac']['grants'][ $roleSlug ][] = $permissionSlug;
    }
}
