<?php

/**
 * Admin routes.
 *
 * Every route is a controller action returning a page view, so the routes
 * survive `route:cache`. Paths sit under `admin.route_prefix`, names under
 * `artisanpack.ecommerce.admin.`, and every route runs `admin.middleware`
 * followed by the `ecommerce-admin.access` check and the
 * `ecommerce-admin.throttle` limiter (which only counts Livewire updates).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\Http\Controllers\AdminScreenController;
use ArtisanPackUI\EcommerceAdminLivewire\Http\Middleware\EnsureAdminAccess;
use ArtisanPackUI\EcommerceAdminLivewire\Http\Middleware\ThrottleAdminMutations;
use Illuminate\Support\Facades\Route;

Route::prefix( trim( (string) config( 'artisanpack.ecommerce-admin-livewire.admin.route_prefix', 'ecommerce-admin' ), '/' ) )
    ->middleware( [ ...(array) config( 'artisanpack.ecommerce-admin-livewire.admin.middleware', [ 'web', 'auth' ] ), EnsureAdminAccess::ALIAS, ThrottleAdminMutations::ALIAS ] )
    ->name( 'artisanpack.ecommerce.admin.' )
    ->group( static function (): void {
        Route::get( '/', [ AdminScreenController::class, 'dashboard' ] )->name( 'dashboard' );

        Route::get( 'orders', [ AdminScreenController::class, 'ordersIndex' ] )->name( 'orders.index' );
        Route::get( 'orders/{order}', [ AdminScreenController::class, 'ordersShow' ] )->whereNumber( 'order' )->name( 'orders.show' );

        Route::get( 'reviews', [ AdminScreenController::class, 'reviewsIndex' ] )->name( 'reviews.index' );

        Route::get( 'products', [ AdminScreenController::class, 'productsIndex' ] )->name( 'products.index' );
        Route::get( 'products/create', [ AdminScreenController::class, 'productsCreate' ] )->name( 'products.create' );
        Route::get( 'products/import', [ AdminScreenController::class, 'productsImport' ] )->name( 'products.import' );
        Route::get( 'products/{product}/edit', [ AdminScreenController::class, 'productsEdit' ] )->whereNumber( 'product' )->name( 'products.edit' );

        Route::get( 'categories', [ AdminScreenController::class, 'categoriesIndex' ] )->name( 'categories.index' );
        Route::get( 'tags', [ AdminScreenController::class, 'tagsIndex' ] )->name( 'tags.index' );
        Route::get( 'inventory', [ AdminScreenController::class, 'inventoryIndex' ] )->name( 'inventory.index' );
        Route::get( 'digital-files', [ AdminScreenController::class, 'digitalFilesIndex' ] )->name( 'digital-files.index' );
        Route::get( 'license-keys', [ AdminScreenController::class, 'licenseKeysIndex' ] )->name( 'license-keys.index' );

        Route::get( 'customers', [ AdminScreenController::class, 'customersIndex' ] )->name( 'customers.index' );
        Route::get( 'customers/{customer}', [ AdminScreenController::class, 'customersShow' ] )->whereNumber( 'customer' )->name( 'customers.show' );

        Route::get( 'promotions', [ AdminScreenController::class, 'promotionsIndex' ] )->name( 'promotions.index' );
        Route::get( 'promotions/create', [ AdminScreenController::class, 'promotionsCreate' ] )->name( 'promotions.create' );
        Route::get( 'promotions/{promotion}/edit', [ AdminScreenController::class, 'promotionsEdit' ] )->whereNumber( 'promotion' )->name( 'promotions.edit' );

        Route::get( 'shipping', [ AdminScreenController::class, 'shippingIndex' ] )->name( 'shipping.index' );
        Route::get( 'tax', [ AdminScreenController::class, 'taxIndex' ] )->name( 'tax.index' );

        Route::get( 'notifications', [ AdminScreenController::class, 'notificationsIndex' ] )->name( 'notifications.index' );
        Route::get( 'notifications/{template}/edit', [ AdminScreenController::class, 'notificationsEdit' ] )->whereNumber( 'template' )->name( 'notifications.edit' );

        Route::get( 'webhooks', [ AdminScreenController::class, 'webhooksIndex' ] )->name( 'webhooks.index' );
        Route::get( 'webhooks/{subscription}', [ AdminScreenController::class, 'webhooksShow' ] )->whereNumber( 'subscription' )->name( 'webhooks.show' );

        Route::get( 'order-statuses', [ AdminScreenController::class, 'orderStatusesIndex' ] )->name( 'order-statuses.index' );

        Route::get( 'kanban-boards', [ AdminScreenController::class, 'kanbanBoardsIndex' ] )->name( 'kanban-boards.index' );
        Route::get( 'kanban-boards/{board}/edit', [ AdminScreenController::class, 'kanbanBoardsEdit' ] )->whereNumber( 'board' )->name( 'kanban-boards.edit' );
        Route::get( 'reports/{report}', [ AdminScreenController::class, 'reportsShow' ] )->where( 'report', '[a-z0-9_-]+' )->name( 'reports.show' );
        Route::get( 'settings/{group}', [ AdminScreenController::class, 'settingsShow' ] )->where( 'group', '[a-z0-9_-]+' )->name( 'settings.show' );
    } );
