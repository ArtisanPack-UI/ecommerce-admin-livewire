<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\NotificationTemplate;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\Ecommerce\Models\WebhookSubscription;
use Tests\Browser\Support\Accessibility;

/*
 * axe on every admin screen (#61), against the seeded demo store. Known
 * component-library failures are listed in Accessibility::LIBRARY_ISSUES.
 */

it( 'has no accessibility violations', function ( string $route, Closure $parameters ): void {
    Accessibility::assertAccessible(
        visit( route( 'artisanpack.ecommerce.admin.' . $route, $parameters() ) )->assertNoJavaScriptErrors(),
    );
} )->with( [
    'dashboard'            => [ 'dashboard', fn (): array => [] ],
    'orders'               => [ 'orders.index', fn (): array => [] ],
    'order'                => [ 'orders.show', fn (): array => [ 'order' => Order::query()->value( 'id' ) ] ],
    'reviews'              => [ 'reviews.index', fn (): array => [] ],
    'products'             => [ 'products.index', fn (): array => [] ],
    'new product'          => [ 'products.create', fn (): array => [] ],
    'edit product'         => [ 'products.edit', fn (): array => [ 'product' => Product::query()->value( 'id' ) ] ],
    'product import'       => [ 'products.import', fn (): array => [] ],
    'categories'           => [ 'categories.index', fn (): array => [] ],
    'tags'                 => [ 'tags.index', fn (): array => [] ],
    'inventory'            => [ 'inventory.index', fn (): array => [] ],
    'digital files'        => [ 'digital-files.index', fn (): array => [] ],
    'license keys'         => [ 'license-keys.index', fn (): array => [] ],
    'customers'            => [ 'customers.index', fn (): array => [] ],
    'customer'             => [ 'customers.show', fn (): array => [ 'customer' => Customer::query()->value( 'id' ) ] ],
    'promotions'           => [ 'promotions.index', fn (): array => [] ],
    'new promotion'        => [ 'promotions.create', fn (): array => [] ],
    'edit promotion'       => [ 'promotions.edit', fn (): array => [ 'promotion' => Promotion::query()->value( 'id' ) ] ],
    'shipping'             => [ 'shipping.index', fn (): array => [] ],
    'tax'                  => [ 'tax.index', fn (): array => [] ],
    'notifications'        => [ 'notifications.index', fn (): array => [] ],
    'edit notification'    => [ 'notifications.edit', fn (): array => [ 'template' => NotificationTemplate::query()->value( 'id' ) ] ],
    'webhooks'             => [ 'webhooks.index', fn (): array => [] ],
    'webhook'              => [ 'webhooks.show', fn (): array => [ 'subscription' => WebhookSubscription::query()->value( 'id' ) ?? WebhookSubscription::factory()->create()->id ] ],
    'order statuses'       => [ 'order-statuses.index', fn (): array => [] ],
    'kanban boards'        => [ 'kanban-boards.index', fn (): array => [] ],
    'edit kanban board'    => [ 'kanban-boards.edit', fn (): array => [ 'board' => KanbanBoard::query()->value( 'id' ) ] ],
    'sales report'         => [ 'reports.show', fn (): array => [ 'report' => 'sales' ] ],
    'inventory report'     => [ 'reports.show', fn (): array => [ 'report' => 'inventory' ] ],
    'settings'             => [ 'settings.show', fn (): array => [ 'group' => 'general' ] ],
] );
