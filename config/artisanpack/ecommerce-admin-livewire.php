<?php

/**
 * Ecommerce admin (Livewire) configuration.
 *
 * Read under the `artisanpack.ecommerce-admin-livewire` key.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

return [

    /*
    |--------------------------------------------------------------------------
    | Admin routes
    |--------------------------------------------------------------------------
    |
    | `middleware` wraps every admin route. The engine does not apply 2FA, so
    | add the host's 2FA / `verified` middleware here. Every entry except a
    | middleware group (such as `web`) is also made Livewire persistent
    | middleware, so it runs again on each Livewire update from an admin page.
    | The command palette searches through its own admin route, so it runs
    | this stack too. Livewire updates from admin pages count toward the
    | engine's `ecommerce.admin.mutate` rate limiter, except updates that only
    | read (polls, picker searches, sorting, and paging). Set `routes_enabled` to false to register the routes
    | yourself. `auto_register_cms_nav` injects the admin's navigation into
    | the cms-framework admin menu when present.
    |
    */

    'admin' => [
        'route_prefix'          => 'ecommerce-admin',
        'middleware'            => [ 'web', 'auth' ],
        'routes_enabled'        => true,
        'auto_register_cms_nav' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | `export_max_rows` caps a single CSV export; the user is told when an
    | export was cut short. `max_selection` caps how many rows can be ticked
    | one by one (0: the largest page size times 50); bigger sets use
    | "select all matching".
    |
    */

    'tables' => [
        'per_page'        => 25,
        'per_page_values' => [ 10, 25, 50, 100 ],
        'export_max_rows' => 10000,
        'max_selection'   => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Command palette
    |--------------------------------------------------------------------------
    |
    | `limit` caps the results returned per entity type.
    |
    */

    'spotlight' => [
        'enabled'  => true,
        'shortcut' => 'meta.k',
        'limit'    => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Real-time updates
    |--------------------------------------------------------------------------
    |
    | When enabled and Laravel Echo is present, index screens subscribe to the
    | `private-ecommerce.admin` channel.
    |
    */

    'realtime' => [
        'enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Imports
    |--------------------------------------------------------------------------
    */

    'imports' => [
        'disk'     => 'local',
        'max_rows' => 5000,
        'queue'    => null,
    ],
];
