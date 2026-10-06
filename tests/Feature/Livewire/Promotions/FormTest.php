<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Contracts\PromotionCondition as PromotionConditionContract;
use ArtisanPackUI\Ecommerce\Models\Cart;
use ArtisanPackUI\Ecommerce\Models\Coupon;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\Ecommerce\Models\PromotionAction;
use ArtisanPackUI\Ecommerce\Models\PromotionCondition;
use ArtisanPackUI\Ecommerce\Registries\PromotionConditionRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Form;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'promotion.viewAny', 'promotion.view', 'promotion.create', 'promotion.update', 'product.viewAny', 'coupon.create', 'coupon.update', 'coupon.delete' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceAdminLivewire.ruleBuilder.describe' );
} );

/**
 * The row ids of a rule list, in order.
 *
 * @return array<int, string>
 */
function ruleIds( $component, string $list ): array
{
    return array_column( $component->get( 'ruleRows.' . $list ), 'id' );
}

describe( 'details', function (): void {
    it( 'renders the create form with the stacking explanation', function (): void {
        Livewire::test( Form::class )
            ->assertOk()
            ->assertSee( [ 'New promotion', 'Stacking and priority', 'Lower numbers apply first.' ] )
            ->assertSeeHtml( 'data-rule-builder' );
    } );

    it( 'is denied without promotion.create', function (): void {
        Gate::define( 'ecommerce.promotion.create', static fn (): bool => false );

        Livewire::test( Form::class )->assertForbidden();
    } );

    it( 'is denied an existing promotion without promotion.view', function (): void {
        Gate::define( 'ecommerce.promotion.view', static fn (): bool => false );

        Livewire::test( Form::class, [ 'promotion' => Promotion::factory()->create()->id ] )->assertForbidden();
    } );

    it( 'fills the key from the name until the key is typed', function (): void {
        Livewire::test( Form::class )
            ->set( 'name', 'Black Friday 2026' )
            ->assertSet( 'key', 'black-friday-2026' )
            ->set( 'key', 'bf' )
            ->set( 'name', 'Something else' )
            ->assertSet( 'key', 'bf' );
    } );

    it( 'creates a promotion with its rules and goes to its edit page', function (): void {
        $component = Livewire::test( Form::class )
            ->set( 'name', 'Black Friday' )
            ->set( 'description', 'Doorbusters' )
            ->set( 'priority', 5 )
            ->set( 'isExclusive', true )
            ->set( 'startsAt', '2026-11-27T00:00' )
            ->set( 'endsAt', '2026-11-30T23:59' )
            ->set( 'usageLimitTotal', '500' )
            ->set( 'usageLimitPerCustomer', '1' )
            ->set( 'ruleToAdd.conditions', 'min-subtotal' )->call( 'addRule', 'conditions' )
            ->set( 'ruleRows.conditions.0.config.amount', 5000 )
            ->set( 'ruleToAdd.actions', 'percent-off-cart' )->call( 'addRule', 'actions' )
            ->set( 'ruleRows.actions.0.config.percent', '10' )
            ->call( 'save' )
            ->assertHasNoErrors();

        $promotion = Promotion::query()->where( 'key', 'black-friday' )->sole();

        $component->assertRedirect( route( 'artisanpack.ecommerce.admin.promotions.edit', [ 'promotion' => $promotion->id ] ) );

        expect( $promotion )
            ->name->toBe( 'Black Friday' )
            ->description->toBe( 'Doorbusters' )
            ->priority->toBe( 5 )
            ->is_exclusive->toBeTrue()
            ->usage_limit_total->toBe( 500 )
            ->usage_limit_per_customer->toBe( 1 )
            ->and( $promotion->starts_at->format( 'Y-m-d H:i' ) )->toBe( '2026-11-27 00:00' )
            ->and( $promotion->conditions->map->only( [ 'type', 'config' ] )->all() )->toBe( [ [ 'type' => 'min-subtotal', 'config' => [ 'amount' => 5000 ] ] ] )
            ->and( $promotion->actions->map->only( [ 'type', 'config' ] )->all() )->toBe( [ [ 'type' => 'percent-off-cart', 'config' => [ 'percent' => 10 ] ] ] );
    } );

    it( 'validates the details', function (): void {
        Promotion::factory()->create( [ 'key' => 'taken' ] );

        Livewire::test( Form::class )
            ->set( 'name', '' )
            ->set( 'key', 'taken' )
            ->set( 'priority', 'high' )
            ->set( 'startsAt', '2026-12-01T00:00' )
            ->set( 'endsAt', '2026-11-01T00:00' )
            ->set( 'usageLimitTotal', '0' )
            ->call( 'save' )
            ->assertHasErrors( [ 'name' => 'required', 'key' => 'unique', 'priority' => 'integer', 'endsAt' => 'after', 'usageLimitTotal' => 'min' ] )
            ->assertSet( 'tab', 'details' );

        Livewire::test( Form::class )
            ->set( 'name', 'x' )
            ->set( 'key', 'Not A Key' )
            ->call( 'save' )
            ->assertHasErrors( [ 'key' => 'regex' ] );
    } );

    it( 'updates a promotion and disables it early', function (): void {
        $promotion = Promotion::factory()->create( [ 'name' => 'Spring', 'key' => 'spring' ] );

        Livewire::test( Form::class, [ 'promotion' => $promotion->id ] )
            ->assertSet( 'name', 'Spring' )
            ->set( 'name', 'Spring sale' )
            ->set( 'isActive', false )
            ->call( 'save' )
            ->assertHasNoErrors()
            ->assertSet( 'key', 'spring' );

        expect( $promotion->fresh() )
            ->name->toBe( 'Spring sale' )
            ->is_active->toBeFalse();
    } );

    it( 'keeps the source on coupon while the promotion has codes', function (): void {
        $promotion = Promotion::factory()->coupon()->create();
        Coupon::factory()->create( [ 'promotion_id' => $promotion->id ] );

        Livewire::test( Form::class, [ 'promotion' => $promotion->id ] )
            ->set( 'sourceType', 'automatic' )
            ->call( 'save' )
            ->assertHasErrors( [ 'sourceType' => 'in' ] );
    } );

    it( 'shows the coupons tab only for coupon promotions, and the usage and activity tabs once saved', function (): void {
        Livewire::test( Form::class )->assertDontSeeHtml( 'data-promotion-usage' );

        Livewire::test( Form::class, [ 'promotion' => Promotion::factory()->create()->id ] )
            ->assertDontSeeHtml( 'data-promotion-coupons' )
            ->assertSeeHtml( 'data-promotion-usage' );

        Livewire::test( Form::class, [ 'promotion' => Promotion::factory()->coupon()->create()->id ] )
            ->assertSeeHtml( 'data-promotion-coupons' );
    } );

    it( 'is read-only without promotion.update', function (): void {
        Gate::define( 'ecommerce.promotion.update', static fn (): bool => false );

        Livewire::test( Form::class, [ 'promotion' => Promotion::factory()->create()->id ] )
            ->assertSet( 'readOnly', true )
            ->assertDontSeeHtml( 'data-save' )
            ->call( 'save' )
            ->assertForbidden();
    } );
} );

describe( 'rule builder', function (): void {
    it( 'loads stored rules in order', function (): void {
        $promotion = Promotion::factory()->create();
        PromotionAction::factory()->create( [ 'promotion_id' => $promotion->id, 'type' => 'free-shipping', 'config' => [] ] );
        PromotionAction::factory()->create( [ 'promotion_id' => $promotion->id, 'type' => 'fixed-off-cart', 'config' => [ 'amount' => 500 ] ] );

        $component = Livewire::test( Form::class, [ 'promotion' => $promotion->id ] );

        expect( array_column( $component->get( 'ruleRows.actions' ), 'type' ) )->toBe( [ 'free-shipping', 'fixed-off-cart' ] );
    } );

    it( 'offers every registered condition and action, including satellite ones', function (): void {
        app( PromotionConditionRegistry::class )->register( 'has-points', new class implements PromotionConditionContract {
            public function key(): string
            {
                return 'has-points';
            }

            public function label(): string
            {
                return 'Customer has loyalty points';
            }

            public function evaluate( Cart $cart, array $config ): bool
            {
                return true;
            }
        } );

        Livewire::test( Form::class )
            ->assertSee( [ 'Minimum subtotal', 'Customer has loyalty points', 'Percent off cart', 'Free shipping' ] )
            ->set( 'ruleToAdd.conditions', 'has-points' )
            ->call( 'addRule', 'conditions' )
            ->assertSee( 'Edit them as JSON' )
            ->assertSee( '"Customer has loyalty points"' );
    } );

    it( 'refuses to add an unknown type', function (): void {
        Livewire::test( Form::class )
            ->set( 'ruleToAdd.conditions', 'nope' )
            ->call( 'addRule', 'conditions' )
            ->assertHasErrors( 'ruleToAdd.conditions' )
            ->assertSet( 'ruleRows.conditions', [] );
    } );

    it( 'moves rows with the buttons and ignores moves past the ends', function (): void {
        $component = Livewire::test( Form::class )
            ->set( 'ruleToAdd.actions', 'free-shipping' )->call( 'addRule', 'actions' )
            ->set( 'ruleToAdd.actions', 'fixed-off-cart' )->call( 'addRule', 'actions' );

        [ $first, $second ] = ruleIds( $component, 'actions' );

        $component->call( 'moveRule', 'actions', 1, -1 );
        expect( ruleIds( $component, 'actions' ) )->toBe( [ $second, $first ] );

        $component->call( 'moveRule', 'actions', 0, -1 )->call( 'moveRule', 'actions', 1, 1 );
        expect( ruleIds( $component, 'actions' ) )->toBe( [ $second, $first ] );
    } );

    it( 'reorders by drag only when the ids match the rows', function (): void {
        $component = Livewire::test( Form::class )
            ->set( 'ruleToAdd.actions', 'free-shipping' )->call( 'addRule', 'actions' )
            ->set( 'ruleToAdd.actions', 'fixed-off-cart' )->call( 'addRule', 'actions' );

        [ $first, $second ] = ruleIds( $component, 'actions' );

        $component->call( 'reorderRules', 'actions', [ $second, $first ] );
        expect( ruleIds( $component, 'actions' ) )->toBe( [ $second, $first ] );

        $component->call( 'reorderRules', 'actions', [ $first ] )
            ->call( 'reorderRules', 'actions', [ $first, $first ] )
            ->call( 'reorderRules', 'actions', [ $first, 'forged' ] );
        expect( ruleIds( $component, 'actions' ) )->toBe( [ $second, $first ] );
    } );

    it( 'removes a row', function (): void {
        Livewire::test( Form::class )
            ->set( 'ruleToAdd.conditions', 'customer-first-order' )->call( 'addRule', 'conditions' )
            ->call( 'removeRule', 'conditions', 0 )
            ->assertSet( 'ruleRows.conditions', [] );
    } );

    it( 'opens one row\'s settings at a time', function (): void {
        $component = Livewire::test( Form::class )
            ->set( 'ruleToAdd.conditions', 'min-subtotal' )->call( 'addRule', 'conditions' );

        [ $id ] = ruleIds( $component, 'conditions' );

        $component->assertSet( 'openRule', 'conditions:' . $id )
            ->assertSeeHtml( 'data-rule-settings="min-subtotal"' )
            ->call( 'toggleRule', 'conditions', $id )
            ->assertSet( 'openRule', null )
            ->assertDontSeeHtml( 'data-rule-settings="min-subtotal"' );
    } );

    it( 'validates each row before saving and opens the first bad one', function (): void {
        $component = Livewire::test( Form::class )
            ->set( 'name', 'Spring' )
            ->set( 'ruleToAdd.actions', 'free-shipping' )->call( 'addRule', 'actions' )
            ->set( 'ruleToAdd.actions', 'percent-off-cart' )->call( 'addRule', 'actions' );

        [ , $second ] = ruleIds( $component, 'actions' );

        $component->call( 'toggleRule', 'actions', $second )
            ->set( 'ruleRows.actions.1.config.percent', '' )
            ->call( 'save' )
            ->assertHasErrors( [ 'ruleRows.actions.1.config.percent' => 'required' ] )
            ->assertSet( 'tab', 'rules' )
            ->assertSet( 'openRule', 'actions:' . $second )
            ->assertSee( 'Has errors' );

        expect( Promotion::query()->count() )->toBe( 0 );
    } );

    it( 'flags a stored row whose type is no longer registered', function (): void {
        $promotion = Promotion::factory()->create();
        PromotionCondition::factory()->create( [ 'promotion_id' => $promotion->id, 'type' => 'gone-satellite', 'config' => [] ] );

        Livewire::test( Form::class, [ 'promotion' => $promotion->id ] )
            ->assertSee( 'Unavailable (gone-satellite)' )
            ->call( 'save' )
            ->assertHasErrors( 'ruleRows.conditions.0.type' )
            ->assertSee( 'This is no longer available. Remove it.' );
    } );

    it( 'saves rules in the order shown', function (): void {
        $promotion = Promotion::factory()->create();

        $component = Livewire::test( Form::class, [ 'promotion' => $promotion->id ] )
            ->set( 'ruleToAdd.actions', 'free-shipping' )->call( 'addRule', 'actions' )
            ->set( 'ruleToAdd.actions', 'fixed-off-cart' )->call( 'addRule', 'actions' )
            ->set( 'ruleRows.actions.1.config.amount', 250 )
            ->call( 'moveRule', 'actions', 1, -1 )
            ->call( 'save' )
            ->assertHasNoErrors();

        expect( $promotion->actions()->pluck( 'type' )->all() )->toBe( [ 'fixed-off-cart', 'free-shipping' ] );
    } );

    it( 'summarizes the rule in plain language', function (): void {
        $product = Product::factory()->create( [ 'name' => 'Linen Shirt' ] );

        Livewire::test( Form::class )
            ->assertSee( 'No discount yet.' )
            ->set( 'ruleToAdd.actions', 'percent-off-cart' )->call( 'addRule', 'actions' )
            ->set( 'ruleRows.actions.0.config.percent', 10 )
            ->assertSee( '10% off the cart on every cart.' )
            ->set( 'ruleToAdd.conditions', 'min-subtotal' )->call( 'addRule', 'conditions' )
            ->set( 'ruleRows.conditions.0.config.amount', 5000 )
            ->set( 'ruleToAdd.conditions', 'customer-first-order' )->call( 'addRule', 'conditions' )
            ->assertSee( '10% off the cart when the subtotal is at least $50.00 and it is the customer&#039;s first order.', false )
            ->set( 'ruleToAdd.actions', 'buy-x-get-y' )->call( 'addRule', 'actions' )
            ->set( 'ruleRows.actions.1.config.buy_quantity', 2 )
            ->set( 'ruleRows.actions.1.config.buy_product_ids', [ $product->id ] )
            ->assertSee( 'buy 2 of Linen Shirt, get 1 free' );
    } );

    it( 'saves a cart-contains-category condition with match any', function (): void {
        $category = ArtisanPackUI\Ecommerce\Models\ProductCategory::factory()->create();

        Livewire::test( Form::class )
            ->set( 'name', 'Category sale' )
            ->set( 'ruleToAdd.conditions', 'cart-contains-category' )->call( 'addRule', 'conditions' )
            ->set( 'ruleRows.conditions.0.config.category_ids', [ $category->id ] )
            ->set( 'ruleToAdd.actions', 'percent-off-cart' )->call( 'addRule', 'actions' )
            ->set( 'ruleRows.actions.0.config.percent', 10 )
            ->call( 'save' )
            ->assertHasNoErrors();

        expect( PromotionCondition::query()->sole()->config )->toBe( [ 'category_ids' => [ $category->id ], 'include_descendants' => true, 'match' => 'any' ] );
    } );

    it( 'requires a percent on percent-off-cart', function (): void {
        Livewire::test( Form::class )
            ->set( 'name', 'No percent' )
            ->set( 'ruleToAdd.actions', 'percent-off-cart' )->call( 'addRule', 'actions' )
            ->call( 'save' )
            ->assertHasErrors( [ 'ruleRows.actions.0.config.percent' => 'required' ] );

        expect( Promotion::query()->count() )->toBe( 0 );
    } );

    it( 'rejects an empty min-quantity quantity', function (): void {
        Livewire::test( Form::class )
            ->set( 'name', 'Bulk' )
            ->set( 'ruleToAdd.conditions', 'min-quantity' )->call( 'addRule', 'conditions' )
            ->set( 'ruleToAdd.actions', 'percent-off-cart' )->call( 'addRule', 'actions' )
            ->set( 'ruleRows.actions.0.config.percent', 10 )
            ->call( 'save' )
            ->assertHasErrors( [ 'ruleRows.conditions.0.config.quantity' => 'required' ] );
    } );

    it( 'runs the engine schema check on a cast config', function (): void {
        Livewire::test( Form::class )
            ->set( 'name', 'Fractional' )
            ->set( 'ruleToAdd.conditions', 'min-quantity' )->call( 'addRule', 'conditions' )
            ->set( 'ruleRows.conditions.0.config.quantity', '2.5' )
            ->set( 'ruleToAdd.actions', 'percent-off-cart' )->call( 'addRule', 'actions' )
            ->set( 'ruleRows.actions.0.config.percent', 10 )
            ->call( 'save' )
            ->assertHasErrors( [ 'ruleRows.conditions.0.config.quantity' ] );

        expect( Promotion::query()->count() )->toBe( 0 );
    } );

    it( 'lets a satellite describe its own rows', function (): void {
        addFilter( 'ap.ecommerceAdminLivewire.ruleBuilder.describe', static fn ( ?string $text, string $registry, string $type ): ?string => 'free-shipping' === $type ? 'shipping on the house' : $text, 10, 3 );

        Livewire::test( Form::class )
            ->set( 'ruleToAdd.actions', 'free-shipping' )->call( 'addRule', 'actions' )
            ->assertSee( 'Shipping on the house on every cart.' );
    } );

    it( 'refuses rule changes without the write ability', function (): void {
        Gate::define( 'ecommerce.promotion.update', static fn (): bool => false );

        Livewire::test( Form::class, [ 'promotion' => Promotion::factory()->create()->id ] )
            ->set( 'ruleToAdd.actions', 'free-shipping' )
            ->call( 'addRule', 'actions' )
            ->assertForbidden();
    } );
} );

it( 'records saved times in the app time zone', function (): void {
    config( [ 'app.timezone' => 'UTC' ] );

    Livewire::test( Form::class )
        ->set( 'name', 'Timed' )
        ->set( 'startsAt', '2026-11-27T09:30' )
        ->call( 'save' );

    expect( Promotion::query()->sole()->starts_at->equalTo( Carbon::parse( '2026-11-27 09:30:00', 'UTC' ) ) )->toBeTrue();
} );
