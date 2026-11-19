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
        ->assertSeeHtml( '<caption class="apui-sr-only">Promotions</caption>' )
        ->assertSee( [ 'Black Friday', 'Automatic', 'Scheduled', 'November 27, 2026', '0 / 500' ] )
        ->assertSee( [ 'Welcome', 'Coupon code', 'Active', 'Always' ] )
        ->assertSeeHtml( '<bdi dir="ltr" data-promotion-uses>12</bdi>' );
} );

it( 'shows the code count under coupon promotions only', function (): void {
    $coupon = Promotion::factory()->create( [ 'name' => 'Welcome', 'source_type' => 'coupon' ] );
    Coupon::factory()->count( 2 )->create( [ 'promotion_id' => $coupon->id ] );
    Promotion::factory()->create( [ 'name' => 'Free shipping', 'source_type' => 'automatic' ] );

    $html = Livewire::test( Index::class )->html();

    expect( substr_count( $html, 'data-promotion-codes' ) )->toBe( 1 )
        ->and( $html )->toContain( '2 codes' )
        ->and( $html )->toContain( 'data-promotion-source="coupon"' )
        ->and( $html )->toContain( 'data-promotion-source="automatic"' )
        ->and( $html )->toContain( route( 'artisanpack.ecommerce.admin.promotions.edit', [ 'promotion' => $coupon->id ] ) );
} );

it( 'reads uses as "used / limit" only when there is a limit, with thousands separators', function (): void {
    $limited   = Promotion::factory()->make( [ 'times_used' => 128, 'usage_limit_total' => 500 ] );
    $unlimited = Promotion::factory()->make( [ 'times_used' => 1204, 'usage_limit_total' => null ] );

    expect( Index::usesCount( $limited ) )->toBe( '128 / 500' )
        ->and( Index::usesCount( $unlimited ) )->toBe( '1,204' );
} );

it( 'gives exclusive promotions the accent badge and the rest a named dash', function (): void {
    Promotion::factory()->create( [ 'name' => 'Stacks', 'is_exclusive' => false ] );
    Promotion::factory()->create( [ 'name' => 'Alone', 'is_exclusive' => true ] );

    $html = Livewire::test( Index::class )->html();

    expect( substr_count( $html, 'data-promotion-exclusive' ) )->toBe( 1 )
        ->and( $html )->toMatch( '/data-color="accent"[^>]*data-promotion-exclusive|data-promotion-exclusive[^>]*data-color="accent"/' )
        ->and( substr_count( $html, 'aria-label="Not exclusive"' ) )->toBe( 1 );
} );

it( 'labels a switched-off promotion "Switched off"', function (): void {
    Promotion::factory()->create( [ 'is_active' => false ] );

    expect( Index::stateLabel( 'disabled' ) )->toBe( 'Switched off' );

    Livewire::test( Index::class )->assertSeeHtml( 'data-promotion-state="disabled"' )->assertSee( 'Switched off' );
} );

it( 'is built from design-system parts with no daisyUI classes left', function (): void {
    Promotion::factory()->create( [ 'name' => 'Fall sale', 'source_type' => 'coupon', 'is_exclusive' => true ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )->toContain( 'data-promotions-index' )
        ->and( $html )->toContain( 'class="apui-link ec-promotions-name"' )
        ->and( $html )->not->toMatch( '/\b(link-hover|font-semibold|opacity-75|text-xs|badge-|btn-)\b/' )
        ->and( $html )->not->toContain( 'style=' );
} );

it( 'offers New promotion in the empty state only with promotion.create', function (): void {
    Livewire::test( Index::class )
        ->assertSee( 'No promotions yet' )
        ->assertSeeHtml( 'data-empty-new-promotion' );

    Gate::define( 'ecommerce.promotion.create', static fn (): bool => false );

    Livewire::test( Index::class )
        ->assertSee( 'No promotions yet' )
        ->assertDontSeeHtml( 'data-empty-new-promotion' );
} );

it( 'confirms bulk delete inline, saying the codes stop working', function (): void {
    $promotion = Promotion::factory()->create();

    $component = Livewire::test( Index::class )
        ->set( 'selected', [ (string) $promotion->id ] )
        ->call( 'runBulkAction', 'delete' )
        ->assertSeeHtml( 'role="alertdialog"' )
        ->assertSee( 'Their coupon codes stop working at checkout.' );

    expect( Promotion::query()->whereKey( $promotion->id )->exists() )->toBeTrue();

    $component->call( 'cancelBulkAction' )->assertDontSeeHtml( 'role="alertdialog"' );

    expect( Promotion::query()->whereKey( $promotion->id )->exists() )->toBeTrue();
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

it( 'groups the uses count in the interface locale', function (): void {
    $limited = Promotion::factory()->create( [ 'times_used' => 1204, 'usage_limit_total' => 5000 ] );

    app()->setLocale( 'de' );

    expect( Index::usesCount( $limited ) )->toBe( '1.204 / 5.000' );
} );
