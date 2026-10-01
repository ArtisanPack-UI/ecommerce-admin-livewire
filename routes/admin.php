<?php

/**
 * Admin routes.
 *
 * Every route is a controller action returning a page view, so the routes
 * survive `route:cache`. Paths sit under `admin.route_prefix`, names under
 * `artisanpack.ecommerce.admin.`, and every route runs `admin.middleware`
 * followed by the `ecommerce-admin.access` check.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Http\Controllers\AdminScreenController;
use ArtisanPackUI\EcommerceAdminLivewire\Http\Middleware\EnsureAdminAccess;
use Illuminate\Support\Facades\Route;

Route::prefix( trim( (string) config( 'artisanpack.ecommerce-admin-livewire.admin.route_prefix', 'ecommerce-admin' ), '/' ) )
    ->middleware( [ ...(array) config( 'artisanpack.ecommerce-admin-livewire.admin.middleware', [ 'web', 'auth' ] ), EnsureAdminAccess::ALIAS ] )
    ->name( 'artisanpack.ecommerce.admin.' )
    ->group( static function (): void {
        Route::get( '/', [ AdminScreenController::class, 'dashboard' ] )->name( 'dashboard' );
    } );
