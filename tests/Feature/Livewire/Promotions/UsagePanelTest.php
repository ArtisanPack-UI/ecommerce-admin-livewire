<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\Ecommerce\Models\PromotionUsage;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\UsagePanel;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'promotion.viewAny', 'promotion.view', 'order.view', 'customer.view' ] );
    $this->actingAs( makeUser() );
} );

it( 'lists each order the promotion discounted with totals per currency', function (): void {
    $promotion = Promotion::factory()->create();
    $customer  = Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Lovelace' ] );
    $order     = Order::factory()->create( [ 'order_number' => 'BF000001' ] );

    PromotionUsage::factory()->create( [ 'promotion_id' => $promotion->id, 'order_id' => $order->id, 'customer_id' => $customer->id, 'amount_discounted' => 1500, 'currency' => 'USD' ] );
    PromotionUsage::factory()->create( [ 'promotion_id' => $promotion->id, 'amount_discounted' => 500, 'currency' => 'USD' ] );
    PromotionUsage::factory()->create( [ 'promotion_id' => $promotion->id, 'amount_discounted' => 900, 'currency' => 'EUR' ] );
    PromotionUsage::factory()->create( [ 'amount_discounted' => 99999 ] );

    Livewire::test( UsagePanel::class, [ 'promotion' => $promotion ] )
        ->assertOk()
        ->assertSee( [ '#BF000001', 'Ada Lovelace', '$15.00', 'Guest' ] )
        ->assertSee( [ 'Discounted on 2 orders in USD', '$20.00', 'Discounted on 1 order in EUR' ] )
        ->assertDontSee( '$999.99' )
        ->assertSeeHtml( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => $order->id ] ) )
        ->assertSeeHtml( route( 'artisanpack.ecommerce.admin.customers.show', [ 'customer' => $customer->id ] ) );
} );

it( 'does not link orders or name customers the user cannot open', function (): void {
    Gate::define( 'ecommerce.order.view', static fn (): bool => false );
    Gate::define( 'ecommerce.customer.view', static fn (): bool => false );

    $promotion = Promotion::factory()->create();
    $usage     = PromotionUsage::factory()->create( [ 'promotion_id' => $promotion->id, 'customer_id' => Customer::factory()->create( [ 'first_name' => 'Secret', 'email' => 'secret@example.test' ] )->id ] );

    Livewire::test( UsagePanel::class, [ 'promotion' => $promotion ] )
        ->assertDontSee( [ 'Secret', 'secret@example.test' ] )
        ->assertDontSeeHtml( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => $usage->order_id ] ) )
        ->assertDontSeeHtml( route( 'artisanpack.ecommerce.admin.customers.show', [ 'customer' => $usage->customer_id ] ) );
} );

it( 'builds the usage panel from design-system parts with no DaisyUI classes', function (): void {
    $promotion = Promotion::factory()->create();
    $order     = Order::factory()->create( [ 'order_number' => 'BF000002' ] );

    PromotionUsage::factory()->create( [ 'promotion_id' => $promotion->id, 'order_id' => $order->id, 'amount_discounted' => 3720, 'currency' => 'USD' ] );

    $html = Livewire::test( UsagePanel::class, [ 'promotion' => $promotion ] )->html();

    expect( $html )
        ->toContain( 'class="apui-stat ec-usage-total' )
        ->toContain( 'data-size="sm"' )
        ->toContain( 'Total discounted' )
        ->toContain( 'class="apui-table ec-table"' )
        ->toContain( 'data-part="head-cell"' )
        ->toContain( '<bdi dir="ltr" class="ec-mono">#BF000002</bdi>' )
        ->not->toContain( 'class="table' )
        ->not->toContain( 'rounded-box' )
        ->not->toContain( 'opacity-75' )
        ->not->toContain( 'link-hover' );

    // A discount keeps its minus sign but isn't coloured as an error.
    expect( Illuminate\Support\Str::betweenFirst( $html, 'data-usage-orders', '</table>' ) )
        ->toContain( "\u{2212}\$37.20" )
        ->not->toContain( 'data-tone="negative"' );
} );

it( 'labels deleted orders and guests plainly, without a link', function (): void {
    $promotion = Promotion::factory()->create();
    $usage     = PromotionUsage::factory()->create( [ 'promotion_id' => $promotion->id, 'customer_id' => null ] );

    // The foreign key cascades, so a usage row only outlives its order when the order is removed behind
    // it; defer the check (the test's transaction never commits) and point the row at a missing order.
    Illuminate\Support\Facades\DB::statement( 'PRAGMA defer_foreign_keys = ON' );
    Illuminate\Support\Facades\DB::table( ( new PromotionUsage() )->getTable() )->where( 'id', $usage->id )->update( [ 'order_id' => 999999 ] );

    $html = Livewire::test( UsagePanel::class, [ 'promotion' => $promotion ] )->html();

    expect( Illuminate\Support\Str::betweenFirst( $html, 'data-deleted-order', '</td>' ) )->toContain( 'Deleted order' )->not->toContain( '<a ' )
        ->and( $html )->toContain( 'class="ec-cell-muted ec-deleted"' )
        ->and( Illuminate\Support\Str::betweenFirst( $html, 'data-guest', '</td>' ) )->toContain( 'Guest' );
} );

it( 'shows the empty state', function (): void {
    Livewire::test( UsagePanel::class, [ 'promotion' => Promotion::factory()->create() ] )
        ->assertSee( [ 'Not used yet', 'Orders that this promotion discounts appear here.' ] )
        ->assertSeeHtml( 'ec-empty-sunken' )
        ->assertDontSeeHtml( 'data-usage-totals' );
} );

it( 'is denied without promotion.view', function (): void {
    Gate::define( 'ecommerce.promotion.view', static fn (): bool => false );

    Livewire::test( UsagePanel::class, [ 'promotion' => Promotion::factory()->create() ] )->assertForbidden();
} );
