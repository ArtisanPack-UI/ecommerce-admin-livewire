<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\ActivityLogEntry;
use ArtisanPackUI\Ecommerce\Models\Coupon;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\CouponsPanel;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'promotion.viewAny', 'promotion.view', 'coupon.create', 'coupon.update', 'coupon.delete' ] );
    $this->actingAs( makeUser() );

    $this->promotion = Promotion::factory()->coupon()->create( [ 'key' => 'welcome', 'times_used' => 3 ] );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceAdminLivewire.coupons.usage' );
} );

/**
 * The one-time token the code delete button carries.
 */
function couponDeleteToken( $component ): string
{
    expect( preg_match( "/deleteCode\\( '([^']+)' \\)/", $component->html(), $matches ) )->toBe( 1 );

    return $matches[1];
}

it( 'lists the promotion\'s codes and explains how uses are counted', function (): void {
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'WELCOME10' ] );
    Coupon::factory()->create( [ 'code' => 'SOMEONEELSE' ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->assertOk()
        ->assertSee( [ 'WELCOME10', '1 code in all', 'not per code' ] )
        ->assertDontSee( 'SOMEONEELSE' );
} );

it( 'is denied without promotion.view', function (): void {
    Gate::define( 'ecommerce.promotion.view', static fn (): bool => false );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )->assertForbidden();
} );

it( 'adds a code, normalized', function (): void {
    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'newCode', '  welcome10 ' )
        ->call( 'addCode' )
        ->assertHasNoErrors()
        ->assertSet( 'newCode', '' )
        ->assertSee( 'WELCOME10' );

    expect( Coupon::query()->where( 'promotion_id', $this->promotion->id )->pluck( 'code' )->all() )->toBe( [ 'WELCOME10' ] );
} );

it( 'keeps codes unique across the store', function (): void {
    Coupon::factory()->create( [ 'code' => 'WELCOME10' ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'newCode', 'Welcome10' )
        ->call( 'addCode' )
        ->assertHasErrors( [ 'newCode' => 'unique' ] );
} );

it( 'validates the code', function ( string $code, string $rule ): void {
    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'newCode', $code )
        ->call( 'addCode' )
        ->assertHasErrors( [ 'newCode' => $rule ] );
} )->with( [
    'blank'       => [ '   ', 'required' ],
    'spaces'      => [ 'HELLO THERE', 'regex' ],
    'punctuation' => [ 'SAVE!', 'regex' ],
    'too long'    => [ str_repeat( 'A', 81 ), 'max' ],
] );

it( 'renames a code', function (): void {
    $coupon = Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'WELCOM10' ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->call( 'startRename', $coupon->id )
        ->assertSet( 'editingCode', 'WELCOM10' )
        ->set( 'editingCode', 'welcome10' )
        ->call( 'saveRename' )
        ->assertHasNoErrors()
        ->assertSet( 'editingId', null );

    expect( $coupon->fresh()->code )->toBe( 'WELCOME10' );
} );

it( 'ignores codes from other promotions', function (): void {
    $other = Coupon::factory()->create( [ 'code' => 'NOTYOURS' ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->call( 'startRename', $other->id )
        ->assertSet( 'editingId', null )
        ->call( 'confirmDelete', $other->id )
        ->assertSet( 'deletingId', null );
} );

it( 'deletes a leaked code after confirming', function (): void {
    $coupon = Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'LEAKED' ] );

    $component = Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->call( 'confirmDelete', $coupon->id )
        ->assertSee( 'It stops working at checkout right away.' );

    $component->call( 'deleteCode', couponDeleteToken( $component ) );

    expect( Coupon::query()->whereKey( $coupon->id )->exists() )->toBeFalse();
} );

it( 'generates unique codes from the unambiguous alphabet', function (): void {
    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'generateCount', 500 )
        ->set( 'generatePrefix', 'fall-' )
        ->set( 'generateLength', 6 )
        ->call( 'generateCodes' )
        ->assertHasNoErrors();

    $codes = Coupon::query()->where( 'promotion_id', $this->promotion->id )->pluck( 'code' );

    expect( $codes )->toHaveCount( 500 )
        ->and( $codes->unique() )->toHaveCount( 500 )
        ->and( $codes->every( static fn ( string $code ): bool => 1 === preg_match( '/^FALL-[' . CouponsPanel::ALPHABET . ']{6}$/', $code ) ) )->toBeTrue()
        ->and( ActivityLogEntry::query()->where( 'event_type', 'coupons.generated' )->value( 'payload' ) )->toMatchArray( [ 'count' => 500, 'prefix' => 'FALL-' ] );
} );

it( 'validates the generate settings', function (): void {
    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'generateCount', CouponsPanel::MAX_GENERATE + 1 )
        ->set( 'generatePrefix', 'no spaces' )
        ->set( 'generateLength', 3 )
        ->call( 'generateCodes' )
        ->assertHasErrors( [ 'generateCount' => 'max', 'generatePrefix' => 'regex', 'generateLength' => 'min' ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'generatePrefix', str_repeat( 'A', 20 ) )
        ->set( 'generateLength', 32 )
        ->set( 'generateCount', 1 )
        ->call( 'generateCodes' );

    expect( Coupon::query()->where( 'promotion_id', $this->promotion->id )->count() )->toBe( 1 );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'generatePrefix', str_repeat( 'A', 20 ) )
        ->set( 'generateLength', 32 )
        ->set( 'generateCount', 1 )
        ->call( 'generateCodes' );

    expect( Coupon::query()->where( 'promotion_id', $this->promotion->id )->count() )->toBe( 2 );
} );

it( 'exports the codes to CSV', function (): void {
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'B-CODE' ] );
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'A-CODE' ] );

    $component = Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )->call( 'exportCodes' );
    $download  = $component->effects['download'] ?? null;

    expect( $download['name'] )->toStartWith( 'coupons-welcome-' );

    $content = base64_decode( (string) $download['content'] );

    expect( strpos( $content, 'A-CODE' ) )->toBeLessThan( strpos( $content, 'B-CODE' ) )
        ->and( $content )->toContain( 'Code' );
} );

it( 'searches codes', function (): void {
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'SPRING_10' ] );
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'SPRINGX10' ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'search', 'g_1' )
        ->assertSee( 'SPRING_10' )
        ->assertDontSee( 'SPRINGX10' );
} );

it( 'shows per-code uses when something records them', function (): void {
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'TRACKED' ] );

    addFilter( 'ap.ecommerceAdminLivewire.coupons.usage', static fn ( array $counts ): array => [ 'TRACKED' => 7 ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->assertDontSeeHtml( 'data-coupon-usage-note' )
        ->assertSeeInOrder( [ 'Uses', 'TRACKED', '7' ] );
} );

it( 'refuses writes without the coupon abilities', function (): void {
    Gate::define( 'ecommerce.coupon.create', static fn (): bool => false );
    Gate::define( 'ecommerce.coupon.delete', static fn (): bool => false );

    $coupon = Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->assertDontSeeHtml( 'data-add-code' )
        ->set( 'newCode', 'NEW' )
        ->call( 'addCode' )
        ->assertForbidden();

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->call( 'confirmDelete', $coupon->id )
        ->assertForbidden();
} );
