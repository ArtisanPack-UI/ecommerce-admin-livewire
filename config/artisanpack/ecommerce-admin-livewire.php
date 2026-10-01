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
    | add the host's 2FA / `verified` middleware here. Set `routes_enabled` to
    | false to register the routes yourself. `auto_register_cms_nav` injects
    | the admin's navigation into the cms-framework admin menu when present.
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
    */

    'tables' => [
        'per_page'        => 25,
        'per_page_values' => [ 10, 25, 50, 100 ],
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
