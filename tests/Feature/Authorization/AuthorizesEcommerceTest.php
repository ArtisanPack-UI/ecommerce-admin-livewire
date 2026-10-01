<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Order;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\OrderScreen;

it( 'refuses to mount without the screen ability', function (): void {
    Livewire::actingAs( makeUser() )
        ->test( OrderScreen::class )
        ->assertForbidden();
} );

it( 'lets a user with only order.viewAny list orders but refuses every mutation', function (): void {
    grantAbilities( [ 'order.viewAny' ] );
    $order = Order::factory()->create();

    $user  = makeUser();
    $mount = static fn () => Livewire::actingAs( $user )->test( OrderScreen::class )->assertOk();

    $mount()->call( 'refund', $order->getKey() )->assertForbidden();
    $mount()->call( 'editFulfilled', $order->getKey() )->assertForbidden();
    $mount()->call( 'viewReport' )->assertForbidden();
} );

it( 'allows the action once its ability is held', function (): void {
    grantAbilities( [ 'order.viewAny', 'order.refund', 'order.edit-fulfilled', 'report.view' ] );
    $order = Order::factory()->create();

    Livewire::actingAs( makeUser() )
        ->test( OrderScreen::class )
        ->call( 'refund', $order->getKey() )->assertSet( 'message', 'refunded' )
        ->call( 'editFulfilled', $order->getKey() )->assertSet( 'message', 'edited' )
        ->call( 'viewReport' )->assertSet( 'message', 'report' );
} );

it( 'grants everything through the umbrella ecommerce.admin gate', function (): void {
    $user = makeUser();
    grantAdmin( $user );
    $order = Order::factory()->create();

    Livewire::actingAs( $user )
        ->test( OrderScreen::class )
        ->call( 'refund', $order->getKey() )
        ->assertSet( 'message', 'refunded' );
} );

it( 'rejects a forged refund call from a user who lost the ability', function (): void {
    grantAbilities( [ 'order.viewAny', 'order.refund' ] );
    $order = Order::factory()->create();

    $component = Livewire::actingAs( makeUser() )->test( OrderScreen::class );

    Gate::define( 'ecommerce.order.refund', static fn (): bool => false );

    $component->call( 'refund', $order->getKey() )
        ->assertForbidden()
        ->assertSet( 'message', '' );
} );

it( 'respects the engine ability filter', function (): void {
    grantAbilities( [ 'order.viewAny' ] );
    $order = Order::factory()->create();

    addFilter( 'ap.ecommerce.abilities.order.refund', static fn (): bool => true );

    Livewire::actingAs( makeUser() )
        ->test( OrderScreen::class )
        ->call( 'refund', $order->getKey() )
        ->assertSet( 'message', 'refunded' );

    removeAllFilters( 'ap.ecommerce.abilities.order.refund' );
} );
