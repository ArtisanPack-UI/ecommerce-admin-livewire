<?php

declare( strict_types=1 );

namespace Tests\Concerns;

/**
 * Boots the package with `admin.routes_enabled` off.
 */
trait DisablesAdminRoutes
{
    /**
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     */
    protected function defineEnvironment( $app ): void
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'artisanpack.ecommerce-admin-livewire.admin.routes_enabled', false );
    }
}
