<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderEdit;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductPrice;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Models\Shipment;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\EditPanel;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'order.viewAny', 'order.view', 'order.update', 'product.viewAny', 'product.view' ] );
    $this->actingAs( makeUser( [ 'name' => 'Ada Admin' ] ) );
    config()->set( 'auth.providers.users.model', Tests\Fixtures\User::class );

    $this->product = Product::factory()->create( [ 'name' => 'Linen Shirt' ] );
    $this->small   = ProductVariant::factory()->create( [ 'product_id' => $this->product->id, 'name' => 'Small', 'sku' => 'SHIRT-S' ] );
    $this->large   = ProductVariant::factory()->create( [ 'product_id' => $this->product->id, 'name' => 'Large', 'sku' => 'SHIRT-L' ] );
    ProductPrice::factory()->forPriceable( $this->large )->create( [ 'currency' => 'USD', 'price_amount' => 1_200 ] );

    $this->order = Order::factory()->create( [
        'subtotal_amount'  => 2_000,
        'total_amount'     => 2_000,
        'shipping_address' => [ 'first_name' => 'Ada', 'address1' => '1 Main St', 'city' => 'Springfield', 'postal_code' => '12345', 'country_code' => 'US' ],
    ] );
    $this->item = OrderItem::factory()->create( [
        'order_id'           => $this->order->id,
        'product_id'         => $this->product->id,
        'product_variant_id' => $this->small->id,
        'product_snapshot'   => [ 'name' => 'Linen Shirt', 'sku' => 'SHIRT-S', 'type' => 'simple', 'options' => [ 'Size' => 'S' ] ],
        'quantity'           => 2,
        'unit_price_amount'  => 1_000,
        'total_amount'       => 2_000,
    ] );
} );

/**
 * The action token on a rendered button calling `$method`.
 */
function editPanelToken( $component, string $method ): string
{
    preg_match( '/' . $method . '\(\s*(?:\d+,\s*)?(?:&quot;|&#039;|\'|")([^&\'"]+)/', $component->html(), $matches );

    return $matches[1] ?? '';
}

it( 'renders the edit history and the edit button', function (): void {
    Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->assertOk()
        ->assertSee( 'This order has not been edited.' )
        ->assertSee( 'Edit order' );
} );

it( 'is denied without order.view', function (): void {
    Gate::define( 'ecommerce.order.view', static fn (): bool => false );

    Livewire::test( EditPanel::class, [ 'order' => $this->order->id ] )->assertForbidden();
} );

it( 'refuses editing without order.update', function (): void {
    Gate::define( 'ecommerce.order.update', static fn (): bool => false );

    Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->assertDontSee( 'Edit order' )
        ->call( 'startEdit' )
        ->assertForbidden();
} );

it( 'drafts a quantity change without touching the order, previews it, and applies it', function (): void {
    $component = Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->call( 'startEdit' )
        ->assertSet( "draftItems.{$this->item->id}.quantity", 2 )
        ->set( "draftItems.{$this->item->id}.quantity", 3 );

    expect( $this->item->fresh()->quantity )->toBe( 2 );

    $component->call( 'previewEdit' )
        ->assertHasNoErrors()
        ->assertSee( 'Linen Shirt: quantity 2 → 3' )
        ->assertSee( 'Total: $20.00 → $30.00' )
        ->assertSee( 'Payment action required: the total rises by $10.00' );

    expect( $this->item->fresh()->quantity )->toBe( 2 )
        ->and( OrderEdit::query()->count() )->toBe( 0 );

    $component->set( 'reason', 'Customer added one' )
        ->call( 'applyEdit', editPanelToken( $component, 'applyEdit' ) )
        ->assertHasNoErrors()
        ->assertSet( 'editing', false )
        ->assertDispatched( OrderPanelRegistry::ORDER_UPDATED_EVENT );

    expect( $this->item->fresh()->quantity )->toBe( 3 )
        ->and( $this->order->fresh()->total_amount )->toBe( 3_000 )
        ->and( OrderEdit::query()->sole()->reason )->toBe( 'Customer added one' );

    Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->assertSee( 'Edit #' )
        ->assertSee( 'Customer added one' )
        ->assertSee( 'Ada Admin' )
        ->assertSee( 'Linen Shirt: quantity 2 → 3' );
} );

it( 'changes a size by replacing the line with the new variant', function (): void {
    $component = Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->call( 'startEdit' )
        ->set( "draftItems.{$this->item->id}.variant_id", $this->large->id )
        ->set( 'reason', 'Wrong size' );

    $component->call( 'applyEdit', editPanelToken( $component, 'applyEdit' ) )->assertHasNoErrors();

    $line = $this->order->fresh()->items()->sole();

    expect( $line->product_variant_id )->toBe( $this->large->id )
        ->and( $line->quantity )->toBe( 2 )
        ->and( $line->unit_price_amount )->toBe( 1_200 )
        ->and( $line->product_snapshot['sku'] )->toBe( 'SHIRT-L' );
} );

it( 'fixes a postal code and keeps the address\'s other fields', function (): void {
    $component = Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->call( 'startEdit' )
        ->set( 'shipping.postal_code', '54321' )
        ->call( 'previewEdit' )
        ->assertSee( 'Shipping address changed.' )
        ->set( 'reason', 'Typo' );

    $component->call( 'applyEdit', editPanelToken( $component, 'applyEdit' ) )->assertHasNoErrors();

    expect( $this->order->fresh()->shipping_address )->toMatchArray( [ 'postal_code' => '54321', 'address1' => '1 Main St', 'city' => 'Springfield' ] );
} );

it( 'adds a product at its current price', function (): void {
    $component = Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->call( 'startEdit' )
        ->set( 'addProductId', $this->product->id )
        ->call( 'addItem' )
        ->assertHasErrors( [ 'addVariantId' ] )
        ->set( 'addVariantId', $this->large->id )
        ->set( 'addQuantity', 1 )
        ->call( 'addItem' )
        ->assertHasNoErrors()
        ->assertCount( 'newItems', 1 )
        ->set( 'reason', 'Add one' );

    $component->call( 'applyEdit', editPanelToken( $component, 'applyEdit' ) )->assertHasNoErrors();

    expect( $this->order->fresh()->items()->count() )->toBe( 2 )
        ->and( $this->order->fresh()->total_amount )->toBe( 3_200 );
} );

it( 'refuses a product with no price in the order currency', function (): void {
    Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->call( 'startEdit' )
        ->set( 'addProductId', $this->product->id )
        ->set( 'addVariantId', $this->small->id )
        ->call( 'addItem' )
        ->assertHasErrors( [ 'addProductId' ] )
        ->assertCount( 'newItems', 0 );
} );

it( 'validates the edit', function ( array $set, string $error ): void {
    $component = Livewire::test( EditPanel::class, [ 'order' => $this->order ] )->call( 'startEdit' );

    foreach ( $set as $key => $value ) {
        $component->set( str_replace( '{item}', (string) $this->item->id, $key ), $value );
    }

    $component->call( 'applyEdit', editPanelToken( $component, 'applyEdit' ) )
        ->assertHasErrors( [ str_replace( '{item}', (string) $this->item->id, $error ) ] );

    expect( OrderEdit::query()->count() )->toBe( 0 );
} )->with( [
    'no reason'        => [ [ 'draftItems.{item}.quantity' => 3 ], 'reason' ],
    'nothing changed'  => [ [ 'reason' => 'x' ], 'edit' ],
    'zero quantity'    => [ [ 'draftItems.{item}.quantity' => 0, 'reason' => 'x' ], 'draftItems.{item}.quantity' ],
    'last item gone'   => [ [ 'draftItems.{item}.remove' => true, 'reason' => 'x' ], 'draftItems' ],
    'bad country'      => [ [ 'shipping.country_code' => 'USA', 'reason' => 'x' ], 'shipping.country_code' ],
] );

it( 'rolls back the latest edit', function (): void {
    $component = Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->call( 'startEdit' )
        ->set( "draftItems.{$this->item->id}.quantity", 5 )
        ->set( 'reason', 'Mistake' );

    $component->call( 'applyEdit', editPanelToken( $component, 'applyEdit' ) );

    $edit     = OrderEdit::query()->sole();
    $history  = Livewire::test( EditPanel::class, [ 'order' => $this->order ] )->assertSee( 'Roll back' );

    $history->call( 'rollbackEdit', $edit->id, editPanelToken( $history, 'rollbackEdit' ) )
        ->assertDispatched( OrderPanelRegistry::ORDER_UPDATED_EVENT );

    expect( $this->item->fresh()->quantity )->toBe( 2 )
        ->and( OrderEdit::query()->count() )->toBe( 2 );
} );

it( 'only rolls back the latest edit', function (): void {
    $older = OrderEdit::factory()->create( [ 'order_id' => $this->order->id ] );
    OrderEdit::factory()->create( [ 'order_id' => $this->order->id ] );

    Livewire::test( EditPanel::class, [ 'order' => $this->order ] )->call( 'rollbackEdit', $older->id, 'token' );

    expect( OrderEdit::query()->count() )->toBe( 2 );
} );

it( 'needs order.edit-fulfilled once fulfillment has started, and then only edits addresses', function (): void {
    $this->order->forceFill( [ 'fulfillment_status' => 'partial' ] )->save();

    Gate::define( 'ecommerce.order.edit-fulfilled', static fn (): bool => false );

    Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->assertDontSee( 'Edit order' )
        ->call( 'startEdit' )
        ->assertForbidden();

    grantAbilities( [ 'order.edit-fulfilled' ] );

    $component = Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->assertSee( 'only the addresses can be edited' )
        ->call( 'startEdit' )
        ->assertSee( 'items and the shipping method are locked' )
        ->set( 'shipping.city', 'Shelbyville' )
        ->set( 'reason', 'Moved' );

    $component->call( 'applyEdit', editPanelToken( $component, 'applyEdit' ) )->assertHasNoErrors();

    expect( $this->order->fresh()->shipping_address['city'] )->toBe( 'Shelbyville' );
} );

it( 'blocks editing once a label has been printed', function (): void {
    Shipment::factory()->create( [ 'order_id' => $this->order->id, 'label_id' => 99 ] );

    Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->assertSee( 'A shipping label has been printed for this order' )
        ->assertDontSee( 'Edit order' )
        ->call( 'startEdit' )
        ->assertHasErrors( [ 'edit' ] )
        ->assertSet( 'editing', false );
} );

it( 'prices added lines on the server, ignoring a tampered price', function (): void {
    $component = Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->call( 'startEdit' )
        ->set( 'addProductId', $this->product->id )
        ->set( 'addVariantId', $this->large->id )
        ->call( 'addItem' )
        ->set( 'newItems.0.unit_price_amount', 1 )
        ->set( 'reason', 'Add one' );

    $component->call( 'applyEdit', editPanelToken( $component, 'applyEdit' ) )->assertHasNoErrors();

    expect( $this->order->fresh()->items()->where( 'product_variant_id', $this->large->id )->sole()->unit_price_amount )->toBe( 1_200 );
} );

it( 'does not let the client write the preview', function (): void {
    Livewire::test( EditPanel::class, [ 'order' => $this->order ] )->set( 'preview', [ 'currency' => 'XXX' ] );
} )->throws( \Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class );

it( 'refuses to add a product that is not active', function (): void {
    $draft = Product::factory()->draft()->create();

    Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->call( 'startEdit' )
        ->set( 'addProductId', $draft->id )
        ->call( 'addItem' )
        ->assertHasErrors( [ 'addProductId' ] );
} );

it( 'needs product.view to add a product', function (): void {
    Gate::define( 'ecommerce.product.view', static fn (): bool => false );

    Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->call( 'startEdit' )
        ->set( 'addProductId', $this->product->id )
        ->set( 'addVariantId', $this->large->id )
        ->call( 'addItem' )
        ->assertForbidden();
} );

it( 'ignores lines from another order and malformed lines', function (): void {
    $foreign = OrderItem::factory()->create( [ 'quantity' => 4 ] );

    $component = Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->call( 'startEdit' )
        ->set( "draftItems.{$foreign->id}", [ 'quantity' => 1, 'remove' => true ] )
        ->set( "draftItems.{$this->item->id}.quantity", 3 )
        ->set( 'reason', 'x' );

    $component->call( 'applyEdit', editPanelToken( $component, 'applyEdit' ) )->assertHasNoErrors();

    expect( $foreign->fresh() )->not->toBeNull()
        ->and( $foreign->fresh()->quantity )->toBe( 4 );
} );

it( 'refuses a shipping method that is not registered', function (): void {
    $component = Livewire::test( EditPanel::class, [ 'order' => $this->order ] )
        ->call( 'startEdit' )
        ->set( 'shippingMethod', 'teleport' )
        ->set( 'reason', 'x' );

    $component->call( 'applyEdit', editPanelToken( $component, 'applyEdit' ) )->assertHasErrors( [ 'shippingMethod' ] );
} );
