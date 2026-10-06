<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Coupon;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\Ecommerce\Models\PromotionUsage;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Index;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\PromotionsQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    Carbon::setTestNow( '2026-10-01 12:00:00' );

    grantAbilities( [ 'promotion.viewAny', 'promotion.view', 'promotion.create', 'promotion.update', 'promotion.delete' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    Carbon::setTestNow();
} );

/**
 * The one-time token the bulk confirmation carries.
 */
function promotionsConfirmToken( $component ): string
{
    expect( preg_match( "/confirmBulkAction\\( '([^']+)' \\)/", $component->html(), $matches ) )->toBe( 1 );

    return $matches[1];
}

it( 'renders promotions with source, state, window, uses, priority, and exclusivity', function (): void {
    Promotion::factory()->create( [
        'name'              => 'Black Friday',
        'source_type'       => 'automatic',
        'starts_at'         => Carbon::parse( '2026-11-27 00:00' ),
        'ends_at'           => Carbon::parse( '2026-11-30 23:59' ),
        'usage_limit_total' => 500,
        'priority'          => 5,
        'is_exclusive'      => true,
    ] );
    Promotion::factory()->create( [ 'name' => 'Welcome', 'source_type' => 'coupon', 'times_used' => 12 ] );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeHtml( '<caption class="sr-only">Promotions</caption>' )
        ->assertSee( [ 'Black Friday', 'Automatic', 'Scheduled', 'November 27, 2026', '0 of 500', 'Yes' ] )
        ->assertSee( [ 'Welcome', 'Coupon code', 'Active', 'Always', '12 (no limit)' ] );
} );

it( 'works out each state', function (): void {
    $state = static fn ( array $attributes ): string => PromotionsQuery::state( Promotion::factory()->make( $attributes ) );

    expect( $state( [] ) )->toBe( 'active' )
        ->and( $state( [ 'is_active' => false ] ) )->toBe( 'disabled' )
        ->and( $state( [ 'starts_at' => Carbon::now()->addDay() ] ) )->toBe( 'scheduled' )
        ->and( $state( [ 'ends_at' => Carbon::now() ] ) )->toBe( 'expired' )
        ->and( $state( [ 'starts_at' => Carbon::now()->subDay(), 'ends_at' => Carbon::now()->addDay() ] ) )->toBe( 'active' )
        ->and( $state( [ 'is_active' => false, 'ends_at' => Carbon::now()->subDay() ] ) )->toBe( 'disabled' );
} );

it( 'filters by state, source, and exclusivity', function (): void {
    $active    = Promotion::factory()->create( [ 'name' => 'Active one' ] );
    $scheduled = Promotion::factory()->create( [ 'name' => 'Later one', 'starts_at' => Carbon::now()->addWeek() ] );
    $expired   = Promotion::factory()->create( [ 'name' => 'Old one', 'ends_at' => Carbon::now()->subWeek(), 'source_type' => 'coupon' ] );
    $disabled  = Promotion::factory()->create( [ 'name' => 'Off one', 'is_active' => false, 'is_exclusive' => true ] );

    $ids = static fn ( array $filters ): array => ( new PromotionsQuery() )->build( '', $filters )->pluck( 'id' )->sort()->values()->all();

    expect( $ids( [ 'state' => 'active' ] ) )->toBe( [ $active->id ] )
        ->and( $ids( [ 'state' => 'scheduled' ] ) )->toBe( [ $scheduled->id ] )
        ->and( $ids( [ 'state' => 'expired' ] ) )->toBe( [ $expired->id ] )
        ->and( $ids( [ 'state' => 'disabled' ] ) )->toBe( [ $disabled->id ] )
        ->and( $ids( [ 'source_type' => 'coupon' ] ) )->toBe( [ $expired->id ] )
        ->and( $ids( [ 'exclusive' => '1' ] ) )->toBe( [ $disabled->id ] );

    Livewire::test( Index::class )
        ->set( 'filters.state', 'scheduled' )
        ->assertSee( 'Later one' )
        ->assertDontSee( 'Active one' );
} );

it( 'searches by name, key, and exact coupon code', function (): void {
    $welcome = Promotion::factory()->create( [ 'name' => 'Welcome offer', 'key' => 'welcome', 'source_type' => 'coupon' ] );
    Coupon::factory()->create( [ 'promotion_id' => $welcome->id, 'code' => 'HELLO10' ] );
    Promotion::factory()->create( [ 'name' => 'Summer sale', 'key' => 'summer' ] );

    Livewire::test( Index::class )
        ->set( 'search', 'summ' )->assertSee( 'Summer sale' )->assertDontSee( 'Welcome offer' )
        ->set( 'search', 'hello10' )->assertSee( 'Welcome offer' )->assertDontSee( 'Summer sale' );
} );

it( 'is denied without promotion.viewAny', function (): void {
    Gate::define( 'ecommerce.promotion.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'links to the new promotion form only with promotion.create', function (): void {
    Livewire::test( Index::class )->assertSeeHtml( route( 'artisanpack.ecommerce.admin.promotions.create' ) );

    Gate::define( 'ecommerce.promotion.create', static fn (): bool => false );

    Livewire::test( Index::class )->assertDontSeeHtml( route( 'artisanpack.ecommerce.admin.promotions.create' ) );
} );

it( 'switches promotions off early and back on', function (): void {
    $promotion = Promotion::factory()->create();

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $promotion->id ] )
        ->call( 'runBulkAction', 'deactivate' );

    expect( $promotion->fresh()->is_active )->toBeFalse();

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $promotion->id ] )
        ->call( 'runBulkAction', 'activate' );

    expect( $promotion->fresh()->is_active )->toBeTrue();
} );

it( 'deletes unused promotions and keeps used ones', function (): void {
    $unused = Promotion::factory()->create();
    $used   = Promotion::factory()->create();
    PromotionUsage::factory()->create( [ 'promotion_id' => $used->id ] );

    $component = Livewire::test( Index::class )
        ->set( 'selected', [ (string) $unused->id, (string) $used->id ] )
        ->call( 'runBulkAction', 'delete' );

    $component->call( 'confirmBulkAction', promotionsConfirmToken( $component ) );

    expect( Promotion::query()->whereKey( $unused->id )->exists() )->toBeFalse()
        ->and( Promotion::query()->whereKey( $used->id )->exists() )->toBeTrue()
        ->and( sentToasts( $component ) )->toContain( '1 promotion was kept.' );
} );

it( 'refuses to delete without promotion.delete', function (): void {
    Gate::define( 'ecommerce.promotion.delete', static fn (): bool => false );

    $promotion = Promotion::factory()->create();

    Livewire::test( Index::class )
        ->set( 'selected', [ (string) $promotion->id ] )
        ->call( 'runBulkAction', 'delete' )
        ->assertForbidden();
} );

it( 'skips promotions a per-record policy denies and says so', function (): void {
    $open   = Promotion::factory()->create( [ 'name' => 'Open', 'is_active' => false ] );
    $locked = Promotion::factory()->create( [ 'name' => 'Locked', 'is_active' => false ] );

    addFilter( 'ap.ecommerce.abilities.promotion.update', static fn ( bool $allowed, $user, $request, mixed $subject = null ): bool => $allowed && ! ( $subject instanceof Promotion && $subject->is( $locked ) ), 10, 4 );

    $component = Livewire::test( Index::class )
        ->set( 'selected', [ $open->id, $locked->id ] )
        ->call( 'runBulkAction', 'activate' )
        ->assertOk();

    removeAllFilters( 'ap.ecommerce.abilities.promotion.update' );

    expect( $open->refresh()->is_active )->toBeTrue()
        ->and( $locked->refresh()->is_active )->toBeFalse()
        ->and( sentToasts( $component ) )->toContain( '1 row skipped because you may not change it.' );
} );
